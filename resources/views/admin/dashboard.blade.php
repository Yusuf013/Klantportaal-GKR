{{--
    Het dashboard van GKR. Beantwoordt één vraag: "wat vraagt vandaag aandacht?"
    Alle gegevens komen uit Admin\DashboardController.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-maven font-bold text-xl text-[#011936] leading-tight">
            Dashboard
        </h2>
        <p class="text-xs text-gray-400 font-medium mt-1">Wat vandaag aandacht vraagt bij GKR</p>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Melding na een actie, bijvoorbeeld na het aanmaken van een project --}}
            @if(session('success'))
                <div class="bg-emerald-50 border border-emerald-100 text-emerald-700 rounded-xl px-4 py-3 text-sm font-medium" role="status">
                    {{ session('success') }}
                </div>
            @endif

            {{-- 1. Begroeting en wat er op GKR wacht. Het enige donkere blok van de pagina. --}}
            <section class="bg-[#011936] text-white rounded-2xl p-8" data-dashboard="actions" data-open="{{ count($actions) }}">
                <h1 class="font-maven font-bold text-2xl leading-tight">{{ $greeting }}</h1>
                <p class="text-sm mt-2" style="color: #94b8ff;">{{ $summary }}</p>

                @if(count($actions) > 0)
                    <ul class="mt-6 space-y-3">
                        @foreach($actions as $action)
                            <li data-action="{{ $action['key'] }}" data-count="{{ $action['count'] }}"
                                class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white/10 rounded-xl px-5 py-4">
                                <p class="text-sm font-medium leading-snug">{{ $action['text'] }}</p>
                                <a href="{{ $action['url'] }}"
                                   class="inline-flex items-center justify-center bg-white text-[#011936] text-xs font-bold px-4 py-2.5 rounded-lg hover:bg-gray-100 transition shrink-0 w-fit">
                                    {{ $action['button'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm font-medium mt-6 bg-white/10 rounded-xl px-5 py-4">Er wacht op dit moment niets op GKR: geen aanvragen, geen ongelezen berichten en geen reacties op documenten.</p>
                @endif
            </section>

            {{-- 2. Koppelingen die niet werken. Alleen zichtbaar als er iets aan de hand is. --}}
            @if(count($connections) > 0)
                <section class="bg-amber-50 border border-amber-200 rounded-2xl p-6" data-dashboard="connections">
                    <h3 class="font-maven font-bold text-lg text-[#011936]">Koppelingen die aandacht vragen</h3>

                    <ul class="mt-4 space-y-3">
                        @foreach($connections as $problem)
                            <li data-connection="{{ $problem['key'] }}" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <p class="text-sm text-amber-700 font-medium leading-snug">{{ $problem['text'] }}</p>
                                <a href="{{ $problem['url'] }}" class="text-xs font-bold text-[#011936] hover:underline shrink-0">{{ $problem['button'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- 3. De afspraken van vandaag --}}
                <section class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" data-dashboard="today">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between gap-4">
                        <h3 class="font-maven font-bold text-lg text-[#011936]">Afspraken vandaag</h3>
                        <a href="{{ route('admin.appointments.index') }}" class="text-xs font-bold text-gray-400 hover:text-[#011936] transition">Naar de agenda</a>
                    </div>

                    @forelse($today as $appointment)
                        <div class="p-6 border-b border-gray-100 flex items-start gap-4" data-today-appointment>
                            <div class="font-maven font-bold text-sm text-[#011936] shrink-0" style="min-width: 96px;">{{ $appointment['time'] }}</div>
                            <div class="min-w-0">
                                <p class="font-bold text-sm text-[#011936] leading-snug">{{ $appointment['title'] }}</p>
                                <p class="text-xs text-gray-500 font-medium mt-1">{{ $appointment['client'] }}, {{ $appointment['type'] }}</p>
                                @if($appointment['with'] !== '')
                                    <p class="text-xs text-gray-400 mt-1">Met {{ $appointment['with'] }}</p>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-6">
                            <p class="text-sm text-gray-500">Vandaag staan er geen afspraken.</p>

                            @if($upcoming)
                                <p class="text-xs text-gray-400 mt-2" data-upcoming-appointment>
                                    Eerstvolgende: {{ $upcoming['date'] }} om {{ $upcoming['time'] }}, "{{ $upcoming['title'] }}" met {{ $upcoming['client'] }}.
                                </p>
                            @endif
                        </div>
                    @endforelse
                </section>

                {{-- 4. Deadlines die eraan komen of al voorbij zijn --}}
                <section class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" data-dashboard="deadlines">
                    <div class="p-6 border-b border-gray-100">
                        <h3 class="font-maven font-bold text-lg text-[#011936]">Deadlines</h3>
                        <p class="text-xs text-gray-400 font-medium mt-1">Lopende projecten die binnen 14 dagen aflopen of al te laat zijn</p>
                    </div>

                    @forelse($deadlines as $deadline)
                        <a href="{{ $deadline['url'] }}" class="block p-6 border-b border-gray-100 hover:bg-gray-50 transition group" data-deadline="{{ $deadline['daysLeft'] }}">
                            <div class="flex items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="font-bold text-sm text-[#011936] truncate group-hover:text-indigo-600 transition-colors">{{ $deadline['name'] }}</p>
                                    <p class="text-xs text-gray-400 font-medium mt-1">{{ $deadline['client'] }}, {{ $deadline['date'] }}</p>
                                </div>
                                <span class="text-xs font-bold shrink-0 {{ $deadline['late'] ? 'text-red-500' : 'text-[#011936]' }}">{{ $deadline['label'] }}</span>
                            </div>
                        </a>
                    @empty
                        <div class="p-6">
                            <p class="text-sm text-gray-500">Geen deadlines in de komende 14 dagen.</p>
                        </div>
                    @endforelse
                </section>
            </div>

            {{-- 5. Alle projecten, met een filter op status --}}
            <x-projects-overview :projects="$projects" />

        </div>
    </div>
</x-app-layout>