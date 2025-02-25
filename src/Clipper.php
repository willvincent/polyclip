<?php

declare(strict_types=1);

namespace Polyclip;

use Brick\Math\BigDecimal;
use GeoJson\Feature\Feature;
use GeoJson\Feature\FeatureCollection;
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
     * @return Feature The resulting geometry as a GeoJson Feature
     */
    public static function union($geom, ...$moreGeoms): Feature
    {
        $geoms = self::prepareGeometries($geom, ...$moreGeoms);
        $resultArray = Operation::run('union', ...$geoms);

        return self::arrayToFeature($resultArray);
    }

    /**
     * Performs an intersection operation on the given geometries.
     *
     * @param  mixed  $geom  The first geometry (GeoJson object, GeoJSON string, or array)
     * @param  mixed  ...$moreGeoms  Additional geometries
     * @return Feature The resulting geometry as a GeoJson Feature
     */
    public static function intersection($geom, ...$moreGeoms): Feature
    {
        $geoms = self::prepareGeometries($geom, ...$moreGeoms);
        $resultArray = Operation::run('intersection', ...$geoms);

        return self::arrayToFeature($resultArray);
    }

    /**
     * Performs an XOR operation on the given geometries.
     *
     * @param  mixed  $geom  The first geometry (GeoJson object, GeoJSON string, or array)
     * @param  mixed  ...$moreGeoms  Additional geometries
     * @return Feature The resulting geometry as a GeoJson Feature
     */
    public static function xor($geom, ...$moreGeoms): Feature
    {
        $geoms = self::prepareGeometries($geom, ...$moreGeoms);
        $resultArray = Operation::run('xor', ...$geoms);

        return self::arrayToFeature($resultArray);
    }

    /**
     * Performs a difference operation on the given geometries.
     *
     * @param  mixed  $geom  The subject geometry (GeoJson object, GeoJSON string, or array)
     * @param  mixed  ...$moreGeoms  Geometries to subtract
     * @return Feature The resulting geometry as a GeoJson Feature
     */
    public static function difference($geom, ...$moreGeoms): Feature
    {
        $geoms = self::prepareGeometries($geom, ...$moreGeoms);
        $resultArray = Operation::run('difference', ...$geoms);

        return self::arrayToFeature($resultArray);
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
     * @return array Prepared geometries as a flat array of coordinate arrays
     */
    private static function prepareGeometries(...$geoms): array
    {
        $coordinateArrays = [];
        foreach ($geoms as $geom) {
            $result = self::geoJsonToArray($geom);
            $coordinateArrays = array_merge($coordinateArrays, $result);
        }

        return $coordinateArrays;
    }

    /**
     * Converts input to coordinate arrays, handling FeatureCollections.
     *
     * @param  string|GeoJson|array  $geom
     * @return array Array of coordinate arrays
     *
     * @throws \InvalidArgumentException
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

        if ($geom instanceof FeatureCollection) {
            $coordinateArrays = [];
            foreach ($geom->getFeatures() as $feature) {
                $geometry = $feature->getGeometry();
                if ($geometry !== null) {
                    $coordinateArrays[] = self::geometryToArray($geometry);
                }
            }

            return $coordinateArrays;
        } elseif ($geom instanceof Feature) {
            $geometry = $geom->getGeometry();
            if ($geometry === null) {
                throw new \InvalidArgumentException('Feature has no geometry');
            }

            return [self::geometryToArray($geometry)];
        } elseif ($geom instanceof Geometry) {
            return [self::geometryToArray($geom)];
        } elseif (is_array($geom)) {
            return [self::convertToBigDecimal($geom)];
        } else {
            throw new \InvalidArgumentException('Input must be a GeoJson object, GeoJSON string, or coordinate array');
        }
    }

    /**
     * Converts a Geometry object to a coordinate array.
     *
     * @throws \InvalidArgumentException
     */
    private static function geometryToArray(Geometry $geometry): array
    {
        if ($geometry instanceof Polygon || $geometry instanceof MultiPolygon) {
            $coordinates = $geometry->getCoordinates();

            return self::convertToBigDecimal($coordinates);
        }
        throw new \InvalidArgumentException('Only Polygon or MultiPolygon geometries are supported');
    }

    /**
     * Recursively converts all numbers in the coordinate array to BigDecimal.
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
     * Converts a coordinate array to a Geometry object.
     */
    private static function arrayToGeometry(array $array): Geometry
    {
        $array = self::convertToFloat($array);
        if (empty($array)) {
            return new Polygon([]);
        }
        if (count($array) === 1) {
            return new Polygon($array[0]);
        }

        return new MultiPolygon($array);
    }

    /**
     * Converts a coordinate array to a Feature with empty properties.
     */
    private static function arrayToFeature(array $array): Feature
    {
        $geometry = self::arrayToGeometry($array);

        return new Feature($geometry, []);
    }
}
