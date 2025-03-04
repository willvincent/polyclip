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
     * @param SweepEvent[] $events
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
            if (! $segment->isInResult() || $segment->ringOut !== null || isset($processedSegments[$segment->id])) {
                continue;
            }

            $events = [];
            $startingPoint = $segment->leftSE->point;
            $intersectionLEs = [];
            $event = $segment->leftSE;
            $nextEvent = $segment->rightSE;

            // Stack to track ring context dynamically
            $ringContextStack = [array_map(fn($ring) => $ring->id, array_filter($segment->rings, fn($ring) => $ring !== null))];

            while (true) {
                $events[] = $event;
                $prevEvent = $event;
                $event = $nextEvent;
                $processedSegments[$event->segment->id] = true;

                // Check if the ring is complete
                if ($event->point->x->isEqualTo($startingPoint->x) && $event->point->y->isEqualTo($startingPoint->y)) {
                    $ringsOut[] = new RingOut($events);
                    break;
                }

                // Get all available linked events
                $availableLEs = $event->getAvailableLinkedEvents();
                if (empty($availableLEs)) {
                    throw new \RuntimeException('Unable to complete output ring');
                }

                // Check for a closing event
                $closingLEs = array_filter($availableLEs, function($le) use ($startingPoint) {
                    return $le->otherSE->point->x->isEqualTo($startingPoint->x) &&
                        $le->otherSE->point->y->isEqualTo($startingPoint->y);
                });
                if (!empty($closingLEs)) {
                    $nextEvent = array_values($closingLEs)[0]->otherSE;
                    continue;
                }

                // Get current ring context from the top of the stack
                $currentRingIds = end($ringContextStack);

                // Prefer segments sharing ring IDs with the current context
                $sharedRingLEs = array_filter($availableLEs, function($le) use ($currentRingIds) {
                    $segmentRingIds = array_map(fn($ring) => $ring->id, array_filter($le->segment->rings, fn($ring) => $ring !== null));
                    if (is_array($currentRingIds)) {
                        return !empty(array_intersect($segmentRingIds, $currentRingIds));
                    }
                    return !empty($segmentRingIds);
                });
                $candidateLEs = !empty($sharedRingLEs) ? array_values($sharedRingLEs) : $availableLEs;

                // Select the next event
                if (count($candidateLEs) === 1) {
                    $nextEvent = $candidateLEs[0]->otherSE;
                    // Update ring context if switching to a new ring
                    if (empty($sharedRingLEs)) {
                        $newRingIds = array_map(fn($ring) => $ring->id, array_filter($candidateLEs[0]->segment->rings, fn($ring) => $ring !== null));
                        $ringContextStack[] = $newRingIds;
                    }
                } else {
                    $comparator = $event->getLeftmostComparator($prevEvent);
                    usort($candidateLEs, $comparator);
                    $nextEvent = $candidateLEs[0]->otherSE;
                    // Update ring context if switching to a new ring
                    if (!in_array($candidateLEs[0], $sharedRingLEs, true)) {
                        $newRingIds = array_map(fn($ring) => $ring->id, array_filter($candidateLEs[0]->segment->rings, fn($ring) => $ring !== null));
                        $ringContextStack[] = $newRingIds;
                    }
                }

                // Handle intersections by splitting off completed rings
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
                    array_pop($ringContextStack); // Pop context when a ring is completed
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
        $prevPt = $this->events[$numEvents - 1]->point;
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

        if ($points[0] !== $points[count($points) - 1]) {
            $points[] = $points[0];
        }

        $geom = [];
        if ($this->isExteriorRing()) {
            $points = array_reverse($points); // Counterclockwise for exterior rings
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