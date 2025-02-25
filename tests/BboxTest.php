<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Polyclip\Lib\Bbox;
use Polyclip\Lib\Vector;
use Brick\Math\BigDecimal;

class BboxTest extends TestCase
{
    public function testPointInBboxOutside(): void
    {
        $bbox = new Bbox(
            new Vector(BigDecimal::of(1), BigDecimal::of(2)),
            new Vector(BigDecimal::of(5), BigDecimal::of(6))
        );
        $this->assertFalse($bbox->pointInBbox(new Vector(BigDecimal::of(0), BigDecimal::of(3))));
        $this->assertFalse($bbox->pointInBbox(new Vector(BigDecimal::of(3), BigDecimal::of(30))));
        $this->assertFalse($bbox->pointInBbox(new Vector(BigDecimal::of(3), BigDecimal::of(-30))));
        $this->assertFalse($bbox->pointInBbox(new Vector(BigDecimal::of(9), BigDecimal::of(3))));
    }

    public function testPointInBboxInside(): void
    {
        $bbox = new Bbox(
            new Vector(BigDecimal::of(1), BigDecimal::of(2)),
            new Vector(BigDecimal::of(5), BigDecimal::of(6))
        );
        $this->assertTrue($bbox->pointInBbox(new Vector(BigDecimal::of(1), BigDecimal::of(2))));
        $this->assertTrue($bbox->pointInBbox(new Vector(BigDecimal::of(5), BigDecimal::of(6))));
        $this->assertTrue($bbox->pointInBbox(new Vector(BigDecimal::of(1), BigDecimal::of(6))));
        $this->assertTrue($bbox->pointInBbox(new Vector(BigDecimal::of(5), BigDecimal::of(2))));
        $this->assertTrue($bbox->pointInBbox(new Vector(BigDecimal::of(3), BigDecimal::of(4))));
    }

    public function testPointInBboxBarelyInsideAndOutside(): void
    {
        $epsilon = BigDecimal::of(1e-10);
        $bbox = new Bbox(
            new Vector(BigDecimal::of(1), BigDecimal::of(0.8)),
            new Vector(BigDecimal::of(1.2), BigDecimal::of(6))
        );
        $this->assertTrue($bbox->pointInBbox(new Vector(BigDecimal::of(1.2)->minus($epsilon), BigDecimal::of(6))));
        $this->assertFalse($bbox->pointInBbox(new Vector(BigDecimal::of(1.2)->plus($epsilon), BigDecimal::of(6))));
        $this->assertTrue($bbox->pointInBbox(new Vector(BigDecimal::of(1), BigDecimal::of(0.8)->plus($epsilon))));
        $this->assertFalse($bbox->pointInBbox(new Vector(BigDecimal::of(1), BigDecimal::of(0.8)->minus($epsilon))));
    }

    public function testGetBboxOverlapDisjoint(): void
    {
        $bbox1 = new Bbox(
            new Vector(BigDecimal::of(4), BigDecimal::of(4)),
            new Vector(BigDecimal::of(6), BigDecimal::of(6))
        );
        $bboxAbove = new Bbox(
            new Vector(BigDecimal::of(7), BigDecimal::of(7)),
            new Vector(BigDecimal::of(8), BigDecimal::of(8))
        );
        $bboxLeft = new Bbox(
            new Vector(BigDecimal::of(1), BigDecimal::of(5)),
            new Vector(BigDecimal::of(3), BigDecimal::of(8))
        );
        $bboxDown = new Bbox(
            new Vector(BigDecimal::of(2), BigDecimal::of(2)),
            new Vector(BigDecimal::of(3), BigDecimal::of(3))
        );
        $bboxRight = new Bbox(
            new Vector(BigDecimal::of(12), BigDecimal::of(1)),
            new Vector(BigDecimal::of(14), BigDecimal::of(9))
        );

        $this->assertNull($bbox1->getBboxOverlap($bbox1, $bboxAbove));
        $this->assertNull($bbox1->getBboxOverlap($bbox1, $bboxLeft));
        $this->assertNull($bbox1->getBboxOverlap($bbox1, $bboxDown));
        $this->assertNull($bbox1->getBboxOverlap($bbox1, $bboxRight));
    }

    public function testGetBboxOverlapTouchingOnePoint(): void
    {
        $bbox1 = new Bbox(
            new Vector(BigDecimal::of(4), BigDecimal::of(4)),
            new Vector(BigDecimal::of(6), BigDecimal::of(6))
        );
        $bboxUpperRight = new Bbox(
            new Vector(BigDecimal::of(6), BigDecimal::of(6)),
            new Vector(BigDecimal::of(7), BigDecimal::of(8))
        );
        $overlap = $bbox1->getBboxOverlap($bbox1, $bboxUpperRight);

        $this->assertNotNull($overlap);
        $this->assertEquals(BigDecimal::of(6), $overlap->lowerLeft->x);
        $this->assertEquals(BigDecimal::of(6), $overlap->lowerLeft->y);
        $this->assertEquals(BigDecimal::of(6), $overlap->upperRight->x);
        $this->assertEquals(BigDecimal::of(6), $overlap->upperRight->y);

    }

