# Polyclip

Polyclip is a PHP library designed, based on [polyclip-ts](https://github.com/luizbarboza/polyclip-ts)
for performing geometric operations on polygons and multi-polygons, including union, intersection, XOR,
and difference. It supports input and output in GeoJSON format and leverages a sweep line algorithm for
efficient computation. The library uses Brick\Math for high-precision arithmetic, ensuring accurate
handling of large or small coordinates, and SplayTree for efficient data management.


## Installation

Install Polyclip with composer. Run:

```bash
composer require willvincent/polyclip
```
Dependencies:

- PHP 8.2+ (due to strict typing)
- [brick/math](https://github.com/brick/math) for big decimal arithmetic
- [willvincent/splaytree](https://github.com/willvincent/splay-tree) for efficient splay tree operations

## Usage

### Basic Operations

The Clipper class provides static methods to perform geometric operations.
You can pass multiple geometries as separate arguments or as a single FeatureCollection.
Each method returns a `GeoJson\Feature\Feature` object with the resulting geometry and empty properties.

#### Union: Combines multiple geometries into a single geometry.

```php
use Polyclip\Clipper;
use GeoJson\Geometry\Polygon;

$polygon1 = new Polygon([[[0, 0], [2, 0], [2, 2], [0, 2], [0, 0]]]);
$polygon2 = new Polygon([[[1, 1], [3, 1], [3, 3], [1, 3], [1, 1]]]);

$result = Clipper::union($polygon1, $polygon2);
echo json_encode($result); // Outputs the resulting GeoJSON Feature
```

#### Intersection: Computes the overlapping area of multiple geometries.

```php
$result = Clipper::intersection($polygon1, $polygon2);
```

#### XOR: Computes the symmetric difference (exclusive or) between geometries.

```php
$result = Clipper::xor($polygon1, $polygon2);
```

#### Difference: Subtracts one or more geometries from a subject geometry.

```php
$subject = new Polygon([[[0, 0], [2, 0], [2, 2], [0, 2], [0, 0]]]);
$clip = new Polygon([[[1, 1], [3, 1], [3, 3], [1, 3], [1, 1]]]);

$result = Clipper::difference($subject, $clip);
```

Using a FeatureCollection:

```php
use GeoJson\Feature\FeatureCollection;
use GeoJson\Feature\Feature;

$collection = new FeatureCollection([
    new Feature($polygon1),
    new Feature($polygon2),
]);

$result = Clipper::union($collection);
```

### Input Formats

Geometries can be provided in the following formats:

#### GeoJson Objects: Use classes like GeoJson\Geometry\Polygon or GeoJson\Geometry\MultiPolygon.

```php
$polygon = new Polygon([[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]]);
```

#### GeoJSON Strings:

```php
$geojson = '{"type": "Polygon", "coordinates": [[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]]}';
```

#### Arrays: Coordinate arrays representing polygons or multi-polygons.

```php
$polygonArray = [[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]]; // Single polygon
$multiPolygonArray = [
    [[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]], // Polygon 1
    [[[2, 2], [3, 2], [3, 3], [2, 3], [2, 2]]]  // Polygon 2
];
```

### Setting Precision

To handle floating-point precision issues (e.g., near-coincident points), set an epsilon value for comparisons:

```php
Clipper::setPrecision(0.0001); // Sets the precision for all subsequent operations
```

This epsilon determines how close two points can be before they’re considered equal, which is crucial
for robust geometric computations.

### Error Handling

Ensure your input geometries are valid. The library throws \InvalidArgumentException for:

- Invalid GeoJSON strings
- Features without geometries
- Unsupported geometry types (only Polygon and MultiPolygon are supported)
- Coordinate arrays that don’t form valid polygons

Wrap calls in a try-catch block if needed:
```php
try {
    $result = Clipper::union($geom1, $geom2);
} catch (\InvalidArgumentException $e) {
    echo "Error: " . $e->getMessage();
}
```

## Notes

- Input Requirements: Polygons must be simple (non-self-intersecting). Self-intersecting polygons may produce
  incorrect results.
- Holes: Polygons can include interior rings (holes). 
- Properties: Output features have empty properties; input properties are not preserved. 
- Precision: Internal calculations use BigDecimal for accuracy, but output coordinates are floats 
  (as per GeoJSON standard). 
- Performance: The sweep line algorithm is efficient, but performance varies with polygon size and complexity.
- There are still discrepancies between this package and the output from polyclip-ts, more tests need to be
  implemented, and additional logic may be needed for edge cases, but functionality is in place and working for
  not overly complex use cases.

## Limitations

- Supports only Polygon and MultiPolygon geometries (no points, lines, etc.).
- Assumes simple polygons; self-intersections are not handled.
- Very complex polygons with many intersections may impact performance.

## Contributing

Contributions are welcome! Feel free to:

- Report issues or bugs on the GitHub repository.
- Submit pull requests with improvements or fixes.

Please include tests and documentation updates with your contributions.

## License

This project is [MIT Licensed](LICENSE.md)
