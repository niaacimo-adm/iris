<?php

namespace App\Support;

use InvalidArgumentException;
use ZipArchive;

/**
 * Reads the service-area outline of one IA from a KMZ, KML or GeoJSON file (all in WGS84 latitude/longitude).
 * Plain PHP, no framework needed. Every problem is thrown as an InvalidArgumentException with a message
 * that can be shown to the user as is.
 *
 * Result: ['lots' => [['geometry' => GeoJSON geometry array, 'area_ha' => float, 'name' => ?string]],
 *          'total_ha' => float, 'vertices' => int, 'warnings' => [string]]
 */
class IaMapImport
{
    public const MAX_BYTES = 12 * 1024 * 1024;   // file size (KMZ is compressed, so the KML inside is checked too)
    public const MAX_LOTS = 3000;
    public const MAX_VERTICES = 300000;

    /** Region V with some margin: lon 122-125.5, lat 11.5-14.5. Anything outside is probably the wrong file or swapped order. */
    private const LON = [122.0, 125.5];

    private const LAT = [11.5, 14.5];

    public static function fromFile(string $path, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (! in_array($ext, ['kmz', 'kml', 'geojson', 'json'], true)) {
            throw new InvalidArgumentException('Use a .kmz, .kml or .geojson file (export from Google Earth or QGIS).');
        }

        if (! is_file($path) || filesize($path) === 0) {
            throw new InvalidArgumentException('The file is empty.');
        }

        if (filesize($path) > self::MAX_BYTES) {
            throw new InvalidArgumentException('The file is too large (maximum 12 MB). Simplify it or split it per IA.');
        }

        $warnings = [];

        $lots = match ($ext) {
            'kmz' => self::fromKml(self::kmlFromKmz($path), $warnings),
            'kml' => self::fromKml((string) file_get_contents($path), $warnings),
            default => self::fromGeoJson((string) file_get_contents($path), $warnings),
        };

        return self::finish($lots, $warnings);
    }

    /* ------------------------------------------------------------------ readers */

    private static function kmlFromKmz(string $path): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new InvalidArgumentException('This server cannot open .kmz files (the PHP zip extension is off). Save the file as .kml or .geojson instead.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('This .kmz file cannot be opened. It may be damaged; export it again.');
        }

