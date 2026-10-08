<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * De eigen projecten van de klant, voor "Selecteer project" bij een nieuwe afspraak.
 */
class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Project::query()->where('user_id', $request->user()->id)->orderBy('name')->get(['id', 'name']),
        );
    }
}
