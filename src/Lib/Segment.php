<?php

declare(strict_types=1);

namespace Polyclip\Lib;

use Polyclip\Lib\Geometry\RingIn;
use Polyclip\Lib\Geometry\RingOut;

class Segment
{
    private static int $segmentId = 0;

    public int $id;

    public SweepEvent $leftSE;

    public SweepEvent $rightSE;

    /**
     * @var mixed[]|null
     */
    public ?array $rings = null; // Array of RingIn objects

    /**
     * @var mixed[]|null
     */
    public ?array $windings = null; // Array of winding numbers

    public ?RingOut $ringOut = null;

    public ?Segment $consumedBy = null;

    public ?Segment $prev = null;

    private ?Segment $_prevInResult = null;

    /**
     * @var mixed[]|null
     */
    private ?array $_beforeState = null; // [rings: RingIn[], windings: int[], multiPolys: MultiPolyIn[]]

    /**
     * @var mixed[]|null
     */
    private ?array $_afterState = null;

    private ?bool $_isInResult = null;

    /**
     * @param  mixed[]  $rings
     * @param  mixed[]  $windings
     */
    public function __construct(SweepEvent $leftSE, SweepEvent $rightSE, array $rings, array $windings)
    {
        $this->id = ++self::$segmentId;
        $this->leftSE = $leftSE;
        $this->leftSE->segment = $this;
        $this->leftSE->otherSE = $rightSE;
        $this->rightSE = $rightSE;
        $this->rightSE->segment = $this;
        $this->rightSE->otherSE = $leftSE;
        $this->rings = $rings;
        $this->windings = $windings;
    }

    public static function fromRing(Vector $pt1, Vector $pt2, ?RingIn $ring): Segment
    {
        $cmpPts = SweepEvent::comparePoints($pt1, $pt2);
        if ($cmpPts < 0) {
            $leftPt = $pt1;
            $rightPt = $pt2;
            $winding = 1;
        } elseif ($cmpPts > 0) {
            $leftPt = $pt2;
            $rightPt = $pt1;
            $winding = -1;
        } else {
            throw new \InvalidArgumentException("Tried to create degenerate segment at [{$pt1->x}, {$pt1->y}]");
        }

        $leftSE = new SweepEvent($leftPt, true);
        $rightSE = new SweepEvent($rightPt, false);

        return new Segment($leftSE, $rightSE, [$ring], [$winding]);
    }

