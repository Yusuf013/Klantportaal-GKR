{{--
    Herbruikbaar blok met de Meta-cijfers (versie 2).
    Gebruikt op de klantpagina én op de admin-detailpagina.

    Nieuw in versie 2 (feedback Stijn):
    - Klik op een cijfer: grafiek en tabel laten dat cijfer zien.
    - Standaard "Vertoningen" in plaats van besteding (besteding is een vast dagbudget).
    - Tabel per dag/maand onder de grafiek.
    - Korte uitleg in gewone taal bij het gekozen cijfer (voorstel, nog af te stemmen met GKR).
--}}
@props(['report' => null, 'error' => null, 'days' => 28, 'explain' => true])

@php
    $periods = [7 => '7 dagen', 28 => '28 dagen', 90 => '90 dagen', 365 => '12 maanden'];
    $activeDays = $report['days'] ?? $days;
    $nl = fn ($number, $decimals = 0) => number_format($number, $decimals, ',', '.');

    $trendText = function ($trend) use ($nl) {
        if ($trend === null) {
            return 'Geen vergelijking';
        }
        return ($trend > 0 ? '+' : '') . $nl($trend, 1) . '% t.o.v. vorige periode';
    };

    // Meer besteding is niet "goed" of "fout", dus die krijgt een neutrale kleur
    $trendClass = function ($trend, $neutral = false) {
        if ($trend === null || $neutral || $trend == 0) {
            return 'text-gray-400';
        }
        return $trend > 0 ? 'text-emerald-600' : 'text-red-500';
    };

    $defaultMetric = 'impressions';

    if ($report) {
        $t = $report['totals'];
        $isMonthly = $activeDays === 365;
        $periodText = $isMonthly ? 'de afgelopen 12 maanden' : "de afgelopen {$activeDays} dagen";

        // Volgorde = volgorde van de kaarten. Vertoningen eerst, besteding achteraan.
        $metrics = [
            'impressions' => [
                'label'   => 'Vertoningen',
                'value'   => $nl($t['impressions']),
                'unit'    => 'number',
                'neutral' => false,
                'explain' => "Je advertenties zijn in {$periodText} {$nl($t['impressions'])} keer getoond op Facebook en Instagram. Eén persoon kan je advertentie meerdere keren zien.",
            ],
            'clicks' => [
                'label'   => 'Klikken',
                'value'   => $nl($t['clicks']),
                'unit'    => 'number',
                'neutral' => false,
                'explain' => "In {$periodText} is {$nl($t['clicks'])} keer op je advertentie geklikt om meer te bekijken.",
            ],
            'ctr' => [
                'label'   => 'Klikratio (CTR)',
                'value'   => $nl($t['ctr'], 1) . '%',
                'unit'    => 'percent',
                'neutral' => false,
                'explain' => 'Van elke 1.000 keer dat je advertentie werd getoond, werd er ongeveer ' . $nl(round($t['ctr'] * 10)) . ' keer op geklikt. Hoe hoger dit percentage, hoe beter je advertentie mensen aanspreekt.',
            ],
            'conversions' => [
                'label'   => 'Conversies',
                'value'   => $nl($t['conversions']),
                'unit'    => 'number',
                'neutral' => false,
                'explain' => "In {$periodText} deed iemand {$nl($t['conversions'])} keer wat het doel van je advertentie is, bijvoorbeeld een formulier invullen of contact opnemen.",
            ],
            'spend' => [
                'label'   => 'Besteed budget',
                'value'   => '€ ' . $nl($t['spend'], 2),
                'unit'    => 'euro',
                'neutral' => true,
                'explain' => "In {$periodText} is € {$nl($t['spend'], 2)} aan je advertenties uitgegeven. Het budget wordt vooraf vastgesteld, dus dit bedrag schommelt meestal weinig.",
            ],
        ];

        foreach ($metrics as $key => $metric) {
            $metrics[$key]['trend'] = $report['trends'][$key] ?? null;
        }

        // Rijen voor grafiek en tabel, inclusief klikratio per dag/maand
        $rows = array_map(function ($row) use ($isMonthly) {
            $date = $isMonthly
                ? \Carbon\Carbon::parse($row['date'] . '-01')->translatedFormat('M Y')
                : \Carbon\Carbon::parse($row['date'])->format('d-m');

            return [
                'label'       => $date,
                'impressions' => $row['impressions'],
                'clicks'      => $row['clicks'],
                'ctr'         => $row['impressions'] > 0 ? round($row['clicks'] / $row['impressions'] * 100, 2) : 0,
                'conversions' => $row['conversions'],
                'spend'       => $row['spend'],
            ];
        }, $report['series']);

        // Alleen wat de grafiek nodig heeft naar JavaScript sturen
        $chartMeta = [];
        foreach ($metrics as $key => $metric) {
            $chartMeta[$key] = ['label' => $metric['label'], 'unit' => $metric['unit'], 'explain' => $metric['explain']];
        }

        $cellValue = function ($key, $value) use ($nl) {
            return match ($key) {
                'ctr'   => $nl($value, 1) . '%',
                'spend' => '€ ' . $nl($value, 2),
                default => $nl($value),
            };
        };
    }
@endphp

