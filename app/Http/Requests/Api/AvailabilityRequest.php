<?php

namespace App\Http\Requests\Api;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Vrije blokken opvragen voor 1-2 (klant) of meer (admin) medewerkers.
 */
class AvailabilityRequest extends FormRequest
{
    public function rules(): array
    {
        $isAdmin = $this->user()->isAdmin();

        return [
            'employee_ids' => ['required', 'array', 'min:1', 'max:'.($isAdmin ? 5 : 2)],
            'employee_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where(fn ($q) => $q->where('is_admin', true))],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            // Duur en reistijd kiest alleen een admin; een klant krijgt altijd de standaardduur.
            'duration_minutes' => ['nullable', 'integer', Rule::in(config('appointments.allowed_durations'))],
            'travel_minutes' => ['nullable', 'integer', Rule::in(config('appointments.allowed_travel_minutes'))],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $days = CarbonImmutable::parse($this->input('from'))->diffInDays(CarbonImmutable::parse($this->input('to')));

            if ($days > config('appointments.max_range_days')) {
                $validator->errors()->add('to', 'Vraag maximaal '.config('appointments.max_range_days').' dagen tegelijk op.');
            }
        }];
    }

    public function durationMinutes(): int
    {
        return $this->user()->isAdmin() && $this->filled('duration_minutes')
            ? (int) $this->input('duration_minutes')
            : (int) config('appointments.client_duration_minutes');
    }

    public function travelMinutes(): int
    {
        return $this->user()->isAdmin() ? (int) $this->input('travel_minutes', 0) : 0;
    }
}
