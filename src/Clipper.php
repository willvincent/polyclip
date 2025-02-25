<?php

namespace Polyclip;

use Polyclip\Lib\Operation;
use Polyclip\Lib\Util;

final class Clipper {
    /**
     * Performs a union operation on the given geometries.
     *
     * @param array $geom The first geometry (Polygon or MultiPolygon)
     * @param array ...$moreGeoms Additional geometries
     * @return array The resulting geometry
     */
    public static function union(array $geom, array ...$moreGeoms): array
    {
        return Operation::run('union', $geom, ...$moreGeoms);
    }

    /**
     * Performs an intersection operation on the given geometries.
     *
     * @param array $geom The first geometry (Polygon or MultiPolygon)
     * @param array ...$moreGeoms Additional geometries
     * @return array The resulting geometry
     */
    public static function intersection(array $geom, array ...$moreGeoms): array
    {
        return Operation::run('intersection', $geom, ...$moreGeoms);
    }

    /**
     * Performs an XOR operation on the given geometries.
     *
     * @param array $geom The first geometry (Polygon or MultiPolygon)
     * @param array ...$moreGeoms Additional geometries
     * @return array The resulting geometry
     */
    public static function xor(array $geom, array ...$moreGeoms): array
    {
        return Operation::run('xor', $geom, ...$moreGeoms);
    }

    /**
     * Performs a difference operation on the given geometries.
     *
     * @param array $geom The subject geometry (Polygon or MultiPolygon)
     * @param array ...$moreGeoms Geometries to subtract
     * @return array The resulting geometry
     */
    public static function difference(array $geom, array ...$moreGeoms): array
    {
        return Operation::run('difference', $geom, ...$moreGeoms);
    }

    /**
     * Sets the default precision (epsilon) for floating-point comparisons.
     *
     * @param float $epsilon The precision value
     */
    public static function setPrecision(float $epsilon): void
    {
        Util::$defaultEpsilon = $epsilon;
    }

}

