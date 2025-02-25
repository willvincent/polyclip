<?php

declare(strict_types=1);

namespace Polyclip\Tests;

use PHPUnit\Framework\TestCase;
use Polyclip\Lib\Vector;
use Brick\Math\BigDecimal;

class VectorTest extends TestCase
{
    public function testCrossProduct(): void
    {
        $v1 = new Vector(BigDecimal::of(1), BigDecimal::of(2));
        $v2 = new Vector(BigDecimal::of(3), BigDecimal::of(4));
        $this->assertEquals(BigDecimal::of(-2), Vector::crossProduct($v1, $v2));
    }

    public function testDotProduct(): void
    {
        $v1 = new Vector(BigDecimal::of(1), BigDecimal::of(2));
        $v2 = new Vector(BigDecimal::of(3), BigDecimal::of(4));
        $this->assertEquals(BigDecimal::of(11), Vector::dotProduct($v1, $v2));
    }

    public function testLengthHorizontal(): void
    {
        $v = new Vector(BigDecimal::of(3), BigDecimal::of(0));
        $this->assertTrue(BigDecimal::of(3)->isEqualTo(Vector::length($v)));
    }

    public function testLengthVertical(): void
    {
        $v = new Vector(BigDecimal::of(0), BigDecimal::of(-2));
        $this->assertTrue(BigDecimal::of(2)->isEqualTo(Vector::length($v)));
    }

    public function testLength345Triangle(): void
    {
        $v = new Vector(BigDecimal::of(3), BigDecimal::of(4));
        $this->assertTrue(BigDecimal::of(5)->isEqualTo(Vector::length($v)));
    }

    public function testSineAndCosineOfAngleParallel(): void
    {
        $shared = new Vector(BigDecimal::of(0), BigDecimal::of(0));
        $base = new Vector(BigDecimal::of(1), BigDecimal::of(0));
        $angle = new Vector(BigDecimal::of(1), BigDecimal::of(0));
        $this->assertEquals(BigDecimal::of(0), Vector::sineOfAngle($shared, $base, $angle));
        $this->assertEquals(BigDecimal::of(1), Vector::cosineOfAngle($shared, $base, $angle));
    }

    public function testSineAndCosineOfAngle90Degrees(): void
    {
        $shared = new Vector(BigDecimal::of(0), BigDecimal::of(0));
        $base = new Vector(BigDecimal::of(1), BigDecimal::of(0));
        $angle = new Vector(BigDecimal::of(0), BigDecimal::of(-1));
        $this->assertEquals(BigDecimal::of(1), Vector::sineOfAngle($shared, $base, $angle));
        $this->assertEquals(BigDecimal::of(0), Vector::cosineOfAngle($shared, $base, $angle));
    }

    public function testPerpendicularVertical(): void
    {
        $v = new Vector(BigDecimal::of(0), BigDecimal::of(1));
        $r = Vector::perpendicular($v);
        $this->assertEquals(BigDecimal::of(0), Vector::dotProduct($v, $r));
        $this->assertNotEquals(BigDecimal::of(0), Vector::crossProduct($v, $r));
    }

    public function testVerticalIntersection(): void
    {
        $p = new Vector(BigDecimal::of(42), BigDecimal::of(3));
        $v = new Vector(BigDecimal::of(-2), BigDecimal::of(0));
        $x = BigDecimal::of(37);
        $i = Vector::verticalIntersection($p, $v, $x);
        $this->assertNotNull($i);
        $this->assertTrue(BigDecimal::of(37)->isEqualTo($i->x));
        $this->assertTrue(BigDecimal::of(3)->isEqualTo($i->y));
    }

    public function testHorizontalIntersection(): void
    {
        $p = new Vector(BigDecimal::of(42), BigDecimal::of(3));
        $v = new Vector(BigDecimal::of(0), BigDecimal::of(4));
        $y = BigDecimal::of(37);
        $i = Vector::horizontalIntersection($p, $v, $y);
        $this->assertNotNull($i);
        $this->assertTrue(BigDecimal::of(42)->isEqualTo($i->x));
        $this->assertTrue(BigDecimal::of(37)->isEqualTo($i->y));
    }

    public function testIntersectionHorizontalAndVertical(): void
    {
        $p1 = new Vector(BigDecimal::of(42), BigDecimal::of(42));
        $v1 = new Vector(BigDecimal::of(0), BigDecimal::of(2));
        $p2 = new Vector(BigDecimal::of(-32), BigDecimal::of(46));
        $v2 = new Vector(BigDecimal::of(-1), BigDecimal::of(0));
        $i = Vector::intersection($p1, $v1, $p2, $v2);
        $this->assertNotNull($i);
        $this->assertTrue(BigDecimal::of(42)->isEqualTo($i->x));
        $this->assertTrue(BigDecimal::of(46)->isEqualTo($i->y));
    }

    // Add more tests for other vector operations as per TypeScript tests
}