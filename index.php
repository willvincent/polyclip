<?php

ini_set('memory_limit', '2G');

require 'vendor/autoload.php';

use GeoJson\GeoJson;
use Polyclip\Clipper;

$featureCollection = GeoJson::jsonUnserialize(json_decode('{
    "type": "FeatureCollection",
    "features": [
        {"type": "Feature", "geometry": {"type": "Polygon", "coordinates": [[[0,0], [1,0], [1,1], [0,1], [0,0]]]}, "properties": {}},
        {"type": "Feature", "geometry": {"type": "Polygon", "coordinates": [[[1,1], [2,1], [2,2], [1,2], [1,1]]]}, "properties": {}}
    ]
}', true));
$result = Clipper::union($featureCollection);

print_r($result);
