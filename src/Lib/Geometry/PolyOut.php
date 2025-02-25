<?php

declare(strict_types=1);

namespace Polyclip\Lib\Geometry;

class PolyOut
{
    public RingOut $exteriorRing;

    /** @var RingOut[] */
    public array $interiorRings = [];

    public function __construct(RingOut $exteriorRing)
    {
        $this->exteriorRing = $exteriorRing;
        $exteriorRing->poly = $this;
    }

    public function addInterior(RingOut $ring): void
    {
        $this->interiorRings[] = $ring;
        $ring->poly = $this;
    }

    /**
     * @return mixed[]|null
     */
    public function getGeom(): ?array
    {
        $geom0 = $this->exteriorRing->getGeom();
        if ($geom0 === null) {
            return null;
        }
        $geom = [$geom0];
        foreach ($this->interiorRings as $ring) {
            $ringGeom = $ring->getGeom();
            if ($ringGeom !== null) {
                $geom[] = $ringGeom;
            }
        }

        return $geom;
    }
}
