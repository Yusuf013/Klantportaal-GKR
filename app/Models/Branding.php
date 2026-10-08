<?php

namespace App\Models;

use Database\Factories\BrandingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Huisstijl van de installatie (FR-02 / NFR-03, ADR-010): organisatienaam, twee kleuren en een
 * logo. Tokens, geen layout — de app bepaalt waar ze gebruikt worden.
 *
 * `klant_id` en `logo_path` zijn bewust niet mass-assignable: de klant komt nooit uit de
 * request (skill `multi-tenancy`) en het logopad zet alleen BrandingLogoService.
 */
#[Fillable(['organization_name', 'primary_color', 'accent_color'])]
class Branding extends Model
{
    /** @use HasFactory<BrandingFactory> */
    use HasFactory;

    /**
     * Het enige leespunt voor branding. Nu één record voor de hele installatie; schrijft niet,
     * dus een GET blijft een pure read, ook als er nog nooit iets is opgeslagen.
     *
     * #33: scopen op de klant uit de geverifieerde sessie (of, publiek, de request-host).
     */
    public static function current(): self
    {
        return static::query()->orderBy('id')->firstOrNew();
    }

    protected function organizationName(): Attribute
    {
        return Attribute::get(fn (?string $value) => $value ?? config('branding.defaults.organization_name'));
    }

    protected function primaryColor(): Attribute
    {
        return Attribute::get(fn (?string $value) => $value ?? config('branding.defaults.primary_color'));
    }

    protected function accentColor(): Attribute
    {
        return Attribute::get(fn (?string $value) => $value ?? config('branding.defaults.accent_color'));
    }

    /**
     * Map waarin het logo van deze branding staat. Nu altijd `branding/default`; na #33
     * automatisch per klant, zonder dat upload- of leescode verandert.
     */
    public function logoDirectory(): string
    {
        return 'branding/'.($this->klant_id ?? 'default');
    }

    /**
     * Root-relatief pad voor clients (bv. `/storage/branding/default/<hash>.png`). Relatief
     * omdat `APP_URL` per omgeving afwijkt van het adres waarop de app de API bereikt
     * (lokaal `http://localhost` zonder de poort van `artisan serve`); de client plakt het
     * achter zijn eigen base-URL. Gaat uit van een disk op hetzelfde origin (`public`).
     */
    public function logoPublicPath(): ?string
    {
        if ($this->logo_path === null) {
            return null;
        }

        return parse_url(Storage::disk(config('branding.disk'))->url($this->logo_path), PHP_URL_PATH);
    }
}
