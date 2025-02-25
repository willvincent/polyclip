<?php

declare(strict_types=1);

namespace Polyclip\Lib\Geometry;

use Brick\Math\BigDecimal;
use InvalidArgumentException;
use Polyclip\Lib\Bbox;
use Polyclip\Lib\SweepEvent;
use Polyclip\Lib\Vector;

class MultiPolyIn
{
    public bool $isSubject;

    /** @var PolyIn[] */
    public array $polys = [];

    public Bbox $bbox;

    /**
     * @param mixed[] $geom
     * @param bool $isSubject
     * @throws \Brick\Math\Exception\DivisionByZeroException
     * @throws \Brick\Math\Exception\NumberFormatException
     */
    public function __construct(array $geom, bool $isSubject)
    {
        if (empty($geom)) {
            throw new InvalidArgumentException('Input geometry is not a valid Polygon or MultiPolygon');
        }

        // If $geom is a single polygon (array of rings), don't wrap it further
        // Check if $geom[0][0] is an array of coordinates (i.e., a ring)
        if (isset($geom[0][0]) && is_array($geom[0][0]) && isset($geom[0][0][0])) {
            // This is a Polygon: [[[x, y], ...], ...]
            $poly = new PolyIn($geom, $this);
            $this->polys = [$poly];
        } else {
            // This is a MultiPolygon: [[[[x, y], ...]], ...]
            $this->polys = [];
            for ($i = 0, $iMax = count($geom); $i < $iMax; $i++) {
                $poly = new PolyIn($geom[$i], $this);
                $this->polys[] = $poly;
            }
        }

        $this->bbox = new Bbox(
            new Vector(BigDecimal::of(PHP_FLOAT_MAX), BigDecimal::of(PHP_FLOAT_MAX)),
            new Vector(BigDecimal::of(-PHP_FLOAT_MAX), BigDecimal::of(-PHP_FLOAT_MAX))
        );
        foreach ($this->polys as $poly) {
            $this->updateBbox($poly);
        }
        $this->isSubject = $isSubject;
    }

    /**
     * Updates the bounding box with a polygon's bounding box.
     */
    private function updateBbox(PolyIn $poly): void
    {
        if ($poly->bbox->lowerLeft->x->isLessThan($this->bbox->lowerLeft->x)) {
            $this->bbox = new Bbox(
                new Vector($poly->bbox->lowerLeft->x, $this->bbox->lowerLeft->y),
                $this->bbox->upperRight
            );
        }
        if ($poly->bbox->lowerLeft->y->isLessThan($this->bbox->lowerLeft->y)) {
            $this->bbox = new Bbox(
                new Vector($this->bbox->lowerLeft->x, $poly->bbox->lowerLeft->y),
                $this->bbox->upperRight
            );
        }
        if ($poly->bbox->upperRight->x->isGreaterThan($this->bbox->upperRight->x)) {
            $this->bbox = new Bbox(
                $this->bbox->lowerLeft,
                new Vector($poly->bbox->upperRight->x, $this->bbox->upperRight->y)
            );
        }
        if ($poly->bbox->upperRight->y->isGreaterThan($this->bbox->upperRight->y)) {
            $this->bbox = new Bbox(
                $this->bbox->lowerLeft,
                new Vector($this->bbox->upperRight->x, $poly->bbox->upperRight->y)
            );
        }
    }

    /**
     * Returns all sweep events for this multi-polygon.
     *
     * @return SweepEvent[]
     */
    public function getSweepEvents(): array
    {
        $sweepEvents = [];
        foreach ($this->polys as $poly) {
            $sweepEvents = array_merge($sweepEvents, $poly->getSweepEvents());
        }

        return $sweepEvents;
    }
}
