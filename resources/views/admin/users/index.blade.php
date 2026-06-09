<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-maven font-bold text-xl text-[#011936] leading-tight">
                {{ __('Gebruikersbeheer & Instellingen') }}
            </h2>
            <p class="text-xs text-gray-400 font-medium mt-1">Beheer de rollen van GKR medewerkers en portaalklanten</p>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-10">
            
            @if(session('success'))
                <div class="p-4 bg-emerald-50 border border-emerald-150 text-emerald-700 rounded-xl text-sm font-medium flex items-center shadow-sm">
                    <svg class="w-5 h-5 mr-2 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="p-4 bg-red-50 border border-red-150 text-red-700 rounded-xl text-sm font-medium flex items-center shadow-sm">
                    <svg class="w-5 h-5 mr-2 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    {{ session('error') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                
                <div class="bg-white rounded-2xl shadow-sm border border-gray-150 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50/50 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-xs font-bold text-[#011936] uppercase tracking-wider flex items-center">
                            <span class="w-2 h-2 bg-[#011936] rounded-full mr-2"></span>
                            GKR Team (Admins)
                        </h3>
                        <span class="text-xs bg-gray-100 text-gray-600 px-2.5 py-0.5 rounded-md font-bold">{{ $admins->count() }}</span>
                    </div>
                    <div class="p-4 overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-wider border-b border-gray-100 bg-gray-50/30">
                                    <th class="p-3">Naam</th>
                                    <th class="p-3">E-mailadres</th>
                                    <th class="p-3 text-right">Actie</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 font-medium text-gray-700">
                                @foreach($admins as $admin)
                                    <tr class="hover:bg-gray-50/40 transition">
                                        <td class="p-3 font-bold text-[#011936]">{{ $admin->name }}</td>
                                        <td class="p-3 text-gray-500 text-xs">{{ $admin->email }}</td>
                                        <td class="p-3 text-right">
                                            @if($admin->id !== auth()->id())
                                                <button type="button" onclick="openConfirmAdminModal({{ $admin->id }}, '{{ $admin->name }}', 'intrekken')" class="text-xs font-bold text-red-500 hover:text-red-700 transition underline">
                                                    Intrekken
                                                </button>
                                            @else
                                                <span class="text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-md border border-emerald-100">Jijzelf</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-150 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50/50 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-xs font-bold text-[#011936] uppercase tracking-wider flex items-center">
                            <span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
                            Klanten
                        </h3>
                        <span class="text-xs bg-blue-50 text-blue-700 px-2.5 py-0.5 rounded-md font-bold">{{ $clients->count() }}</span>
                    </div>
                    <div class="p-4 overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-wider border-b border-gray-100 bg-gray-50/30">
                                    <th class="p-3">Naam</th>
                                    <th class="p-3">E-mailadres</th>
                                    <th class="p-3 text-right">Machtiging</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 font-medium text-gray-700">
                                @forelse($clients as $client)
                                    <tr class="hover:bg-gray-50/40 transition">
                                        <td class="p-3 font-semibold text-gray-800">{{ $client->name }}</td>
                                        <td class="p-3 text-gray-400 text-xs">{{ $client->email }}</td>
                                        <td class="p-3 text-right">
                                            <button type="button" onclick="openConfirmAdminModal({{ $client->id }}, '{{ $client->name }}', 'toewijzen')" class="inline-flex items-center px-2.5 py-1 border border-gray-200 text-[11px] font-bold rounded-lg text-gray-600 hover:bg-[#011936] hover:text-white hover:border-[#011936] transition shadow-2xs">
                                                Maak Admin +
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="p-6 text-center text-xs text-gray-400 italic">Er zijn nog geen klanten geregistreerd in het portaal.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div id="confirmAdminModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true">
        <div onclick="closeConfirmAdminModal()" class="fixed inset-0 bg-[#011936]/30 backdrop-blur-sm transition-opacity"></div>
        
        <div class="relative bg-white rounded-3xl shadow-2xl max-w-md w-full p-8 text-center transform transition-all border border-gray-100">
            <div class="w-20 h-20 bg-[#10B981] rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg shadow-emerald-500/20">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>

            <h3 class="text-xl font-bold text-[#011936] uppercase tracking-wide mb-2" id="adminModalTitle">RECHTEN AANGEPAST</h3>
            <p class="text-sm text-gray-500 mb-8 leading-relaxed font-medium" id="adminModalDescription">
                Weet je zeker dat je de rechten van deze gebruiker wilt aanpassen? De wijziging wordt direct verwerkt in de database.
            </p>

            <div class="flex items-center space-x-3">
                <button type="button" onclick="closeConfirmAdminModal()" class="flex-1 py-3 border border-gray-200 text-gray-500 text-xs font-bold rounded-xl hover:bg-gray-50 transition">
                    Annuleren
                </button>
                
                <form id="adminModalForm" method="POST" class="flex-1">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="w-full py-3 bg-[#10B981] hover:bg-[#059669] text-white text-xs font-bold rounded-xl transition shadow-md">
                        JA, IK GA AKKOORD
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openConfirmAdminModal(userId, userName, actionType) {
    const modal = document.getElementById('confirmAdminModal');
    const title = document.getElementById('adminModalTitle');
    const desc = document.getElementById('adminModalDescription');
    const form = document.getElementById('adminModalForm');

    if (actionType === 'toewijzen') {
        title.innerText = "ADMIN RECHTEN TOEWIJZEN";
        desc.innerText = "Weet je zeker dat je " + userName + " beheerder wilt maken? Dit account krijgt direct toegang tot alle admin-omgevingen.";
    } else {
        title.innerText = "ADMIN RECHTEN INTREKKEN";
        desc.innerText = "Weet je zeker dat je de adminrechten van " + userName + " wilt intrekken? Dit account wordt direct teruggezet naar een klant-account.";
    }

    // FIX: Zorg dat de URL exact matcht met de /admin prefix en de {user} parameter
    form.action = "/admin/gebruikers/" + userId + "/toggle-admin";

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.classList.add('overflow-hidden');
}

        function closeConfirmAdminModal() {
            const modal = document.getElementById('confirmAdminModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }
    </script>
</x-app-layout>