<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Support;

use App\Domain\Pillars\Enums\CombatantCommand;
use App\Domain\Pillars\Models\Base;
use Illuminate\Support\Collection;

/**
 * Prerendered inline map SVGs for the Navy Bases family, ported 1:1 from the
 * legacy React components:
 *
 *  - {@see usMap()}    → src/components/USBasesMap.tsx (960x560 CONUS schematic
 *    with the Hawaii / Alaska inset box)
 *  - {@see worldMap()} → src/components/WorldMapSVG.tsx (1000x500 world
 *    silhouette with an optional [minLng, maxLng, minLat, maxLat] zoom viewport)
 *
 * The legacy maps are interactive only through hover state; the "hovered" pin
 * treatment (bigger radius, white stroke, glow filter and a mono caption) is the
 * resting state on the single-base detail page, which passes its own slug as the
 * hovered one — so the server-rendered markup here is what the live site paints.
 *
 * Lives outside the Blade views because three of the five bases views need the
 * same geometry, and the coordinate tables must not be triplicated.
 */
final class BaseMapSvg
{
    /** Default world viewport: [minLng, maxLng, minLat, maxLat] (WorldMapSVG L22). */
    private const WORLD_VIEWPORT = [-180.0, 180.0, -60.0, 78.0];

    private const WORLD_WIDTH = 1000;

    private const WORLD_HEIGHT = 500;

    /**
     * Pins for a list of bases — the port of `basesToPins` (WorldMapSVG L346).
     *
     * @param  Collection<int, Base>|array<int, Base>  $bases
     * @return list<array{slug: string, name: string, lat: float, lng: float, region: string}>
     */
    public static function pins(Collection|array $bases): array
    {
        $pins = [];
        foreach ($bases as $base) {
            $pins[] = [
                'slug' => $base->slug,
                'name' => $base->name,
                'lat' => (float) $base->lat,
                'lng' => (float) $base->lng,
                'region' => $base->region instanceof CombatantCommand ? $base->region->value : '',
            ];
        }

        return $pins;
    }

