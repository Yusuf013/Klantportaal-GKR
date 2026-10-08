<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateBrandingRequest;
use App\Http\Requests\Api\UploadBrandingLogoRequest;
use App\Http\Resources\BrandingResource;
use App\Models\Branding;
use App\Services\BrandingLogoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * White-label branding voor de installatie (FR-02 / NFR-03, ADR-010).
 *
 * Lezen is publiek: het inlogscherm van de app moet de huisstijl al tonen vóórdat er een
 * gebruiker bekend is. Schrijven kan alleen een admin (routes: `auth:sanctum` + `admin`,
 * plus BrandingPolicy). Welke branding je krijgt komt nooit uit de request: nu is er één
 * record; na #33 bepaalt de server de klant (sessie of request-host).
 */
class BrandingController extends Controller
{
    public function show(): JsonResponse
    {
        return $this->respond(Branding::current());
    }

    /**
     * Volledige vervanging van naam en kleuren; het beheerscherm stuurt altijd het hele
     * formulier. Autorisatie en validatie (incl. contrast) zitten in UpdateBrandingRequest.
     */
    public function update(UpdateBrandingRequest $request): JsonResponse
    {
        $branding = Branding::current();
        $branding->fill($request->validated())->save();

        return $this->respond($branding);
    }

    /**
     * Apart endpoint (multipart, POST): PHP parseert geen bestanden in een PUT-body, en een
     * kleurwijziging hoeft zo nooit een logo opnieuw te versturen.
     */
    public function storeLogo(UploadBrandingLogoRequest $request, BrandingLogoService $logos): JsonResponse
    {
        return $this->respond($logos->replace(Branding::current(), $request->file('logo')));
    }

    /**
     * Terug naar "geen logo" — de default.
     */
    public function destroyLogo(BrandingLogoService $logos): JsonResponse
    {
        $branding = Branding::current();
        Gate::authorize('update', $branding);

        return $this->respond($logos->remove($branding));
    }

    /**
     * Altijd 200: JsonResource zou 201 geven voor een net aangemaakt record, maar voor de
     * client is dit steeds "de huidige branding", geen nieuwe resource.
     */
    private function respond(Branding $branding): JsonResponse
    {
        return (new BrandingResource($branding))->response()->setStatusCode(200);
    }
}
