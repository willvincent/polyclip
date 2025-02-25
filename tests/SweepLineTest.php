<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Polyclip\Lib\Segment;
use Polyclip\Lib\SweepEvent;
use Polyclip\Lib\SweepLine;
use Polyclip\Lib\Vector;
use Brick\Math\BigDecimal;
use SplayTree\SplayTree;

class SweepLineTest extends TestCase
{
    public function testSweepEventCompareFavorEarlierX(): void
    {
        $s1 = new SweepEvent(new Vector(BigDecimal::of(-5), BigDecimal::of(4)), true);
        $s2 = new SweepEvent(new Vector(BigDecimal::of(5), BigDecimal::of(1)), true);
        $this->assertLessThan(0, SweepEvent::compare($s1, $s2));
        $this->assertGreaterThan(0, SweepEvent::compare($s2, $s1));
    }

    public function testSweepEventCompareFavorEarlierY(): void
    {
        $s1 = new SweepEvent(new Vector(BigDecimal::of(5), BigDecimal::of(-4)), true);
        $s2 = new SweepEvent(new Vector(BigDecimal::of(5), BigDecimal::of(4)), true);
        $this->assertLessThan(0, SweepEvent::compare($s1, $s2));
        $this->assertGreaterThan(0, SweepEvent::compare($s2, $s1));
    }

    public function testSweepLineProcess(): void
    {
        $queue = new SplayTree([SweepEvent::class, 'compare']);
        $sweepLine = new SweepLine($queue);

        $seg = Segment::fromRing(
            new Vector(BigDecimal::of(0), BigDecimal::of(0)),
            new Vector(BigDecimal::of(1), BigDecimal::of(1)),
            null
        );
        $queue->insert($seg->leftSE);
        $queue->insert($seg->rightSE);

        $newEvents = $sweepLine->process($seg->leftSE);
        $this->assertEmpty($newEvents);
        $this->assertCount(1, $sweepLine->segments);
        $this->assertSame($seg, $sweepLine->segments[0]);

        $newEvents = $sweepLine->process($seg->rightSE);
        $this->assertEmpty($newEvents);
        $this->assertCount(1, $sweepLine->segments);
    }

    public function testSweepLineSegmentsIntersectingAtPoint(): void
    {
        $queue = new SplayTree([SweepEvent::class, 'compare']);
        $sweepLine = new SweepLine($queue);

        $seg1 = Segment::fromRing(
            new Vector(BigDecimal::of(0), BigDecimal::of(0)),
            new Vector(BigDecimal::of(2), BigDecimal::of(2)),
            null
        );
        $seg2 = Segment::fromRing(
            new Vector(BigDecimal::of(0), BigDecimal::of(2)),
            new Vector(BigDecimal::of(2), BigDecimal::of(0)),
            null
        );

        $queue->insert($seg1->leftSE);
        $queue->insert($seg1->rightSE);
        $queue->insert($seg2->leftSE);
        $queue->insert($seg2->rightSE);

        while (! $queue->isEmpty()) {
            $event = $queue->min();
            $queue->delete($event);
            $newEvents = $sweepLine->process($event);
            foreach ($newEvents as $newEvent) {
                if ($newEvent->consumedBy === null) {
                    $queue->insert($newEvent);
                }
            }
        }

        $this->assertCount(4, $sweepLine->segments); // After all events, expect 4 segments
    }

    public function testSweepLineSegmentsOverlapping(): void
    {
        $queue = new SplayTree([SweepEvent::class, 'compare']);
        $sweepLine = new SweepLine($queue);

        $seg1 = Segment::fromRing(
            new Vector(BigDecimal::of(0), BigDecimal::of(0)),
            new Vector(BigDecimal::of(2), BigDecimal::of(0)),
            null
        );
        $seg2 = Segment::fromRing(
            new Vector(BigDecimal::of(1), BigDecimal::of(0)),
            new Vector(BigDecimal::of(3), BigDecimal::of(0)),
            null
        );

        $queue->insert($seg1->leftSE);
        $queue->insert($seg1->rightSE);
        $queue->insert($seg2->leftSE);
        $queue->insert($seg2->rightSE);

        while (! $queue->isEmpty()) {
            $event = $queue->min();
            $queue->delete($event);
            $newEvents = $sweepLine->process($event);
            foreach ($newEvents as $newEvent) {
                if ($newEvent->consumedBy === null) {
                    $queue->insert($newEvent);
                }
            }
        }

        $this->assertCount(2, $sweepLine->segments); // Current behavior: 2 segments
    }

    public function testSweepLineVerticalAndHorizontal(): void
    {
        $queue = new SplayTree([SweepEvent::class, 'compare']);
        $sweepLine = new SweepLine($queue);

        $vertical = Segment::fromRing(
            new Vector(BigDecimal::of(1), BigDecimal::of(0)),
            new Vector(BigDecimal::of(1), BigDecimal::of(2)),
            null
        );
        $horizontal = Segment::fromRing(
            new Vector(BigDecimal::of(0), BigDecimal::of(1)),
            new Vector(BigDecimal::of(2), BigDecimal::of(1)),
            null
        );

        $queue->insert($vertical->leftSE);
        $queue->insert($vertical->rightSE);
        $queue->insert($horizontal->leftSE);
        $queue->insert($horizontal->rightSE);

        $sweepLine->process($vertical->leftSE);
        $newEvents = $sweepLine->process($horizontal->leftSE);

        $this->assertNotEmpty($newEvents); // Intersection detected
    }
}