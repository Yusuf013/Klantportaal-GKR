<?php

namespace Tests\Feature;

use App\Models\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Elk brandingveld heeft een default (skill `white-label-branding`): een installatie zonder
 * record, of met lege velden, levert altijd complete waarden op.
 */
class BrandingModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_without_a_record_returns_unsaved_defaults(): void
    {
        $branding = Branding::current();

        $this->assertFalse($branding->exists);
        $this->assertSame('Klantportaal', $branding->organization_name);
        $this->assertSame('#011936', $branding->primary_color);
        $this->assertSame('#059669', $branding->accent_color);
        $this->assertNull($branding->logoPublicPath());
        $this->assertSame(0, Branding::count(), 'Een read mag geen record aanmaken.');
    }

    public function test_null_columns_fall_back_to_defaults_individually(): void
    {
        Branding::create(['organization_name' => 'Acme Bouw']);

        $branding = Branding::current();

        $this->assertSame('Acme Bouw', $branding->organization_name);
        $this->assertSame('#011936', $branding->primary_color);
        $this->assertNull($branding->getRawOriginal('primary_color'), '"Niet geconfigureerd" blijft zichtbaar.');
    }

    public function test_klant_id_and_logo_path_are_not_mass_assignable(): void
    {
        $branding = Branding::create([
            'organization_name' => 'Acme Bouw',
            'klant_id' => 99,
            'logo_path' => '../../.env',
        ]);

        $this->assertNull($branding->fresh()->klant_id);
        $this->assertNull($branding->fresh()->logo_path);
    }

    public function test_logo_directory_is_prepared_per_klant(): void
    {
        $this->assertSame('branding/default', (new Branding)->logoDirectory());

        $branding = new Branding;
        $branding->klant_id = 7;
        $this->assertSame('branding/7', $branding->logoDirectory());
    }

    public function test_logo_public_path_is_root_relative(): void
    {
        $branding = new Branding;
        $branding->logo_path = 'branding/default/abc.png';

        $this->assertSame('/storage/branding/default/abc.png', $branding->logoPublicPath());
    }
}