    public static function compare(Segment $a, Segment $b): int
    {
        $alx = $a->leftSE->point->x;
        $blx = $b->leftSE->point->x;
        $arx = $a->rightSE->point->x;
        $brx = $b->rightSE->point->x;

        if ($brx->isLessThan($alx)) {
            return 1;
        }
        if ($arx->isLessThan($blx)) {
            return -1;
        }

        $aly = $a->leftSE->point->y;
        $bly = $b->leftSE->point->y;
        $ary = $a->rightSE->point->y;
        $bry = $b->rightSE->point->y;

        if ($alx->isLessThan($blx)) {
            if ($bly->isLessThan($aly) && $bly->isLessThan($ary)) {
                return 1;
            }
            if ($bly->isGreaterThan($aly) && $bly->isGreaterThan($ary)) {
                return -1;
            }

            $aCmpBLeft = $a->comparePoint($b->leftSE->point);
            if ($aCmpBLeft < 0) {
                return 1;
            }
            if ($aCmpBLeft > 0) {
                return -1;
            }

            $bCmpARight = $b->comparePoint($a->rightSE->point);
            if ($bCmpARight !== 0) {
                return $bCmpARight;
            }

            return -1;
        }

        if ($alx->isGreaterThan($blx)) {
            if ($aly->isLessThan($bly) && $aly->isLessThan($bry)) {
                return -1;
            }
            if ($aly->isGreaterThan($bly) && $aly->isGreaterThan($bry)) {
                return 1;
            }

            $bCmpALeft = $b->comparePoint($a->leftSE->point);
            if ($bCmpALeft !== 0) {
                return $bCmpALeft;
            }

            $aCmpBRight = $a->comparePoint($b->rightSE->point);
            if ($aCmpBRight < 0) {
                return 1;
            }
            if ($aCmpBRight > 0) {
                return -1;
            }

            return 1;
        }

        if ($aly->isLessThan($bly)) {
            return -1;
        }
        if ($aly->isGreaterThan($bly)) {
            return 1;
        }

        if ($arx->isLessThan($brx)) {
            $bCmpARight = $b->comparePoint($a->rightSE->point);
            if ($bCmpARight !== 0) {
                return $bCmpARight;
            }
        }

        if ($arx->isGreaterThan($brx)) {
            $aCmpBRight = $a->comparePoint($b->rightSE->point);
            if ($aCmpBRight < 0) {
                return 1;
            }
            if ($aCmpBRight > 0) {
                return -1;
            }
        }

        if (! $arx->isEqualTo($brx)) {
            $ay = $ary->minus($aly);
            $ax = $arx->minus($alx);
            $by = $bry->minus($bly);
            $bx = $brx->minus($blx);
            if ($ay->isGreaterThan($ax) && $by->isLessThan($bx)) {
                return 1;
            }
            if ($ay->isLessThan($ax) && $by->isGreaterThan($bx)) {
                return -1;
            }
        }

        if ($arx->isGreaterThan($brx)) {
            return 1;
        }
        if ($arx->isLessThan($brx)) {
            return -1;
        }

        if ($ary->isLessThan($bry)) {
            return -1;
        }
        if ($ary->isGreaterThan($bry)) {
            return 1;
        }

        return $a->id < $b->id ? -1 : ($a->id > $b->id ? 1 : 0);
    }

    public function replaceRightSE(SweepEvent $newRightSE): void
    {
        $this->rightSE = $newRightSE;
        $this->rightSE->segment = $this;
        $this->rightSE->otherSE = $this->leftSE;
        $this->leftSE->otherSE = $this->rightSE;
    }

    public function bbox(): Bbox
    {
        $y1 = $this->leftSE->point->y;
        $y2 = $this->rightSE->point->y;

        return new Bbox(
            new Vector($this->leftSE->point->x, $y1->isLessThan($y2) ? $y1 : $y2),
            new Vector($this->rightSE->point->x, $y1->isGreaterThan($y2) ? $y1 : $y2)
        );
    }

    public function vector(): Vector
    {
        return new Vector(
            $this->rightSE->point->x->minus($this->leftSE->point->x),
            $this->rightSE->point->y->minus($this->leftSE->point->y)
        );
    }

    public function isAnEndpoint(Vector $pt): bool
    {
        return ($pt->x->isEqualTo($this->leftSE->point->x) && $pt->y->isEqualTo($this->leftSE->point->y)) ||
            ($pt->x->isEqualTo($this->rightSE->point->x) && $pt->y->isEqualTo($this->rightSE->point->y));
    }

    public function comparePoint(Vector $point): int
    {
        $orient = Util::orientation();

        return $orient($this->leftSE->point, $point, $this->rightSE->point);
    }

