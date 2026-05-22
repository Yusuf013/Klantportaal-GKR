@props(['projects'])

<div class="bg-white rounded-2xl shadow-sm border border-gray-150 overflow-hidden">
    <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h3 class="font-maven font-bold text-xl text-[#011936]">Alle Projecten</h3>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mt-0.5">Gecureerde lijst van actieve dossiers</p>
        </div>
        
        <div class="flex items-center space-x-2 bg-gray-50 border border-gray-200 px-4 py-2 rounded-2xl text-xs font-bold text-[#011936] w-fit">
            <span class="text-gray-400 uppercase tracking-wider text-[10px]">Filter:</span>
            <span>Status: Alle</span>
            <svg class="w-4 h-4 text-gray-500 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50/50">
                    <th class="p-4 pl-6 text-[11px] font-bold text-gray-400 uppercase tracking-wider">Projectnaam</th>
                    <th class="p-4 text-[11px] font-bold text-gray-400 uppercase tracking-wider">Status</th>
                    <th class="p-4 text-[11px] font-bold text-gray-400 uppercase tracking-wider">Voortgang</th>
                    <th class="p-4 pr-6 text-[11px] font-bold text-gray-400 uppercase tracking-wider text-right">Acties</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($projects as $project)
                    <tr class="hover:bg-gray-50/60 transition duration-150 group">
                        <td class="p-4 pl-6">
                            <div class="flex items-center space-x-4">
                                <div class="w-11 h-11 bg-blue-50/60 text-[#011936] rounded-xl flex items-center justify-center border border-blue-100/50 shrink-0 group-hover:bg-blue-50 transition-colors">
                                    @if(Str::contains(Str::lower($project->name), ['branding', 'brand', 'identity']))
                                        <svg class="w-5 h-5 text-[#011936]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                        </svg>
                                    @elseif(Str::contains(Str::lower($project->name), ['marketing', 'automation']))
                                        <svg class="w-5 h-5 text-[#011936]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 text-[#011936]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                        </svg>
                                    @endif
                                </div>
                                <div>
                                    <h4 class="font-bold text-[#011936] text-sm leading-snug group-hover:text-indigo-600 transition-colors">
                                        {{ $project->name }}
                                    </h4>
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        Klant: {{ $project->user->name ?? 'Onbekende klant' }}
                                    </p>
                                </div>
                            </div>
                        </td>

                        <td class="p-4 vertical-align-middle">
                            @if($project->status === 'Actief' || $project->status === 'Strategie' || $project->status === 'Ontwikkeling')
                                <span class="text-[10px] font-bold px-2.5 py-1 rounded-md bg-blue-50 text-blue-600 border border-blue-100 uppercase tracking-wider">
                                    Actief
                                </span>
                            @else
                                <span class="text-[10px] font-bold px-2.5 py-1 rounded-md bg-gray-100 text-gray-500 border border-gray-200 uppercase tracking-wider">
                                    {{ $project->status }}
                                </span>
                            @endif
                        </td>

                        <td class="p-4">
                            <div class="flex flex-col w-48">
                                <span class="text-xs font-bold text-[#011936] mb-1.5">
                                    {{ $project->progress ?? 0 }}%
                                </span>
                                <div class="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-[#011936] h-1.5 rounded-full transition-all duration-500" 
                                         style="width: {{ $project->progress ?? 0 }}%">
                                    </div>
                                </div>
                            </div>
                        </td>

                        <td class="p-4 pr-6 text-right">
                            <a href="{{ route('admin.projects.show', $project->id) }}" class="inline-flex items-center text-xs font-bold text-gray-400 hover:text-[#011936] transition-colors">
                                Bekijken
                                <svg class="w-3.5 h-3.5 ml-1 transform group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="p-12 text-center text-sm text-gray-400 italic">
                            Er zijn op dit moment geen lopende projecten.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>