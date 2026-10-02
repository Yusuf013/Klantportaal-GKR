<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('admin.meta-ads.index', ['days' => $days]) }}" class="text-xs text-gray-400 font-medium hover:text-[#011936]">&larr; Terug naar alle klanten</a>
            <h2 class="font-maven font-bold text-xl text-[#011936] leading-tight mt-1">
                Meta Ads – {{ $client->name }}
            </h2>
            <p class="text-xs text-gray-400 font-medium mt-1">Dit is precies wat de klant zelf ziet</p>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-meta-ads-report :report="$report" :error="$error" :days="$days" />
        </div>
    </div>
</x-app-layout>