<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallbackRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Belverzoeken afhandelen (admin).
 */
class CallbackRequestController extends Controller
{
    public function index(): JsonResponse
    {
        $requests = CallbackRequest::query()
            ->with(['client:id,name', 'project:id,name', 'handler:id,name'])
            ->orderByRaw("status = 'open' desc")
            ->latest()
            ->limit(100)
            ->get();

        return response()->json($requests->map(fn (CallbackRequest $c) => $this->present($c)));
    }

    public function update(Request $request, CallbackRequest $callbackRequest): JsonResponse
    {
        $validated = $request->validate(['status' => ['required', 'in:open,afgehandeld']]);

        $handled = $validated['status'] === CallbackRequest::STATUS_AFGEHANDELD;
        $callbackRequest->forceFill([
            'status' => $validated['status'],
            'handled_by' => $handled ? $request->user()->id : null,
            'handled_at' => $handled ? now() : null,
        ])->save();

        return response()->json($this->present($callbackRequest->load(['client:id,name', 'project:id,name', 'handler:id,name'])));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CallbackRequest $c): array
    {
        return [
            'id' => $c->id,
            'client' => ['id' => $c->client->id, 'name' => $c->client->name],
            'project' => $c->project ? ['id' => $c->project->id, 'name' => $c->project->name] : null,
            'phone' => $c->phone,
            'note' => $c->note,
            'status' => $c->status,
            'handled_by' => $c->handler?->name,
            'created_at' => $c->created_at,
        ];
    }
}
