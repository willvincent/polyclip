<?php

declare(strict_types=1);

namespace Polyclip\Lib;

use SplayTree\SplayTree;

class SweepLine
{
    private SplayTree $queue;

    private SplayTree $tree;

    /**
     * @var Segment[]
     */
    public array $segments = [];

    public function __construct(SplayTree $queue, ?callable $comparator = null)
    {
        $this->queue = $queue;
        $this->tree = new SplayTree($comparator ?? [Segment::class, 'compare']);
    }

    /**
     * @return SweepEvent[]
     */
    public function process(SweepEvent $event): array
    {
        $segment = $event->segment;
        $newEvents = [];

        // Handle consumed events
        if ($event->consumedBy) {
            if ($event->isLeft) {
                $this->queue->delete($event->otherSE);
            } else {
                $this->tree->delete($segment);
            }

            return $newEvents;
        }

        // Add segment to tree for left events
        if ($event->isLeft) {
            $this->tree->insert($segment);
        }

        // Find previous and next segments, skipping consumed ones
        $prevSeg = $this->tree->prev($segment);
        while ($prevSeg !== null && $prevSeg->consumedBy !== null) {
            $prevSeg = $this->tree->prev($prevSeg);
        }

        $nextSeg = $this->tree->next($segment);
        while ($nextSeg !== null && $nextSeg->consumedBy !== null) {
            $nextSeg = $this->tree->next($nextSeg);
        }

        if ($event->isLeft) {
            // Check intersections with previous segment
            $prevMySplitter = null;
            if ($prevSeg) {
                $prevInter = $prevSeg->getIntersection($segment);
                if ($prevInter !== null) {
                    if (! $segment->isAnEndpoint($prevInter)) {
                        $prevMySplitter = $prevInter;
                    }
                    if (! $prevSeg->isAnEndpoint($prevInter)) {
                        $newEventsFromSplit = $this->_splitSafely($prevSeg, $prevInter);
                        $newEvents = array_merge($newEvents, $newEventsFromSplit);
                    }
                }
            }

            // Check intersections with next segment
            $nextMySplitter = null;
            if ($nextSeg) {
                $nextInter = $nextSeg->getIntersection($segment);
                if ($nextInter !== null) {
                    if (! $segment->isAnEndpoint($nextInter)) {
                        $nextMySplitter = $nextInter;
                    }
                    if (! $nextSeg->isAnEndpoint($nextInter)) {
                        $newEventsFromSplit = $this->_splitSafely($nextSeg, $nextInter);
                        $newEvents = array_merge($newEvents, $newEventsFromSplit);
                    }
                }
            }

            // Handle splitting if intersections are found
            if ($prevMySplitter !== null || $nextMySplitter !== null) {
                $mySplitter = null;
                if ($prevMySplitter === null) {
                    $mySplitter = $nextMySplitter;
                } elseif ($nextMySplitter === null) {
                    $mySplitter = $prevMySplitter;
                } else {
                    $cmpSplitters = SweepEvent::comparePoints($prevMySplitter, $nextMySplitter);
                    $mySplitter = $cmpSplitters <= 0 ? $prevMySplitter : $nextMySplitter;
                }

                $this->queue->delete($segment->rightSE);
                $newEvents[] = $segment->rightSE;

                $newEventsFromSplit = $segment->split($mySplitter);
                $newEvents = array_merge($newEvents, $newEventsFromSplit);
            }

            if (! empty($newEvents)) {
                $this->tree->delete($segment);
                $newEvents[] = $event;
            } else {
                $this->segments[] = $segment;
                $segment->prev = $prevSeg;
            }
        } else {
            // Right event: check intersections between prev and next segments
            if ($prevSeg && $nextSeg) {
                $inter = $prevSeg->getIntersection($nextSeg);
                if ($inter !== null) {
                    if (! $prevSeg->isAnEndpoint($inter)) {
                        $newEventsFromSplit = $this->_splitSafely($prevSeg, $inter);
                        $newEvents = array_merge($newEvents, $newEventsFromSplit);
                    }
                    if (! $nextSeg->isAnEndpoint($inter)) {
                        $newEventsFromSplit = $this->_splitSafely($nextSeg, $inter);
                        $newEvents = array_merge($newEvents, $newEventsFromSplit);
                    }
                }
            }
            $this->tree->delete($segment);
        }

        return $newEvents;
    }

    /**
     * @return SweepEvent[]
     */
    private function _splitSafely(Segment $segment, Vector $point): array
    {
        $this->tree->delete($segment);
        $rightSE = $segment->rightSE;
        $this->queue->delete($rightSE);
        $newEvents = $segment->split($point);
        $newEvents[] = $rightSE;
        if ($segment->consumedBy === null) {
            $this->tree->insert($segment);
        }

        return $newEvents;
    }
}
