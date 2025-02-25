<?php

declare(strict_types=1);

namespace Polyclip;

use Brick\Math\BigDecimal;
use GeoJson\Feature\Feature;
use GeoJson\GeoJson;
use GeoJson\Geometry\Geometry;
use GeoJson\Geometry\MultiPolygon;
use GeoJson\Geometry\Polygon;
use Polyclip\Lib\Operation;
use Polyclip\Lib\Util;

final class Clipper
{
    /**
     * Performs a union operation on the given geometries.
     *
     * @param  mixed  $geom  The first geometry (GeoJson object, GeoJSON string, or array)
     * @param  mixed  ...$moreGeoms  Additional geometries
     * @return GeoJson The resulting geometry as a GeoJson object
     */
    public static function union($geom, ...$moreGeoms): GeoJson
    {
        $geoms = self::prepareGeometries($geom, ...$moreGeoms);
        $resultArray = Operation::run('union', ...$geoms);

        return self::arrayToGeoJson($resultArray);
    }

    /**
     * Performs an intersection operation on the given geometries.
     *
     * @param  mixed  $geom  The first geometry (GeoJson object, GeoJSON string, or array)
     * @param  mixed  ...$moreGeoms  Additional geometries
     * @return GeoJson The resulting geometry as a GeoJson object
     */
    public static function intersection($geom, ...$moreGeoms): GeoJson
    {
        $geoms = self::prepareGeometries($geom, ...$moreGeoms);
        $resultArray = Operation::run('intersection', ...$geoms);

        return self::arrayToGeoJson($resultArray);
    }

    /**
     * Performs an XOR operation on the given geometries.
     *
     * @param  mixed  $geom  The first geometry (GeoJson object, GeoJSON string, or array)
     * @param  mixed  ...$moreGeoms  Additional geometries
     * @return GeoJson The resulting geometry as a GeoJson object
     */
    public static function xor($geom, ...$moreGeoms): GeoJson
    {
        $geoms = self::prepareGeometries($geom, ...$moreGeoms);
        $resultArray = Operation::run('xor', ...$geoms);

        return self::arrayToGeoJson($resultArray);
    }

    /**
     * Performs a difference operation on the given geometries.
     *
     * @param  mixed  $geom  The subject geometry (GeoJson object, GeoJSON string, or array)
     * @param  mixed  ...$moreGeoms  Geometries to subtract
     * @return GeoJson The resulting geometry as a GeoJson object
     */
    public static function difference($geom, ...$moreGeoms): GeoJson
    {
        $geoms = self::prepareGeometries($geom, ...$moreGeoms);
        $resultArray = Operation::run('difference', ...$geoms);

        return self::arrayToGeoJson($resultArray);
    }

    /**
     * Sets the default precision (epsilon) for floating-point comparisons.
     *
     * @param  float  $epsilon  The precision value
     */
    public static function setPrecision(float $epsilon): void
    {
        Util::$defaultEpsilon = $epsilon;
    }

    /**
     * Prepares geometries by converting them to the internal array format.
     *
     * @param  mixed  ...$geoms  Geometries to prepare
     * @return mixed[] Prepared geometries in array format
     */
    private static function prepareGeometries(...$geoms): array
    {
        return array_map([self::class, 'geoJsonToArray'], $geoms);
    }

    /**
     * Recursively converts all numbers in the coordinate array to BigDecimal.
     *
     * @param  mixed[]  $coordinates
     * @return mixed[]
     */
    private static function convertToBigDecimal(array $coordinates): array
    {
        return array_map(function ($item) {
            if (is_array($item)) {
                return self::convertToBigDecimal($item);
            }

            return BigDecimal::of($item);
        }, $coordinates);
    }

    /**
     * Recursively converts all BigDecimal instances in the coordinate array to floats.
     *
     * @param  mixed[]  $coordinates
     * @return mixed[]
     */
    private static function convertToFloat(array $coordinates): array
    {
        return array_map(function ($item) {
            if (is_array($item)) {
                return self::convertToFloat($item);
            }
            if ($item instanceof BigDecimal) {
                return $item->toFloat();
            }

            return $item;
        }, $coordinates);
    }

    /**
     * @param  string|GeoJson|mixed[]  $geom
     * @return mixed[]
     */
    private static function geoJsonToArray($geom): array
    {
        if (is_string($geom)) {
            $decoded = json_decode($geom, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid GeoJSON string provided');
            }
            $geom = GeoJson::jsonUnserialize($decoded);
        }

        if ($geom instanceof GeoJson) {
            if ($geom instanceof Feature) {
                $geom = $geom->getGeometry();
                if ($geom === null) {
                    throw new \InvalidArgumentException('Feature has no geometry');
                }
            }
            if ($geom instanceof Geometry) {
                $coordinates = $geom->getCoordinates();
                if ($geom instanceof Polygon || $geom instanceof MultiPolygon) {
                    // Convert all coordinates to BigDecimal
                    return self::convertToBigDecimal($coordinates);
                }
                throw new \InvalidArgumentException('Only Polygon or MultiPolygon geometries are supported');
            }
        }

        if (is_array($geom)) {
            $geom = self::convertToBigDecimal($geom);
            if (empty($geom) || ! is_array($geom[0])) {
                throw new \InvalidArgumentException('Array must represent a valid Polygon or MultiPolygon');
            }

            return $geom;
        }

        throw new \InvalidArgumentException('Input must be a GeoJson object, GeoJSON string, or coordinate array');
    }

    /**
     * @param  mixed[]  $array
     */
    private static function arrayToGeoJson(array $array): GeoJson
    {
        if (empty($array)) {
            return new Polygon([]);
        }

        // Convert BigDecimal to floats
        $array = self::convertToFloat($array);

        // If there's only one polygon, return it as a Polygon; otherwise, MultiPolygon
        if (count($array) === 1) {
            return new Polygon($array[0]);
        } else {
            return new MultiPolygon($array);
        }
    }
}
