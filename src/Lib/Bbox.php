<?php

declare(strict_types=1);

namespace Polyclip\Lib;

class Bbox
{
    public function __construct(
        public Vector $lowerLeft,
        public Vector $upperRight) {}

    public function isInBbox(Bbox $bbox, Vector $point): bool
    {
        return $bbox->pointInBbox($point);
    }

    public function pointInBbox(Vector $point): bool
    {
        return $this->lowerLeft->x->isLessThanOrEqualTo($point->x) &&
               ($point->x->isLessThanOrEqualTo($this->upperRight->x)) &&
               ($this->lowerLeft->y->isLessThanOrEqualTo($point->y)) &&
               ($point->y->isLessThanOrEqualTo($this->upperRight->y));
    }

    public function getBboxOverlap(Bbox $bbox1, Bbox $bbox2): ?Bbox
    {
        if ($bbox2->upperRight->x->isLessThan($bbox1->lowerLeft->x) ||
            $bbox1->upperRight->x->isLessThan($bbox2->lowerLeft->x) ||
            $bbox2->upperRight->y->isLessThan($bbox1->lowerLeft->y) ||
            $bbox1->upperRight->y->isLessThan($bbox2->lowerLeft->y)) {
            return null;
        }

        $lowerX = $bbox1->lowerLeft->x->isLessThan($bbox2->lowerLeft->x) ? $bbox2->lowerLeft->x : $bbox1->lowerLeft->x;
        $upperX = $bbox1->upperRight->x->isLessThan($bbox2->upperRight->x) ? $bbox1->upperRight->x : $bbox2->upperRight->x;
        $lowerY = $bbox1->lowerLeft->y->isLessThan($bbox2->lowerLeft->y) ? $bbox2->lowerLeft->y : $bbox1->lowerLeft->y;
        $upperY = $bbox1->upperRight->y->isLessThan($bbox2->upperRight->y) ? $bbox1->upperRight->y : $bbox2->upperRight->y;

        return new Bbox(
            lowerLeft: new Vector($lowerX, $lowerY),
            upperRight: new Vector($upperX, $upperY),
        );
    }

    public function __toString(): string
    {
        return '['.$this->lowerLeft->x.','.$this->lowerLeft->y.'],['.$this->upperRight->x.','.$this->upperRight->y.']';
    }
}
