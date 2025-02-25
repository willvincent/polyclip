<?php

namespace Polyclip\Lib\Geometry;

use Brick\Math\BigDecimal;
use Polyclip\Lib\Bbox;
use Polyclip\Lib\SweepEvent;
use Polyclip\Lib\Vector;

class PolyIn
{
    public MultiPolyIn $multiPoly;
    public RingIn $exteriorRing;
    /** @var RingIn[] */
    public array $interiorRings = [];
    public Bbox $bbox;

    /**
     * @param BigDecimal $geomPoly Array of rings, each ring being an array of [x, y] coordinates
     * @param MultiPolyIn $multiPoly The parent multi-polygon
     * @throws \InvalidArgumentException If geometry is invalid
     */
    public function __construct(array $geomPoly, MultiPolyIn $multiPoly)
    {
        if (!is_array($geomPoly) || empty($geomPoly)) {
            throw new \InvalidArgumentException("Input geometry is not a valid Polygon or MultiPolygon");
        }
        $this->exteriorRing = new RingIn($geomPoly[0], $this, true);
        $this->bbox = new Bbox(
            $this->exteriorRing->getLowerLeft(),
            $this->exteriorRing->getUpperRight()
        );
        $this->interiorRings = [];
        for ($i = 1, $iMax = count($geomPoly); $i < $iMax; $i++) {
            $ring = new RingIn($geomPoly[$i], $this, false);
            $this->updateBbox($ring);
            $this->interiorRings[] = $ring;
        }
        $this->multiPoly = $multiPoly;
    }

    /**
     * Updates the bounding box with a ring's bounding box.
     *
     * @param RingIn $ring
     */
    private function updateBbox(RingIn $ring): void
    {
        if ($ring->getLowerLeft()->x->isLessThan($this->bbox->lowerLeft->x)) {
            $this->bbox = new Bbox(
                new Vector($ring->getLowerLeft()->x, $this->bbox->lowerLeft->y),
                $this->bbox->upperRight
            );
        }
        if ($ring->getLowerLeft()->y->isLessThan($this->bbox->lowerLeft->y)) {
            $this->bbox = new Bbox(
                new Vector($this->bbox->lowerLeft->x, $ring->getLowerLeft()->y),
                $this->bbox->upperRight
            );
        }
        if ($ring->getUpperRight()->x->isGreaterThan($this->bbox->upperRight->x)) {
            $this->bbox = new Bbox(
                $this->bbox->lowerLeft,
                new Vector($ring->getUpperRight()->x, $this->bbox->upperRight->y)
            );
        }
        if ($ring->getUpperRight()->y->isGreaterThan($this->bbox->upperRight->y)) {
            $this->bbox = new Bbox(
                $this->bbox->lowerLeft,
                new Vector($this->bbox->upperRight->x, $ring->getUpperRight()->y)
            );
        }
    }

    /**
     * Returns all sweep events for this polygon.
     *
     * @return SweepEvent[]
     */
    public function getSweepEvents(): array
    {
        $sweepEvents = $this->exteriorRing->getSweepEvents();
        foreach ($this->interiorRings as $ring) {
            $sweepEvents = array_merge($sweepEvents, $ring->getSweepEvents());
        }
        return $sweepEvents;
    }
}