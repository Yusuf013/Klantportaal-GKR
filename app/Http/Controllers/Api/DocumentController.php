<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;

/**
 * Walking-skeleton-endpoint (zie plan "Infrastructuur, testen en API-laag vóór de iOS-basis").
 *
 * Scoping is nu nog op de bestaande `user_id`-keten (User -> Project -> Document), niet op
 * een Klant-model/KlantScope (ADR-001) — dat is een aparte, grotere vervolgstap. Wél al server-
 * side, nooit uit de request zelf, consistent met de niet-onderhandelbare eis in CLAUDE.md en
 * skill `multi-tenancy`.
 *
 * Conventie voor toekomstige by-ID-endpoints op deze resource (skill `api-design`): een document
 * van een andere gebruiker/klant moet altijd `404` teruggeven, nooit `403` — het bestaan van een
 * ID die niet van de aanvrager is, mag niet extern waarneembaar zijn. Er bestaat nu nog geen
 * `GET /documenten/{id}`, dus dit is nog niet van toepassing, maar wel al vastgelegd voor als
 * die er komt.
 */
class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $documents = Document::whereHas('project', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })->with('project:id,name')->get(['id', 'project_id', 'name', 'status', 'approved_at']);

        return response()->json($documents);
    }
}
