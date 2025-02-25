<?php

declare(strict_types=1);

use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;
use Polyclip\Lib\Geometry\MultiPolyOut;
use Polyclip\Lib\Geometry\PolyOut;
use Polyclip\Lib\Geometry\RingOut;
use Polyclip\Lib\Segment;
use Polyclip\Lib\Util;
use Polyclip\Lib\Vector;

class GeometryOutputTest extends TestCase
{
    protected function setUp(): void
    {
        Util::reset();
        Util::$defaultEpsilon = 1e-10;
    }

    protected function tearDown(): void
    {
        Util::reset();
    }

    public function test_ring_out_factory_simple_triangle(): void
    {
        $p1 = new Vector(BigDecimal::of(0), BigDecimal::of(0));
        $p2 = new Vector(BigDecimal::of(1), BigDecimal::of(1));
        $p3 = new Vector(BigDecimal::of(0), BigDecimal::of(1));

        $seg1 = Segment::fromRing($p1, $p2, null);
        $seg2 = Segment::fromRing($p2, $p3, null);
        $seg3 = Segment::fromRing($p3, $p1, null);

        $reflection = new \ReflectionClass($seg1);
        $property = $reflection->getProperty('_isInResult');
        $property->setAccessible(true);
        $property->setValue($seg1, true);
        $property->setValue($seg2, true);
        $property->setValue($seg3, true);

        $rings = RingOut::factory([$seg1, $seg2, $seg3]);

        $this->assertCount(1, $rings);
        $this->assertEquals(
            [
                [[0, 0], [0, 1], [1, 1], [0, 0]],
            ],
            [$rings[0]->getGeom()]
        );
    }

    public function test_ring_out_exterior_ring(): void
    {
        $p1 = new Vector(BigDecimal::of(0), BigDecimal::of(0));
        $p2 = new Vector(BigDecimal::of(1), BigDecimal::of(1));
        $p3 = new Vector(BigDecimal::of(0), BigDecimal::of(1));

        $seg1 = Segment::fromRing($p1, $p2, null);
        $seg2 = Segment::fromRing($p2, $p3, null);
        $seg3 = Segment::fromRing($p3, $p1, null);

        $reflection = new \ReflectionClass($seg1);
        $property = $reflection->getProperty('_isInResult');
        $property->setAccessible(true);
        $property->setValue($seg1, true);
        $property->setValue($seg2, true);
        $property->setValue($seg3, true);

        $ring = RingOut::factory([$seg1, $seg2, $seg3])[0];

        $this->assertNull($ring->enclosingRing());
        $this->assertTrue($ring->isExteriorRing());
        $this->assertEquals(
            [[0, 0], [0, 1], [1, 1], [0, 0]],
            $ring->getGeom()
        );
    }

    public function test_poly_out_basic(): void
    {
        $ring1 = $this->createMock(RingOut::class);
        $ring1->method('getGeom')->willReturn([[0, 0], [1, 0], [1, 1], [0, 0]]);
        $ring2 = $this->createMock(RingOut::class);
        $ring2->method('getGeom')->willReturn([[0.2, 0.2], [0.8, 0.2], [0.8, 0.8], [0.2, 0.2]]);
        $ring3 = $this->createMock(RingOut::class);
        $ring3->method('getGeom')->willReturn([[0.3, 0.3], [0.7, 0.3], [0.7, 0.7], [0.3, 0.3]]);

        $poly = new PolyOut($ring1);
        $poly->addInterior($ring2);
        $poly->addInterior($ring3);

        $this->assertSame($ring1->poly, $poly);
        $this->assertSame($ring2->poly, $poly);
        $this->assertSame($ring3->poly, $poly);
        $this->assertEquals([
            [[0, 0], [1, 0], [1, 1], [0, 0]],
            [[0.2, 0.2], [0.8, 0.2], [0.8, 0.8], [0.2, 0.2]],
            [[0.3, 0.3], [0.7, 0.3], [0.7, 0.7], [0.3, 0.3]],
        ], $poly->getGeom());
    }

