<?php

declare(strict_types=1);

namespace Polyclip\Lib\Geometry;

use Polyclip\Lib\Segment;
use Polyclip\Lib\SweepEvent;
use Polyclip\Lib\Util;

class RingOut
{
    /** @var SweepEvent[] */
    public array $events;

    public ?PolyOut $poly = null;

    private ?bool $_isExteriorRing = null;

    private ?RingOut $_enclosingRing = null;

    /**
     * @param  SweepEvent[]  $events
     */
    public function __construct(array $events)
    {
        $this->events = $events;
        foreach ($events as $event) {
            $event->segment->ringOut = $this;
        }
    }

    /**
     * @param Segment[] $allSegments
     * @return RingOut[]
     */
    public static function factory(array $allSegments): array
    {
        $ringsOut = [];
        $processedSegments = [];

        foreach ($allSegments as $segment) {
            // Skip segments that aren’t in the result, already processed, or assigned to a ring
            if (! $segment->isInResult() || $segment->ringOut !== null || isset($processedSegments[$segment->id])) {
                continue;
            }

            // Log the starting segment
            error_log("Starting new ring with segment {$segment->id}: [{$segment->leftSE->point->x}, {$segment->leftSE->point->y}] -> [{$segment->rightSE->point->x}, {$segment->rightSE->point->y}]");

            $events = [];
            $startingPoint = $segment->leftSE->point;
            $intersectionLEs = [];
            $event = $segment->leftSE;
            $nextEvent = $segment->rightSE;

            // Log initial ring IDs for context
            $initialRingIds = array_map(fn($ring) => $ring->id, array_filter($segment->rings, fn($ring) => $ring !== null));
            error_log("Initial ring IDs: " . implode(', ', $initialRingIds));

            while (true) {
                $events[] = $event;
                $prevEvent = $event;
                $event = $nextEvent;
                $processedSegments[$event->segment->id] = true;

                // Log the current event being processed
                error_log("Processing event at [{$event->point->x}, {$event->point->y}]");

                // Check if the ring is complete
                if ($event->point->x->isEqualTo($startingPoint->x) && $event->point->y->isEqualTo($startingPoint->y)) {
                    error_log("Ring closed successfully.");
                    $ringsOut[] = new RingOut($events);
                    break;
                }

                // Get and log available linked events
                $availableLEs = $event->getAvailableLinkedEvents();
                error_log("Available linked events: " . count($availableLEs));
                foreach ($availableLEs as $le) {
                    error_log(" - Event at [{$le->point->x}, {$le->point->y}], segment {$le->segment->id}");
                }

                if (empty($availableLEs)) {
                    error_log("No available linked events. Unable to complete ring.");
                    throw new \RuntimeException('Unable to complete output ring');
                }

                // Check for a closing event
                $closingLEs = array_filter($availableLEs, function($le) use ($startingPoint) {
                    return ($le->otherSE->point->x->isEqualTo($startingPoint->x) &&
                        $le->otherSE->point->y->isEqualTo($startingPoint->y));
                });
                if (!empty($closingLEs)) {
                    error_log("Found closing event.");
                    $nextEvent = array_values($closingLEs)[0]->otherSE;
                    continue;
                }

                // Filter candidates based on shared ring IDs, falling back to all available events
                if (!empty($initialRingIds)) {
                    $sharedRingLEs = array_filter($availableLEs, function($le) use ($initialRingIds) {
                        $segmentRingIds = array_map(fn($ring) => $ring->id, array_filter($le->segment->rings, fn($ring) => $ring !== null));
                        return !empty(array_intersect($segmentRingIds, $initialRingIds));
                    });
                    $candidateLEs = !empty($sharedRingLEs) ? array_values($sharedRingLEs) : $availableLEs;
                } else {
                    $candidateLEs = $availableLEs;
                }

                // Log candidate events
                error_log("Candidate events: " . count($candidateLEs));
                foreach ($candidateLEs as $le) {
                    error_log(" - Candidate event at [{$le->point->x}, {$le->point->y}], segment {$le->segment->id}");
                }

                // Select the next event
                if (count($candidateLEs) === 1) {
                    $nextEvent = $candidateLEs[0]->otherSE;
                } else {
                    $comparator = $event->getLeftmostComparator($prevEvent);
                    usort($candidateLEs, $comparator);
                    $nextEvent = $candidateLEs[0]->otherSE;
                    error_log("Selected leftmost event at [{$nextEvent->point->x}, {$nextEvent->point->y}]");
                }

                // Handle intersections
                $indexLE = null;
                foreach ($intersectionLEs as $idx => $intersection) {
                    if ($intersection['point']->x->isEqualTo($event->point->x) &&
                        $intersection['point']->y->isEqualTo($event->point->y)) {
                        $indexLE = $idx;
                        break;
                    }
                }
                if ($indexLE !== null) {
                    error_log("Intersection detected at [{$event->point->x}, {$event->point->y}]");
                    $intersection = array_splice($intersectionLEs, $indexLE, 1)[0];
                    $ringEvents = array_splice($events, $intersection['index']);
                    array_unshift($ringEvents, $ringEvents[0]->otherSE);
                    $ringsOut[] = new RingOut(array_reverse($ringEvents));
                    continue;
                }
                $intersectionLEs[] = ['index' => count($events), 'point' => $event->point];
            }
        }

        return $ringsOut;
    }

    /**
     * @return mixed[]|null
     */
    public function getGeom(): ?array
    {
        $orient = Util::orientation();
        $numEvents = count($this->events);
        if ($numEvents < 3) {
            return null;
        }

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

        if (count($points) < 3) {
            return null;
        }

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
            $this->_isExteriorRing = $enclosing ? ! $enclosing->isExteriorRing() : true;
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
            if (SweepEvent::compare($leftMostEvt, $evt) > 0) {
                $leftMostEvt = $evt;
            }
        }

        $prevSeg = $leftMostEvt->segment->prevInResult();
        $prevPrevSeg = $prevSeg ? $prevSeg->prevInResult() : null;

        while (true) {
            if ($prevSeg === null) {
                return null;
            }
            if ($prevPrevSeg === null) {
                return $prevSeg->ringOut;
            }

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
}
