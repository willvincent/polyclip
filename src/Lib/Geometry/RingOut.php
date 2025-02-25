<?php

namespace Polyclip\Lib\Geometry;

use Polyclip\Lib\SweepEvent;
use Polyclip\Lib\Util;

class RingOut
{
    /** @var SweepEvent[] */
    public array $events;
    public ?PolyOut $poly = null;
    private ?bool $_isExteriorRing = null;
    private ?RingOut $_enclosingRing = null;

    public function __construct(array $events)
    {
        $this->events = $events;
        foreach ($events as $event) {
            $event->segment->ringOut = $this;
        }
    }

    public static function factory(array $allSegments): array {
        $ringsOut = [];
        $processedSegments = [];
        foreach ($allSegments as $segment) {
            if (!$segment->isInResult() || $segment->ringOut !== null || isset($processedSegments[$segment->id])) {
                continue;
            }
            $events = [];
            $startingPoint = $segment->leftSE->point;
            $intersectionLEs = [];
            $event = $segment->leftSE;
            $nextEvent = $segment->rightSE;
            error_log("Starting ring from segment {$segment->id} at [{$segment->leftSE->point->x}, {$segment->leftSE->point->y}]");
            while (true) {
                error_log("Current event at [{$event->point->x}, {$event->point->y}]");
                $events[] = $event;
                $prevEvent = $event;
                $event = $nextEvent;
                $processedSegments[$event->segment->id] = true;
                if ($event->point === $startingPoint) {
                    $ringsOut[] = new RingOut($events);
                    break;
                }
                while (true) {
                    $availableLEs = $event->getAvailableLinkedEvents();
                    if (empty($availableLEs)) {
                        throw new \RuntimeException("Unable to complete output ring");
                    }
                    if (count($availableLEs) === 1) {
                        $nextEvent = $availableLEs[0]->otherSE;
                        break;
                    }
                    $indexLE = null;
                    foreach ($intersectionLEs as $idx => $intersection) {
                        if ($intersection['point']->x->isEqualTo($event->point->x) &&
                            $intersection['point']->y->isEqualTo($event->point->y)) {
                            $indexLE = $idx;
                            break;
                        }
                    }
                    if ($indexLE !== null) {
                        $intersection = array_splice($intersectionLEs, $indexLE, 1)[0];
                        $ringEvents = array_splice($events, $intersection['index']);
                        array_unshift($ringEvents, $ringEvents[0]->otherSE);
                        $ringsOut[] = new RingOut(array_reverse($ringEvents));
                        continue;
                    }
                    $intersectionLEs[] = ['index' => count($events), 'point' => $event->point];
                    $comparator = $event->getLeftmostComparator($prevEvent);
                    usort($availableLEs, $comparator);
                    $nextEvent = $availableLEs[0]->otherSE;
                    break;
                }
            }
        }
        return $ringsOut;
    }

    public function getGeom(): ?array {
        $orient = Util::orientation();
        $numEvents = count($this->events);
        if ($numEvents < 3) return null;

        $points = [];
        $prevPt = $this->events[$numEvents - 1]->point; // Start with the last point for wrap-around

        for ($i = 0; $i < $numEvents; $i++) {
            $pt = $this->events[$i]->point;
            $nextPt = $this->events[($i + 1) % $numEvents]->point;
            if ($orient($prevPt, $pt, $nextPt) !== 0) {
                $points[] = $pt;
                $prevPt = $pt;
            }
        }

        if (count($points) < 3) return null;

        // Close the ring if necessary
        if ($points[0] !== $points[count($points) - 1]) {
            $points[] = $points[0];
        }

        $geom = [];
        if ($this->isExteriorRing()) {
            // Reverse for counterclockwise order for exterior rings
            $points = array_reverse($points);
        }
        foreach ($points as $pt) {
            $geom[] = [$pt->x->toFloat(), $pt->y->toFloat()];
        }
        return $geom;
    }

    public function isExteriorRing(): bool
    {
        if ($this->_isExteriorRing === null) {
            $enclosing = $this->enclosingRing();
            $this->_isExteriorRing = $enclosing ? !$enclosing->isExteriorRing() : true;
        }
        return $this->_isExteriorRing;
    }

    public function enclosingRing(): ?RingOut
    {
        if ($this->_enclosingRing === null) {
            $this->_enclosingRing = $this->_calcEnclosingRing();
        }
        return $this->_enclosingRing;
    }

    private function _calcEnclosingRing(): ?RingOut
    {
        $leftMostEvt = $this->events[0];
        for ($i = 1, $iMax = count($this->events); $i < $iMax; $i++) {
            $evt = $this->events[$i];
            if (SweepEvent::compare($leftMostEvt, $evt) > 0) $leftMostEvt = $evt;
        }

        $prevSeg = $leftMostEvt->segment->prevInResult();
        $prevPrevSeg = $prevSeg ? $prevSeg->prevInResult() : null;

        while (true) {
            if ($prevSeg === null) return null;
            if ($prevPrevSeg === null) return $prevSeg->ringOut;

            if ($prevPrevSeg->ringOut !== $prevSeg->ringOut) {
                if ($prevPrevSeg->ringOut?->enclosingRing() !== $prevSeg->ringOut) {
                    return $prevSeg->ringOut;
                } else {
                    return $prevSeg->ringOut?->enclosingRing();
                }
            }

            $prevSeg = $prevPrevSeg->prevInResult();
            $prevPrevSeg = $prevSeg ? $prevSeg->prevInResult() : null;
        }
    }

    function isCollinear($p1, $p2, $p3) {
        // Cross product to check collinearity
        $cross = ($p2->x - $p1->x) * ($p3->y - $p1->y) - ($p2->y - $p1->y) * ($p3->x - $p1->x);
        return $cross === 0;
    }
}