    public function test_multi_poly_out_basic(): void
    {
        $poly1 = $this->createMock(PolyOut::class);
        $poly1->method('getGeom')->willReturn([
            [[0, 0], [1, 0], [1, 1], [0, 0]],
        ]);
        $poly2 = $this->createMock(PolyOut::class);
        $poly2->method('getGeom')->willReturn([
            [[2, 2], [3, 2], [3, 3], [2, 2]],
        ]);

        $multiPoly = new MultiPolyOut([]);
        $multiPoly->polys = [$poly1, $poly2];

        $this->assertEquals([
            [[[0, 0], [1, 0], [1, 1], [0, 0]]],
            [[[2, 2], [3, 2], [3, 3], [2, 2]]],
        ], $multiPoly->getGeom());
    }

    public function test_ring_out_factory_bow_tie(): void
    {
        $p1 = new Vector(BigDecimal::of(0), BigDecimal::of(0));
        $p2 = new Vector(BigDecimal::of(2), BigDecimal::of(2));
        $p3 = new Vector(BigDecimal::of(4), BigDecimal::of(0));
        $p4 = new Vector(BigDecimal::of(2), BigDecimal::of(-2));

        $seg1 = Segment::fromRing($p1, $p2, null);
        $seg2 = Segment::fromRing($p2, $p3, null);
        $seg3 = Segment::fromRing($p3, $p4, null);
        $seg4 = Segment::fromRing($p4, $p1, null);

        $reflection = new \ReflectionClass($seg1);
        $property = $reflection->getProperty('_isInResult');
        $property->setAccessible(true);
        $property->setValue($seg1, true);
        $property->setValue($seg2, true);
        $property->setValue($seg3, true);
        $property->setValue($seg4, true);

        $rings = RingOut::factory([$seg1, $seg2, $seg3, $seg4]);

        $this->assertCount(1, $rings);
        $geom = $rings[0]->getGeom();
        $this->assertEquals(
            [[0, 0], [2, -2], [4, 0], [2, 2], [0, 0]],
            $geom
        );
    }

    public function test_poly_out_with_hole(): void
    {
        $outerRing = $this->createMock(RingOut::class);
        $outerRing->method('getGeom')->willReturn([[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]]);
        $outerRing->method('isExteriorRing')->willReturn(true);

        $innerRing = $this->createMock(RingOut::class);
        $innerRing->method('getGeom')->willReturn([[1, 1], [3, 1], [3, 3], [1, 3], [1, 1]]);
        $innerRing->method('isExteriorRing')->willReturn(false);
        $innerRing->method('enclosingRing')->willReturn($outerRing);

        $poly = new PolyOut($outerRing);
        $poly->addInterior($innerRing);

        $this->assertEquals(
            [
                [[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]],
                [[1, 1], [3, 1], [3, 3], [1, 3], [1, 1]],
            ],
            $poly->getGeom()
        );
    }

    public function test_multi_poly_out_with_nested_rings(): void
    {
        $outer = $this->createMock(RingOut::class);
        $outer->method('getGeom')->willReturn([[0, 0], [5, 0], [5, 5], [0, 5], [0, 0]]);
        $outer->method('isExteriorRing')->willReturn(true);

        $middle = $this->createMock(RingOut::class);
        $middle->method('getGeom')->willReturn([[1, 1], [4, 1], [4, 4], [1, 4], [1, 1]]);
        $middle->method('isExteriorRing')->willReturn(false);
        $middle->method('enclosingRing')->willReturn($outer);

        $inner = $this->createMock(RingOut::class);
        $inner->method('getGeom')->willReturn([[2, 2], [3, 2], [3, 3], [2, 3], [2, 2]]);
        $inner->method('isExteriorRing')->willReturn(true);
        $inner->method('enclosingRing')->willReturn($middle);

        $multiPoly = new MultiPolyOut([$outer, $middle, $inner]);

        $expected = [
            [ // Polygon 1: outer with middle as hole
                [[0, 0], [5, 0], [5, 5], [0, 5], [0, 0]],
                [[1, 1], [4, 1], [4, 4], [1, 4], [1, 1]],
            ],
            [ // Polygon 2: inner
                [[2, 2], [3, 2], [3, 3], [2, 3], [2, 2]],
            ],
        ];

        $this->assertEquals($expected, $multiPoly->getGeom());
    }
}
