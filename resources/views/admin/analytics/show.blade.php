<x-app-layout>
    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {{-- Navigatie Terug + Header --}}
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="{{ route('admin.analytics.index') }}" class="inline-flex items-center text-xs font-semibold text-gray-500 hover:text-gray-800 transition-colors mb-2">
                    ← Terug naar Analytics Overzicht
                </a>
                <h1 class="text-2xl font-bold text-gray-900">{{ $clientData['name'] }}</h1>
                <p class="text-sm text-gray-500 font-mono">{{ $clientData['domain'] }} (GA4 ID: {{ $clientData['property_id'] }})</p>
            </div>
            
            <div class="bg-white px-4 py-2 rounded-xl border border-gray-100 shadow-sm text-xs font-semibold text-gray-600">
                📅 Afgelopen 7 dagen
            </div>
        </div>

        {{-- Top KPI Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Actieve Bezoekers</span>
                <p class="text-3xl font-black text-[#011936] mt-2">{{ number_format($clientData['stats']['active_users']) }}</p>
                <span class="text-xs font-bold text-emerald-600 mt-2 block">↑ +14.2%</span>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Paginaweergaven</span>
                <p class="text-3xl font-black text-[#011936] mt-2">{{ number_format($clientData['stats']['pageviews']) }}</p>
                <span class="text-xs font-bold text-emerald-600 mt-2 block">↑ +8.1%</span>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Gem. Tijdsduur</span>
                <p class="text-3xl font-black text-[#011936] mt-2">{{ $clientData['stats']['avg_duration'] }}</p>
                <span class="text-xs font-medium text-gray-400 mt-2 block">Stabiel</span>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Bounce Ratio</span>
                <p class="text-3xl font-black text-[#011936] mt-2">{{ $clientData['stats']['bounce_rate'] }}</p>
                <span class="text-xs font-bold text-emerald-600 mt-2 block">↓ -2.4% (Beter)</span>
            </div>
        </div>

        {{-- Grafieken Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            {{-- Bezoekers Trend (Lijngrafiek) --}}
            <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <h3 class="font-bold text-gray-800 mb-4">Bezoekersverloop</h3>
                <canvas id="trafficChart" class="w-full h-64"></canvas>
            </div>

            {{-- Verkeersbronnen (Donut) --}}
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <h3 class="font-bold text-gray-800 mb-4">Verkeersherkomst</h3>
                <div class="relative h-64 flex items-center justify-center">
                    <canvas id="sourcesChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Top Pagina's Tabel --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-gray-800">Meest Bezochte Pagina's</h3>
            </div>
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/50 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100">
                        <th class="px-6 py-3.5">URL</th>
                        <th class="px-6 py-3.5">Weergaven</th>
                        <th class="px-6 py-3.5">Gem. Tijdsduur op pagina</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @foreach($clientData['top_pages'] as $page)
                        <tr>
                            <td class="px-6 py-4 font-mono text-xs font-bold text-gray-800">{{ $page['url'] }}</td>
                            <td class="px-6 py-4 font-extrabold text-gray-900">{{ number_format($page['views']) }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ $page['duration'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>

    {{-- CHART.JS INTEGRATIE --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // 1. Lijngrafiek: Bezoekersverloop
        const ctxTraffic = document.getElementById('trafficChart').getContext('2d');
        new Chart(ctxTraffic, {
            type: 'line',
            data: {
                labels: @json($clientData['traffic_labels']),
                datasets: [{
                    label: 'Bezoekers',
                    data: @json($clientData['traffic_data']),
                    borderColor: '#011936',
                    backgroundColor: 'rgba(1, 25, 54, 0.05)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 3
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } } }
        });

        // 2. Donutgrafiek: Verkeersbronnen
        const ctxSources = document.getElementById('sourcesChart').getContext('2d');
        new Chart(ctxSources, {
            type: 'doughnut',
            data: {
                labels: @json($clientData['sources']['labels']),
                datasets: [{
                    data: @json($clientData['sources']['data']),
                    backgroundColor: ['#011936', '#3b82f6', '#10b981', '#f59e0b'],
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    </script>
</x-app-layout>