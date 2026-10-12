{{-- De link uit de welkomstmail is verlopen, al gebruikt of aangepast --}}
<x-guest-layout>
    <h1 class="font-maven font-bold text-xl text-[#011936] leading-tight">Deze link werkt niet meer</h1>
    <p class="text-sm text-gray-500 mt-2">
        De link is verlopen of al gebruikt. Heb je al een wachtwoord ingesteld? Log dan gewoon in.
        Anders kun je GKR om een nieuwe uitnodiging vragen via <a href="mailto:info@gkr.nl" class="underline text-[#011936]">info@gkr.nl</a>.
    </p>

    <div class="flex items-center justify-between mt-6">
        @if (Route::has('password.request'))
            <a href="{{ route('password.request') }}" class="text-sm text-gray-600 underline hover:text-gray-900">Wachtwoord vergeten?</a>
        @endif
        <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">Inloggen</a>
    </div>
</x-guest-layout>