<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Klanten en hun projecten voor "Selecteer klant" / "Selecteer project" (admin).
 */
class ClientController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            User::query()->where('is_admin', false)->orderBy('name')->get(['id', 'name']),
        );
    }

    public function projects(User $client): JsonResponse
    {
        abort_if($client->isAdmin(), 404);

        return response()->json(
            Project::query()->where('user_id', $client->id)->orderBy('name')->get(['id', 'name']),
        );
    }
}
