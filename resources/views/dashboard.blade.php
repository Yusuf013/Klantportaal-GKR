{{--
    Het dashboard van de klant. Beantwoordt één vraag: "wat moet ik nu weten of doen?"
    Alle gegevens komen uit DashboardController en zijn van de ingelogde klant zelf.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-maven font-bold text-xl text-[#011936] leading-tight">
            Dashboard
        </h2>
        <p class="text-xs text-gray-400 font-medium mt-1">Wat er speelt in jouw samenwerking met GKR</p>
    </x-slot>

    @php
        $nl = fn ($number, $decimals = 0) => number_format($number, $decimals, ',', '.');
        // Conversies: hele getallen zonder komma, Google kan kommagetallen geven
        $nlConv = fn ($number) => $nl($number, floor($number) == $number ? 0 : 1);

        $trendText = function ($trend) use ($nl) {
            if ($trend === null) {
                return 'geen vergelijking';
            }

            return ($trend > 0 ? '+' : '') . $nl($trend, 1) . '%';
        };

        // Meer kosten is niet "goed" of "fout", dus die krijgen een neutrale kleur
        $trendClass = function ($trend, $neutral = false) {
            if ($trend === null || $neutral || $trend == 0) {
                return 'text-gray-400';
            }

            return $trend > 0 ? 'text-emerald-600' : 'text-red-500';
        };

        // Het aantal regels in de lijst "wat er voor je klaarstaat"
        $openCount = count($actions);
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- 1. Begroeting en wat er op de klant wacht. Het enige donkere blok van de pagina. --}}
            <section class="bg-[#011936] text-white rounded-2xl p-8" data-dashboard="actions" data-open="{{ $openCount }}">
                <h1 class="font-maven font-bold text-2xl leading-tight">{{ $greeting }}</h1>

                @if(count($actions) > 0)
                    <p class="text-sm mt-2" style="color: #94b8ff;">
                        {{ $openCount === 1 ? 'Er staat 1 ding voor je klaar.' : 'Er staan ' . $openCount . ' dingen voor je klaar.' }}
                    </p>

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
                    <p class="text-sm mt-2" style="color: #94b8ff;">Je bent helemaal bij. Er staat niets voor je open.</p>

                    <div class="mt-6 flex flex-col sm:flex-row gap-3">
                        <a href="{{ route('client.appointments.index') }}"
                           class="inline-flex items-center justify-center bg-white text-[#011936] text-xs font-bold px-4 py-2.5 rounded-lg hover:bg-gray-100 transition w-fit">
                            Afspraak maken
                        </a>
                        <a href="{{ route('chat.index') }}"
                           class="inline-flex items-center justify-center bg-white/10 text-white text-xs font-bold px-4 py-2.5 rounded-lg transition w-fit">
                            Bericht sturen
                        </a>
                    </div>
                @endif
            </section>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- 2. Projecten: voortgang en deadline --}}
                <section class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" data-dashboard="projects">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between gap-4">
                        <h3 class="font-maven font-bold text-lg text-[#011936]">Jouw projecten</h3>
                        @if(count($projects) > 0)
                            <a href="{{ route('client.projects.index') }}" class="text-xs font-bold text-gray-400 hover:text-[#011936] transition">Alle projecten</a>
                        @endif
                    </div>

                    @forelse($projects as $project)
                        <a href="{{ $project['url'] }}" class="block p-6 border-b border-gray-100 hover:bg-gray-50 transition group">
                            <div class="flex items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <h4 class="font-bold text-sm text-[#011936] truncate group-hover:text-indigo-600 transition-colors">{{ $project['name'] }}</h4>
                                    <p class="text-xs text-gray-400 font-medium mt-1">{{ $project['deadline'] }}</p>
                                </div>

                                @if($project['done'])
                                    <span class="text-[10px] font-bold px-2.5 py-1 rounded-md bg-emerald-50 text-emerald-600 border border-emerald-100 uppercase tracking-wider shrink-0">{{ $project['status'] }}</span>
                                @else
                                    <span class="text-[10px] font-bold px-2.5 py-1 rounded-md bg-blue-50 text-blue-600 border border-blue-100 uppercase tracking-wider shrink-0">{{ $project['status'] }}</span>
                                @endif
                            </div>

                            <div class="flex items-center gap-3 mt-4">
                                <div class="flex-1 bg-gray-100 h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-[#011936] h-1.5 rounded-full" style="width: {{ $project['progress'] }}%"></div>
                                </div>
                                <span class="text-xs font-bold text-[#011936] shrink-0" style="min-width: 36px; text-align: right;">{{ $project['progress'] }}%</span>
                            </div>
                        </a>
                    @empty
                        <div class="p-12 text-center">
                            <p class="text-sm font-bold text-[#011936]">Er zijn nog geen projecten aan je account gekoppeld</p>
                            <p class="mt-2 text-xs text-gray-400">Zodra GKR een project voor je aanmaakt, volg je hier de voortgang.</p>
                        </div>
                    @endforelse
                </section>

                {{-- 3. De eerstvolgende afspraak --}}
                <section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col" data-dashboard="appointment">
                    <h3 class="font-maven font-bold text-lg text-[#011936]">Volgende afspraak</h3>

                    @if($nextAppointment)
                        <div class="flex items-start gap-4 mt-6">
                            {{-- Datumblokje: dag groot, maand klein --}}
                            <div class="bg-[#011936] text-white rounded-xl text-center shrink-0" style="width: 56px; padding: 8px 0;">
                                <div class="font-maven font-bold text-xl leading-tight">{{ $nextAppointment['day'] }}</div>
                                <div class="text-[10px] font-bold uppercase tracking-wider">{{ $nextAppointment['month'] }}</div>
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold text-sm text-[#011936] leading-snug">{{ $nextAppointment['title'] }}</p>
                                <p class="text-xs text-gray-500 font-medium mt-1">{{ $nextAppointment['date'] }}, {{ $nextAppointment['time'] }}</p>
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ $nextAppointment['type'] }}@if($nextAppointment['with'] !== ''), met {{ $nextAppointment['with'] }}@endif
                                </p>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-gray-500 mt-4">Er staat nog geen afspraak gepland.</p>
                    @endif

                    @if($waitingForGkr > 0)
                        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 mt-4" data-waiting-for-gkr="{{ $waitingForGkr }}">
                            {{ $waitingForGkr === 1 ? '1 aanvraag wacht op bevestiging van GKR.' : $waitingForGkr . ' aanvragen wachten op bevestiging van GKR.' }}
                        </p>
                    @endif

                    <div class="mt-6 pt-4 border-t border-gray-100" style="margin-top: auto;">
                        <a href="{{ route('client.appointments.index') }}"
                           class="inline-block w-full text-center bg-[#011936] text-white text-xs font-bold py-2.5 rounded-lg hover:bg-black transition">
                            {{ $nextAppointment ? 'Naar mijn agenda' : 'Afspraak maken' }}
                        </a>
                    </div>
                </section>
            </div>

            {{-- 4. Advertenties in het kort. Alleen als er een account is gekoppeld. --}}
            @if(count($ads) > 0)
                <section class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" data-dashboard="ads">
                    <div class="p-6 border-b border-gray-100">
                        <h3 class="font-maven font-bold text-lg text-[#011936]">Advertenties in het kort</h3>
                        <p class="text-xs text-gray-400 font-medium mt-1">De afgelopen {{ $adsDays }} dagen, vergeleken met de {{ $adsDays }} dagen daarvoor</p>
                    </div>

                    @foreach($ads as $summary)
                        <div class="p-6 border-b border-gray-100" data-ads="{{ $summary['label'] }}">
                            <div class="flex items-center justify-between gap-4">
                                <p class="font-bold text-sm text-[#011936]">
                                    {{ $summary['label'] }}
                                    @if($summary['report']['is_fake'] ?? false)
                                        <span class="ml-2 bg-amber-50 text-amber-700 border border-amber-100 px-2 py-0.5 rounded-md font-bold text-xs">Testgegevens</span>
                                    @endif
                                </p>
                                <a href="{{ $summary['url'] }}" class="text-xs font-bold text-gray-400 hover:text-[#011936] transition shrink-0">Alle cijfers</a>
                            </div>

                            @if($summary['report'])
                                @php
                                    $totals = $summary['report']['totals'];
                                    $trends = $summary['report']['trends'];
                                @endphp
                                <dl class="grid grid-cols-3 gap-4 mt-4">
                                    <div>
                                        <dt class="text-xs text-gray-400 font-medium">Klikken</dt>
                                        <dd class="font-maven font-bold text-xl text-[#011936] mt-1">{{ $nl($totals['clicks']) }}</dd>
                                        <dd class="text-xs font-bold {{ $trendClass($trends['clicks'] ?? null) }}">{{ $trendText($trends['clicks'] ?? null) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-gray-400 font-medium">Conversies</dt>
                                        <dd class="font-maven font-bold text-xl text-[#011936] mt-1">{{ $nlConv($totals['conversions']) }}</dd>
                                        <dd class="text-xs font-bold {{ $trendClass($trends['conversions'] ?? null) }}">{{ $trendText($trends['conversions'] ?? null) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-gray-400 font-medium">Kosten</dt>
                                        <dd class="font-maven font-bold text-xl text-[#011936] mt-1">€ {{ $nl($totals['spend'], 2) }}</dd>
                                        <dd class="text-xs font-bold {{ $trendClass($trends['spend'] ?? null, true) }}">{{ $trendText($trends['spend'] ?? null) }}</dd>
                                    </div>
                                </dl>
                            @else
                                {{-- Er ligt nog geen rapport klaar. Het dashboard wacht daar niet op. --}}
                                <p class="text-sm text-gray-500 mt-4">De cijfers worden bijgewerkt. Bekijk ze via "Alle cijfers".</p>
                            @endif
                        </div>
                    @endforeach
                </section>
            @endif

        </div>
    </div>
</x-app-layout>