    /**
     * The CONUS schematic (USBasesMap.tsx). `$hoveredSlug` renders the enlarged,
     * captioned marker treatment for one pin.
     *
     * @param  list<array{slug: string, name: string, lat: float, lng: float, region: string}>  $pins
     */
    public static function usMap(array $pins, ?string $hoveredSlug = null): string
    {
        $label = 'Map of the United States showing '.count($pins).' U.S. Navy bases';

        $svg = '<svg viewBox="0 0 960 560" preserveAspectRatio="xMidYMid meet" style="width:100%;height:100%"'
            .' role="img" aria-label="'.e($label).'">'
            .'<defs><filter id="bases-glow"><feGaussianBlur stdDeviation="3" result="coloredBlur"/>'
            .'<feMerge><feMergeNode in="coloredBlur"/><feMergeNode in="SourceGraphic"/></feMerge></filter></defs>';

        $svg .= '<g style="opacity:0.03">';
        for ($i = 0; $i < 25; $i++) {
            $svg .= '<line x1="'.($i * 40).'" y1="0" x2="'.($i * 40).'" y2="560" stroke="#FAFAF8" stroke-width="0.5"/>';
        }
        for ($i = 0; $i < 15; $i++) {
            $svg .= '<line x1="0" y1="'.($i * 40).'" x2="960" y2="'.($i * 40).'" stroke="#FAFAF8" stroke-width="0.5"/>';
        }
        $svg .= '</g>';

        $svg .= '<path d="'.self::usOutlinePath().'" fill="rgba(21,35,64,0.85)" stroke="rgba(201,168,76,0.3)"'
            .' stroke-width="1.5" stroke-linejoin="round"/>';

        $svg .= '<g><rect x="60" y="430" width="240" height="120" rx="2" fill="none" stroke="rgba(201,168,76,0.15)"'
            .' stroke-width="1" stroke-dasharray="4 3"/>'
            .'<text x="80" y="450" fill="rgba(250,250,248,0.25)" font-family="\'IBM Plex Mono\', monospace"'
            .' font-size="9" letter-spacing="1.5">HAWAII</text>';
        foreach (self::hawaiiIslandPaths() as $d) {
            $svg .= '<path d="'.$d.'" fill="rgba(21,35,64,0.85)" stroke="rgba(201,168,76,0.25)" stroke-width="1"'
                .' stroke-linejoin="round"/>';
        }
        $svg .= '<text x="210" y="450" fill="rgba(250,250,248,0.25)" font-family="\'IBM Plex Mono\', monospace"'
            .' font-size="9" letter-spacing="1.5">ALASKA</text>'
            .'<path d="M 215,465 L 240,460 L 268,465 L 280,480 L 272,498 L 248,504 L 225,498 L 215,485 Z"'
            .' fill="rgba(21,35,64,0.85)" stroke="rgba(201,168,76,0.25)" stroke-width="1"/></g>';

        foreach ($pins as $pin) {
            [$x, $y] = self::usMarkerPosition($pin['lat'], $pin['lng']);
            $hovered = $hoveredSlug !== null && $hoveredSlug === $pin['slug'];
            $r = $hovered ? 8 : 5;

            $svg .= '<g style="cursor:pointer" role="button" tabindex="0" aria-label="'
                .e($pin['name'].' — click to view details').'">'
                .'<circle cx="'.$x.'" cy="'.$y.'" r="'.($hovered ? $r + 4 : $r).'" fill="transparent"'
                .' style="pointer-events:all"/>'
                .'<circle cx="'.$x.'" cy="'.$y.'" r="'.$r.'" fill="#C9A84C" stroke="'
                .($hovered ? '#FAFAF8' : 'rgba(10,22,40,0.8)').'" stroke-width="'.($hovered ? '2' : '1.5').'"'
                .($hovered ? ' filter="url(#bases-glow)"' : '').'/>';
            if ($hovered) {
                $svg .= '<text x="'.($x + 10).'" y="'.($y + 4).'" fill="#FAFAF8"'
                    .' font-family="\'IBM Plex Mono\', monospace" font-size="11" letter-spacing="0.8"'
                    .' style="text-transform:uppercase;pointer-events:none">'.e($pin['name']).'</text>';
            }
            $svg .= '</g>';
        }

        return $svg.'</svg>';
    }