        $name = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);

            if (strtolower(substr($entry, -4)) === '.kml') {
                if (strtolower(basename($entry)) === 'doc.kml') {
                    $name = $entry;
                    break;
                }
                $name ??= $entry;
            }
        }

        if ($name === null) {
            $zip->close();
            throw new InvalidArgumentException('There is no .kml inside this .kmz file.');
        }

        $stat = $zip->statName($name);

        if (($stat['size'] ?? 0) > self::MAX_BYTES * 4) {
            $zip->close();
            throw new InvalidArgumentException('The map inside this .kmz is too large. Simplify it or split it per IA.');
        }

        $content = $zip->getFromName($name);
        $zip->close();

        return (string) $content;
    }

    /** @return list<array{polys: list<list<list<array{float,float}>>>, name: ?string}> */
    private static function fromKml(string $xml, array &$warnings): array
    {
        $prev = libxml_use_internal_errors(true);
        // LIBXML_NONET: never fetch anything from the network. Entities are not expanded (no LIBXML_NOENT).
        $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if ($doc === false) {
            throw new InvalidArgumentException('This is not a valid KML file.');
        }

        $lots = [];
        $skipped = 0;

        foreach ($doc->xpath("//*[local-name()='Placemark']") ?: [] as $placemark) {
            $polygons = $placemark->xpath(".//*[local-name()='Polygon']") ?: [];

            if ($polygons === []) {
                $skipped++;

                continue;
            }

            $polys = [];

            foreach ($polygons as $polygon) {
                $rings = [];

                foreach ($polygon->xpath("./*[local-name()='outerBoundaryIs']//*[local-name()='coordinates']") ?: [] as $c) {
                    $rings[] = self::coords((string) $c);
                }

                foreach ($polygon->xpath("./*[local-name()='innerBoundaryIs']//*[local-name()='coordinates']") ?: [] as $c) {
                    $rings[] = self::coords((string) $c);
                }

                if ($rings !== []) {
                    $polys[] = $rings;
                }
            }

            $name = trim((string) ($placemark->xpath("./*[local-name()='name']")[0] ?? ''));

            if ($polys !== []) {
                $lots[] = ['polys' => $polys, 'name' => $name !== '' ? mb_substr($name, 0, 100) : null];
            }
        }

        if ($skipped > 0) {
            $warnings[] = "$skipped non-polygon item(s) (points or lines) were ignored.";
        }

        return $lots;
    }

    /** "lon,lat,alt lon,lat,alt ..." */
    private static function coords(string $text): array
    {
        $ring = [];

        foreach (preg_split('/\s+/', trim($text)) ?: [] as $pair) {
            $p = explode(',', $pair);

            if (count($p) >= 2 && is_numeric($p[0]) && is_numeric($p[1])) {
                $ring[] = [(float) $p[0], (float) $p[1]];
            }
        }

        return $ring;
    }

    private static function fromGeoJson(string $json, array &$warnings): array
    {
        $data = json_decode($json, true);

        if (! is_array($data) || ! isset($data['type'])) {
            throw new InvalidArgumentException('This is not a valid GeoJSON file.');
        }

        $features = match ($data['type']) {
            'FeatureCollection' => $data['features'] ?? [],
            'Feature' => [$data],
            default => [['type' => 'Feature', 'properties' => [], 'geometry' => $data]],
        };

        $lots = [];
        $skipped = 0;

        foreach ($features as $feature) {
            $geometry = $feature['geometry'] ?? null;
            $polys = is_array($geometry) ? self::polygonsOf($geometry) : [];

            if ($polys === []) {
                $skipped++;

                continue;
            }

            $props = $feature['properties'] ?? [];
            $name = trim((string) ($props['name'] ?? $props['Name'] ?? ''));
            $lots[] = ['polys' => $polys, 'name' => $name !== '' ? mb_substr($name, 0, 100) : null];
        }

        if ($skipped > 0) {
            $warnings[] = "$skipped item(s) without a polygon were ignored.";
        }

        return $lots;
    }

    private static function polygonsOf(array $geometry): array
    {
        $type = $geometry['type'] ?? '';

        return match ($type) {
            'Polygon' => [self::rings($geometry['coordinates'] ?? [])],
            'MultiPolygon' => array_map(fn ($p) => self::rings($p), $geometry['coordinates'] ?? []),
            'GeometryCollection' => array_merge(...array_map(fn ($g) => is_array($g) ? self::polygonsOf($g) : [], $geometry['geometries'] ?? [[]])),
            default => [],
        };
    }

    private static function rings(array $rings): array
    {
        return array_values(array_filter(array_map(function ($ring) {
            return array_values(array_filter(array_map(
                fn ($c) => is_array($c) && isset($c[0], $c[1]) && is_numeric($c[0]) && is_numeric($c[1]) ? [(float) $c[0], (float) $c[1]] : null,
                is_array($ring) ? $ring : []
            )));
        }, $rings)));
    }

    /* ------------------------------------------------------------------ checks + result */

    private static function finish(array $raw, array $warnings): array
    {
        if ($raw === []) {
            throw new InvalidArgumentException('No polygons were found in this file. The service area must be drawn as polygons (not points or lines).');
        }

        // coordinates must be latitude / longitude, not metres (UTM, PRS92...)
        foreach ($raw as $lot) {
            foreach ($lot['polys'] as $rings) {
                foreach ($rings as $ring) {
                    foreach ($ring as [$x, $y]) {
                        if (abs($x) > 180 || abs($y) > 90) {
                            if (abs($x) <= 90 && abs($y) <= 180) {
                                throw new InvalidArgumentException('Latitude and longitude look swapped (longitude must come first, as in KML and GeoJSON). Re-export the file.');
                            }

                            throw new InvalidArgumentException('These coordinates are in metres (a projected system such as UTM), not latitude/longitude. Export the file as WGS84 (EPSG:4326) from QGIS or Google Earth.');
                        }
                    }
                }
            }
        }

        $lots = [];
        $vertices = 0;
        $outside = 0;
        $swapped = 0;
        $dropped = 0;

        foreach ($raw as $lot) {
            $polygons = [];
            $ha = 0.0;

            foreach ($lot['polys'] as $rings) {
                $clean = [];

                foreach ($rings as $i => $ring) {
                    $ring = self::closeRing($ring);

                    if (count($ring) < 4 || self::ringAreaM2($ring) < 1.0) {
                        if ($i === 0) {
                            break;   // outer ring unusable: skip the polygon
                        }

                        continue;
                    }

                    $clean[] = array_map(fn ($c) => [round($c[0], 6), round($c[1], 6)], $ring);
                }

                if ($clean === []) {
                    continue;
                }

                [$cx, $cy] = self::centroid($clean[0]);

                if ($cx < self::LON[0] || $cx > self::LON[1] || $cy < self::LAT[0] || $cy > self::LAT[1]) {
                    $outside++;

                    if ($cy >= self::LON[0] && $cy <= self::LON[1] && $cx >= self::LAT[0] && $cx <= self::LAT[1]) {
                        $swapped++;
                    }

                    continue;
                }

                $area = self::ringAreaM2($clean[0]);

                foreach (array_slice($clean, 1) as $hole) {
                    $area -= self::ringAreaM2($hole);
                }

                $ha += max($area, 0) / 10000;
                $vertices += array_sum(array_map('count', $clean));
                $polygons[] = $clean;
            }

            if ($polygons === []) {
                $dropped++;

                continue;
            }

            $lots[] = [
                'geometry' => count($polygons) === 1
                    ? ['type' => 'Polygon', 'coordinates' => $polygons[0]]
                    : ['type' => 'MultiPolygon', 'coordinates' => $polygons],
                'area_ha' => round($ha, 4),
                'name' => $lot['name'],
            ];
        }

        if ($swapped > 0 && $swapped === $outside && $lots === []) {
            throw new InvalidArgumentException('Latitude and longitude look swapped (longitude must come first, as in KML and GeoJSON). Re-export the file.');
        }

        if ($outside > 0 && $lots === []) {
            throw new InvalidArgumentException('The shapes are outside Region V (Bicol). Check that this is the right IA\'s file and that it is in WGS84.');
        }

        if ($outside > 0) {
            throw new InvalidArgumentException("$outside shape(s) are outside Region V (Bicol). Remove them from the file, or check that it is the right IA's file.");
        }

        if ($lots === []) {
            throw new InvalidArgumentException('No usable polygons were found (they are empty or too small).');
        }

        if (count($lots) > self::MAX_LOTS) {
            throw new InvalidArgumentException('Too many lots ('.count($lots).'; maximum '.self::MAX_LOTS.'). Split the file per IA.');
        }

        if ($vertices > self::MAX_VERTICES) {
            throw new InvalidArgumentException('The outline is too detailed ('.number_format($vertices).' points; maximum '.number_format(self::MAX_VERTICES).'). Simplify it in QGIS or Google Earth.');
        }

        if ($dropped > 0) {
            $warnings[] = "$dropped empty or very small shape(s) were ignored.";
        }

        return [
            'lots' => $lots,
            'total_ha' => round(array_sum(array_column($lots, 'area_ha')), 4),
            'vertices' => $vertices,
            'warnings' => $warnings,
        ];
    }

    private static function closeRing(array $ring): array
    {
        $out = [];

        foreach ($ring as $c) {
            if ($out === [] || $out[count($out) - 1] !== $c) {
                $out[] = $c;
            }
        }

        if (count($out) >= 3 && $out[0] !== $out[count($out) - 1]) {
            $out[] = $out[0];
        }

        return $out;
    }

    /** Area in square metres: local flat projection around the ring (accurate to well under 1% at lot size). */
    private static function ringAreaM2(array $ring): float
    {
        $lat0 = array_sum(array_column($ring, 1)) / max(count($ring), 1);
        $kx = 111320 * cos(deg2rad($lat0));
        $ky = 110574;
        $sum = 0.0;

        for ($i = 0, $n = count($ring) - 1; $i < $n; $i++) {
            $sum += ($ring[$i][0] * $kx) * ($ring[$i + 1][1] * $ky) - ($ring[$i + 1][0] * $kx) * ($ring[$i][1] * $ky);
        }

        return abs($sum) / 2;
    }

    private static function centroid(array $ring): array
    {
        $n = max(count($ring) - 1, 1);

        return [array_sum(array_column($ring, 0)) / $n, array_sum(array_column($ring, 1)) / $n];
    }
}
