<?php

declare(strict_types=1);

namespace Polyclip\Tests;

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

    // Add more tests for sweep line operations, segment comparisons, etc., as per TypeScript tests
}