    public function testGetBboxOverlapOverlappingTwoPoints(): void
    {
        $bbox1 = new Bbox(
            new Vector(BigDecimal::of(4), BigDecimal::of(4)),
            new Vector(BigDecimal::of(6), BigDecimal::of(6))
        );
        $bboxMatching = new Bbox(
            new Vector(BigDecimal::of(4), BigDecimal::of(4)),
            new Vector(BigDecimal::of(6), BigDecimal::of(6))
        );
        $overlap = $bbox1->getBboxOverlap($bbox1, $bboxMatching);
        $this->assertNotNull($overlap);
        $this->assertEquals(BigDecimal::of(4), $overlap->lowerLeft->x);
        $this->assertEquals(BigDecimal::of(4), $overlap->lowerLeft->y);
        $this->assertEquals(BigDecimal::of(6), $overlap->upperRight->x);
        $this->assertEquals(BigDecimal::of(6), $overlap->upperRight->y);

        $bboxPartial = new Bbox(
            new Vector(BigDecimal::of(5), BigDecimal::of(5)),
            new Vector(BigDecimal::of(7), BigDecimal::of(7))
        );
        $overlap = $bbox1->getBboxOverlap($bbox1, $bboxPartial);
        $this->assertNotNull($overlap);
        $this->assertEquals(BigDecimal::of(5), $overlap->lowerLeft->x);
        $this->assertEquals(BigDecimal::of(5), $overlap->lowerLeft->y);
        $this->assertEquals(BigDecimal::of(6), $overlap->upperRight->x);
        $this->assertEquals(BigDecimal::of(6), $overlap->upperRight->y);

    }

    public function testGetBboxOverlapOneCompletelyInsideAnother(): void
    {
        $outer = new Bbox(
            new Vector(BigDecimal::of(0), BigDecimal::of(0)),
            new Vector(BigDecimal::of(10), BigDecimal::of(10))
        );
        $inner = new Bbox(
            new Vector(BigDecimal::of(2), BigDecimal::of(2)),
            new Vector(BigDecimal::of(4), BigDecimal::of(4))
        );
        $overlap = $outer->getBboxOverlap($outer, $inner);

        $this->assertNotNull($overlap);
        $this->assertEquals(BigDecimal::of(2), $overlap->lowerLeft->x);
        $this->assertEquals(BigDecimal::of(2), $overlap->lowerLeft->y);
        $this->assertEquals(BigDecimal::of(4), $overlap->upperRight->x);
        $this->assertEquals(BigDecimal::of(4), $overlap->upperRight->y);
    }

    public function testGetBboxOverlapPartialOverlap(): void
    {
        $bbox1 = new Bbox(
            new Vector(BigDecimal::of(0), BigDecimal::of(0)),
            new Vector(BigDecimal::of(5), BigDecimal::of(5))
        );
        $bbox2 = new Bbox(
            new Vector(BigDecimal::of(3), BigDecimal::of(3)),
            new Vector(BigDecimal::of(8), BigDecimal::of(8))
        );
        $overlap = $bbox1->getBboxOverlap($bbox1, $bbox2);

        $this->assertNotNull($overlap);
        $this->assertEquals(BigDecimal::of(3), $overlap->lowerLeft->x);
        $this->assertEquals(BigDecimal::of(3), $overlap->lowerLeft->y);
        $this->assertEquals(BigDecimal::of(5), $overlap->upperRight->x);
        $this->assertEquals(BigDecimal::of(5), $overlap->upperRight->y);
    }

    public function testGetBboxOverlapTouchingAlongEdge(): void
    {
        $bbox1 = new Bbox(
            new Vector(BigDecimal::of(0), BigDecimal::of(0)),
            new Vector(BigDecimal::of(5), BigDecimal::of(5))
        );
        $bbox2 = new Bbox(
            new Vector(BigDecimal::of(5), BigDecimal::of(0)),
            new Vector(BigDecimal::of(10), BigDecimal::of(5))
        );
        $overlap = $bbox1->getBboxOverlap($bbox1, $bbox2);

        $this->assertNotNull($overlap);
        $this->assertEquals(BigDecimal::of(5), $overlap->lowerLeft->x);
        $this->assertEquals(BigDecimal::of(0), $overlap->lowerLeft->y);
        $this->assertEquals(BigDecimal::of(5), $overlap->upperRight->x);
        $this->assertEquals(BigDecimal::of(5), $overlap->upperRight->y);
    }

    public function testGetBboxOverlapTouchingAtAllCorners(): void
    {
        $bbox1 = new Bbox(
            new Vector(BigDecimal::of(0), BigDecimal::of(0)),
            new Vector(BigDecimal::of(5), BigDecimal::of(5))
        );

        $corners = [
            'upper-left' => new Bbox(
                new Vector(BigDecimal::of(-1), BigDecimal::of(5)),
                new Vector(BigDecimal::of(0), BigDecimal::of(6))
            ),
            'upper-right' => new Bbox(
                new Vector(BigDecimal::of(5), BigDecimal::of(5)),
                new Vector(BigDecimal::of(6), BigDecimal::of(6))
            ),
            'lower-left' => new Bbox(
                new Vector(BigDecimal::of(-1), BigDecimal::of(-1)),
                new Vector(BigDecimal::of(0), BigDecimal::of(0))
            ),
            'lower-right' => new Bbox(
                new Vector(BigDecimal::of(5), BigDecimal::of(-1)),
                new Vector(BigDecimal::of(6), BigDecimal::of(0))
            ),
        ];

        foreach ($corners as $name => $bbox2) {
            $overlap = $bbox1->getBboxOverlap($bbox1, $bbox2);
            $this->assertNotNull($overlap, "Overlap should exist for $name corner");
            $this->assertEquals(0, $overlap->lowerLeft->x->compareTo($overlap->upperRight->x) == 0 ? $overlap->lowerLeft->y->compareTo($overlap->upperRight->y) : 0, "Overlap should be a point for $name corner");
        }
    }
}
