<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-maven font-bold text-xl text-[#011936] leading-tight">
                {{ __('Advertentieresultaten') }}
            </h2>
            <p class="text-xs text-gray-400 font-medium mt-1">Jouw advertenties op Facebook en Instagram, cijfers tot en met gisteren</p>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(! $account)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-150 p-10 text-center">
                    <p class="text-sm font-bold text-[#011936]">Er zijn nog geen advertentiecijfers beschikbaar</p>
                    <p class="mt-2 text-xs text-gray-400">Zodra GKR jouw advertentieaccount heeft gekoppeld, zie je hier je resultaten.</p>
                </div>
            @else
                <x-meta-ads-report :report="$report" :error="$error" :days="$days" />

                <p class="text-xs text-gray-400 text-center">
                    Vragen over deze cijfers? Bespreek ze in je volgende afspraak met GKR.
                </p>
            @endif
        </div>
    </div>
</x-app-layout>