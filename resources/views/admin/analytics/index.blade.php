<x-app-layout>
    @php
        // Kleine hulpfuncties voor de weergave
        $fmtDuration = fn (float $s) => sprintf('%dm %02ds', intdiv((int) round($s), 60), ((int) round($s)) % 60);
        $fmtPercent = fn (float $r) => number_format($r * 100, 1, ',', '.') . '%';

        $channelNames = [
            'Organic Search' => 'Organisch zoeken',
            'Paid Search' => 'Betaald zoeken',
            'Direct' => 'Direct',
            'Organic Social' => 'Social (organisch)',
            'Paid Social' => 'Social (betaald)',
            'Referral' => 'Verwijzingen',
            'Email' => 'E-mail',
            'Organic Video' => 'Video (organisch)',
            'Display' => 'Display',
            'Unassigned' => 'Niet toegewezen',
        ];

        // Vooraf berekend: @json kan niet goed overweg met komma's in de expressie
        $channelLabels = $data ? array_map(fn ($c) => $channelNames[$c['name']] ?? $c['name'], $data['channels']) : [];
        $channelValues = $data ? array_column($data['channels'], 'sessions') : [];
    @endphp

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Website GKR</h1>
                <p class="text-sm text-gray-500 mt-1">
                    @if($data)
                        {{ \Illuminate\Support\Carbon::parse($data['period']['start'])->locale('nl')->isoFormat('D MMM YYYY') }}
                        t/m {{ \Illuminate\Support\Carbon::parse($data['period']['end'])->locale('nl')->isoFormat('D MMM YYYY') }},
                        vergeleken met de {{ $data['period']['label'] }} daarvoor.
                        Cijfers van vandaag zijn nog niet verwerkt door Google.
                    @else
                        Cijfers uit Google Analytics over de afgelopen {{ $periods[$days] }}.
                    @endif
                </p>
            </div>

            @if($data)
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-500">
                        Opgehaald om {{ \Illuminate\Support\Carbon::parse($data['fetched_at'])->timezone(config('app.timezone'))->format('H:i') }}
                    </span>
                    <form method="POST" action="{{ route('admin.analytics.refresh', ['periode' => $days]) }}">
                        @csrf
                        <button type="submit" class="px-3 py-1.5 text-xs font-bold text-[#011936] bg-white border border-gray-200 hover:bg-gray-50 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-[#011936]/30">
                            Cijfers vernieuwen
                        </button>
                    </form>
                </div>
            @endif
        </div>

        {{-- Periodefilter: gewone links, zodat de gekozen periode in de URL staat --}}
        <nav class="mb-8 inline-flex flex-wrap gap-1 p-1 bg-white rounded-xl border border-gray-100 shadow-sm" aria-label="Periode kiezen">
            @foreach($periods as $optionDays => $optionLabel)
                <a href="{{ route('admin.analytics.index', ['periode' => $optionDays]) }}"
                   @if($optionDays === $days) aria-current="page" @endif
                   class="px-3 py-1.5 text-xs font-bold rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-[#011936]/30 {{ $optionDays === $days ? 'bg-[#011936] text-white' : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50' }}">
                    {{ $optionLabel }}
                </a>
            @endforeach
        </nav>

        @if(session('warning'))
            <div class="mb-6 px-4 py-3 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-900">
                {{ session('warning') }}
            </div>
        @endif

        @if(session('success'))
            <div class="mb-6 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        {{-- Foutmelding of nog niet ingesteld --}}
        @if($error)
            <div class="bg-white p-6 rounded-2xl border border-rose-200 shadow-sm">
                <h3 class="font-bold text-gray-900">Geen cijfers beschikbaar</h3>
                <p class="text-sm text-gray-600 mt-1">{{ $error }}</p>
            </div>
        @else

            {{-- KPI-kaarten --}}
            @php
                $cards = [
                    ['label' => 'Bezoekers', 'kpi' => $data['kpis']['active_users'], 'display' => number_format($data['kpis']['active_users']['value'], 0, ',', '.')],
                    ['label' => 'Sessies', 'kpi' => $data['kpis']['sessions'], 'display' => number_format($data['kpis']['sessions']['value'], 0, ',', '.')],
                    ['label' => 'Conversies', 'kpi' => $data['kpis']['key_events'], 'display' => number_format($data['kpis']['key_events']['value'], 0, ',', '.')],
                    ['label' => 'Gem. sessieduur', 'kpi' => $data['kpis']['avg_session_duration'], 'display' => $fmtDuration($data['kpis']['avg_session_duration']['value'])],
                    ['label' => 'Betrokkenheid', 'kpi' => $data['kpis']['engagement_rate'], 'display' => $fmtPercent($data['kpis']['engagement_rate']['value'])],
                ];
            @endphp

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
                @foreach($cards as $card)
                    <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
                        <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ $card['label'] }}</span>
                        <p class="text-3xl font-black text-[#011936] mt-2">{{ $card['display'] }}</p>

                        @php $change = $card['kpi']['change']; @endphp
                        @if(is_null($change))
                            <span class="text-xs text-gray-400 mt-2 block">Geen vergelijking</span>
                        @elseif($change > 0)
                            <span class="text-xs font-bold text-emerald-600 mt-2 block">↑ +{{ number_format($change, 1, ',', '.') }}%</span>
                        @elseif($change < 0)
                            <span class="text-xs font-bold text-rose-600 mt-2 block">↓ {{ number_format($change, 1, ',', '.') }}%</span>
                        @else
                            <span class="text-xs text-gray-400 mt-2 block">Gelijk</span>
                        @endif
                    </div>
                @endforeach
            </div>

            @if($data['kpis']['key_events']['value'] == 0)
                <div class="mb-8 px-4 py-3 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-900">
                    Er worden nog geen conversies gemeten. Stel in Google Analytics key events in, bijvoorbeeld voor het contactformulier, om aanvragen via de website te kunnen volgen.
                </div>
            @endif

            {{-- Grafieken --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <h3 class="font-bold text-gray-800 mb-4">Bezoekers per {{ $days >= 365 ? 'maand' : 'dag' }}</h3>
                    <div class="relative h-64">
                        <canvas id="trafficChart" aria-label="Lijngrafiek met bezoekers over tijd"></canvas>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <h3 class="font-bold text-gray-800 mb-4">Waar bezoekers vandaan komen</h3>
                    @if(count($data['channels']))
                        <div class="relative h-64">
                            <canvas id="channelsChart" aria-label="Staafdiagram met sessies per kanaal"></canvas>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">Geen sessies in deze periode.</p>
                    @endif
                </div>
            </div>

            {{-- Pagina's --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-bold text-gray-800">Meest bekeken pagina's</h3>
                    <p class="text-xs text-gray-500 mt-1">Een lage betrokkenheid kan wijzen op een pagina waar bezoekers snel afhaken.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50/50 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100">
                                <th class="px-6 py-3.5">Pagina</th>
                                <th class="px-6 py-3.5">Weergaven</th>
                                <th class="px-6 py-3.5">Betrokkenheid</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            @forelse($data['top_pages'] as $page)
                                <tr>
                                    <td class="px-6 py-4 font-mono text-xs font-bold text-gray-800 break-all">{{ $page['path'] }}</td>
                                    <td class="px-6 py-4 font-extrabold text-gray-900">{{ number_format($page['views'], 0, ',', '.') }}</td>
                                    <td class="px-6 py-4">
                                        {{-- Drempel van 40% is een eerste inschatting; bespreek met GKR --}}
                                        <span class="{{ $page['engagement_rate'] < 0.4 ? 'text-rose-600 font-bold' : 'text-gray-600' }}">
                                            {{ $fmtPercent($page['engagement_rate']) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-6 text-center text-gray-500">Geen paginaweergaven in deze periode.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    @if($data)
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            new Chart(document.getElementById('trafficChart'), {
                type: 'line',
                data: {
                    labels: @json($data['daily']['labels']),
                    datasets: [{
                        label: 'Bezoekers',
                        data: @json($data['daily']['values']),
                        borderColor: '#011936',
                        backgroundColor: 'rgba(1, 25, 54, 0.05)',
                        fill: true,
                        tension: 0.3,
                        borderWidth: 3,
                        pointRadius: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });

            @if(count($data['channels']))
                new Chart(document.getElementById('channelsChart'), {
                    type: 'bar',
                    data: {
                        labels: @json($channelLabels),
                        datasets: [{
                            label: 'Sessies',
                            data: @json($channelValues),
                            backgroundColor: '#011936',
                            borderRadius: 6
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });
            @endif
        </script>
    @endif
</x-app-layout>