<?php

namespace Polyclip\Tests;

use GeoJson\GeoJson;
use PHPUnit\Framework\TestCase;
use Polyclip\Clipper;

class ClipperTest extends TestCase
{
    public function test_end_to_end()
    {
        // Define the test directory
        $endToEndDir = __DIR__.'/end-to-end';
        $targets = array_diff(scandir($endToEndDir), ['.', '..']);

        foreach ($targets as $target) {
            // Skip dotfiles (e.g., .DS_Store)
            if (strpos($target, '.') === 0) {
                continue;
            }

            $targetDir = $endToEndDir.'/'.$target;
            $argsFile = $targetDir.'/args.geojson';

            // Read and parse input geometries
            $args = GeoJson::jsonUnserialize(json_decode(file_get_contents($argsFile)));
            $this->assertNotEmpty($args, "Failed to load args.geojson for target: $target");

            // Find all result files
            $resultFiles = glob($targetDir.'/*.geojson');
            foreach ($resultFiles as $resultFile) {
                if ($resultFile === $argsFile) {
                    continue; // Skip the input file
                }

                $operation = pathinfo($resultFile, PATHINFO_FILENAME);
                $resultGeojson = GeoJson::jsonUnserialize(json_decode(file_get_contents($resultFile), true));
                $this->assertNotEmpty($resultGeojson, "Failed to load $operation.geojson for target: $target");
                $expectedCoordinates = $resultGeojson->getGeometry()->getCoordinates();
                // Define operations to test
                $operations = ($operation === 'all')
                    ? ['union', 'intersection', 'xor', 'difference']
                    : [$operation];

                foreach ($operations as $op) {
                    // Set precision if specified
                    if (isset($resultGeojson->getProperties()['precision'])) {

                        Clipper::setPrecision($resultGeojson->getProperties()['precision']);
                    } else {
                        Clipper::setPrecision(1e-12);
                    }

                    // Verify the operation exists
                    if (! method_exists(Clipper::class, $op)) {
                        $this->fail("Unknown operation '$op' for target: $target");
                    }

                    // Perform the operation
                    $result = Clipper::$op(...$args);
//                    error_log(json_encode($result, JSON_PRETTY_PRINT));

                    if (isset($resultGeojson->getProperties()['noCoordinates'])) {
                        // If we expect no coordinates, we cannot call getCoordinates()
                        $parsed = json_decode(json_encode($result->getGeometry()));
                        $this->assertObjectNotHasProperty('coordinates', $parsed);
                    } else {
                        $resultCoordinates = $result->getGeometry()->getCoordinates();

                        // Assert coordinates match
                        $this->assertEquals(
                            $expectedCoordinates,
                            $resultCoordinates,
                            "Target: $target, Operation: $op failed"
                        );
                    }
                }
            }
        }
    }
}
