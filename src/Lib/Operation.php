<?php

namespace Polyclip\Lib;

use Polyclip\Lib\Geometry\MultiPolyIn;
use Polyclip\Lib\Geometry\MultiPolyOut;
use Polyclip\Lib\Geometry\RingOut;
use SplayTree\SplayTree;

class Operation
{
    public static string $type;
    public static int $numMultiPolys;

    public static function run(string $type, array $geom, array ...$moreGeoms): array
    {
        self::$type = $type;

        // Convert inputs to MultiPolyIn objects
        $multipolys = [new MultiPolyIn($geom, true)];
        foreach ($moreGeoms as $g) {
            $multipolys[] = new MultiPolyIn($g, false);
        }
        self::$numMultiPolys = count($multipolys);

        // BBox optimization for difference
        if ($type === "difference") {
            $subject = $multipolys[0];
            $i = 1;
            while ($i < count($multipolys)) {
                if ($subject->bbox->getBboxOverlap($subject->bbox, $multipolys[$i]->bbox) !== null) {
                    $i++;
                } else {
                    array_splice($multipolys, $i, 1);
                }
            }
        }

        // BBox optimization for intersection
        if ($type === "intersection") {
            for ($i = 0; $i < count($multipolys); $i++) {
                for ($j = $i + 1; $j < count($multipolys); $j++) {
                    if ($multipolys[$i]->bbox->getBboxOverlap($multipolys[$i]->bbox, $multipolys[$j]->bbox) === null) {
                        return [];
                    }
                }
            }
        }

        // Initialize event queue
        $queue = new SplayTree([SweepEvent::class, 'compare']);
        foreach ($multipolys as $multiPolygon) {
            $sweepEvents = $multiPolygon->getSweepEvents();
            foreach ($sweepEvents as $event) {
                $queue->insert($event);
            }
        }

        // Process events with sweep line
        $sweepLine = new SweepLine($queue);
        while (!$queue->isEmpty()) {
            $event = $queue->min();
            $queue->delete($event);
            $newEvents = $sweepLine->process($event);
            foreach ($newEvents as $newEvent) {
                if ($newEvent->consumedBy === null) {
                    $queue->insert($newEvent);
                }
            }
        }

        // Reset precision utilities
        Util::reset();

        // Construct output geometry
        $ringsOut = RingOut::factory($sweepLine->segments);
        $result = new MultiPolyOut($ringsOut);
        return $result->getGeom();
    }
}