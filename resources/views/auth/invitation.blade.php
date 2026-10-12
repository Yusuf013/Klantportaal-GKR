{{-- De klant kiest via de link uit de welkomstmail een eigen wachtwoord --}}
<x-guest-layout>
    <h1 class="font-maven font-bold text-xl text-[#011936] leading-tight">Welkom, {{ $name }}</h1>
    <p class="text-sm text-gray-500 mt-2">Kies een wachtwoord voor je account. Daarna kom je direct in het klantportaal.</p>

    <form method="POST" action="{{ $action }}" class="mt-6">
        @csrf

        <div>
            <x-input-label for="email" value="E-mailadres" />
            {{-- Alleen ter info: het adres is door GKR ingevuld en kan hier niet veranderen --}}
            <x-text-input id="email" class="block mt-1 w-full bg-gray-50" type="email" :value="$email" disabled autocomplete="username" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" value="Nieuw wachtwoord" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autofocus autocomplete="new-password" />
            <p class="text-xs text-gray-400 mt-1">Minimaal 8 tekens.</p>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" value="Herhaal het wachtwoord" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
        </div>

        <div class="flex items-center justify-end mt-6">
            <x-primary-button>Wachtwoord instellen</x-primary-button>
        </div>
    </form>
</x-guest-layout>