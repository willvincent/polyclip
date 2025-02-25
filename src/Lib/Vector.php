<?php

declare(strict_types=1);

namespace Polyclip\Lib;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class Vector
{
    /**
     * @param  SweepEvent[]|null  $events
     */
    public function __construct(
        public BigDecimal $x,
        public BigDecimal $y,
        public ?array $events = null
    ) {}

    public static function crossProduct(Vector $vector1, Vector $vector2): BigDecimal
    {
        return $vector1->x->multipliedBy($vector2->y)->minus($vector1->y->multipliedBy($vector2->x));
    }

    public static function dotProduct(Vector $vector1, Vector $vector2): BigDecimal
    {
        return $vector1->x->multipliedBy($vector2->x)->plus($vector1->y->multipliedBy($vector2->y));
    }

    public static function length(Vector $vector): BigDecimal
    {
        return static::dotProduct($vector, $vector)->sqrt(10);
    }

    public static function sineOfAngle(Vector $shared, Vector $base, Vector $angle): BigDecimal
    {
        $vectorBase = new Vector(
            x: $base->x->minus($shared->x),
            y: $base->y->minus($shared->y),
        );
        $vectorAngle = new Vector(
            x: $angle->x->minus($shared->x),
            y: $angle->y->minus($shared->y),
        );

        return static::crossProduct($vectorAngle, $vectorBase)
            ->dividedBy(static::length($vectorAngle), 10, RoundingMode::DOWN)
            ->dividedBy(static::length($vectorBase), 10, RoundingMode::DOWN);
    }

    public static function cosineOfAngle(Vector $shared, Vector $base, Vector $angle): BigDecimal
    {
        $vectorBase = new Vector(
            x: $base->x->minus($shared->x),
            y: $base->y->minus($shared->y),
        );

        $vectorAngle = new Vector(
            x: $angle->x->minus($shared->x),
            y: $angle->y->minus($shared->y),
        );

        return static::dotProduct($vectorAngle, $vectorBase)
            ->dividedBy(static::length($vectorAngle), 10, RoundingMode::DOWN)
            ->dividedBy(static::length($vectorBase), 10, RoundingMode::DOWN);
    }

    public static function horizontalIntersection(Vector $point, Vector $vector, BigDecimal $y): ?Vector
    {
        if ($vector->y->isZero()) {
            return null;
        }

        return new Vector(
            x: $point->x->plus(($vector->x->dividedBy($vector->y, 10, RoundingMode::DOWN))->multipliedBy($y->minus($point->y))),
            y: $y,
        );
    }

    public static function verticalIntersection(Vector $point, Vector $vector, BigDecimal $x): ?Vector
    {
        if ($vector->x->isZero()) {
            return null;
        }

        return new Vector(
            x: $x,
            y: $point->y->plus(($vector->y->dividedBy($vector->x, 10, RoundingMode::DOWN))->multipliedBy($x->minus($point->x))),
        );
    }

    public static function intersection(Vector $point1, Vector $vector1, Vector $point2, Vector $vector2): ?Vector
    {
        // take some shortcuts for vertical and horizontal lines
        // this also ensures we don't calculate an intersection and then discover
        // it's actually outside the bounding box of the line
        if ($vector1->x->isZero()) {
            return static::verticalIntersection($point2, $vector2, $point1->x);
        }
        if ($vector2->x->isZero()) {
            return static::verticalIntersection($point1, $vector1, $point2->x);
        }
        if ($vector1->y->isZero()) {
            return static::horizontalIntersection($point2, $vector2, $point1->y);
        }
        if ($vector2->y->isZero()) {
            return static::horizontalIntersection($point1, $vector1, $point2->y);
        }

        // General case for non-overlapping segments.
        // This algorithm is based on Schneider and Eberly.
        // http://www.cimec.org.ar/~ncalvo/Schneider_Eberly.pdf - pg 244
        $cross = static::crossProduct($vector1, $vector2);
        if ($cross->isZero()) {
            return null;
        }

        $vector = new Vector(
            x: $point2->x->minus($point1->x),
            y: $point2->y->minus($point1->y),
        );
        $d1 = static::crossProduct($vector, $vector1)->dividedBy($cross, 20, RoundingMode::DOWN);
        $d2 = static::crossProduct($vector, $vector2)->dividedBy($cross, 20, RoundingMode::DOWN);

        $x1 = $point1->x->plus($d2->multipliedBy($vector1->x));
        $x2 = $point2->x->plus($d1->multipliedBy($vector2->x));
        $y1 = $point1->y->plus($d2->multipliedBy($vector1->y));
        $y2 = $point2->y->plus($d1->multipliedBy($vector2->y));

        $x = ($x1->plus($x2))->dividedBy(2, 20, RoundingMode::DOWN);
        $y = ($y1->plus($y2))->dividedBy(2, 20, RoundingMode::DOWN);

        return new Vector($x, $y);
    }

    public static function perpendicular(Vector $vector): Vector
    {
        return new Vector(
            x: $vector->y->negated(),
            y: $vector->x,
        );
    }
}
