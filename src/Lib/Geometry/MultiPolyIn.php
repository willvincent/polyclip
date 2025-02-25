<?php

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
     * @param BigDecimal $geom Geometry (Polygon or MultiPolygon)
     * @param bool $isSubject Whether this is the subject geometry
     * @throws InvalidArgumentException If geometry is invalid
     */
    public function __construct(array $geom, bool $isSubject)
    {
        if (!is_array($geom)) {
            throw new InvalidArgumentException("Input geometry is not a valid Polygon or MultiPolygon");
        }

        // If the input looks like a polygon, convert it to a multi-polygon
        try {
            if (isset($geom[0][0][0]) && $geom[0][0][0] instanceof BigDecimal) {
                $geom = [$geom];
            }
        } catch (\Exception $e) {
            // Handle malformed input or empty arrays
            throw new InvalidArgumentException("Input geometry is not a valid Polygon or MultiPolygon");
        }

        $this->polys = [];
        $this->bbox = new Bbox(
            new Vector(BigDecimal::of(PHP_FLOAT_MAX), BigDecimal::of(PHP_FLOAT_MAX)),
            new Vector(BigDecimal::of(-PHP_FLOAT_MAX), BigDecimal::of(-PHP_FLOAT_MAX))
        );
        for ($i = 0, $iMax = count($geom); $i < $iMax; $i++) {
            $poly = new PolyIn($geom[$i], $this);
            $this->updateBbox($poly);
            $this->polys[] = $poly;
        }
        $this->isSubject = $isSubject;
    }

    /**
     * Updates the bounding box with a polygon's bounding box.
     *
     * @param PolyIn $poly
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