<?php

namespace App\Http\Resources;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Een afspraak voor de app (FR-08, ADR-011). Klant en admin krijgen dezelfde vorm; admin-only
 * velden (klant, Outlook-status) alleen voor admins.
 *
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
{
    /** Kale JSON zonder `data`-envelope, zoals de bestaande API-endpoints. */
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $isAdmin = (bool) $request->user()?->isAdmin();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'location' => $this->location,
            'location_label' => $this->locationLabel(),
            'travel_minutes' => $this->effectiveTravelMinutes(),
            'status' => $this->status,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'duration_minutes' => $this->durationMinutes(),
            'description' => $this->description,
            'online_meeting_url' => $this->online_meeting_url,
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ]),
            'client' => $this->when($isAdmin && $this->relationLoaded('client'), fn () => [
                'id' => $this->client->id,
                'name' => $this->client->name,
            ]),
            'organizer' => $this->when(
                $this->relationLoaded('organizer') && $this->organizer !== null,
                fn () => new EmployeeResource($this->organizer),
            ),
            'participants' => $this->when(
                $this->relationLoaded('attendees'),
                fn () => EmployeeResource::collection($this->internalParticipants()),
            ),
            'options' => $this->when(
                $this->hasStatus(AppointmentStatus::Voorstel) && $this->relationLoaded('options'),
                fn () => $this->options->sortBy('start_time')->values()->map(fn ($o) => [
                    'id' => $o->id,
                    'start_time' => $o->start_time,
                    'end_time' => $o->end_time,
                ]),
            ),
            'can_cancel' => ! $isAdmin
                && ! $this->hasStatus(AppointmentStatus::Geannuleerd)
                && $this->start_time->isFuture(),
            'calendar_sync_status' => $this->when($isAdmin, $this->calendar_sync_status),
            'calendar_sync_message' => $this->when($isAdmin, fn () => $this->syncMessage()),
        ];
    }

    /**
     * In gewone taal: staat de afspraak al in Outlook? Null = niets te melden.
     */
    private function syncMessage(): ?string
    {
        return match ($this->calendar_sync_status) {
            Appointment::SYNC_PENDING => 'Wordt nu in Outlook gezet.',
            Appointment::SYNC_FAILED => 'Deze afspraak staat nog niet in Outlook. We konden hem er niet in zetten; zet hem zelf in je agenda of vraag de beheerder de koppeling te controleren.',
            default => null,
        };
    }
}