    /**
     * The world silhouette (WorldMapSVG.tsx).
     *
     * @param  list<array{slug: string, name: string, lat: float, lng: float, region: string}>  $pins
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $viewport
     */
    public static function worldMap(array $pins, ?array $viewport = null, ?string $hoveredSlug = null, ?string $ariaLabel = null): string
    {
        $viewport ??= self::WORLD_VIEWPORT;
        [$minLng, $maxLng, $minLat, $maxLat] = $viewport;
        $lngSpan = $maxLng - $minLng;
        $latSpan = $maxLat - $minLat;
        $project = static fn (float $lat, float $lng): array => [
            (($lng - $minLng) / $lngSpan) * self::WORLD_WIDTH,
            (($maxLat - $lat) / $latSpan) * self::WORLD_HEIGHT,
        ];

        $label = $ariaLabel ?? 'World map showing '.count($pins).' U.S. Navy overseas installations';

        $svg = '<svg viewBox="0 0 '.self::WORLD_WIDTH.' '.self::WORLD_HEIGHT.'" preserveAspectRatio="xMidYMid meet"'
            .' style="width:100%;height:100%" role="img" aria-label="'.e($label).'">'
            .'<defs><filter id="world-glow"><feGaussianBlur stdDeviation="3" result="coloredBlur"/>'
            .'<feMerge><feMergeNode in="coloredBlur"/><feMergeNode in="SourceGraphic"/></feMerge></filter></defs>';

        $svg .= '<g style="opacity:0.04">';
        for ($i = 0; $i < (int) ceil(self::WORLD_WIDTH / 50); $i++) {
            $svg .= '<line x1="'.($i * 50).'" y1="0" x2="'.($i * 50).'" y2="'.self::WORLD_HEIGHT.'"'
                .' stroke="#FAFAF8" stroke-width="0.5"/>';
        }
        for ($i = 0; $i < (int) ceil(self::WORLD_HEIGHT / 50); $i++) {
            $svg .= '<line x1="0" y1="'.($i * 50).'" x2="'.self::WORLD_WIDTH.'" y2="'.($i * 50).'"'
                .' stroke="#FAFAF8" stroke-width="0.5"/>';
        }
        $svg .= '</g>';

        foreach (self::CONTINENTS as $points) {
            $d = '';
            foreach ($points as $i => $point) {
                [$x, $y] = $project($point[0], $point[1]);
                $d .= ($i === 0 ? 'M ' : ' L ').sprintf('%.1f,%.1f', $x, $y);
            }
            $svg .= '<path d="'.$d.' Z" fill="rgba(21,35,64,0.85)" stroke="rgba(201,168,76,0.3)"'
                .' stroke-width="1.2" stroke-linejoin="round"/>';
        }

        foreach ($pins as $pin) {
            [$x, $y] = $project($pin['lat'], $pin['lng']);
            if ($x < -10 || $x > self::WORLD_WIDTH + 10 || $y < -10 || $y > self::WORLD_HEIGHT + 10) {
                continue;
            }
            $hovered = $hoveredSlug !== null && $hoveredSlug === $pin['slug'];
            $r = $hovered ? 7 : 4.5;
            $cx = sprintf('%.4f', $x);
            $cy = sprintf('%.4f', $y);

            $svg .= '<g style="cursor:default" data-pin-region="'.e($pin['region']).'" aria-label="'.e($pin['name']).'">'
                .'<circle cx="'.$cx.'" cy="'.$cy.'" r="'.($r + 4).'" fill="transparent" style="pointer-events:all"/>'
                .'<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="#C9A84C" stroke="'
                .($hovered ? '#FAFAF8' : 'rgba(10,22,40,0.85)').'" stroke-width="'.($hovered ? '2' : '1.5').'"'
                .($hovered ? ' filter="url(#world-glow)"' : '').'/>';
            if ($hovered) {
                $svg .= '<text x="'.sprintf('%.4f', $x + 10).'" y="'.sprintf('%.4f', $y + 4).'" fill="#FAFAF8"'
                    .' font-family="\'IBM Plex Mono\', monospace" font-size="11" letter-spacing="0.8"'
                    .' style="text-transform:uppercase;pointer-events:none">'.e($pin['name']).'</text>';
            }
            $svg .= '</g>';
        }

        return $svg.'</svg>';
    }

    /**
     * Zoom viewport for a set of pins (NavyBasesCountry.tsx L98-113): the pins'
     * bounding box padded by the per-country pad, or the whole world when empty.
     *
     * @param  list<array{slug: string, name: string, lat: float, lng: float, region: string}>  $pins
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    public static function viewportForPins(array $pins, ?string $countrySlug): ?array
    {
        if ($pins === []) {
            return null;
        }

        [$padLng, $padLat] = self::TIGHT_PAD[$countrySlug] ?? [8.0, 6.0];
        $lats = array_column($pins, 'lat');
        $lngs = array_column($pins, 'lng');

        return [min($lngs) - $padLng, max($lngs) + $padLng, min($lats) - $padLat, max($lats) + $padLat];
    }

    /**
     * Zoom viewport centred on one overseas base (NavyBaseDetail.tsx L142-150).
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public static function viewportForBase(Base $base): array
    {
        [$spanLng, $spanLat] = self::TIGHT_ZOOM[$base->country_slug] ?? [12.0, 8.0];
        $lat = (float) $base->lat;
        $lng = (float) $base->lng;

        return [$lng - $spanLng, $lng + $spanLng, $lat - $spanLat, $lat + $spanLat];
    }

    /** Small-island territories need a tighter zoom (NavyBaseDetail.tsx L142). */
    private const TIGHT_ZOOM = [
        'guam' => [3.0, 2.0],
        'british-indian-ocean-territory' => [2.0, 1.4],
        'bahrain' => [4.0, 2.7],
    ];

    /** Same idea for the country hubs (NavyBasesCountry.tsx L102). */
    private const TIGHT_PAD = [
        'guam' => [3.0, 2.0],
        'british-indian-ocean-territory' => [2.0, 1.4],
        'bahrain' => [4.0, 2.7],
    ];

