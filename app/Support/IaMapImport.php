<?php

namespace App\Support;

use InvalidArgumentException;
use ZipArchive;

/**
 * Reads the map of one IA from a KMZ, KML or GeoJSON file (all in WGS84 latitude/longitude).
 * Plain PHP, no framework needed. Every problem is thrown as an InvalidArgumentException with a message
 * that can be shown to the user as is.
 *
 * Polygons become service-area LOTS. Lines and points become the CANAL NETWORK (kinds: canal, structure, dam, damage).
 * One file may hold both.
 *
 * Result: ['lots'     => [['geometry' => GeoJSON geometry, 'area_ha' => float, 'name' => ?string]],
 *          'features' => [['kind' => string, 'name' => ?string, 'group' => ?string, 'geometry' => GeoJSON, 'length_m' => float]],
 *          'total_ha' => float, 'canal_km' => float, 'vertices' => int, 'warnings' => [string]]
 */
class IaMapImport
{
    public const MAX_BYTES = 12 * 1024 * 1024;   // file size (KMZ is compressed, so the KML inside is checked too)
    public const MAX_LOTS = 3000;
    public const MAX_FEATURES = 5000;
    public const MAX_VERTICES = 300000;

    public const KINDS = [
        'canal' => 'Canal',
        'structure' => 'Structure',
        'dam' => 'Dam / intake',
        'damage' => 'Damage (for repair)',
    ];

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

        $raw = match ($ext) {
            'kmz' => self::fromKml(self::kmlFromKmz($path), $warnings),
            'kml' => self::fromKml((string) file_get_contents($path), $warnings),
            default => self::fromGeoJson((string) file_get_contents($path), $warnings),
        };

        return self::finish($raw['lots'], $raw['features'], $warnings);
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

    /** @return array{lots: list<array>, features: list<array>} */
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
        $features = [];
        $skipped = 0;

        foreach ($doc->xpath("//*[local-name()='Placemark']") ?: [] as $placemark) {
            $name = trim((string) ($placemark->xpath("./*[local-name()='name']")[0] ?? ''));
            $name = $name !== '' ? $name : null;

            // folders from the outside in: "CONSOLIDATED / CANALS / NIA / MC / STRUCTURES"
            $path = array_map(
                fn ($n) => trim((string) $n),
                $placemark->xpath("ancestor::*[local-name()='Folder']/*[local-name()='name']") ?: []
            );

            $polygons = $placemark->xpath(".//*[local-name()='Polygon']") ?: [];

            if ($polygons !== []) {
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

                if ($polys !== []) {
                    $lots[] = ['polys' => $polys, 'name' => $name !== null ? mb_substr($name, 0, 100) : null];
                }

                continue;
            }

            $lines = [];

            foreach ($placemark->xpath(".//*[local-name()='LineString']/*[local-name()='coordinates']") ?: [] as $c) {
                $line = self::coords((string) $c);

                if (count($line) >= 2) {
                    $lines[] = $line;
                }
            }

            if ($lines !== []) {
                $features[] = self::feature(
                    $path, $name,
                    count($lines) === 1 ? ['type' => 'LineString', 'coordinates' => $lines[0]] : ['type' => 'MultiLineString', 'coordinates' => $lines]
                );

                continue;
            }

            $points = [];

            foreach ($placemark->xpath(".//*[local-name()='Point']/*[local-name()='coordinates']") ?: [] as $c) {
                $p = self::coords((string) $c);

                if ($p !== []) {
                    $points[] = $p[0];
                }
            }

            if ($points !== []) {
                $features[] = self::feature($path, $name, ['type' => 'Point', 'coordinates' => $points[0]]);

                continue;
            }

            $skipped++;
        }

        if ($skipped > 0) {
            $warnings[] = "$skipped item(s) without a polygon, line or point were ignored.";
        }

