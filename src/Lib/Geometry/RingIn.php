<?php

declare(strict_types=1);

namespace Polyclip\Lib\Geometry;

use Brick\Math\BigDecimal;
use Polyclip\Lib\Bbox;
use Polyclip\Lib\Segment;
use Polyclip\Lib\SweepEvent;
use Polyclip\Lib\Util;
use Polyclip\Lib\Vector;

class RingIn
{
    private static int $ringId = 0; // Static counter for unique IDs
    public int $id;

    public PolyIn $poly;

    public bool $isExterior;

    /** @var Segment[] */
    public array $segments = [];

    public Bbox $bbox;

    /**
     * @param  mixed[]  $geomRing  Array of [x, y] coordinates
     * @param  PolyIn  $poly  The parent polygon
     * @param  bool  $isExterior  Whether this is an exterior ring
     *
     * @throws \InvalidArgumentException If geometry is invalid
     */
    public function __construct(array $geomRing, PolyIn $poly, bool $isExterior)
    {
        $this->id = ++self::$ringId;

        if (empty($geomRing) || count($geomRing) < 3) {
            throw new \InvalidArgumentException('Input geometry is not a valid Polygon or MultiPolygon');
        }

        $this->poly = $poly;
        $this->isExterior = $isExterior;
        $this->segments = [];

        // Validate first point
        if (count($geomRing[0]) < 2 || ! ($geomRing[0][0] instanceof BigDecimal) || ! ($geomRing[0][1] instanceof BigDecimal)) {
            throw new \InvalidArgumentException('Input geometry is not a valid Polygon or MultiPolygon');
        }

        $snap = Util::createSnap();
        $firstPoint = $snap(new Vector($geomRing[0][0], $geomRing[0][1]));
        $this->bbox = new Bbox(
            new Vector($firstPoint->x, $firstPoint->y),
            new Vector($firstPoint->x, $firstPoint->y)
        );

        $prevPoint = $firstPoint;
        for ($i = 1, $iMax = count($geomRing); $i < $iMax; $i++) {
            if (count($geomRing[$i]) < 2 || ! ($geomRing[$i][0] instanceof BigDecimal) || ! ($geomRing[$i][1] instanceof BigDecimal)) {
                throw new \InvalidArgumentException('Input geometry is not a valid Polygon or MultiPolygon');
            }
            $point = $snap(new Vector($geomRing[$i][0], $geomRing[$i][1]));
            // Skip repeated points
            if ($point->x->isEqualTo($prevPoint->x) && $point->y->isEqualTo($prevPoint->y)) {
                continue;
            }
            $this->segments[] = Segment::fromRing($prevPoint, $point, $this);
            $this->updateBbox($point);
            $prevPoint = $point;
        }

        // Close the ring if the last point is not the same as the first
        if (! $firstPoint->x->isEqualTo($prevPoint->x) || ! $firstPoint->y->isEqualTo($prevPoint->y)) {
            $this->segments[] = Segment::fromRing($prevPoint, $firstPoint, $this);
        }
    }

    /**
     * Updates the bounding box with a new point.
     */
    private function updateBbox(Vector $point): void
    {
        if ($point->x->isLessThan($this->bbox->lowerLeft->x)) {
            $this->bbox = new Bbox(
                new Vector($point->x, $this->bbox->lowerLeft->y),
                $this->bbox->upperRight
            );
        }
        if ($point->y->isLessThan($this->bbox->lowerLeft->y)) {
            $this->bbox = new Bbox(
                new Vector($this->bbox->lowerLeft->x, $point->y),
                $this->bbox->upperRight
            );
        }
        if ($point->x->isGreaterThan($this->bbox->upperRight->x)) {
            $this->bbox = new Bbox(
                $this->bbox->lowerLeft,
                new Vector($point->x, $this->bbox->upperRight->y)
            );
        }
        if ($point->y->isGreaterThan($this->bbox->upperRight->y)) {
            $this->bbox = new Bbox(
                $this->bbox->lowerLeft,
                new Vector($this->bbox->upperRight->x, $point->y)
            );
        }
    }

    /**
     * Returns all sweep events for this ring.
     *
     * @return SweepEvent[]
     */
    public function getSweepEvents(): array
    {
        $sweepEvents = [];
        foreach ($this->segments as $segment) {
            $sweepEvents[] = $segment->leftSE;
            $sweepEvents[] = $segment->rightSE;
        }

        return $sweepEvents;
    }

    /**
     * Gets the lower left point of the bounding box.
     */
    public function getLowerLeft(): Vector
    {
        return $this->bbox->lowerLeft;
    }

    /**
     * Gets the upper right point of the bounding box.
     */
    public function getUpperRight(): Vector
    {
        return $this->bbox->upperRight;
    }
}