    /**
     * USBasesMap.tsx L10 — the CONUS linear projection, rounded like Math.round.
     *
     * @return array{0: int, 1: int}
     */
    private static function usProject(float $lat, float $lng): array
    {
        return [
            self::jsRound(50 + (($lng + 124.7) / 57.8) * 860),
            self::jsRound(30 + ((49.4 - $lat) / 24.9) * 440),
        ];
    }

    /**
     * USBasesMap.tsx L19 — the Hawaii inset projection (unrounded).
     *
     * @return array{0: float, 1: float}
     */
    private static function hawaiiProject(float $lat, float $lng): array
    {
        return [
            95 + (($lng + 160.3) / 5.5) * 100,
            480 + ((22.3 - $lat) / 3.3) * 30,
        ];
    }

    /**
     * USBasesMap.tsx L25 — Hawaii pins clamp into the inset box.
     *
     * @return array{0: int, 1: int}
     */
    private static function usMarkerPosition(float $lat, float $lng): array
    {
        if ($lat < 25 && $lng < -140) {
            [$x, $y] = self::hawaiiProject($lat, $lng);

            return [
                self::jsRound(max(95.0, min(198.0, $x))),
                self::jsRound(max(478.0, min(512.0, $y))),
            ];
        }

        return self::usProject($lat, $lng);
    }

    private static function jsRound(float $value): int
    {
        return (int) floor($value + 0.5);
    }

    /** @return list<string> */
    private static function hawaiiIslandPaths(): array
    {
        $paths = [];
        foreach (self::HAWAII_ISLANDS as $island) {
            $d = '';
            foreach ($island as $i => $point) {
                [$x, $y] = self::hawaiiProject($point[0], $point[1]);
                $d .= ($i === 0 ? 'M ' : ' L ').sprintf('%.1f,%.1f', $x, $y);
            }
            $paths[] = $d.' Z';
        }

        return $paths;
    }

    private static function usOutlinePath(): string
    {
        $d = '';
        foreach (self::US_BORDER as $i => $point) {
            [$x, $y] = self::usProject($point[0], $point[1]);
            $d .= ($i === 0 ? 'M ' : ' L ').$x.','.$y;
        }

        return $d.' Z';
    }

    /** USBasesMap.tsx L38 — simplified outlines of the four main Hawaiian islands. */
    private const HAWAII_ISLANDS = [
        [[22.23, -159.4], [22.15, -159.3], [21.95, -159.35], [21.87, -159.45], [21.9, -159.6], [22.03, -159.78], [22.15, -159.7]],
        [[21.7, -158.0], [21.5, -157.7], [21.3, -157.65], [21.25, -157.8], [21.3, -158.1], [21.55, -158.25]],
        [[21.0, -156.6], [20.9, -156.1], [20.7, -155.98], [20.6, -156.4], [20.8, -156.5], [20.8, -156.68]],
        [[20.27, -155.9], [20.0, -155.1], [19.5, -154.8], [19.0, -155.5], [18.9, -155.68], [19.35, -155.9], [19.7, -156.05], [20.02, -155.83]],
    ];

