<?php

namespace Polyclip\Tests;

use Brick\Math\BigDecimal;
use GeoJson\Exception\UnserializationException;
use GeoJson\GeoJson;
use GeoJson\Geometry\MultiPolygon;
use GeoJson\Geometry\Polygon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Polyclip\Clipper;
use TypeError;

class ClipperTest extends TestCase
{
    private const EPSILON = 0.000001;

    /**
     * Normalizes GeoJson coordinates to floats for comparison.
     *
     * @param GeoJson $geoJson
     * @return mixed[]
     */
    private function normalizeCoordinates(GeoJson $geoJson): array
    {
        $toFloat = function ($coord) {
            if (is_array($coord)) {
                return array_map(fn($c) => $c instanceof BigDecimal ? $c->toFloat() : $c, $coord);
            }
            return $coord;
        };

        if ($this->noCoordinates($geoJson)) {
            return [];
        }

        $coordinates = $geoJson->getCoordinates();
        if (empty($coordinates)) {
            return [];
        }
        return $this->recursiveMap($coordinates, $toFloat);
    }

    private function noCoordinates(GeoJson|string|false $geoJson): bool
    {
        $json = json_encode($geoJson);
        if (!$json) {
            return false;
        }
        $geo = json_decode($json, true);
        return (!isset($geo['coordinates']));
    }

    /**
     * Recursively applies a function to all elements in an array.
     *
     * @param mixed[] $array
     * @param callable $func
     * @return mixed[]
     */
    private function recursiveMap(array $array, callable $func): array
    {
        return array_map(function ($item) use ($func) {
            return is_array($item) ? $this->recursiveMap($item, $func) : $func($item);
        }, $array);
    }

    /**
     * Asserts that two GeoJson objects are equal within a tolerance.
     *
     * @param GeoJson $expected
     * @param GeoJson $actual
     * @param float $epsilon
     */
    private function assertGeoJsonEquals(GeoJson $expected, GeoJson $actual, float $epsilon = self::EPSILON): void
    {
        $this->assertInstanceOf(get_class($expected), $actual);

        $expectedCoords = $this->normalizeCoordinates($expected);
        $actualCoords = $this->normalizeCoordinates($actual);

        $this->assertEqualsWithDelta($expectedCoords, $actualCoords, $epsilon, 'GeoJson coordinates do not match');
    }

    // --- Union Tests ---

    /**
     * Test union of non-overlapping polygons.
     */
    public function testUnionOfNonOverlappingPolygons(): void
    {
        $polygon1 = new Polygon([[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]]);
        $polygon2 = new Polygon([[[2, 2], [3, 2], [3, 3], [2, 3], [2, 2]]]);
        $expected = new MultiPolygon([
            [[[0, 0], [0, 1], [1, 1], [1, 0], [0, 0]]],
            [[[2, 2], [2, 3], [3, 3], [3, 2], [2, 2]]]
        ]);

        $result = Clipper::union($polygon1, $polygon2);
        $this->assertGeoJsonEquals($expected, $result);
    }

    /**
     * Test union of overlapping polygons.
     */
    public function testUnionOfOverlappingPolygons(): void
    {
        $polygon1 = new Polygon([[[0, 0], [2, 0], [2, 2], [0, 2], [0, 0]]]);
        $polygon2 = new Polygon([[[1, 1], [3, 1], [3, 3], [1, 3], [1, 1]]]);
        $expected = new Polygon([[[0, 0], [0, 2], [1, 2], [1, 3], [3, 3], [3, 1], [2, 1], [2, 0], [0, 0]]]);

        $result = Clipper::union($polygon1, $polygon2);
        $this->assertGeoJsonEquals($expected, $result);
    }

