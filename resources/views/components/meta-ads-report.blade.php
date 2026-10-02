{{-- Herbruikbaar blok met de Meta-cijfers. Gebruikt op de klantpagina én op de admin-detailpagina. --}}
@props(['report' => null, 'error' => null, 'days' => 28])

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

    if ($report) {
        $t = $report['totals'];
        $cards = [
            ['label' => 'Besteed budget',  'value' => '€ ' . $nl($t['spend'], 2),   'trend' => $report['trends']['spend'],       'neutral' => true],
            ['label' => 'Vertoningen',     'value' => $nl($t['impressions']),       'trend' => $report['trends']['impressions'], 'neutral' => false],
            ['label' => 'Klikken',         'value' => $nl($t['clicks']),            'trend' => $report['trends']['clicks'],      'neutral' => false],
            ['label' => 'Klikratio (CTR)', 'value' => $nl($t['ctr'], 1) . '%',      'trend' => $report['trends']['ctr'],         'neutral' => false],
            ['label' => 'Conversies',      'value' => $nl($t['conversions']),       'trend' => $report['trends']['conversions'], 'neutral' => false],
        ];

        // Labels voor de grafiek: per dag "03-09", per maand "sep 2026"
        $chartLabels = array_map(function ($row) {
            return strlen($row['date']) === 7
                ? \Carbon\Carbon::parse($row['date'] . '-01')->translatedFormat('M Y')
                : \Carbon\Carbon::parse($row['date'])->format('d-m');
        }, $report['series']);
        $chartValues = array_column($report['series'], 'spend');
    }
@endphp

<div class="space-y-6">
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
        {{-- Kerncijfers --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
            @foreach($cards as $card)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-150 p-5">
                    <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">{{ $card['label'] }}</p>
                    <p class="mt-2 text-2xl font-bold text-[#011936]">{{ $card['value'] }}</p>
                    <p class="mt-1 text-xs font-medium {{ $trendClass($card['trend'], $card['neutral']) }}">{{ $trendText($card['trend']) }}</p>
                </div>
            @endforeach
        </div>

        {{-- Grafiek besteding --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-150 p-6">
            <h3 class="text-xs font-bold text-[#011936] uppercase tracking-wider mb-4">
                Besteding per {{ $activeDays === 365 ? 'maand' : 'dag' }}
            </h3>
            <div class="relative h-64">
                <canvas id="meta-spend-chart"></canvas>
            </div>
        </div>

        @once
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        @endonce
        <script>
            (() => {
                const labels = @js($chartLabels);
                const values = @js($chartValues);
                new Chart(document.getElementById('meta-spend-chart'), {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{ label: 'Besteding (€)', data: values, backgroundColor: '#011936', borderRadius: 4 }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { callback: (v) => '€ ' + v } } },
                    },
                });
            })();
        </script>
    @endif
</div>