    /** USBasesMap.tsx L68 — the CONUS border trace. */
    private const US_BORDER = [
        [49.0, -124.7], [48.5, -124.5], [47.5, -124.3], [46.2, -124.0], [44.5, -124.1],
        [42.0, -124.3], [41.0, -124.2], [40.0, -124.2], [38.5, -123.0], [37.8, -122.5],
        [36.8, -122.0], [36.0, -121.5], [35.5, -120.8], [34.5, -120.5], [34.0, -119.5],
        [33.7, -118.3], [33.0, -117.3], [32.5, -117.1], [32.5, -114.7], [31.3, -111.0],
        [31.3, -108.2], [31.8, -106.6], [30.0, -104.0], [29.5, -103.0], [29.0, -102.0],
        [28.0, -100.0], [26.0, -97.2], [27.0, -97.2], [28.0, -96.5], [29.0, -95.0],
        [29.5, -94.5], [29.8, -93.8], [29.5, -92.5], [29.0, -91.0], [29.0, -89.5],
        [29.4, -89.0], [30.2, -89.3], [30.3, -88.5], [30.3, -87.5], [30.0, -86.0],
        [30.0, -85.5], [29.9, -84.5], [29.0, -83.0], [28.0, -82.7], [27.0, -82.5],
        [26.0, -81.8], [25.2, -80.8], [25.3, -80.1], [26.5, -80.0], [27.5, -80.2],
        [29.0, -80.8], [30.5, -81.5], [31.5, -81.0], [32.0, -80.8], [33.0, -79.5],
        [34.0, -77.8], [35.2, -75.5], [36.0, -75.7], [37.0, -76.0], [37.5, -76.3],
        [38.0, -76.0], [38.5, -75.1], [39.0, -74.8], [39.5, -74.2], [40.5, -74.0],
        [40.8, -73.9], [41.0, -72.0], [41.5, -71.5], [42.0, -70.0], [42.3, -70.8],
        [43.0, -70.5], [43.5, -70.0], [44.0, -68.5], [44.5, -67.5], [45.0, -67.0],
        [47.0, -67.5], [47.3, -68.3], [47.0, -69.0], [46.0, -70.0], [45.5, -71.0],
        [45.0, -71.5], [45.0, -73.0], [44.5, -74.5], [44.0, -76.0], [43.5, -76.5],
        [43.5, -79.0], [42.5, -79.0], [42.0, -80.5], [41.7, -83.5], [41.7, -84.8],
        [41.7, -87.0], [42.5, -87.5], [43.0, -87.5], [44.0, -87.5], [45.0, -87.0],
        [45.5, -87.5], [46.5, -87.5], [46.8, -89.5], [46.5, -90.5], [46.8, -92.0],
        [48.0, -89.5], [48.5, -89.0], [49.0, -95.0], [49.0, -104.0], [49.0, -117.0],
        [49.0, -124.7],
    ];