    public function getIntersection(Segment $other): ?Vector
    {
        $tBbox = $this->bbox();
        $oBbox = $other->bbox();
        $bboxOverlap = $tBbox->getBboxOverlap($tBbox, $oBbox);
        if ($bboxOverlap === null) {
            return null;
        }

        $tlp = $this->leftSE->point;
        $trp = $this->rightSE->point;
        $olp = $other->leftSE->point;
        $orp = $other->rightSE->point;

        $touchesOtherLSE = $tBbox->pointInBbox($olp) && $this->comparePoint($olp) === 0;
        $touchesThisLSE = $oBbox->pointInBbox($tlp) && $other->comparePoint($tlp) === 0;
        $touchesOtherRSE = $tBbox->pointInBbox($orp) && $this->comparePoint($orp) === 0;
        $touchesThisRSE = $oBbox->pointInBbox($trp) && $other->comparePoint($trp) === 0;

        if ($touchesThisLSE && $touchesOtherLSE) {
            if ($touchesThisRSE && ! $touchesOtherRSE) {
                return $trp;
            }
            if (! $touchesThisRSE && $touchesOtherRSE) {
                return $orp;
            }

            return null;
        }

        if ($touchesThisLSE) {
            if ($touchesOtherRSE && ! ($tlp->x->isEqualTo($orp->x) && $tlp->y->isEqualTo($orp->y))) {
                return null;
            }

            return $tlp;
        }

        if ($touchesOtherLSE) {
            if ($touchesThisRSE && ! ($trp->x->isEqualTo($olp->x) && $trp->y->isEqualTo($olp->y))) {
                return null;
            }

            return $olp;
        }

        if ($touchesThisRSE && $touchesOtherRSE) {
            return null;
        }

        if ($touchesThisRSE) {
            return $trp;
        }
        if ($touchesOtherRSE) {
            return $orp;
        }

        $point = Vector::intersection($tlp, $this->vector(), $olp, $other->vector());
        if ($point === null || ! $bboxOverlap->pointInBbox($point)) {
            return null;
        }

        $snap = Util::createSnap();

        return $snap($point);
    }

    /**
     * @return SweepEvent[]
     */
    public function split(Vector $point): array
    {
        $newEvents = [];
        $alreadyLinked = $point->events !== null;

        $newLeftSE = new SweepEvent($point, true);
        $newRightSE = new SweepEvent($point, false);
        $oldRightSE = $this->rightSE;
        $this->replaceRightSE($newRightSE);
        $newEvents[] = $newRightSE;
        $newEvents[] = $newLeftSE;
        $newSeg = new Segment($newLeftSE, $oldRightSE, array_slice($this->rings, 0), array_slice($this->windings, 0));

        if (SweepEvent::comparePoints($newSeg->leftSE->point, $newSeg->rightSE->point) > 0) {
            $newSeg->swapEvents();
        }
        if (SweepEvent::comparePoints($this->leftSE->point, $this->rightSE->point) > 0) {
            $this->swapEvents();
        }

        return $newEvents;
    }

    private function swapEvents(): void
    {
        $tmpEvt = $this->rightSE;
        $this->rightSE = $this->leftSE;
        $this->leftSE = $tmpEvt;
        $this->leftSE->isLeft = true;
        $this->rightSE->isLeft = false;
        $this->windings = array_map(fn ($w) => -$w, $this->windings);
    }

    public function consume(Segment $other): void
    {
        $consumer = $this;
        $consumee = $other;
        while ($consumer->consumedBy) {
            $consumer = $consumer->consumedBy;
        }
        while ($consumee->consumedBy) {
            $consumee = $consumee->consumedBy;
        }

        $cmp = self::compare($consumer, $consumee);
        if ($cmp === 0) {
            return;
        }
        if ($cmp > 0) {
            [$consumer, $consumee] = [$consumee, $consumer];
        }
        if ($consumer->prev === $consumee) {
            [$consumer, $consumee] = [$consumee, $consumer];
        }

        foreach ($consumee->rings as $i => $ring) {
            $winding = $consumee->windings[$i];
            $index = array_search($ring, $consumer->rings, true);
            if ($index === false) {
                $consumer->rings[] = $ring;
                $consumer->windings[] = $winding;
            } else {
                $consumer->windings[$index] += $winding;
            }
        }
        $consumee->rings = null;
        $consumee->windings = null;
        $consumee->consumedBy = $consumer;
        $consumee->leftSE->consumedBy = $consumer->leftSE;
        $consumee->rightSE->consumedBy = $consumer->rightSE;
    }

