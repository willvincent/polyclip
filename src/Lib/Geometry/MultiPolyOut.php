<?php

namespace Polyclip\Lib\Geometry;

class MultiPolyOut
{
    /** @var RingOut[] */
    public array $rings;

    /** @var PolyOut[] */
    public array $polys;

    /**
     * @param mixed[] $rings
     */
    public function __construct(array $rings)
    {
        $this->rings = $rings;
        $this->polys = $this->_composePolys($rings);
    }

    /**
     * @return mixed[]
     */
    public function getGeom(): array
    {
        $geom = [];
        foreach ($this->polys as $poly) {
            $polyGeom = $poly->getGeom();
            if ($polyGeom !== null) {
                $geom[] = $polyGeom;
            }
        }

        return $geom;
    }

    /**
     * @param mixed[] $rings
     * @return mixed[]
     */
    private function _composePolys(array $rings): array
    {
        $polys = [];
        foreach ($rings as $ring) {
            if ($ring->poly !== null) {
                continue;
            }
            if ($ring->isExteriorRing()) {
                $polys[] = new PolyOut($ring);
            } else {
                $enclosingRing = $ring->enclosingRing();
                if ($enclosingRing?->poly === null) {
                    $polys[] = new PolyOut($enclosingRing);
                }
                $enclosingRing->poly->addInterior($ring);
            }
        }

        return $polys;
    }
}