    /** WorldMapSVG.tsx L42 — sparse continent / island silhouettes, in source order. */
    private const CONTINENTS = [
        [[70, -168], [70, -140], [60, -130], [49, -125], [40, -124], [33, -118], [28, -112], [25, -109], [22, -106], [16, -94], [16, -88], [12, -83], [9, -78], [8, -77], [10, -75], [13, -71], [18, -68], [25, -80], [30, -82], [35, -76], [40, -73], [45, -65], [49, -55], [55, -57], [60, -64], [60, -78], [56, -78], [60, -94], [70, -95], [75, -100], [76, -110], [73, -125], [70, -150], [70, -168]],
        [[12, -72], [10, -62], [5, -52], [-5, -35], [-15, -39], [-23, -43], [-33, -53], [-40, -63], [-50, -69], [-55, -68], [-50, -73], [-40, -73], [-30, -71], [-18, -71], [-8, -79], [0, -80], [8, -78], [12, -72]],
        [[70, 28], [70, 18], [62, 6], [55, 8], [50, -1], [50, -10], [43, -10], [36, -6], [37, -2], [38, 5], [38.9, 16.6], [40, 18], [42, 19], [40, 24], [37, 27], [40, 30], [45, 30], [48, 35], [55, 38], [60, 40], [65, 35], [70, 30], [70, 28]],
        [[37, -6], [35, 0], [33, 11], [32, 22], [31, 33], [22, 36], [12, 43], [10, 51], [4, 47], [-1, 41], [-10, 40], [-22, 35], [-30, 32], [-34, 26], [-34, 20], [-28, 16], [-18, 12], [-8, 13], [0, 9], [4, 8], [8, -8], [10, -16], [14, -17], [20, -17], [27, -12], [33, -8], [37, -6]],
        [[70, 30], [75, 60], [78, 100], [70, 130], [60, 145], [52, 141], [43, 135], [42.5, 130.6], [40.0, 129.7], [38.6, 128.5], [36.0, 129.4], [35.1, 129.0], [34.3, 126.5], [36.7, 126.2], [37.7, 126.7], [38.7, 125.2], [39.7, 124.3], [37.4, 122.5], [34.3, 120.2], [30, 122], [22, 115], [15, 109], [10, 105], [5, 102], [1.35, 103.5], [1.5, 104.3], [10, 116], [10, 125], [15, 125], [12, 110], [5, 100], [10, 92], [20, 88], [22, 70], [25, 65], [28, 58], [25, 50], [22, 44], [15, 44], [12, 47], [13, 53], [25, 60], [35, 50], [38, 45], [40, 42], [44, 40], [50, 35], [55, 35], [60, 30], [70, 30]],
        [[-10, 130], [-12, 142], [-18, 146], [-25, 153], [-37, 150], [-39, 146], [-35, 137], [-32, 132], [-32, 125], [-34, 116], [-30, 115], [-22, 114], [-15, 121], [-12, 130], [-10, 130]],
        [[41.5, 140.9], [40.6, 141.5], [38.3, 141.0], [36.9, 140.7], [35.7, 140.9], [34.9, 140.0], [35.4, 139.8], [35.2, 139.6], [34.6, 138.9], [34.6, 137.2], [33.5, 135.8], [34.6, 135.0], [34.2, 132.5], [33.9, 130.9], [34.4, 131.2], [35.5, 132.7], [36.8, 136.0], [37.4, 137.2], [38.0, 139.4], [39.9, 140.0], [41.2, 140.3], [41.5, 140.9]],
        [[45.5, 141.9], [44.1, 145.1], [43.3, 145.8], [42.9, 144.3], [41.9, 143.2], [42.5, 141.3], [41.7, 141.0], [42.9, 140.2], [44.8, 141.6], [45.5, 141.9]],
        [[33.9, 130.9], [33.2, 131.8], [31.9, 131.5], [31.0, 130.7], [31.2, 130.2], [32.1, 130.2], [32.7, 129.8], [33.2, 129.6], [33.5, 129.9], [33.9, 130.9]],
        [[34.35, 134.7], [33.3, 134.2], [32.7, 133.0], [33.4, 132.4], [34.1, 132.8], [34.35, 134.7]],
        [[66.3, -16.2], [65.6, -13.6], [64.3, -14.6], [63.4, -18.4], [63.8, -22.7], [64.5, -21.7], [64.8, -24.0], [65.5, -24.4], [66.1, -22.9], [66.2, -20.5], [66.3, -16.2]],
        [[23.2, -82.4], [23.1, -81.2], [22.7, -79.9], [22.4, -78.2], [21.6, -77.1], [21.3, -76.1], [20.7, -74.9], [20.2, -74.1], [19.9, -75.1], [19.9, -77.0], [20.7, -77.3], [20.9, -78.6], [22.1, -80.5], [22.1, -81.8], [21.9, -84.0], [21.9, -84.9], [22.7, -83.9], [23.2, -82.4]],
        [[19.75, -73.4], [19.85, -71.7], [19.3, -69.3], [18.4, -68.3], [18.2, -71.1], [18.0, -71.7], [18.25, -73.0], [18.35, -74.4], [18.6, -74.3], [19.1, -72.8], [19.75, -73.4]],
        [[18.4, -78.3], [18.5, -77.3], [18.2, -76.2], [17.9, -76.9], [18.05, -78.0], [18.4, -78.3]],
        [[38.25, 15.65], [37.0, 15.3], [36.7, 15.1], [37.1, 13.9], [37.6, 12.6], [38.1, 12.8], [38.2, 13.7], [38.25, 15.65]],
        [[35.6, 23.6], [35.5, 24.2], [35.4, 25.7], [35.2, 26.3], [35.0, 26.2], [34.95, 25.0], [35.0, 24.4], [35.2, 23.5], [35.5, 23.5], [35.6, 23.6]],
        [[13.65, 144.86], [13.52, 144.87], [13.35, 144.77], [13.24, 144.70], [13.27, 144.63], [13.45, 144.65], [13.52, 144.75], [13.61, 144.80], [13.65, 144.86]],
        [[26.28, 50.45], [26.2, 50.62], [25.95, 50.62], [25.79, 50.55], [25.83, 50.46], [26.15, 50.44], [26.28, 50.45]],
        [[-7.22, 72.41], [-7.27, 72.47], [-7.42, 72.47], [-7.44, 72.42], [-7.35, 72.37], [-7.24, 72.38], [-7.22, 72.41]],
    ];
}
