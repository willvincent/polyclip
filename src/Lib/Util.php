<?php

declare(strict_types=1);

namespace Polyclip\Lib;

use Brick\Math\BigDecimal;
use SplayTree\SplayTree;

class Util
{
    protected static ?SplayTree $xTree = null;

    protected static ?SplayTree $yTree = null;

    protected static mixed $pointCache = [];

    public static ?float $defaultEpsilon = null;

    public static function comparator(?float $epsilon = null): callable
    {
        $epsilon = $epsilon ?? self::$defaultEpsilon;
        if (is_null($epsilon)) {
            return function (BigDecimal $a, BigDecimal $b): int {
                return $a->compareTo($b);
            };
        }

        $epsilonDecimal = BigDecimal::of($epsilon);

        return function (BigDecimal $a, BigDecimal $b) use ($epsilonDecimal): int {
            if ($b->minus($a)->abs()->compareTo($epsilonDecimal) <= 0) {
                return 0;
            }

            return $a->compareTo($b);
        };
    }

    public static function createSnap(?float $epsilon = null): callable
    {
        $epsilon = $epsilon ?? self::$defaultEpsilon;
        if (is_null($epsilon)) {
            return function (Vector $vector): Vector {
                return $vector;
            };
        }

        if (self::$xTree === null) {
            self::$xTree = new SplayTree(self::comparator($epsilon));
        }
        if (self::$yTree === null) {
            self::$yTree = new SplayTree(self::comparator($epsilon));
        }

        $snap = function (Vector $vector): Vector {
            $snappedX = self::$xTree->insert($vector->x)->data;
            $snappedY = self::$yTree->insert($vector->y)->data;
            $key = "$snappedX,$snappedY";
            if (! isset(self::$pointCache[$key])) {
                self::$pointCache[$key] = new Vector($snappedX, $snappedY);
            }

            return self::$pointCache[$key];
        };

        // Initialize with [0,0]
        $snap(new Vector(BigDecimal::zero(), BigDecimal::zero()));

        return $snap;
    }

    public static function orientation(?float $epsilon = null): callable
    {
        $epsilon = $epsilon ?? self::$defaultEpsilon;
        if (is_null($epsilon)) {
            $almostCollinear = function (): bool {
                return false;
            };
        } else {
            $almostCollinear = function (
                BigDecimal $area2,
                BigDecimal $ax,
                BigDecimal $ay,
                BigDecimal $cx,
                BigDecimal $cy,
            ) use ($epsilon): bool {
                $epsilonDecimal = BigDecimal::of($epsilon);

                return $area2->power(2)
                    ->compareTo(
                        $cx->minus($ax)
                            ->power(2)
                            ->plus($cy->minus($ay)->power(2))
                            ->multipliedBy($epsilonDecimal)
                    ) <= 0;
            };
        }

        return function (Vector $a, Vector $b, Vector $c) use ($almostCollinear): int {
            $ax = $a->x;
            $ay = $a->y;
            $cx = $c->x;
            $cy = $c->y;

            $area2 = $ay->minus($cy)
                ->multipliedBy($b->x->minus($cx))
                ->minus($ax->minus($cx)->multipliedBy($b->y->minus($cy)));

            if ($almostCollinear($area2, $ax, $ay, $cx, $cy)) {
                return 0;
            }

            return $area2->compareTo(BigDecimal::zero());
        };
    }

    public static function reset(): void
    {
        self::$xTree = null;
        self::$yTree = null;
        self::$pointCache = [];
    }
}
