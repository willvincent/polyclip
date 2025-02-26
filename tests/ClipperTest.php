<?php

namespace Polyclip\Tests;

use GeoJson\Exception\UnserializationException;
use GeoJson\GeoJson;
use PHPUnit\Framework\TestCase;
use Polyclip\Clipper;

class ClipperTest extends TestCase
{
    /**
     * @param mixed[] $coordinates
     * @return int
     */
    private function findMinPointIndex(array $ring): int
    {
        $minIndex = 0;
        $minPoint = $ring[0];
        for ($i = 1; $i < count($ring) - 1; $i++) { // Exclude the closing point
            if ($ring[$i][0] < $minPoint[0] || ($ring[$i][0] == $minPoint[0] && $ring[$i][1] < $minPoint[1])) {
                $minPoint = $ring[$i];
                $minIndex = $i;
            }
        }
        return $minIndex;
    }

    /**
     * @param mixed[] $coordinates
     * @return mixed[]
     */
    private function rotateRing(array $ring, int $startIndex): array
    {
        $n = count($ring) - 1; // Exclude the closing point
        $rotated = array_merge(
            array_slice($ring, $startIndex, $n - $startIndex),
            array_slice($ring, 0, $startIndex)
        );
        $rotated[] = $rotated[0]; // Close the ring
        return $rotated;
    }

    /**
     * @param mixed[] $coordinates
     * @return mixed[]
     */
    private function normalizeRing(array $ring): array
    {
        $minIndex = $this->findMinPointIndex($ring);
        return $this->rotateRing($ring, $minIndex);
    }

    /**
     * @param mixed[] $coordinates
     * @return mixed[]
     */
    private function normalizeCoordinates(array $coordinates): array
    {
        $normalized = [];
        foreach ($coordinates as $polygon) {
            $normalizedPolygon = [];
            foreach ($polygon as $ring) {
                $normalizedPolygon[] = $this->normalizeRing($ring);
            }
            $normalized[] = $normalizedPolygon;
        }
        return $normalized;
    }

    public function test_end_to_end()
    {
        // Define the test directory
        $endToEndDir = __DIR__.'/end-to-end';
        $targets = array_diff(scandir($endToEndDir), ['.', '..']);

        $targets = array_filter($targets, fn($target) => !str_starts_with($target, 'SKIP'));

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
                    $result = Clipper::$op($args);

//                    error_log($target .": \n" . json_encode($result));

                    if (isset($resultGeojson->getProperties()['noCoordinates'])) {
                        // If we expect no coordinates, we cannot call getCoordinates()
                        $parsed = json_decode(json_encode($result->getGeometry()));
                        $this->assertObjectNotHasProperty('coordinates', $parsed);
                    } else {
                        $expectedCoordinates = $this->normalizeCoordinates($expectedCoordinates);
                        $resultCoordinates = $this->normalizeCoordinates($result->getGeometry()->getCoordinates());

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
