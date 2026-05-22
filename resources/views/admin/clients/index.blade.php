<x-app-layout>
    <x-slot name="header">
        <h2 class="font-maven font-bold text-xl text-[#011936] leading-tight">
            {{ __('Klanten Beheer') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-150 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/30 text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                                <th class="p-4 pl-6">Klantnaam</th>
                                <th class="p-4">E-mailadres</th>
                                <th class="p-4 w-48">Actieve Projecten</th>
                                <th class="p-4 w-44">Klant sinds</th>
                                <th class="p-4 pr-6 w-36 text-right">Actie</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            @forelse($clients as $client)
                                <tr class="hover:bg-gray-50/50 transition duration-150 group">
                                    
                                    <td class="p-4 pl-6">
                                        <div class="flex items-center space-x-4">
                                            @php
                                                $initials = Str::upper(substr($client->name, 0, 2));
                                            @endphp
                                            <div class="w-10 h-10 bg-indigo-50 text-indigo-600 border border-indigo-100 rounded-xl flex items-center justify-center font-bold text-xs shrink-0 tracking-wider shadow-sm">
                                                {{ $initials }}
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-[#011936] leading-snug group-hover:text-indigo-600 transition-colors">
                                                    {{ $client->name }}
                                                </h4>
                                                <p class="text-xs text-gray-400 mt-0.5 font-medium">
                                                    Klant-ID: GKR-USR-00{{ $client->id }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="p-4 text-gray-600 font-medium">
                                        {{ $client->email }}
                                    </td>

                                    <td class="p-4">
                                        <span class="inline-flex items-center text-xs font-bold px-2.5 py-1 rounded-md bg-blue-50 text-blue-600 border border-blue-100">
                                        {{ $client->projects->count() }} {{ trans_choice('project|projecten', $client->projects->count(), [], 'nl') }}
                                        </span>
                                    </td>

                                    <td class="p-4 text-gray-500 font-medium">
                                        {{ $client->created_at ? $client->created_at->translatedFormat('d M Y') : 'Onbekend' }}
                                    </td>

                                    <td class="p-4 pr-6 text-right">
                                        <div class="flex items-center justify-end space-x-2">
                                            <a href="{{ route('admin.projects.index') }}" class="inline-flex items-center text-xs font-bold text-gray-400 hover:text-[#011936] transition-colors">
                                                Projecten inzien
                                                <svg class="w-3.5 h-3.5 ml-1 transform group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path>
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-12 text-center text-sm text-gray-400 italic">
                                        Er zijn momenteel geen geregistreerde klanten in het systeem gevonden.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>