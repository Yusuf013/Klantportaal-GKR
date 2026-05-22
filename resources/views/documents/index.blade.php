<x-app-layout>
    <x-slot name="header">
        <h2 class="font-maven font-bold text-xl text-[#011936] leading-tight">
            {{ __('Mijn Documenten') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-150 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/30 text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                                <th class="p-4 pl-6 w-12">
                                    <input type="checkbox" class="rounded border-gray-300 text-[#011936] focus:ring-[#011936] w-4 h-4">
                                </th>
                                <th class="p-4">Naam</th>
                                <th class="p-4 w-32">Type</th>
                                <th class="p-4 w-48">Status</th>
                                <th class="p-4 w-40">Eigenaar</th>
                                <th class="p-4 w-36">Ontvangen</th>
                                <th class="p-4 pr-6 w-36 text-right">Actie</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            @forelse($documents as $document)
                                <tr onclick="window.location='{{ route('documents.show', $document->id) }}'" class="hover:bg-gray-50/50 transition duration-150 group cursor-pointer">
                                    
                                    <td class="p-4 pl-6" onclick="event.stopPropagation();">
                                        <input type="checkbox" class="rounded border-gray-300 text-[#011936] focus:ring-[#011936] w-4 h-4">
                                    </td>

                                    <td class="p-4">
                                        <div class="flex items-center space-x-4">
                                            @php
                                                $lowerName = Str::lower($document->name);
                                                if (Str::contains($lowerName, ['overeenkomst', 'contract', 'ctr'])) {
                                                    $typeLabel = 'CTR'; $typeClass = 'bg-[#0b1b3d] text-white'; $typeName = 'Contract';
                                                } elseif (Str::contains($lowerName, ['offerte', 'proposal', 'off'])) {
                                                    $typeLabel = 'OFF'; $typeClass = 'bg-[#5c84c1] text-white'; $typeName = 'Offerte';
                                                } elseif (Str::contains($lowerName, ['factuur', 'invoice', 'fac'])) {
                                                    $typeLabel = 'FAC'; $typeClass = 'bg-[#52be80] text-white'; $typeName = 'Factuur';
                                                } else {
                                                    $typeLabel = 'RAP'; $typeClass = 'bg-[#df9f28] text-white'; $typeName = 'Rapport';
                                                }
                                            @endphp
                                            <div class="w-10 h-10 {{ $typeClass }} rounded-xl flex items-center justify-center font-bold text-xs shrink-0 tracking-wider shadow-sm">
                                                {{ $typeLabel }}
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-[#011936] leading-snug line-clamp-1 group-hover:text-indigo-600 transition-colors">
                                                    <a href="{{ route('documents.show', $document->id) }}" class="hover:underline">{{ $document->name }}</a>
                                                </h4>
                                                <p class="text-xs text-gray-400 mt-0.5 font-medium">
                                                    GKR-{{ $typeLabel }}-2026-00{{ $document->id }} · {{ $document->project->name ?? 'Algemeen' }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="p-4 text-gray-600 font-medium">
                                        {{ $typeName }}
                                    </td>

                                    <td class="p-4">
                                        @if($document->status === 'Akkoord' || $document->status === 'Goedgekeurd')
                                            <span class="inline-flex items-center text-xs font-bold px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-600 border border-emerald-100">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-2"></span>
                                                Goedgekeurd
                                            </span>
                                        @elseif($document->status === 'Verzonden' || $document->status === 'Wacht op ondertekening')
                                            <span class="inline-flex items-center text-xs font-bold px-3 py-1.5 rounded-full bg-amber-50 text-amber-600 border border-amber-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-2"></span>
                                                Wacht op actie
                                            </span>
                                        @else
                                            <span class="inline-flex items-center text-xs font-bold px-3 py-1.5 rounded-full bg-blue-50 text-blue-600 border border-blue-100">
                                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500 mr-2"></span>
                                                Feedback gegeven
                                            </span>
                                        @endif
                                    </td>

                                    <td class="p-4 text-gray-600 font-medium">
                                        {{ $document->project->user->name ?? 'Team GKR' }}
                                    </td>

                                    <td class="p-4 text-gray-500 font-medium">
                                        {{ $document->created_at ? $document->created_at->translatedFormat('d M Y') : '12 mei 2026' }}
                                    </td>

                                    <td class="p-4 pr-6 text-right" onclick="event.stopPropagation();">
                                        <div class="flex items-center justify-end space-x-2.5 opacity-60 group-hover:opacity-100 transition-opacity">
                                            <a href="{{ route('documents.show', $document->id) }}" class="p-1.5 text-gray-400 hover:text-[#011936] hover:bg-gray-100 rounded-lg transition" title="Bekijken">
                                                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </a>
                                            
                                            <a href="{{ asset('storage/' . $document->file_path) }}" target="_blank" class="p-1.5 text-gray-400 hover:text-[#011936] hover:bg-gray-100 rounded-lg transition" title="Download">
                                                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                </svg>
                                            </a>

                                            <a href="{{ route('documents.show', $document->id) }}" class="p-1.5 text-gray-400 hover:text-[#011936] hover:bg-gray-100 rounded-lg transition" title="Feedback Schrijven">
                                                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                            </a>

                                            <button class="p-1.5 text-gray-400 hover:text-[#011936] hover:bg-gray-100 rounded-lg transition">
                                                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h.01M12 12h.01M19 12h.01M5 12a1 1 0 110-2 1 1 0 010 2zm7 0a1 1 0 110-2 1 1 0 010 2zm7 0a1 1 0 110-2 1 1 0 010 2z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="p-12 text-center text-sm text-gray-400 italic">
                                        Er zijn momenteel nog geen documenten met u gedeeld.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-5 border-t border-gray-100 bg-gray-50/30 flex items-center justify-between text-xs text-gray-400 font-medium">
                    <div>
                        Toon 1 t/m {{ $documents->count() }} van de {{ $documents->count() }} documenten
                    </div>
                    <div class="flex items-center space-x-1">
                        <button class="p-2 border border-gray-200 rounded-lg bg-white hover:bg-gray-50 transition text-gray-400">&larr;</button>
                        <button class="px-3 py-2 bg-[#0b1b3d] text-white rounded-lg font-bold">1</button>
                        <button class="p-2 border border-gray-200 rounded-lg bg-white hover:bg-gray-50 transition text-gray-400">&rarr;</button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>