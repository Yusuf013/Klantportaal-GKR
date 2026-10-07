<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('admin.google-ads.index', ['days' => $days]) }}" class="text-xs text-gray-400 font-medium hover:text-[#011936]">&larr; Terug naar alle klanten</a>
            <h2 class="font-maven font-bold text-xl text-[#011936] leading-tight mt-1">
                Google Ads – {{ $client->name }}
            </h2>
            <p class="text-xs text-gray-400 font-medium mt-1">Wat de klant zelf ziet, plus de cijfers per campagne (die ziet de klant niet)</p>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-ads-report platform="google_ads" :report="$report" :error="$error" :days="$days" :campaigns="true" />
        </div>
    </div>
</x-app-layout>