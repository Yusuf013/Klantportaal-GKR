<x-app-layout>
    <x-slot name="header">
        <h2 class="font-maven font-bold text-xl text-[#011936] leading-tight">
            {{ __('Mijn Projecten') }}
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
                                <th class="p-4">Projectnaam</th>
                                <th class="p-4 w-40">Type</th>
                                <th class="p-4 w-48">Status</th>
                                <th class="p-4 w-44">Documenten</th>
                                <th class="p-4 w-36">Gestart op</th>
                                <th class="p-4 pr-6 w-36 text-right">Actie</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            @forelse($projects as $project)
                                <tr onclick="window.location='{{ route('projects.show', $project->id) }}'" class="hover:bg-gray-50/50 transition duration-150 group cursor-pointer">
                                    
                                    <td class="p-4 pl-6" onclick="event.stopPropagation();">
                                        <input type="checkbox" class="rounded border-gray-300 text-[#011936] focus:ring-[#011936] w-4 h-4">
                                    </td>

                                    <td class="p-4">
                                        <div class="flex items-center space-x-4">
                                            @php
                                                // Genereer tweekleurige project-avatar op basis van de eerste twee letters
                                                $initials = Str::upper(substr($project->name, 0, 2));
                                            @endphp
                                            <div class="w-10 h-10 bg-[#011936]/5 text-[#011936] border border-[#011936]/10 rounded-xl flex items-center justify-center font-bold text-xs shrink-0 tracking-wider shadow-sm">
                                                {{ $initials }}
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-[#011936] leading-snug line-clamp-1 group-hover:text-indigo-600 transition-colors">
                                                    {{ $project->name }}
                                                </h4>
                                                <p class="text-xs text-gray-400 mt-0.5 font-medium">
                                                    ID: GKR-PRJ-2026-00{{ $project->id }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="p-4 text-gray-600 font-medium">
                                        @if(Str::contains(Str::lower($project->name), ['webshop', 'shop', 'store']))
                                            E-commerce
                                        @else
                                            Maatwerk Webapplicatie
                                        @endif
                                    </td>

                                    <td class="p-4">
                                        @if($project->status === 'Afgerond' || $project->status === 'Live')
                                            <span class="inline-flex items-center text-xs font-bold px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-600 border border-emerald-100">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-2"></span>
                                                Opgeleverd
                                            </span>
                                        @elseif($project->status === 'In Afwachting' || $project->status === 'Pauze')
                                            <span class="inline-flex items-center text-xs font-bold px-3 py-1.5 rounded-full bg-amber-50 text-amber-600 border border-amber-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-2"></span>
                                                Wacht op feedback
                                            </span>
                                        @else
                                            <span class="inline-flex items-center text-xs font-bold px-3 py-1.5 rounded-full bg-blue-50 text-blue-600 border border-blue-100">
                                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500 mr-2"></span>
                                                In Ontwikkeling
                                            </span>
                                        @endif
                                    </td>

                                    <td class="p-4 text-gray-500 font-medium">
                                        <div class="flex items-center space-x-1.5">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            <span>{{ $project->documents ? $project->documents->count() : 0 }} bestanden</span>
                                        </div>
                                    </td>

                                    <td class="p-4 text-gray-500 font-medium">
                                        {{ $project->created_at ? $project->created_at->translatedFormat('d M Y') : 'Nieuw' }}
                                    </td>

                                    <td class="p-4 pr-6 text-right" onclick="event.stopPropagation();">
                                        <div class="flex items-center justify-end space-x-2.5 opacity-60 group-hover:opacity-100 transition-opacity">
                                            <a href="{{ route('dashboard') }}" class="p-1.5 text-gray-400 hover:text-[#011936] hover:bg-gray-100 rounded-lg transition" title="Project Inzien">
                                                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
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
                                        Er zijn momenteel geen lopende projecten voor uw account gevonden.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-5 border-t border-gray-100 bg-gray-50/30 flex items-center justify-between text-xs text-gray-400 font-medium">
                    <div>
                        Toon 1 t/m {{ $projects->count() }} van de {{ $projects->count() }} projecten
                    </div>
                    <div class="flex items-center space-x-1">
                        <button class="p-2 border border-gray-200 rounded-lg bg-white hover:bg-gray-50 transition text-gray-400">&larr;</button>
                        <button class="px-3 py-2 bg-[#011936] text-white rounded-lg font-bold">1</button>
                        <button class="p-2 border border-gray-200 rounded-lg bg-white hover:bg-gray-50 transition text-gray-400">&rarr;</button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>