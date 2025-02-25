<?php

declare(strict_types=1);

use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;
use Polyclip\Lib\Util;

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

    public function test_comparator_exactly_equal(): void
    {
        $comparator = Util::comparator();
        $a = BigDecimal::of(1);
        $b = BigDecimal::of(1);
        $this->assertEquals(0, $comparator($a, $b));
    }

    public function test_comparator_flp_equal(): void
    {
        $comparator = Util::comparator();
        $epsilon = BigDecimal::of(1e-10);
        $a = BigDecimal::of(1);
        $b = BigDecimal::of(1)->plus($epsilon->dividedBy(2));
        $this->assertEquals(0, $comparator($a, $b));
    }

    public function test_comparator_barely_less_than(): void
    {
        $comparator = Util::comparator();
        $epsilon = BigDecimal::of(1e-10);
        $a = BigDecimal::of(1);
        $b = BigDecimal::of(1)->plus($epsilon->multipliedBy(2));
        $this->assertEquals(-1, $comparator($a, $b));
    }

    public function test_comparator_less_than(): void
    {
        $comparator = Util::comparator();
        $a = BigDecimal::of(1);
        $b = BigDecimal::of(2);
        $this->assertEquals(-1, $comparator($a, $b));
    }

    public function test_comparator_barely_more_than(): void
    {
        $comparator = Util::comparator();
        $epsilon = BigDecimal::of(1e-10);
        $a = BigDecimal::of(1)->plus($epsilon->multipliedBy(2));
        $b = BigDecimal::of(1);
        $this->assertEquals(1, $comparator($a, $b));
    }

    public function test_comparator_more_than(): void
    {
        $comparator = Util::comparator();
        $a = BigDecimal::of(2);
        $b = BigDecimal::of(1);
        $this->assertEquals(1, $comparator($a, $b));
    }

    public function test_comparator_both_flp_equal_to_zero(): void
    {
        $comparator = Util::comparator();
        $epsilon = BigDecimal::of(1e-10);
        $a = BigDecimal::of(0);
        $b = $epsilon->minus($epsilon->multipliedBy($epsilon));
        $this->assertEquals(0, $comparator($a, $b));
    }

    public function test_comparator_really_close_to_zero(): void
    {
        $comparator = Util::comparator();
        $epsilon = BigDecimal::of(1e-10);
        $a = $epsilon;
        $b = $epsilon->plus($epsilon->multipliedBy($epsilon)->multipliedBy(2));
        $this->assertEquals(0, $comparator($a, $b));
    }
}
