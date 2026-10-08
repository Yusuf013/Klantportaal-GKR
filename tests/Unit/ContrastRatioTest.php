<?php

namespace Tests\Unit;

use App\Support\ContrastRatio;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Ankerwaarden uit WCAG 2.2: zwart/wit is 21:1, gelijke kleuren 1:1, en #767676 op wit is de
 * bekende grenswaarde net boven 4,5:1.
 */
class ContrastRatioTest extends TestCase
{
    public function test_black_on_white_is_21_to_1(): void
    {
        $this->assertEqualsWithDelta(21.0, ContrastRatio::between('#000000', '#FFFFFF'), 0.01);
    }

    public function test_identical_colours_are_1_to_1(): void
    {
        $this->assertEqualsWithDelta(1.0, ContrastRatio::between('#059669', '#059669'), 0.0001);
    }

    public function test_wcag_boundary_grey_on_white(): void
    {
        $this->assertEqualsWithDelta(4.54, ContrastRatio::between('#767676', '#FFFFFF'), 0.01);
    }

    public function test_is_symmetric_and_case_insensitive(): void
    {
        $this->assertSame(
            ContrastRatio::between('#011936', '#ffffff'),
            ContrastRatio::between('#FFFFFF', '#011936'),
        );
    }

    public function test_default_palette_meets_the_branding_rules(): void
    {
        $this->assertGreaterThanOrEqual(4.5, ContrastRatio::between('#011936', '#FFFFFF'));
        $this->assertGreaterThanOrEqual(3.0, ContrastRatio::between('#059669', '#FFFFFF'));
        $this->assertGreaterThanOrEqual(3.0, ContrastRatio::between('#059669', '#011936'));
    }

    public function test_rejects_non_hex_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ContrastRatio::relativeLuminance('rgb(1,2,3)');
    }
}
