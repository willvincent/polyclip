<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Polyclip\Lib\Geometry\MultiPolyOut;
use Polyclip\Lib\Geometry\PolyOut;
use Polyclip\Lib\Geometry\RingIn;
use Polyclip\Lib\Geometry\PolyIn;
use Polyclip\Lib\Geometry\MultiPolyIn;
use Polyclip\Lib\Geometry\RingOut;
use Polyclip\Lib\Segment;
use Polyclip\Lib\Util;
use Polyclip\Lib\Vector;
use Brick\Math\BigDecimal;

class GeometryInputTest extends TestCase
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

    public function testRingInCreateExteriorRing(): void
    {
        $ringGeom = [
            [BigDecimal::of(0), BigDecimal::of(0)],
            [BigDecimal::of(1), BigDecimal::of(0)],
            [BigDecimal::of(1), BigDecimal::of(1)]
        ];
        $poly = $this->createMock(PolyIn::class);
        $ring = new RingIn($ringGeom, $poly, true);

        $this->assertSame($poly, $ring->poly);
        $this->assertTrue($ring->isExterior);
        $this->assertCount(3, $ring->segments);
        $this->assertCount(6, $ring->getSweepEvents());

        $this->assertEquals(BigDecimal::of(0), $ring->segments[0]->leftSE->point->x);
        $this->assertEquals(BigDecimal::of(0), $ring->segments[0]->leftSE->point->y);
        $this->assertEquals(BigDecimal::of(1), $ring->segments[0]->rightSE->point->x);
        $this->assertEquals(BigDecimal::of(0), $ring->segments[0]->rightSE->point->y);
        // Add similar assertions for other segments
    }

    public function testRingInCreateInteriorRing(): void
    {
        $ringGeom = [
            [BigDecimal::of(0), BigDecimal::of(0)],
            [BigDecimal::of(1), BigDecimal::of(1)],
            [BigDecimal::of(1), BigDecimal::of(0)]
        ];
        $poly = $this->createMock(PolyIn::class);
        $ring = new RingIn($ringGeom, $poly, false);

        $this->assertFalse($ring->isExterior);
    }

    public function testPolyInCreation(): void
    {
        $multiPoly = $this->createMock(MultiPolyIn::class);
        $polyGeom = [
            [
                [BigDecimal::of(0), BigDecimal::of(0)],
                [BigDecimal::of(10), BigDecimal::of(0)],
                [BigDecimal::of(10), BigDecimal::of(10)],
                [BigDecimal::of(0), BigDecimal::of(10)]
            ],
            [
                [BigDecimal::of(0), BigDecimal::of(0)],
                [BigDecimal::of(1), BigDecimal::of(1)],
                [BigDecimal::of(1), BigDecimal::of(0)]
            ],
            [
                [BigDecimal::of(2), BigDecimal::of(2)],
                [BigDecimal::of(2), BigDecimal::of(3)],
                [BigDecimal::of(3), BigDecimal::of(3)],
                [BigDecimal::of(3), BigDecimal::of(2)]
            ]
        ];
        $poly = new PolyIn($polyGeom, $multiPoly);

        $this->assertSame($multiPoly, $poly->multiPoly);
        $this->assertCount(4, $poly->exteriorRing->segments);
        $this->assertCount(2, $poly->interiorRings);
        $this->assertCount(3, $poly->interiorRings[0]->segments);
        $this->assertCount(4, $poly->interiorRings[1]->segments);
        $this->assertCount(22, $poly->getSweepEvents());
    }

    public function testMultiPolyInCreationWithMultiPoly(): void
    {

        $multiPolyGeom = [
            [
                [
                    [BigDecimal::of(0), BigDecimal::of(0)],
                    [BigDecimal::of(1), BigDecimal::of(1)],
                    [BigDecimal::of(0), BigDecimal::of(1)]
                ]
            ],
            [
                [
                    [BigDecimal::of(0), BigDecimal::of(0)],
                    [BigDecimal::of(4), BigDecimal::of(0)],
                    [BigDecimal::of(4), BigDecimal::of(9)]
                ],
                [
                    [BigDecimal::of(2), BigDecimal::of(2)],
                    [BigDecimal::of(3), BigDecimal::of(3)],
                    [BigDecimal::of(3), BigDecimal::of(2)]
                ]
            ]
        ];

        $multiPoly = new MultiPolyIn($multiPolyGeom, true);
        $this->assertCount(2, $multiPoly->polys);
        $this->assertCount(18, $multiPoly->getSweepEvents());
    }

    public function testMultiPolyInCreationWithPoly(): void
    {
        $polyGeom = [
            [
                [
                    [BigDecimal::of(0), BigDecimal::of(0)],
                    [BigDecimal::of(1), BigDecimal::of(1)],
                    [BigDecimal::of(0), BigDecimal::of(1)],
                    [BigDecimal::of(0), BigDecimal::of(0)]
                ]
            ]
        ];
        $multiPoly = new MultiPolyIn($polyGeom, true);

        $this->assertCount(1, $multiPoly->polys);
        $this->assertCount(6, $multiPoly->getSweepEvents());
    }

    public function testMultiPolyInCreationWithInvalidInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Input geometry is not a valid Polygon or MultiPolygon');
        new MultiPolyIn([], true);
    }

    public function testRingInWithEmptyGeometry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Input geometry is not a valid Polygon or MultiPolygon');
        $poly = $this->createMock(PolyIn::class);
        new RingIn([], $poly, true);
    }

    public function testRingInWithLessThanThreePoints(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Input geometry is not a valid Polygon or MultiPolygon');
        $poly = $this->createMock(PolyIn::class);
        $geom = [
            [BigDecimal::of(0), BigDecimal::of(0)],
            [BigDecimal::of(1), BigDecimal::of(1)]
        ];
        $ring = new RingIn($geom, $poly, true);
        print_r($ring);
    }

