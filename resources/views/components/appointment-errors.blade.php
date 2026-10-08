@props(['sessionError' => true])

{{--
    Fouten bij afspraken, gelijk voor klant- en adminpagina:
    - session('error'): een weigering uit de afsprakenlogica (bezet moment, buiten werktijd, verkeerde status);
    - $errors: invoerfouten uit de validatie (AppointmentRules), met dezelfde meldingen als de app.
    Met :session-error="false" alleen de invoerfouten, bijvoorbeeld bovenin een formulier.
--}}
@php
    $messages = collect($sessionError && session('error') ? [session('error')] : [])->merge($errors->all())->unique();
@endphp

@if($messages->isNotEmpty())
    <div role="alert" {{ $attributes->merge(['class' => 'p-4 bg-red-50 border border-red-150 text-red-700 rounded-xl text-sm font-medium shadow-sm']) }}>
        <p class="font-bold">Dat lukte niet</p>
        @foreach($messages as $message)
            <p class="mt-0.5">{{ $message }}</p>
        @endforeach
    </div>
@endif
