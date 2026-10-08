<?php

namespace App\Http\Requests\Api;

use App\Models\Branding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Een logo is door een beheerder aangeleverde input, geen vertrouwde data (skill
 * `white-label-branding`, OWASP File Upload Cheat Sheet): type op extensie én inhoud,
 * grootte en afmetingen begrensd, en alleen rasterafbeeldingen.
 *
 * SVG wordt geweigerd (ADR-010): het is XML dat scripts en externe referenties kan bevatten,
 * er is geen sanitizer in de stack, en Laravel's `dimensions`-regel geeft voor SVG altijd
 * `true` — de pixelgrenzen zouden dan juist níet gelden.
 */
class UploadBrandingLogoRequest extends FormRequest
{
    private const RASTER_TYPES = [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP];

    public function authorize(): bool
    {
        return $this->user()?->can('update', Branding::current()) ?? false;
    }

    public function rules(): array
    {
        $logo = config('branding.logo');

        return [
            'logo' => [
                'required',
                'file',
                'mimes:'.implode(',', $logo['mimes']),          // extensie geraden uit de inhoud
                'mimetypes:'.implode(',', $logo['mimetypes']),  // gesnift content-type
                'max:'.$logo['max_kilobytes'],
                sprintf(
                    'dimensions:min_width=%1$d,min_height=%1$d,max_width=%2$d,max_height=%2$d',
                    $logo['min_dimension'],
                    $logo['max_dimension'],
                ),
            ],
        ];
    }

    /**
     * Laatste vangnet: het bestand moet echt als rasterafbeelding te decoderen zijn.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $info = @getimagesize($this->file('logo')->getRealPath());

                if ($info === false || ! in_array($info[2], self::RASTER_TYPES, true)) {
                    $validator->errors()->add('logo', 'Het bestand is geen geldige PNG-, JPG- of WebP-afbeelding.');
                }
            },
        ];
    }

    public function messages(): array
    {
        $logo = config('branding.logo');

        return [
            'logo.required' => 'Kies een logo.',
            'logo.file' => 'Het logo is niet goed ontvangen. Probeer het opnieuw.',
            'logo.mimes' => 'Alleen PNG-, JPG- of WebP-bestanden zijn toegestaan. SVG wordt om veiligheidsredenen niet geaccepteerd.',
            'logo.mimetypes' => 'Alleen PNG-, JPG- of WebP-bestanden zijn toegestaan. SVG wordt om veiligheidsredenen niet geaccepteerd.',
            'logo.max' => sprintf('Het logo mag maximaal %d MB zijn.', $logo['max_kilobytes'] / 1024),
            'logo.dimensions' => sprintf(
                'Het logo moet minimaal %1$d×%1$d en maximaal %2$d×%2$d pixels zijn.',
                $logo['min_dimension'],
                $logo['max_dimension'],
            ),
        ];
    }
}
