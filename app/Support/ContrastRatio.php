<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * WCAG 2.2-contrastverhouding tussen twee sRGB-kleuren (`#RRGGBB`).
 *
 * Definities uit WCAG 2.2 ("relative luminance", "contrast ratio"): lineariseer elk kanaal,
 * weeg met 0.2126 / 0.7152 / 0.0722, en deel (lichtste + 0.05) door (donkerste + 0.05).
 * Uitkomst ligt tussen 1 (gelijk) en 21 (zwart/wit). Dezelfde berekening staat in de iOS-app
 * (`Theme/ContrastRatio.swift`) voor live feedback; deze hier is de autoritatieve.
 */
final class ContrastRatio
{
    public static function between(string $hexA, string $hexB): float
    {
        $a = self::relativeLuminance($hexA);
        $b = self::relativeLuminance($hexB);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    public static function relativeLuminance(string $hex): float
    {
        if (! preg_match('/^#([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})$/', $hex, $m)) {
            throw new InvalidArgumentException("Geen #RRGGBB-kleur: {$hex}");
        }

        [$r, $g, $b] = array_map(
            fn (string $channel) => self::linearize(hexdec($channel) / 255),
            [$m[1], $m[2], $m[3]],
        );

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private static function linearize(float $channel): float
    {
        return $channel <= 0.03928
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4;
    }
}