    /**
     * Test union of a polygon with a hole and another polygon.
     */
    public function testUnionWithHole(): void
    {
        $outer = new Polygon([[[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]], [[1, 1], [1, 2], [2, 2], [2, 1], [1, 1]]]);
        $inner = new Polygon([[[1.5, 1.5], [2.5, 1.5], [2.5, 2.5], [1.5, 2.5], [1.5, 1.5]]]);
        $expected = new Polygon([[[0, 0], [0, 4], [4, 4], [4, 0], [0, 0]], [[1.5, 1.5], [1.5, 2.5], [2.5, 2.5], [2.5, 1.5], [1.5, 1.5]]]);

        $result = Clipper::union($outer, $inner);
        $this->assertGeoJsonEquals($expected, $result);
    }

    /**
     * Test union with real-world lat/lng coordinates.
     */
    public function testUnionWithLatLng(): void
    {
        $polygon1 = new Polygon([[[-74.0, 40.7], [-73.9, 40.7], [-73.9, 40.8], [-74.0, 40.8], [-74.0, 40.7]]]); // NYC area
        $polygon2 = new Polygon([[[-73.95, 40.75], [-73.85, 40.75], [-73.85, 40.85], [-73.95, 40.85], [-73.95, 40.75]]]); // Overlapping area
        $expected = new Polygon([[[-74.0, 40.7], [-74.0, 40.8], [-73.95, 40.8], [-73.95, 40.85], [-73.85, 40.85], [-73.85, 40.75], [-73.9, 40.75], [-73.9, 40.7], [-74.0, 40.7]]]);

        $result = Clipper::union($polygon1, $polygon2);
        $this->assertGeoJsonEquals($expected, $result, self::EPSILON);
    }

    // --- Intersection Tests ---

    /**
     * Test intersection of overlapping polygons.
     */
    public function testIntersectionOfOverlappingPolygons(): void
    {
        $polygon1 = new Polygon([[[0, 0], [2, 0], [2, 2], [0, 2], [0, 0]]]);
        $polygon2 = new Polygon([[[1, 1], [3, 1], [3, 3], [1, 3], [1, 1]]]);
        $expected = new Polygon([[[1, 1], [1, 2], [2, 2], [2, 1], [1, 1]]]);

        $result = Clipper::intersection($polygon1, $polygon2);
        $this->assertGeoJsonEquals($expected, $result);
    }

    /**
     * Test intersection of non-overlapping polygons.
     */
    public function testIntersectionOfNonOverlappingPolygons(): void
    {
        $polygon1 = new Polygon([[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]]);
        $polygon2 = new Polygon([[[2, 2], [3, 2], [3, 3], [2, 3], [2, 2]]]);
        $expected = new Polygon([]); // Empty Polygon

        $result = Clipper::intersection($polygon1, $polygon2);
        $this->assertGeoJsonEquals($expected, $result);
    }

    /**
     * Test intersection of multiple polygons with holes.
     */
    public function testIntersectionWithHoles(): void
    {
        $polygon1 = new Polygon([[[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]], [[1, 1], [1, 2], [2, 2], [2, 1], [1, 1]]]);
        $polygon2 = new Polygon([[[1, 1], [3, 1], [3, 3], [1, 3], [1, 1]]]);
        $expected = new Polygon([[[2, 2], [2, 1], [1, 1], [1, 2], [2, 2]]]);

        $result = Clipper::intersection($polygon1, $polygon2);
        $this->assertGeoJsonEquals($expected, $result);
    }

    /**
     * Test intersection with real-world lat/lng coordinates.
     */
    public function testIntersectionWithLatLng(): void
    {
        $polygon1 = new Polygon([[[-74.0, 40.7], [-73.9, 40.7], [-73.9, 40.8], [-74.0, 40.8], [-74.0, 40.7]]]); // NYC area
        $polygon2 = new Polygon([[[-73.95, 40.75], [-73.85, 40.75], [-73.85, 40.85], [-73.95, 40.85], [-73.95, 40.75]]]); // Overlapping area
        $expected = new Polygon([[[-73.95, 40.75], [-73.95, 40.8], [-73.9, 40.8], [-73.9, 40.75], [-73.95, 40.75]]]);

        $result = Clipper::intersection($polygon1, $polygon2);
        $this->assertGeoJsonEquals($expected, $result, self::EPSILON);
    }

