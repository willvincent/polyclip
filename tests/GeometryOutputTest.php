<?php

declare(strict_types=1);

namespace Polyclip\Tests;

use PHPUnit\Framework\TestCase;
use Polyclip\Lib\Geometry\RingOut;
use Polyclip\Lib\Geometry\PolyOut;
use Polyclip\Lib\Geometry\MultiPolyOut;
use Polyclip\Lib\Segment;
use Polyclip\Lib\SweepEvent;
use Polyclip\Lib\Vector;
use Polyclip\Lib\Util;
use Brick\Math\BigDecimal;

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

    public function testRingOutFactorySimpleTriangle(): void
    {
        $p1 = new Vector(BigDecimal::of(0), BigDecimal::of(0));
        $p2 = new Vector(BigDecimal::of(1), BigDecimal::of(1));
        $p3 = new Vector(BigDecimal::of(0), BigDecimal::of(1));

        $seg1 = Segment::fromRing($p1, $p2, null);
        $seg2 = Segment::fromRing($p2, $p3, null);
        $seg3 = Segment::fromRing($p3, $p1, null);

        // Mock isInResult to true
        $reflection = new \ReflectionClass($seg1);
        $property = $reflection->getProperty('_isInResult');
        $property->setAccessible(true);
        $property->setValue($seg1, true);
        $property->setValue($seg2, true);
        $property->setValue($seg3, true);

        $rings = RingOut::factory([$seg1, $seg2, $seg3]);

        $this->assertCount(1, $rings);
        $this->assertEquals([
            [[0, 0], [1, 1], [0, 1], [0, 0]]
        ], [$rings[0]->getGeom()]);
    }

    public function testRingOutExteriorRing(): void
    {
        $p1 = new Vector(BigDecimal::of(0), BigDecimal::of(0));
        $p2 = new Vector(BigDecimal::of(1), BigDecimal::of(1));
        $p3 = new Vector(BigDecimal::of(0), BigDecimal::of(1));

        $seg1 = Segment::fromRing($p1, $p2, null);
        $seg2 = Segment::fromRing($p2, $p3, null);
        $seg3 = Segment::fromRing($p3, $p1, null);

        // Mock isInResult to true
        $reflection = new \ReflectionClass($seg1);
        $property = $reflection->getProperty('_isInResult');
        $property->setAccessible(true);
        $property->setValue($seg1, true);
        $property->setValue($seg2, true);
        $property->setValue($seg3, true);

        $ring = RingOut::factory([$seg1, $seg2, $seg3])[0];

        $this->assertNull($ring->enclosingRing());
        $this->assertTrue($ring->isExteriorRing());
        $this->assertEquals([[0, 0], [1, 1], [0, 1], [0, 0]], $ring->getGeom());
    }

    public function testPolyOutBasic(): void
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
            [[0.3, 0.3], [0.7, 0.3], [0.7, 0.7], [0.3, 0.3]]
        ], $poly->getGeom());
    }

    public function testMultiPolyOutBasic(): void
    {
        $poly1 = $this->createMock(PolyOut::class);
        $poly1->method('getGeom')->willReturn([
            [[0, 0], [1, 0], [1, 1], [0, 0]]
        ]);
        $poly2 = $this->createMock(PolyOut::class);
        $poly2->method('getGeom')->willReturn([
            [[2, 2], [3, 2], [3, 3], [2, 2]]
        ]);

        $multiPoly = new MultiPolyOut([]);
        $multiPoly->polys = [$poly1, $poly2];

        $this->assertEquals([
            [[[0, 0], [1, 0], [1, 1], [0, 0]]],
            [[[2, 2], [3, 2], [3, 3], [2, 2]]]
        ], $multiPoly->getGeom());
    }

    // Add more tests for complex configurations like bow ties, ringed rings, etc., as per TypeScript tests
}