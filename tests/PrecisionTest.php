<?php

declare(strict_types=1);

namespace Polyclip\Tests;

use PHPUnit\Framework\TestCase;
use Polyclip\Lib\Util;
use Brick\Math\BigDecimal;

class PrecisionTest extends TestCase
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

    public function testComparatorExactlyEqual(): void
    {
        $comparator = Util::comparator();
        $a = BigDecimal::of(1);
        $b = BigDecimal::of(1);
        $this->assertEquals(0, $comparator($a, $b));
    }

    public function testComparatorFlpEqual(): void
    {
        $comparator = Util::comparator();
        $epsilon = BigDecimal::of(1e-10);
        $a = BigDecimal::of(1);
        $b = BigDecimal::of(1)->plus($epsilon->dividedBy(2));
        $this->assertEquals(0, $comparator($a, $b));
    }

    public function testComparatorBarelyLessThan(): void
    {
        $comparator = Util::comparator();
        $epsilon = BigDecimal::of(1e-10);
        $a = BigDecimal::of(1);
        $b = BigDecimal::of(1)->plus($epsilon->multipliedBy(2));
        $this->assertEquals(-1, $comparator($a, $b));
    }

    public function testComparatorLessThan(): void
    {
        $comparator = Util::comparator();
        $a = BigDecimal::of(1);
        $b = BigDecimal::of(2);
        $this->assertEquals(-1, $comparator($a, $b));
    }

    public function testComparatorBarelyMoreThan(): void
    {
        $comparator = Util::comparator();
        $epsilon = BigDecimal::of(1e-10);
        $a = BigDecimal::of(1)->plus($epsilon->multipliedBy(2));
        $b = BigDecimal::of(1);
        $this->assertEquals(1, $comparator($a, $b));
    }

    public function testComparatorMoreThan(): void
    {
        $comparator = Util::comparator();
        $a = BigDecimal::of(2);
        $b = BigDecimal::of(1);
        $this->assertEquals(1, $comparator($a, $b));
    }

    public function testComparatorBothFlpEqualToZero(): void
    {
        $comparator = Util::comparator();
        $epsilon = BigDecimal::of(1e-10);
        $a = BigDecimal::of(0);
        $b = $epsilon->minus($epsilon->multipliedBy($epsilon));
        $this->assertEquals(0, $comparator($a, $b));
    }

    public function testComparatorReallyCloseToZero(): void
    {
        $comparator = Util::comparator();
        $epsilon = BigDecimal::of(1e-10);
        $a = $epsilon;
        $b = $epsilon->plus($epsilon->multipliedBy($epsilon)->multipliedBy(2));
        $this->assertEquals(0, $comparator($a, $b));
    }
}