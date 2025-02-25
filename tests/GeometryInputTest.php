<?php

declare(strict_types=1);

use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;
use Polyclip\Lib\Geometry\MultiPolyIn;
use Polyclip\Lib\Geometry\PolyIn;
use Polyclip\Lib\Geometry\RingIn;
use Polyclip\Lib\Util;

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

    public function test_ring_in_create_exterior_ring(): void
    {
        $ringGeom = [
            [BigDecimal::of(0), BigDecimal::of(0)],
            [BigDecimal::of(1), BigDecimal::of(0)],
            [BigDecimal::of(1), BigDecimal::of(1)],
        ];
        $poly = $this->createMock(PolyIn::class);
        $ring = new RingIn($ringGeom, $poly, true);

        $this->assertSame($poly, $ring->poly);
        $this->assertTrue($ring->isExterior);
        $this->assertCount(3, $ring->segments);
        $this->assertCount(6, $ring->getSweepEvents());

        $this->assertTrue(BigDecimal::of(0)->isEqualTo($ring->segments[0]->leftSE->point->x));
        $this->assertTrue(BigDecimal::of(0)->isEqualTo($ring->segments[0]->leftSE->point->y));
        $this->assertTrue(BigDecimal::of(1)->isEqualTo($ring->segments[0]->rightSE->point->x));
        $this->assertTrue(BigDecimal::of(0)->isEqualTo($ring->segments[0]->rightSE->point->y));
        // Add similar assertions for other segments
    }

    public function test_ring_in_create_interior_ring(): void
    {
        $ringGeom = [
            [BigDecimal::of(0), BigDecimal::of(0)],
            [BigDecimal::of(1), BigDecimal::of(1)],
            [BigDecimal::of(1), BigDecimal::of(0)],
        ];
        $poly = $this->createMock(PolyIn::class);
        $ring = new RingIn($ringGeom, $poly, false);

        $this->assertFalse($ring->isExterior);
    }

    public function test_poly_in_creation(): void
    {
        $multiPoly = $this->createMock(MultiPolyIn::class);
        $polyGeom = [
            [
                [BigDecimal::of(0), BigDecimal::of(0)],
                [BigDecimal::of(10), BigDecimal::of(0)],
                [BigDecimal::of(10), BigDecimal::of(10)],
                [BigDecimal::of(0), BigDecimal::of(10)],
            ],
            [
                [BigDecimal::of(0), BigDecimal::of(0)],
                [BigDecimal::of(1), BigDecimal::of(1)],
                [BigDecimal::of(1), BigDecimal::of(0)],
            ],
            [
                [BigDecimal::of(2), BigDecimal::of(2)],
                [BigDecimal::of(2), BigDecimal::of(3)],
                [BigDecimal::of(3), BigDecimal::of(3)],
                [BigDecimal::of(3), BigDecimal::of(2)],
            ],
        ];
        $poly = new PolyIn($polyGeom, $multiPoly);

        $this->assertSame($multiPoly, $poly->multiPoly);
        $this->assertCount(4, $poly->exteriorRing->segments);
        $this->assertCount(2, $poly->interiorRings);
        $this->assertCount(3, $poly->interiorRings[0]->segments);
        $this->assertCount(4, $poly->interiorRings[1]->segments);
        $this->assertCount(22, $poly->getSweepEvents());
    }

    public function test_multi_poly_in_creation_with_multi_poly(): void
    {

        $multiPolyGeom = [
            [
                [
                    [BigDecimal::of(0), BigDecimal::of(0)],
                    [BigDecimal::of(1), BigDecimal::of(1)],
                    [BigDecimal::of(0), BigDecimal::of(1)],
                ],
            ],
            [
                [
                    [BigDecimal::of(0), BigDecimal::of(0)],
                    [BigDecimal::of(4), BigDecimal::of(0)],
                    [BigDecimal::of(4), BigDecimal::of(9)],
                ],
                [
                    [BigDecimal::of(2), BigDecimal::of(2)],
                    [BigDecimal::of(3), BigDecimal::of(3)],
                    [BigDecimal::of(3), BigDecimal::of(2)],
                ],
            ],
        ];

        $multiPoly = new MultiPolyIn($multiPolyGeom, true);
        $this->assertCount(2, $multiPoly->polys);
        $this->assertCount(18, $multiPoly->getSweepEvents());
    }

    public function test_multi_poly_in_creation_with_poly(): void
    {
        $polyGeom = [
            [
                [
                    [BigDecimal::of(0), BigDecimal::of(0)],
                    [BigDecimal::of(1), BigDecimal::of(1)],
                    [BigDecimal::of(0), BigDecimal::of(1)],
                    [BigDecimal::of(0), BigDecimal::of(0)],
                ],
            ],
        ];
        $multiPoly = new MultiPolyIn($polyGeom, true);

        $this->assertCount(1, $multiPoly->polys);
        $this->assertCount(6, $multiPoly->getSweepEvents());
    }

    public function test_multi_poly_in_creation_with_invalid_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Input geometry is not a valid Polygon or MultiPolygon');
        new MultiPolyIn([], true);
    }

    public function test_ring_in_with_empty_geometry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Input geometry is not a valid Polygon or MultiPolygon');
        $poly = $this->createMock(PolyIn::class);
        new RingIn([], $poly, true);
    }

    public function test_ring_in_with_less_than_three_points(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Input geometry is not a valid Polygon or MultiPolygon');
        $poly = $this->createMock(PolyIn::class);
        $geom = [
            [BigDecimal::of(0), BigDecimal::of(0)],
            [BigDecimal::of(1), BigDecimal::of(1)],
        ];
        $ring = new RingIn($geom, $poly, true);
        print_r($ring);
    }

    //

    public function test_ring_in_with_non_closed_ring(): void
    {
        $poly = $this->createMock(PolyIn::class);
        $geom = [
            [BigDecimal::of(0), BigDecimal::of(0)],
            [BigDecimal::of(1), BigDecimal::of(1)],
            [BigDecimal::of(2), BigDecimal::of(0)],
        ];
        $ring = new RingIn($geom, $poly, true);

        $this->assertCount(3, $ring->segments); // Ring should be auto-closed
        $lastSegment = end($ring->segments);

        // Fix: Check leftSE instead of rightSE
        $this->assertTrue(BigDecimal::of(0)->isEqualTo($lastSegment->leftSE->point->x));
        $this->assertTrue(BigDecimal::of(0)->isEqualTo($lastSegment->leftSE->point->y));
    }

    public function test_ring_in_with_invalid_coordinate_types(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Input geometry is not a valid Polygon or MultiPolygon');
        $poly = $this->createMock(PolyIn::class);
        $geom = [
            ['not_a_number', BigDecimal::of(0)],
            [BigDecimal::of(1), BigDecimal::of(1)],
            [BigDecimal::of(0), BigDecimal::of(1)],
        ];
        new RingIn($geom, $poly, true);
    }

    public function test_ring_in_with_extra_coordinates(): void
    {
        $poly = $this->createMock(PolyIn::class);
        $geom = [
            [BigDecimal::of(0), BigDecimal::of(0), BigDecimal::of(5)],
            [BigDecimal::of(1), BigDecimal::of(1), BigDecimal::of(6)],
            [BigDecimal::of(0), BigDecimal::of(1), BigDecimal::of(7)],
        ];
        $ring = new RingIn($geom, $poly, true);

        $this->assertCount(3, $ring->segments); // Only x, y should be used
        $this->assertTrue(BigDecimal::of(0)->isEqualTo($ring->segments[0]->leftSE->point->x));
        $this->assertTrue(BigDecimal::of(0)->isEqualTo($ring->segments[0]->leftSE->point->y));
    }
}