        return ['lots' => $lots, 'features' => $features];
    }

    /**
     * Decide what a line / point is from the Google Earth folders it sits in
     * (".../DAM/INTAKE", ".../CANALS/NIA/MC", ".../CANALS/NIA/MC/STRUCTURES", ".../DAMAGES/...").
     */
    private static function feature(array $path, ?string $name, array $geometry, ?string $forcedKind = null): array
    {
        $upper = array_map(fn ($s) => strtoupper(trim($s)), $path);
        $isPoint = $geometry['type'] === 'Point';
        $inDamages = (bool) array_filter($upper, fn ($s) => str_starts_with($s, 'DAMAGE'));
        $inCanals = in_array('CANALS', $upper, true) || in_array('CANAL', $upper, true);

        $kind = $forcedKind ?? match (true) {
            $inDamages => 'damage',
            ! $isPoint => 'canal',
            ! $inCanals && (bool) array_filter($upper, fn ($s) => preg_match('/^(DAM|INTAKE|HEADWORKS)(\b|\/)/', $s)) => 'dam',
            default => 'structure',
        };

        // group = the folders below CANALS / DAMAGES, without the STRUCTURES folder: "NIA / MC", "DA / LAT A1"
        $group = [];
        $take = false;

        foreach ($path as $i => $segment) {
            if ($take && $upper[$i] !== 'STRUCTURES' && $upper[$i] !== '') {
                $group[] = trim($segment, " \t\\/");
            }

            if (in_array($upper[$i], ['CANALS', 'CANAL'], true) || str_starts_with($upper[$i], 'DAMAGE')) {
                $take = true;
            }
        }

        $group = implode(' / ', array_filter($group)) ?: null;

        // "Untitled Path" or a bare number is no name: use the canal it belongs to
        $generic = $name === null || preg_match('/^(untitled|path|line|linestring)/i', $name) || is_numeric($name);
        $label = $generic ? ($kind === 'canal' ? ($group ?? 'Canal') : $name) : $name;

        return [
            'kind' => $kind,
            'name' => $label !== null ? mb_substr($label, 0, 150) : null,
            'group' => $group !== null ? mb_substr($group, 0, 100) : null,
            'geometry' => $geometry,
        ];
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

        $items = match ($data['type']) {
            'FeatureCollection' => $data['features'] ?? [],
            'Feature' => [$data],
            default => [['type' => 'Feature', 'properties' => [], 'geometry' => $data]],
        };

        $lots = [];
        $features = [];
        $skipped = 0;

        foreach ($items as $item) {
            $geometry = $item['geometry'] ?? null;
            $props = is_array($item['properties'] ?? null) ? $item['properties'] : [];
            $name = trim((string) ($props['name'] ?? $props['Name'] ?? ''));
            $name = $name !== '' ? $name : null;
            $type = is_array($geometry) ? ($geometry['type'] ?? '') : '';

            if (in_array($type, ['Polygon', 'MultiPolygon', 'GeometryCollection'], true)) {
                $polys = self::polygonsOf($geometry);

                if ($polys !== []) {
                    $lots[] = ['polys' => $polys, 'name' => $name !== null ? mb_substr($name, 0, 100) : null];

                    continue;
                }
            }

            if (in_array($type, ['LineString', 'MultiLineString', 'Point'], true) && is_array($geometry['coordinates'] ?? null)) {
                $clean = self::cleanGeoJsonLine($geometry);

                if ($clean !== null) {
                    $kind = $props['kind'] ?? null;
                    $group = trim((string) ($props['group'] ?? $props['folder'] ?? ''));
                    $path = $group !== '' ? ['CANALS', $group] : [];
                    $f = self::feature($path, $name, $clean, isset(self::KINDS[$kind]) ? $kind : null);
                    $features[] = $f;

                    continue;
                }
            }

            $skipped++;
        }

        if ($skipped > 0) {
            $warnings[] = "$skipped item(s) without a polygon, line or point were ignored.";
        }

        return ['lots' => $lots, 'features' => $features];
    }

    private static function cleanGeoJsonLine(array $geometry): ?array
    {
        $pt = fn ($c) => is_array($c) && isset($c[0], $c[1]) && is_numeric($c[0]) && is_numeric($c[1]) ? [(float) $c[0], (float) $c[1]] : null;

        if ($geometry['type'] === 'Point') {
            $p = $pt($geometry['coordinates']);

            return $p ? ['type' => 'Point', 'coordinates' => $p] : null;
        }

        $lines = $geometry['type'] === 'LineString' ? [$geometry['coordinates']] : $geometry['coordinates'];
        $lines = array_values(array_filter(array_map(
            fn ($l) => is_array($l) ? array_values(array_filter(array_map($pt, $l))) : [],
            $lines
        ), fn ($l) => count($l) >= 2));

        if ($lines === []) {
            return null;
        }

        return count($lines) === 1 ? ['type' => 'LineString', 'coordinates' => $lines[0]] : ['type' => 'MultiLineString', 'coordinates' => $lines];
    }

    private static function polygonsOf(array $geometry): array
    {
        $type = $geometry['type'] ?? '';

        return match ($type) {
            'Polygon' => [self::rings($geometry['coordinates'] ?? [])],
            'MultiPolygon' => array_map(fn ($p) => self::rings($p), $geometry['coordinates'] ?? []),
            'GeometryCollection' => array_merge([], ...array_map(fn ($g) => is_array($g) ? self::polygonsOf($g) : [], $geometry['geometries'] ?? [])),
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

    private static function finish(array $raw, array $rawFeatures, array $warnings): array
    {
        if ($raw === [] && $rawFeatures === []) {
            throw new InvalidArgumentException('Nothing to map was found in this file. Draw the service area as polygons, and canals as lines (structures as points).');
        }

        // coordinates must be latitude / longitude, not metres (UTM, PRS92...)
        $allCoords = function () use ($raw, $rawFeatures) {
            foreach ($raw as $lot) {
                foreach ($lot['polys'] as $rings) {
                    foreach ($rings as $ring) {
                        yield from $ring;
                    }
                }
            }

            foreach ($rawFeatures as $f) {
                $c = $f['geometry']['coordinates'];

                if ($f['geometry']['type'] === 'Point') {
                    yield $c;
                } elseif ($f['geometry']['type'] === 'LineString') {
                    yield from $c;
                } else {
                    foreach ($c as $line) {
                        yield from $line;
                    }
                }
            }
        };

        foreach ($allCoords() as [$x, $y]) {
            if (abs($x) > 180 || abs($y) > 90) {
                if (abs($x) <= 90 && abs($y) <= 180) {
                    throw new InvalidArgumentException('Latitude and longitude look swapped (longitude must come first, as in KML and GeoJSON). Re-export the file.');
                }

                throw new InvalidArgumentException('These coordinates are in metres (a projected system such as UTM), not latitude/longitude. Export the file as WGS84 (EPSG:4326) from QGIS or Google Earth.');
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

                if (! self::inRegion($cx, $cy)) {
                    $outside++;
                    $swapped += self::inRegion($cy, $cx) ? 1 : 0;

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
                'name' => ($lot['name'] !== null && ! is_numeric($lot['name'])) ? $lot['name'] : null,   // numeric names are just area labels
            ];
        }

        // canal network
        $features = [];
        $meters = 0.0;

        foreach ($rawFeatures as $f) {
            $g = $f['geometry'];
            $lines = $g['type'] === 'Point' ? [] : ($g['type'] === 'LineString' ? [$g['coordinates']] : $g['coordinates']);
            $first = $g['type'] === 'Point' ? $g['coordinates'] : $lines[0][0];

            if (! self::inRegion($first[0], $first[1])) {
                $outside++;
                $swapped += self::inRegion($first[1], $first[0]) ? 1 : 0;

                continue;
            }

            $round = fn ($c) => [round($c[0], 6), round($c[1], 6)];

            if ($g['type'] === 'Point') {
                $g['coordinates'] = $round($g['coordinates']);
                $len = 0.0;
                $vertices++;
            } else {
                $lines = array_map(fn ($l) => array_map($round, $l), $lines);
                $len = array_sum(array_map([self::class, 'lineLengthM'], $lines));
                $vertices += array_sum(array_map('count', $lines));
                $g = count($lines) === 1 ? ['type' => 'LineString', 'coordinates' => $lines[0]] : ['type' => 'MultiLineString', 'coordinates' => $lines];
            }

            $features[] = ['kind' => $f['kind'], 'name' => $f['name'], 'group' => $f['group'], 'geometry' => $g, 'length_m' => round($len, 1)];

            if ($f['kind'] === 'canal') {
                $meters += $len;
            }
        }

        if ($outside > 0 && $swapped === $outside && $lots === [] && $features === []) {
            throw new InvalidArgumentException('Latitude and longitude look swapped (longitude must come first, as in KML and GeoJSON). Re-export the file.');
        }

        if ($outside > 0 && $lots === [] && $features === []) {
            throw new InvalidArgumentException('The shapes are outside Region V (Bicol). Check that this is the right IA\'s file and that it is in WGS84.');
        }

        if ($outside > 0) {
            throw new InvalidArgumentException("$outside item(s) are outside Region V (Bicol). Remove them from the file, or check that it is the right IA's file.");
        }

        if ($lots === [] && $features === []) {
            throw new InvalidArgumentException('Nothing usable was found (the shapes are empty or too small).');
        }

        if (count($lots) > self::MAX_LOTS) {
            throw new InvalidArgumentException('Too many lots ('.count($lots).'; maximum '.self::MAX_LOTS.'). Split the file per IA.');
        }

        if (count($features) > self::MAX_FEATURES) {
            throw new InvalidArgumentException('Too many canal items ('.count($features).'; maximum '.self::MAX_FEATURES.'). Split the file per IA.');
        }

        if ($vertices > self::MAX_VERTICES) {
            throw new InvalidArgumentException('The map is too detailed ('.number_format($vertices).' points; maximum '.number_format(self::MAX_VERTICES).'). Simplify it in QGIS or Google Earth.');
        }

        if ($dropped > 0) {
            $warnings[] = "$dropped empty or very small shape(s) were ignored.";
        }

        $counts = array_count_values(array_column($features, 'kind'));

        return [
            'lots' => $lots,
            'features' => $features,
            'total_ha' => round(array_sum(array_column($lots, 'area_ha')), 4),
            'canal_km' => round($meters / 1000, 3),
            'counts' => $counts,   // ['canal' => 16, 'structure' => 100, ...]
            'vertices' => $vertices,
            'warnings' => $warnings,
        ];
    }

    private static function inRegion(float $lon, float $lat): bool
    {
        return $lon >= self::LON[0] && $lon <= self::LON[1] && $lat >= self::LAT[0] && $lat <= self::LAT[1];
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
        $pts = array_slice($ring, 0, max(count($ring) - 1, 1));   // without the closing point
        $n = count($pts);

        return [array_sum(array_column($pts, 0)) / $n, array_sum(array_column($pts, 1)) / $n];
    }

    /** Length of a line in metres (local flat projection, fine at canal scale). */
    public static function lineLengthM(array $line): float
    {
        $len = 0.0;

        for ($i = 0, $n = count($line) - 1; $i < $n; $i++) {
            $kx = 111320 * cos(deg2rad(($line[$i][1] + $line[$i + 1][1]) / 2));
            $dx = ($line[$i + 1][0] - $line[$i][0]) * $kx;
            $dy = ($line[$i + 1][1] - $line[$i][1]) * 110574;
            $len += sqrt($dx * $dx + $dy * $dy);
        }

        return $len;
    }
}