//

    public function testRingInWithNonClosedRing(): void
    {
        $poly = $this->createMock(PolyIn::class);
        $geom = [
            [BigDecimal::of(0), BigDecimal::of(0)],
            [BigDecimal::of(1), BigDecimal::of(1)],
            [BigDecimal::of(2), BigDecimal::of(0)]
        ];
        $ring = new RingIn($geom, $poly, true);

        $this->assertCount(3, $ring->segments); // Ring should be auto-closed
        $lastSegment = end($ring->segments);

        // Fix: Check leftSE instead of rightSE
        $this->assertEquals(BigDecimal::of(0), $lastSegment->leftSE->point->x);
        $this->assertEquals(BigDecimal::of(0), $lastSegment->leftSE->point->y);
    }

    public function testRingInWithInvalidCoordinateTypes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Input geometry is not a valid Polygon or MultiPolygon');
        $poly = $this->createMock(PolyIn::class);
        $geom = [
            ['not_a_number', BigDecimal::of(0)],
            [BigDecimal::of(1), BigDecimal::of(1)],
            [BigDecimal::of(0), BigDecimal::of(1)]
        ];
        new RingIn($geom, $poly, true);
    }

    public function testRingInWithExtraCoordinates(): void
    {
        $poly = $this->createMock(PolyIn::class);
        $geom = [
            [BigDecimal::of(0), BigDecimal::of(0), BigDecimal::of(5)],
            [BigDecimal::of(1), BigDecimal::of(1), BigDecimal::of(6)],
            [BigDecimal::of(0), BigDecimal::of(1), BigDecimal::of(7)]
        ];
        $ring = new RingIn($geom, $poly, true);

        $this->assertCount(3, $ring->segments); // Only x, y should be used
        $this->assertEquals(BigDecimal::of(0), $ring->segments[0]->leftSE->point->x);
        $this->assertEquals(BigDecimal::of(0), $ring->segments[0]->leftSE->point->y);
    }
}