<div class="space-y-6" id="meta-report">
    {{-- Periodekeuze --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="text-xs text-gray-400 font-medium">
            @if($report)
                {{ \Carbon\Carbon::parse($report['start'])->format('d-m-Y') }} t/m {{ \Carbon\Carbon::parse($report['end'])->format('d-m-Y') }}
            @endif
            @if($report['is_fake'] ?? false)
                <span class="ml-2 bg-amber-50 text-amber-700 border border-amber-100 px-2 py-0.5 rounded-md font-bold">Testgegevens</span>
            @endif
        </div>
        <div class="flex gap-1.5">
            @foreach($periods as $value => $label)
                <a href="{{ url()->current() }}?days={{ $value }}"
                   class="px-3 py-1.5 text-xs font-bold rounded-lg border transition {{ $activeDays === $value ? 'bg-[#011936] text-white border-[#011936]' : 'border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    @if($error)
        <div class="p-4 bg-red-50 border border-red-150 text-red-700 rounded-xl text-sm font-medium">
            {{ $error }}
        </div>
    @elseif($report)
        {{-- Kerncijfers: klikbaar --}}
        <div>
            <p class="text-[11px] text-gray-400 mb-2">Klik op een cijfer om het verloop te bekijken.</p>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                @foreach($metrics as $key => $metric)
                    <button type="button"
                            data-metric="{{ $key }}"
                            aria-pressed="{{ $key === $defaultMetric ? 'true' : 'false' }}"
                            class="text-left bg-white rounded-2xl shadow-sm border p-5 transition hover:border-[#011936]/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#011936] {{ $key === $defaultMetric ? 'border-[#011936] ring-1 ring-[#011936]' : 'border-gray-150' }}">
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">{{ $metric['label'] }}</p>
                        <p class="mt-2 text-2xl font-bold text-[#011936]">{{ $metric['value'] }}</p>
                        <p class="mt-1 text-xs font-medium {{ $trendClass($metric['trend'], $metric['neutral']) }}">{{ $trendText($metric['trend']) }}</p>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Grafiek + uitleg --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-150 p-6">
            <h3 class="text-xs font-bold text-[#011936] uppercase tracking-wider">
                <span data-chart-title>{{ $metrics[$defaultMetric]['label'] }}</span> per {{ $isMonthly ? 'maand' : 'dag' }}
            </h3>

            @if($explain)
                <div class="mt-3 flex items-start gap-2 rounded-xl bg-blue-50/60 border border-blue-100 px-4 py-3">
                    <svg class="w-4 h-4 mt-0.5 shrink-0 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <p class="text-xs text-gray-600 leading-relaxed" data-explain>{{ $metrics[$defaultMetric]['explain'] }}</p>
                </div>
            @endif

            <div class="relative h-64 mt-4">
                <canvas id="meta-chart" aria-label="Grafiek van het gekozen cijfer" role="img"></canvas>
            </div>
        </div>

        {{-- Tabel met alle cijfers per dag/maand; de gekozen kolom wordt gemarkeerd --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-150 overflow-hidden">
            <div class="px-6 py-4 bg-gray-50/50 border-b border-gray-100">
                <h3 class="text-xs font-bold text-[#011936] uppercase tracking-wider">Alle cijfers per {{ $isMonthly ? 'maand' : 'dag' }}</h3>
            </div>
            <div class="max-h-96 overflow-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead class="sticky top-0 bg-white">
                        <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-wider border-b border-gray-100">
                            <th class="p-3">{{ $isMonthly ? 'Maand' : 'Datum' }}</th>
                            @foreach($metrics as $key => $metric)
                                <th class="p-3 text-right" data-col="{{ $key }}">{{ $metric['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium text-gray-700">
                        @foreach(array_reverse($rows) as $row)
                            <tr class="hover:bg-gray-50/40 transition">
                                <td class="p-3 text-gray-500">{{ $row['label'] }}</td>
                                @foreach($metrics as $key => $metric)
                                    <td class="p-3 text-right" data-col="{{ $key }}">{{ $cellValue($key, $row[$key]) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @once
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        @endonce
        <script>
            (() => {
                const root = document.getElementById('meta-report');
                const rows = @js($rows);
                const meta = @js($chartMeta);
                const nf = new Intl.NumberFormat('nl-NL');
                const highlight = ['bg-[#011936]/5', 'text-[#011936]', 'font-bold'];
                let activeKey = @js($defaultMetric);

                const format = (unit, value) => {
                    if (unit === 'euro') return '€ ' + nf.format(value);
                    if (unit === 'percent') return nf.format(value) + '%';
                    return nf.format(value);
                };

                const chart = new Chart(document.getElementById('meta-chart'), {
                    type: 'bar',
                    data: {
                        labels: rows.map(r => r.label),
                        datasets: [{ data: [], backgroundColor: '#011936', borderRadius: 4 }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: (ctx) => format(meta[activeKey].unit, ctx.parsed.y) } },
                        },
                        scales: { y: { beginAtZero: true, ticks: { callback: (v) => format(meta[activeKey].unit, v) } } },
                    },
                });

                function select(key) {
                    activeKey = key;
                    chart.data.datasets[0].data = rows.map(r => r[key]);
                    chart.update();

                    // textContent (geen innerHTML): tekst wordt nooit als HTML uitgevoerd
                    root.querySelector('[data-chart-title]').textContent = meta[key].label;
                    const explain = root.querySelector('[data-explain]');
                    if (explain) explain.textContent = meta[key].explain;

                    root.querySelectorAll('[data-metric]').forEach(btn => {
                        const active = btn.dataset.metric === key;
                        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                        btn.classList.toggle('border-[#011936]', active);
                        btn.classList.toggle('ring-1', active);
                        btn.classList.toggle('ring-[#011936]', active);
                        btn.classList.toggle('border-gray-150', !active);
                    });

                    root.querySelectorAll('[data-col]').forEach(cell => {
                        highlight.forEach(c => cell.classList.toggle(c, cell.dataset.col === key));
                    });
                }

                root.querySelectorAll('[data-metric]').forEach(btn => {
                    btn.addEventListener('click', () => select(btn.dataset.metric));
                });

                select(@js($defaultMetric));
            })();
        </script>
    @endif
</div>