<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-maven font-bold text-xl text-[#011936] leading-tight">
                {{ __('Meta Ads – alle klanten') }}
            </h2>
            <p class="text-xs text-gray-400 font-medium mt-1">Advertentieresultaten van alle gekoppelde klanten, tot en met gisteren</p>
        </div>
    </x-slot>

    @php
        $periods = [7 => '7 dagen', 28 => '28 dagen', 90 => '90 dagen', 365 => '12 maanden'];
        $nl = fn ($number, $decimals = 0) => number_format($number, $decimals, ',', '.');
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Periodekeuze --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    @if($isFake)
                        <span class="text-xs bg-amber-50 text-amber-700 border border-amber-100 px-2 py-0.5 rounded-md font-bold">Testgegevens</span>
                    @endif
                </div>
                <div class="flex gap-1.5">
                    @foreach($periods as $value => $label)
                        <a href="{{ route('admin.meta-ads.index', ['days' => $value]) }}"
                           class="px-3 py-1.5 text-xs font-bold rounded-lg border transition {{ $days === $value ? 'bg-[#011936] text-white border-[#011936]' : 'border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Totalen --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-150 p-5">
                    <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Totaal besteed</p>
                    <p class="mt-2 text-2xl font-bold text-[#011936]">€ {{ $nl($totals['spend'], 2) }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-150 p-5">
                    <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Gekoppelde klanten</p>
                    <p class="mt-2 text-2xl font-bold text-[#011936]">{{ count($rows) }} van {{ count($rows) + $unlinked }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-150 p-5">
                    <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Conversies totaal</p>
                    <p class="mt-2 text-2xl font-bold text-[#011936]">{{ $nl($totals['conversions']) }}</p>
                </div>
            </div>

            {{-- Tabel per klant --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-150 overflow-hidden">
                <div class="p-4 overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-wider border-b border-gray-100 bg-gray-50/30">
                                <th class="p-3">Klant</th>
                                <th class="p-3 text-right">Besteed</th>
                                <th class="p-3 text-right">Vertoningen</th>
                                <th class="p-3 text-right">Klikken</th>
                                <th class="p-3 text-right">CTR</th>
                                <th class="p-3 text-right">Conversies</th>
                                <th class="p-3 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-medium text-gray-700">
                            @forelse($rows as $row)
                                <tr class="hover:bg-gray-50/40 transition">
                                    <td class="p-3 font-semibold text-gray-800">{{ $row['client']->name }}</td>
                                    @if($row['report'])
                                        @php $t = $row['report']['totals']; @endphp
                                        <td class="p-3 text-right">€ {{ $nl($t['spend'], 2) }}</td>
                                        <td class="p-3 text-right">{{ $nl($t['impressions']) }}</td>
                                        <td class="p-3 text-right">{{ $nl($t['clicks']) }}</td>
                                        <td class="p-3 text-right">{{ $nl($t['ctr'], 1) }}%</td>
                                        <td class="p-3 text-right">{{ $nl($t['conversions']) }}</td>
                                    @else
                                        <td colspan="5" class="p-3 text-right text-xs text-red-500">Cijfers konden niet worden geladen</td>
                                    @endif
                                    <td class="p-3 text-right">
                                        <a href="{{ route('admin.meta-ads.show', ['user' => $row['client'], 'days' => $days]) }}" class="text-xs font-bold text-[#011936] underline">Bekijk</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-6 text-center text-xs text-gray-400 italic">Er zijn nog geen klanten gekoppeld aan een Meta-advertentieaccount.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($unlinked > 0)
                    <div class="px-6 py-3 border-t border-gray-100 text-xs text-gray-400 flex justify-between">
                        <span>{{ $unlinked }} {{ $unlinked === 1 ? 'klant' : 'klanten' }} zonder koppeling</span>
                        <a href="{{ route('admin.users.index') }}" class="underline">Koppelen in gebruikersbeheer</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>