    // --- Edge Case Tests ---

    /**
     * Test union of polygons touching at a single edge.
     */
    public function testUnionOfTouchingPolygons(): void
    {
        $polygon1 = new Polygon([[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]]);
        $polygon2 = new Polygon([[[1, 0], [2, 0], [2, 1], [1, 1], [1, 0]]]);
        $expected = new Polygon([[[0, 0], [0, 1], [1, 1], [2, 1], [2, 0], [1, 0], [0, 0]]]);

        $result = Clipper::union($polygon1, $polygon2);
        $this->assertGeoJsonEquals($expected, $result);
    }

    /**
     * Test intersection with very close coordinates to test precision.
     */
    public function testIntersectionWithPrecision(): void
    {
        $polygon1 = new Polygon([[[0, 0], [1.000001, 0], [1.000001, 1], [0, 1], [0, 0]]]);
        $polygon2 = new Polygon([[[1, 0], [2, 0], [2, 1], [1, 1], [1, 0]]]);
        $expected = new Polygon([[[1, 0], [1.000001, 0], [1.000001, 1], [1, 1], [1, 0]]]);

        $result = Clipper::intersection($polygon1, $polygon2);
        $this->assertGeoJsonEquals($expected, $result, 6);
    }

    // --- Input Format Tests ---

    /**
     * Test union with GeoJSON string input.
     */
    public function testUnionWithGeoJsonString(): void
    {
        $geoJsonStr1 = '{"type": "Polygon", "coordinates": [[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]]}';
        $geoJsonStr2 = '{"type": "Polygon", "coordinates": [[[0.5, 0.5], [1.5, 0.5], [1.5, 1.5], [0.5, 1.5], [0.5, 0.5]]]}';
        $expected = new Polygon([[[0, 0], [0, 1], [0.5, 1], [0.5, 1.5], [1.5, 1.5], [1.5, 0.5], [1, 0.5], [1, 0], [0, 0]]]);

        $result = Clipper::union($geoJsonStr1, $geoJsonStr2);
        $this->assertGeoJsonEquals($expected, $result);
    }

    /**
     * Test union with coordinate array input.
     */
    public function testUnionWithArrayInput(): void
    {
        $array1 = [[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]];
        $array2 = [[[0.5, 0.5], [1.5, 0.5], [1.5, 1.5], [0.5, 1.5], [0.5, 0.5]]];
        $expected = new Polygon([[[0, 0], [0, 1], [0.5, 1], [0.5, 1.5], [1.5, 1.5], [1.5, 0.5], [1, 0.5], [1, 0], [0, 0]]]);

        $result = Clipper::union($array1, $array2);
        $this->assertGeoJsonEquals($expected, $result);
    }

    // --- Error Handling Tests ---

    /**
     * Test handling of invalid GeoJSON string input.
     */
    public function testInvalidGeoJsonString(): void
    {
        $this->expectException(UnserializationException::class);
        $this->expectExceptionMessage('Polygon expected "coordinates" property of type array, string given');

        $invalidGeoJson = '{ "type": "Polygon", "coordinates": "invalid" }';
        Clipper::union($invalidGeoJson);
    }

    /**
     * Test handling of non-polygon geometry input.
     */
    public function testNonPolygonGeometry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only Polygon or MultiPolygon geometries are supported');

        $point = new \GeoJson\Geometry\Point([0, 0]);
        Clipper::union($point);
    }

    /**
     * Test handling of invalid array input.
     */
    public function testInvalidArrayInput(): void
    {
        $this->expectException(TypeError::class);

        $invalidArray = [[0, 0], [1, 1]]; // Not a closed ring
        Clipper::union($invalidArray);
    }
}
