<x-app-layout>
    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {{-- Header --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Website Prestaties (Admin Overzicht)</h1>
                <p class="text-sm text-gray-500 mt-1">Beheer en bekijk de Google Analytics resultaten van alle gekoppelde klanten.</p>
            </div>
        </div>

        {{-- Top KPI Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Totaal Beheerde Klanten</span>
                <p class="text-3xl font-black text-[#011936] mt-2">{{ $totalOverview['total_clients'] }}</p>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Actieve GA4 Koppelingen</span>
                <p class="text-3xl font-black text-emerald-600 mt-2">{{ $totalOverview['active_trackings'] }} / {{ $totalOverview['total_clients'] }}</p>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Totaal Bezoekers Deze Maand</span>
                <p class="text-3xl font-black text-[#011936] mt-2">{{ number_format($totalOverview['total_combined_visitors']) }}</p>
            </div>
        </div>

        {{-- Klanten Tabel --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h3 class="font-bold text-gray-800">Prestaties per Klant</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50/50 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100">
                            <th class="px-6 py-3.5">Klantnaam</th>
                            <th class="px-6 py-3.5">GA4 Status</th>
                            <th class="px-6 py-3.5">Bezoekers (Maand)</th>
                            <th class="px-6 py-3.5">Trend</th>
                            <th class="px-6 py-3.5">Meest Bezocht</th>
                            {{-- Hier staat de actie kolomkop --}}
                            <th class="px-6 py-3.5 text-right">Actie</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        @foreach($clientsStats as $client)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-4 font-bold text-gray-900">{{ $client['name'] }}</td>
                                <td class="px-6 py-4">
                                    @if($client['status'] === 'Actief')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            ● Actief ({{ $client['ga4_property_id'] }})
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                            ○ Niet ingesteld
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 font-extrabold text-gray-800">
                                    {{ number_format($client['visitors_this_month']) }}
                                </td>
                                <td class="px-6 py-4">
                                    @if($client['visitors_trend'] > 0)
                                        <span class="text-emerald-600 font-bold text-xs">↑ +{{ $client['visitors_trend'] }}%</span>
                                    @elseif($client['visitors_trend'] < 0)
                                        <span class="text-rose-600 font-bold text-xs">↓ {{ $client['visitors_trend'] }}%</span>
                                    @else
                                        <span class="text-gray-400 text-xs">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-600 font-mono text-xs">{{ $client['top_page'] }}</td>
                                {{-- Hier staat de werkende link-knop per klant --}}
                                <td class="px-6 py-4 text-right">
                                    @if($client['status'] === 'Actief')
                                        <a href="{{ route('admin.analytics.show', $client['id']) }}" class="px-3 py-1.5 text-xs font-bold text-white bg-[#011936] hover:bg-[#011936]/90 rounded-lg transition-all inline-block">
                                            Bekijk Dashboard
                                        </a>
                                    @else
                                        <span class="text-xs text-gray-400 italic">Geen data beschikbaar</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</x-app-layout>