    public function prevInResult(): ?Segment
    {
        if ($this->_prevInResult !== null) {
            return $this->_prevInResult;
        }
        if (! $this->prev) {
            return $this->_prevInResult = null;
        }
        if ($this->prev->isInResult()) {
            return $this->_prevInResult = $this->prev;
        }

        return $this->_prevInResult = $this->prev->prevInResult();
    }

    /**
     * @return array|array[]|mixed[]
     */
    public function beforeState(): array
    {
        if ($this->_beforeState !== null) {
            return $this->_beforeState;
        }
        if (! $this->prev) {
            return $this->_beforeState = ['rings' => [], 'windings' => [], 'multiPolys' => []];
        }
        $seg = $this->prev;
        while ($seg->consumedBy !== null && $seg->prev !== null) {
            $seg = $seg->prev;
        }

        return $this->_beforeState = $seg->afterState();
    }

    /**
     * @return mixed[]
     */
    public function afterState(): array
    {
        if ($this->_afterState !== null) {
            return $this->_afterState;
        }

        $beforeState = $this->beforeState();
        $this->_afterState = [
            'rings' => array_slice($beforeState['rings'], 0),
            'windings' => array_slice($beforeState['windings'], 0),
            'multiPolys' => [],
        ];

        $ringsAfter = &$this->_afterState['rings'];
        $windingsAfter = &$this->_afterState['windings'];
        $mpsAfter = &$this->_afterState['multiPolys'];

        foreach ($this->rings as $i => $ring) {
            $winding = $this->windings[$i];
            $index = array_search($ring, $ringsAfter, true);
            if ($index === false) {
                $ringsAfter[] = $ring;
                $windingsAfter[] = $winding;
            } else {
                $windingsAfter[$index] += $winding;
            }
        }

        $polysAfter = [];
        $polysExclude = [];
        foreach ($ringsAfter as $i => $ring) {
            if ($windingsAfter[$i] === 0) {
                continue;
            }
            $poly = $ring->poly;
            if (in_array($poly, $polysExclude, true)) {
                continue;
            }
            if ($ring->isExterior) {
                $polysAfter[] = $poly;
            } else {
                if (! in_array($poly, $polysExclude, true)) {
                    $polysExclude[] = $poly;
                }
                $index = array_search($poly, $polysAfter, true);
                if ($index !== false) {
                    array_splice($polysAfter, $index, 1);
                }
            }
        }

        foreach ($polysAfter as $poly) {
            $mp = $poly->multiPoly;
            if (! in_array($mp, $mpsAfter, true)) {
                $mpsAfter[] = $mp;
            }
        }

        return $this->_afterState;
    }

    public function isInResult(): bool
    {
        if ($this->consumedBy) {
            return false;
        }
        if ($this->_isInResult !== null) {
            return $this->_isInResult;
        }

        $mpsBefore = $this->beforeState()['multiPolys'];
        $mpsAfter = $this->afterState()['multiPolys'];

        switch (Operation::$type) {
            case 'union':
                $this->_isInResult = (count($mpsBefore) === 0) !== (count($mpsAfter) === 0);
                break;
            case 'intersection':
                $least = min(count($mpsBefore), count($mpsAfter));
                $most = max(count($mpsBefore), count($mpsAfter));
                $this->_isInResult = $most === Operation::$numMultiPolys && $least < $most;
                break;
            case 'xor':
                $this->_isInResult = abs(count($mpsBefore) - count($mpsAfter)) % 2 === 1;
                break;
            case 'difference':
                $isJustSubject = fn ($mps) => count($mps) === 1 && $mps[0]->isSubject;
                $this->_isInResult = $isJustSubject($mpsBefore) !== $isJustSubject($mpsAfter);
                break;
            default:
                $this->_isInResult = false;
        }

        return $this->_isInResult;
    }
}
