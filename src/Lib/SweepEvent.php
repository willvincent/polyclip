<?php

namespace Polyclip\Lib;

class SweepEvent
{
    private static int $eventId = 0; // Static counter for unique IDs

    public int $id; // Unique identifier for caching
    public Vector $point;
    public bool $isLeft;
    public ?Segment $segment = null;
    public ?SweepEvent $otherSE = null;
    public ?SweepEvent $consumedBy = null;

    public function __construct(Vector $point, bool $isLeft)
    {
        $this->id = ++self::$eventId;
        $this->point = $point;
        $this->isLeft = $isLeft;
        if ($point->events === null) {
            $point->events = [$this];
        } else {
            $point->events[] = $this;
            $this->checkForConsuming();
        }
    }

    public static function comparePoints(Vector $aPt, Vector $bPt): int
    {
        if ($aPt->x->isLessThan($bPt->x)) return -1;
        if ($aPt->x->isGreaterThan($bPt->x)) return 1;
        if ($aPt->y->isLessThan($bPt->y)) return -1;
        if ($aPt->y->isGreaterThan($bPt->y)) return 1;
        return 0;
    }

    public static function compare(SweepEvent $a, SweepEvent $b): int
    {
        $ptCmp = self::comparePoints($a->point, $b->point);
        if ($ptCmp !== 0) return $ptCmp;
        if ($a->point !== $b->point) $a->link($b);
        if ($a->isLeft !== $b->isLeft) return $a->isLeft ? 1 : -1;
        return Segment::compare($a->segment, $b->segment);
    }

    public function link(SweepEvent $other): void
    {
        // Compare points by value, not object identity
        if ($this->point->x->isEqualTo($other->point->x) && $this->point->y->isEqualTo($other->point->y)) {
            if ($this->point !== $other->point) {
                $otherEvents = $other->point->events;
                foreach ($otherEvents as $event) {
                    if (!in_array($event, $this->point->events, true)) {
                        $this->point->events[] = $event;
                        $event->point = $this->point;
                    }
                }
                $this->checkForConsuming();
            }
        }

        $otherEvents = $other->point->events;
        foreach ($otherEvents as $event) {
            if (!in_array($event, $this->point->events, true)) {
                $this->point->events[] = $event;
                $event->point = $this->point;
            }
        }
        $this->checkForConsuming();
    }

    public function checkForConsuming(): void
    {
        $numEvents = count($this->point->events);
        for ($i = 0; $i < $numEvents; $i++) {
            $evt1 = $this->point->events[$i];
            if (!is_null($evt1->segment)) {
                if ($evt1->segment->consumedBy !== null) continue;
                for ($j = $i + 1; $j < $numEvents; $j++) {
                    $evt2 = $this->point->events[$j];
                    if (!is_null($evt2->segment)) {
                        if ($evt2->segment->consumedBy !== null) continue;
                        if ($evt1->otherSE->point === $evt2->otherSE->point) {
                            $evt1->segment->consume($evt2->segment);
                        }
                    }
                }
            }
        }
    }

    public function getAvailableLinkedEvents(): array
    {
        $events = [];
        foreach ($this->point->events as $evt) {
            if ($evt !== $this && $evt->segment->ringOut === null && $evt->segment->isInResult()) {
                $events[] = $evt;
            }
        }
//        error_log("Point [{$this->point->x}, {$this->point->y}]: " . count($events) . " available events");
        return $events;
    }

    public function getLeftmostComparator(SweepEvent $baseEvent): callable {
        $cache = [];
        $fillCache = function(SweepEvent $linkedEvent) use ($baseEvent, &$cache) {
            $nextEvent = $linkedEvent->otherSE;
            $sine = Vector::sineOfAngle($this->point, $baseEvent->point, $nextEvent->point);
            $cosine = Vector::cosineOfAngle($this->point, $baseEvent->point, $nextEvent->point);
            $cache[$linkedEvent->id] = ['sine' => $sine, 'cosine' => $cosine];
        };
        return function(SweepEvent $a, SweepEvent $b) use (&$cache, $fillCache) {
            if (!isset($cache[$a->id])) $fillCache($a);
            if (!isset($cache[$b->id])) $fillCache($b);
            $asine = $cache[$a->id]['sine'];
            $acosine = $cache[$a->id]['cosine'];
            $bsine = $cache[$b->id]['sine'];
            $bcosine = $cache[$b->id]['cosine'];
            if ($asine->isGreaterThanOrEqualTo(0) && $bsine->isGreaterThanOrEqualTo(0)) {
                if ($acosine->isLessThan($bcosine)) return 1;
                if ($acosine->isGreaterThan($bcosine)) return -1;
                return 0;
            }
            if ($asine->isLessThan(0) && $bsine->isLessThan(0)) {
                if ($acosine->isLessThan($bcosine)) return -1;
                if ($acosine->isGreaterThan($bcosine)) return 1;
                return 0;
            }
            if ($bsine->isLessThan($asine)) return -1;
            if ($bsine->isGreaterThan($asine)) return 1;
            return 0;
        };
    }
}