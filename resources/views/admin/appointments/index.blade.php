<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-maven font-bold text-xl text-[#011936] leading-tight">
                    {{ __('Kalender & Afspraken') }}
                </h2>
                <p class="text-xs text-gray-400 font-medium mt-1">Beheer en monitor de voortgang van alle klantprojecten</p>
            </div>
            
            <button onclick="openAdminCreateModal()" class="inline-flex items-center px-4 py-2.5 bg-[#011936] hover:bg-[#011936]/90 text-white text-xs font-bold rounded-xl transition duration-150 shadow-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                </svg>
                Nieuwe afspraak plannen
            </button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            
            @if(session('success'))
                <div class="p-4 bg-emerald-50 border border-emerald-150 text-emerald-700 rounded-xl text-sm font-medium flex items-center shadow-sm">
                    <svg class="w-5 h-5 mr-2 shrink-0 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {{ session('success') }}
                </div>
            @endif

            @php
                // Haal alle afspraken op waarbij de klant een alternatieve datum heeft gekozen
                $alternativeAppointments = $appointments->where('status', 'Alternatief gekozen');
            @endphp

            @if($alternativeAppointments->count() > 0)
                <div class="space-y-3">
                    @foreach($alternativeAppointments as $altApp)
                        <div class="p-4 bg-blue-50 border border-blue-150 rounded-2xl flex items-start justify-between shadow-sm animate-fade-in">
                            <div class="flex items-start space-x-3">
                                <div class="p-2 bg-blue-500 text-white rounded-xl mt-0.5 shrink-0 animate-pulse-slow">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-blue-900">Klant heeft een alternatief tijdstip gekozen</h4>
                                    <p class="text-xs text-blue-700 font-medium mt-0.5">
                                        <span class="font-bold">{{ $altApp->client->name ?? 'Onbekend' }}</span> kon niet op de voorgestelde momenten voor <span class="italic">"{{ $altApp->title }}"</span>.
                                    </p>
                                    <p class="text-[11px] text-blue-600 mt-1 font-semibold">
                                        Nieuw gekozen moment: {{ \Carbon\Carbon::parse($altApp->start_time)->translatedFormat('l d F Y \o\m H:i') }} uur.
                                    </p>
                                </div>
                            </div>

                            <form action="{{ route('admin.appointments.approve', $altApp->id) }}" method="POST" class="shrink-0 ml-4">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="px-4 py-2 bg-[#011936] hover:bg-[#011936]/90 text-white text-xs font-bold rounded-xl transition shadow-sm whitespace-nowrap">
                                    Gezien & Bevestigen
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif

            @php
                $myPendingAppointments = $appointments->where('status', 'In afwachting')->filter(function($app) {
                    return $app->attendees && $app->attendees->contains(auth()->id());
                });
            @endphp

            @if($myPendingAppointments->count() > 0)
                <div class="bg-amber-50/40 border border-amber-150 rounded-2xl p-6">
                    <h3 class="text-sm font-bold text-amber-800 uppercase tracking-wider mb-4 flex items-center">
                        <span class="w-2 h-2 bg-amber-500 rounded-full mr-2 animate-pulse"></span>
                        Nieuwe aanvragen die op jouw goedkeuring wachten
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($myPendingAppointments as $req)
                            <div class="bg-white p-5 rounded-xl border border-amber-200 shadow-sm flex flex-col justify-between space-y-4">
                                <div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-700 px-2 py-0.5 rounded-md border border-amber-100">In afwachting</span>
                                        <span class="text-xs text-gray-400 font-medium">{{ $req->start_time ? \Carbon\Carbon::parse($req->start_time)->translatedFormat('d M Y') : '' }}</span>
                                    </div>
                                    <h4 class="font-bold text-[#011936] text-sm mt-2">{{ $req->title }}</h4>
                                    <p class="text-xs text-gray-500 mt-1 font-medium">Klant: <span class="text-gray-700 font-semibold">{{ $req->client->name ?? 'Onbekend' }}</span></p>
                                    <p class="text-xs text-gray-500 font-medium">Project: <span class="text-[#011936] font-semibold">{{ $req->project->name ?? 'Geen project' }}</span></p>
                                    <p class="text-xs text-gray-400 mt-2 italic">Tijdslot: {{ $req->start_time ? \Carbon\Carbon::parse($req->start_time)->format('H:i') : '' }} - {{ $req->end_time ? \Carbon\Carbon::parse($req->end_time)->format('H:i') : '' }} ({{ ucfirst($req->type) }})</p>
                                </div>
                                
                                <div class="flex items-center space-x-2 pt-2 border-t border-gray-100">
                                    <form action="{{ route('admin.appointments.reject', $req->id) }}" method="POST" class="w-1/2">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="w-full py-2 border border-gray-200 text-gray-500 hover:text-red-600 text-xs font-bold rounded-xl hover:bg-red-50 transition">
                                            Verwerpen
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.appointments.approve', $req->id) }}" method="POST" class="w-1/2">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="w-full py-2 bg-[#011936] hover:bg-[#011936]/90 text-white text-xs font-bold rounded-xl transition shadow-sm">
                                            Bevestigen
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="bg-white rounded-2xl shadow-sm border border-gray-150 p-6">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center space-x-4">
                        <h3 id="adminCalendarTitle" class="text-lg font-bold text-[#011936] capitalize"></h3>
                        
                        <div class="flex items-center space-x-2 bg-gray-50 p-1.5 rounded-xl border border-gray-200">
                            <input type="checkbox" id="filterMyAppointments" onchange="renderAdminDashboardCalendar()" class="rounded text-[#011936] focus:ring-[#011936] h-4 w-4 cursor-pointer">
                            <label for="filterMyAppointments" class="text-xs font-bold text-gray-650 cursor-pointer select-none">Toon alleen mijn afspraken</label>
                        </div>

                        <div class="inline-flex rounded-lg p-0.5 bg-gray-100 text-xs font-semibold text-gray-500 shadow-inner">
                            <button class="px-3 py-1.5 rounded-md">Dag</button>
                            <button class="px-3 py-1.5 rounded-md">Week</button>
                            <button class="px-3 py-1.5 rounded-md bg-white text-[#011936] shadow-sm font-bold">Maand</button>
                        </div>
                    </div>

                    <div class="flex space-x-2 text-gray-400">
                        <button id="adminPrevMonth" class="p-2 border border-gray-200 rounded-xl hover:text-[#011936] hover:bg-gray-50 transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path></svg>
                        </button>
                        <button id="adminNextMonth" class="p-2 border border-gray-200 rounded-xl hover:text-[#011936] hover:bg-gray-50 transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-7 gap-px bg-gray-100 text-center text-[11px] font-bold text-gray-400 uppercase tracking-wider py-3 rounded-t-xl">
                    <div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div><div>Sun</div>
                </div>

                <div id="adminCalendarGrid" class="grid grid-cols-7 gap-px bg-gray-100 border border-gray-100 rounded-b-xl overflow-hidden">
                </div>
            </div>
        </div>
    </div>

    <div id="adminCreateModal" class="fixed inset-0 z-40 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-4 text-center sm:block sm:p-0">
            <div onclick="closeAdminCreateModal()" class="fixed inset-0 transition-opacity bg-gray-900/40 backdrop-blur-sm" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-4 sm:align-middle sm:max-w-5xl sm:w-full border border-gray-100">
                
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-[#011936] font-maven">Nieuwe afspraak inplannen</h2>
                    <button type="button" onclick="closeAdminCreateModal()" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <form action="{{ route('admin.appointments.store') }}" method="POST" class="p-6">
                    @csrf
                    
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                        
                        <div class="md:col-span-7 space-y-4">
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="client_id" class="block text-xs font-bold text-[#011936] uppercase tracking-wider mb-1.5">Voor welke klant? *</label>
                                    <select name="client_id" id="client_id" required class="w-full rounded-xl border border-gray-200 text-xs text-gray-700 p-3 focus:border-[#011936] focus:ring-[#011936] bg-gray-50/50">
                                        <option value="">-- Selecteer klant --</option>
                                        @foreach($clients as $client)
                                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="project_id" class="block text-xs font-bold text-[#011936] uppercase tracking-wider mb-1.5">Gekoppeld Project *</label>
                                    <select name="project_id" id="project_id" required class="w-full rounded-xl border border-gray-200 text-xs text-gray-700 p-3 focus:border-[#011936] focus:ring-[#011936] bg-gray-50/50">
                                        <option value="">-- Selecteer project --</option>
                                        @foreach($projects as $proj)
                                            <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-[#011936] uppercase tracking-wider mb-2">Afspraaktype *</label>
                                <div class="grid grid-cols-3 gap-3">
                                    <label class="flex flex-col items-center justify-center p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50/50 transition bg-white text-center shadow-sm group">
                                        <input type="radio" name="type" value="telefoon" required class="sr-only">
                                        <svg class="w-5 h-5 text-gray-500 mb-1 group-hover:text-[#011936]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                        <span class="text-[11px] font-bold text-gray-700">Telefoon</span>
                                    </label>
                                    <label class="flex flex-col items-center justify-center p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50/50 transition bg-white text-center shadow-sm group">
                                        <input type="radio" name="type" value="online" checked class="sr-only">
                                        <svg class="w-5 h-5 text-gray-500 mb-1 group-hover:text-[#011936]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                        <span class="text-[11px] font-bold text-gray-700">Online</span>
                                    </label>
                                    <label class="flex flex-col items-center justify-center p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50/50 transition bg-white text-center shadow-sm group">
                                        <input type="radio" name="type" value="fysiek" class="sr-only">
                                        <svg class="w-5 h-5 text-gray-500 mb-1 group-hover:text-[#011936]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                        <span class="text-[11px] font-bold text-gray-700">Fysiek</span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label for="title" class="block text-xs font-bold text-[#011936] uppercase tracking-wider mb-1.5">Onderwerp *</label>
                                <div class="relative">
                                    <svg class="w-4 h-4 text-gray-400 absolute left-4 top-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    <input type="text" name="title" id="title" required placeholder="Bijv. Maandelijkse begrotingsreview" class="w-full rounded-xl border border-gray-200 text-xs pl-10 p-3 focus:border-[#011936] focus:ring-[#011936]">
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs font-bold text-[#011936] uppercase tracking-wider">Kies datum & tijd *</label>
                                    <button type="button" id="add_proposal_slot_btn" onclick="addProposalSlot()" class="text-xs font-bold text-[#011936] hover:underline flex items-center hidden">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                                        Extra moment toevoegen
                                    </button>
                                </div>
                                
                                <div class="space-y-2.5 max-h-[140px] overflow-y-auto pr-1 py-0.5 transition-all duration-300 ease-in-out" id="admin_slots_container">
                                    
                                    <div class="flex items-center space-x-3 slot-row max-h-[80px] transition-all duration-300" id="slot_row_1">
                                        <span class="w-5 h-5 bg-gray-300 text-white flex items-center justify-center text-[10px] font-bold rounded-full shrink-0 transition" id="slot_number_badge_1">1</span>
                                        
                                        <div id="date_trigger_block_1" class="flex-1 flex items-center justify-between border border-gray-200 rounded-xl p-2.5 bg-gray-100 opacity-60 cursor-not-allowed transition shadow-sm">
                                            <div class="flex items-center space-x-4 text-xs font-semibold text-gray-400">
                                                <div class="flex items-center space-x-2">
                                                    <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                    <span id="admin_display_date_1" class="text-gray-400 font-medium">Selecteer een datum...</span>
                                                </div>
                                                <div class="flex items-center space-x-2 border-l border-gray-200 pl-4">
                                                    <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                    <span id="admin_display_time_1" class="text-gray-400 font-medium">Kies tijdslot...</span>
                                                </div>
                                            </div>
                                        </div>
                                        <input type="hidden" name="proposal_dates[0][date]" id="hidden_date_1">
                                        <input type="hidden" name="proposal_dates[0][time_slot]" id="hidden_time_slot_1">
                                    </div>

                                    <div class="flex items-center space-x-3 slot-row max-h-0 opacity-0 pointer-events-none transition-all duration-300 ease-in-out overflow-hidden" id="slot_row_2">
                                        <span class="w-5 h-5 bg-gray-300 text-white flex items-center justify-center text-[10px] font-bold rounded-full shrink-0 transition" id="slot_number_badge_2">2</span>
                                        
                                        <div id="date_trigger_block_2" class="flex-1 flex items-center justify-between border border-gray-200 rounded-xl p-2.5 bg-gray-100 opacity-60 cursor-not-allowed transition shadow-sm">
                                            <div class="flex items-center space-x-4 text-xs font-semibold text-gray-400">
                                                <div class="flex items-center space-x-2">
                                                    <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                    <span id="admin_display_date_2" class="text-gray-400 font-medium">Selecteer een datum...</span>
                                                </div>
                                                <div class="flex items-center space-x-2 border-l border-gray-200 pl-4">
                                                    <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                    <span id="admin_display_time_2" class="text-gray-400 font-medium">Kies tijdslot...</span>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" onclick="removeProposalSlot(2)" class="text-gray-400 hover:text-red-500 transition p-1 shrink-0">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                        <input type="hidden" name="proposal_dates[1][date]" id="hidden_date_2">
                                        <input type="hidden" name="proposal_dates[1][time_slot]" id="hidden_time_slot_2">
                                    </div>

                                    <div class="flex items-center space-x-3 slot-row max-h-0 opacity-0 pointer-events-none transition-all duration-300 ease-in-out overflow-hidden" id="slot_row_3">
                                        <span class="w-5 h-5 bg-gray-300 text-white flex items-center justify-center text-[10px] font-bold rounded-full shrink-0 transition" id="slot_number_badge_3">3</span>
                                        
                                        <div id="date_trigger_block_3" class="flex-1 flex items-center justify-between border border-gray-200 rounded-xl p-2.5 bg-gray-100 opacity-60 cursor-not-allowed transition shadow-sm">
                                            <div class="flex items-center space-x-4 text-xs font-semibold text-gray-400">
                                                <div class="flex items-center space-x-2">
                                                    <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                    <span id="admin_display_date_3" class="text-gray-400 font-medium">Selecteer een datum...</span>
                                                </div>
                                                <div class="flex items-center space-x-2 border-l border-gray-200 pl-4">
                                                    <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                    <span id="admin_display_time_3" class="text-gray-400 font-medium">Kies tijdslot...</span>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" onclick="removeProposalSlot(3)" class="text-gray-400 hover:text-red-500 transition p-1 shrink-0">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                        <input type="hidden" name="proposal_dates[2][date]" id="hidden_date_3">
                                        <input type="hidden" name="proposal_dates[2][time_slot]" id="hidden_time_slot_3">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-5 space-y-4 flex flex-col justify-between">
                            
                            <div class="space-y-4">
                                <div>
                                    <label for="emp_primary" class="block text-xs font-bold text-[#011936] uppercase tracking-wider mb-1.5">Interne Deelnemer / Admin *</label>
                                    <select name="employees[]" id="emp_primary" onchange="checkAdminEmployeeRequirement()" class="w-full rounded-xl border border-gray-200 text-xs text-gray-700 p-3 focus:border-[#011936] focus:ring-[#011936] bg-gray-50/50">
                                        <option value="">-- Kies admin --</option>
                                        @foreach($gkrEmployees as $emp)
                                            <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="emp_secondary" class="block text-xs font-bold text-[#011936] uppercase tracking-wider mb-1.5">Extra Deelnemer (Optioneel)</label>
                                    <select name="employees[]" id="emp_secondary" onchange="checkAdminEmployeeRequirement()" class="w-full rounded-xl border border-gray-200 text-xs text-gray-700 p-3 focus:border-[#011936] focus:ring-[#011936] bg-gray-50/50">
                                        <option value="">-- Kies admin --</option>
                                        @foreach($gkrEmployees as $emp)
                                            <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label for="description" class="block text-xs font-bold text-[#011936] uppercase tracking-wider mb-1.5">Interne opmerkingen</label>
                                <textarea name="description" id="description" rows="3" maxlength="500" oninput="updateCharCount(this)" placeholder="Doel van de bijeenkomst of extra informatie..." class="w-full rounded-xl border border-gray-200 text-xs p-3 focus:border-[#011936] focus:ring-[#011936] bg-gray-50/20"></textarea>
                                <p class="text-right text-[10px] text-gray-400 mt-0.5"><span id="char-counter">0</span>/500</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between">
                        <div class="flex items-center space-x-2 text-[11px] text-gray-400 font-medium">
                            <svg class="w-3.5 h-3.5 text-[#011936]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>Elk ingevuld moment wordt meegestuurd in het actieve voorstel naar de klant.</span>
                        </div>
                        <div class="flex items-center space-x-3 shrink-0">
                            <button type="button" onclick="closeAdminCreateModal()" class="px-4 py-2.5 border border-gray-200 hover:bg-gray-50 text-gray-500 text-xs font-bold rounded-xl transition">
                                Annuleren
                            </button>
                            <button type="submit" class="px-5 py-2.5 bg-[#011936] hover:bg-[#011936]/90 text-white text-xs font-bold rounded-xl transition shadow-md flex items-center">
                                Verzoek verwerken
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="adminDatePickerModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true">
        <div onclick="closeAdminDatePickerModal()" class="fixed inset-0 bg-gray-950/20 backdrop-blur-[2px]"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl sm:max-w-3xl sm:w-full border border-gray-100 grid grid-cols-1 md:grid-cols-12 z-50 h-[400px] overflow-hidden">
            
            <div class="p-6 md:col-span-7 border-r border-gray-100 bg-white h-full flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-5">
                        <span id="pickerMonthYear" class="text-base font-bold text-[#011936] capitalize"></span>
                        <div class="flex space-x-2 text-gray-400">
                            <button type="button" id="pickerPrevMonth" class="p-1 hover:text-[#011936] transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path></svg></button>
                            <button type="button" id="pickerNextMonth" class="p-1 hover:text-[#011936] transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path></svg></button>
                        </div>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-bold text-gray-400 uppercase mb-2">
                        <div>Ma</div><div>Di</div><div>Wo</div><div>Do</div><div>Vr</div><div>Za</div><div>Zo</div>
                    </div>
                    <div id="pickerDaysGrid" class="grid grid-cols-7 gap-1.5 text-center text-xs font-semibold text-gray-700"></div>
                </div>
                <div class="text-[11px] text-gray-500"><span class="inline-block w-1.5 h-1.5 bg-[#011936] rounded-full mr-1"></span>Beschikbare werkdagen</div>
            </div>

            <div class="p-6 md:col-span-5 bg-gray-50/40 h-full flex flex-col justify-between">
                <div class="flex flex-col h-full overflow-hidden">
                    <div class="flex items-center justify-between mb-1">
                        <h4 class="text-sm font-bold text-[#011936]">Beschikbare uren</h4>
                        <button type="button" onclick="closeAdminDatePickerModal()" class="text-gray-400 hover:text-gray-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                    </div>
                    <p id="pickerSelectedDateHuman" class="text-[11px] text-gray-400 font-medium mb-4">Selecteer een datum</p>
                    <div id="adminTimeSlotsContainer" class="space-y-2 overflow-y-auto pr-1 flex-1 flex flex-col justify-center">
                        <p class="text-xs text-gray-400 italic py-4 text-center my-auto">Kies links een datum.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="adminDetailModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true">
        <div onclick="closeAdminDetailModal()" class="fixed inset-0 bg-gray-950/40 backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl sm:max-w-lg sm:w-full border border-gray-100 z-50 overflow-hidden transform transition-all">
            
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                <h3 id="modal_detail_status" class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md border"></h3>
                <button type="button" onclick="closeAdminDetailModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div>
                    <h4 id="modal_detail_title" class="text-base font-bold text-[#011936] font-maven"></h4>
                    <p id="modal_detail_datetime" class="text-xs text-gray-400 font-semibold mt-1"></p>
                </div>

                <div class="border-t border-gray-100 pt-4 grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="block text-gray-400 font-bold uppercase tracking-wider text-[10px]">Klant</span>
                        <span id="modal_detail_client" class="text-gray-700 font-semibold"></span>
                    </div>
                    <div>
                        <span class="block text-gray-400 font-bold uppercase tracking-wider text-[10px]">Project</span>
                        <span id="modal_detail_project" class="text-[#011936] font-semibold"></span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="block text-gray-400 font-bold uppercase tracking-wider text-[10px]">Type Gesprek</span>
                        <span id="modal_detail_type" class="text-gray-700 font-semibold capitalize"></span>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-4">
                    <span class="block text-gray-400 font-bold uppercase tracking-wider text-[10px] text-xs mb-2">Betrokken GKR Admins</span>
                    <div id="modal_detail_attendees" class="flex flex-wrap gap-1.5"></div>
                </div>

                <div id="modal_detail_description_wrapper" class="border-t border-gray-100 pt-4 hidden">
                    <span class="block text-gray-400 font-bold uppercase tracking-wider text-[10px] text-xs mb-1">Interne Opmerking</span>
                    <p id="modal_detail_description" class="text-xs text-gray-600 bg-gray-50 p-3 rounded-xl italic border border-gray-100"></p>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/30 flex justify-end">
                <button type="button" onclick="closeAdminDetailModal()" class="px-4 py-2 bg-[#011936] text-white text-xs font-bold rounded-xl shadow-sm hover:bg-[#011936]/90 transition">Sluiten</button>
            </div>
        </div>
    </div>

    <script>
        const dbAppointments = @json($appointments);
        const currentAdminId = {{ auth()->id() }}; 

        let currentAdminDate = new Date();
        let pickerNavDate = new Date();
        let adminSelectedDateStr = "";
        let activeSlotIndex = 1; 

        const monthsNl = ["januari", "februari", "maart", "april", "mei", "juni", "juli", "augustus", "september", "oktober", "november", "december"];
        const standardSlots = ["09:00 - 10:00", "10:00 - 11:00", "11:00 - 12:00", "13:00 - 14:00", "14:00 - 15:00", "15:00 - 16:00",];

        document.addEventListener('DOMContentLoaded', () => {
            renderAdminDashboardCalendar();
            document.getElementById('adminPrevMonth').onclick = () => { currentAdminDate.setMonth(currentAdminDate.getMonth() - 1); renderAdminDashboardCalendar(); };
            document.getElementById('adminNextMonth').onclick = () => { currentAdminDate.setMonth(currentAdminDate.getMonth() + 1); renderAdminDashboardCalendar(); };
        });

        // 1. DASHBOARD OVERZICHTSAGENDA MOTOR
        function renderAdminDashboardCalendar() {
            const year = currentAdminDate.getFullYear();
            const month = currentAdminDate.getMonth();
            document.getElementById('adminCalendarTitle').innerText = `${monthsNl[month]} ${year}`;
            
            const firstDayIndex = (new Date(year, month, 1).getDay() + 6) % 7;
            const lastDay = new Date(year, month + 1, 0).getDate();
            const grid = document.getElementById('adminCalendarGrid');
            grid.innerHTML = "";

            const filterOn = document.getElementById('filterMyAppointments')?.checked;

            for (let i = 0; i < firstDayIndex; i++) {
                grid.innerHTML += `<div class="bg-gray-50/30 min-h-[110px] border-b border-r border-gray-100"></div>`;
            }

            for (let day = 1; day <= lastDay; day++) {
                const currentDateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const dayBox = document.createElement('div');
                dayBox.className = "bg-white min-h-[110px] p-2 border-b border-r border-gray-100 flex flex-col justify-between";
                
                let dayHeader = `<span class="text-xs font-bold text-gray-700">${day}</span>`;
                let appHtml = `<div class="space-y-1 mt-1 flex-1 overflow-y-auto max-h-[75px] pr-0.5">`;

                dbAppointments.forEach(app => {
                    if (app.start_time && app.start_time.split(/[\sT]+/)[0] === currentDateStr) {
                        
                        // FIX: VERBERG GEANNULEERDE RECORDS DIRECT VAN DE INTERACTIEVE MAANDKALENDER
                        if (app.status === 'Geannuleerd') {
                            return; 
                        }

                        const isAttendee = (app.attendees && app.attendees.some(att => att.id === currentAdminId)) || 
                                           (app.employees && app.employees.some(emp => emp.id === currentAdminId));
                        
                        if (filterOn && !isAttendee) return;

                        // SLIMMERE TIJDSWEERGAVE DIRECT UIT DE RAUWE STRING
                        let timeDisplayStr = "";
                        if (app.start_time) {
                            const timePart = app.start_time.includes('T') ? app.start_time.split('T')[1] : app.start_time.split(' ')[1];
                            timeDisplayStr = ` (${timePart.substring(0, 5)})`;
                        }

                        let color = "bg-gray-150 text-gray-700";
                        if (app.status === 'Bevestigd') {
                            color = isAttendee ? "bg-[#011936] text-white border-[#011936]" : "bg-slate-200 text-slate-700 border-slate-300 opacity-60";
                        }
                        if (app.status === 'In afwachting' || app.status === 'Voorstel') {
                            color = "bg-amber-50 text-amber-800 border-amber-200";
                        }

                        let attendeesBadges = "";
                        const activeAttendees = app.attendees || app.employees || [];
                        activeAttendees.forEach(att => {
                            const initials = att.name.split(' ').map(n => n[0]).join('').toUpperCase();
                            attendeesBadges += `<span class="inline-block bg-white/25 text-[8px] px-1 rounded ml-1 font-mono">${initials}</span>`;
                        });

                        appHtml += `
    <div onclick="openAdminDetailModal(${app.id})" class="text-[9px] p-1 rounded font-bold border truncate flex items-center justify-between cursor-pointer hover:scale-[1.02] active:scale-[0.98] transition-all ${color}" title="Klik voor details: ${app.title}">
        <span class="truncate">${app.title}</span>
        <div class="flex shrink-0 ml-1">${attendeesBadges}</div>
    </div>`;
                    }
                });
                dayBox.innerHTML = dayHeader + appHtml + `</div>`;
                grid.appendChild(dayBox);
            }
        }

        // 2. MODAL CONTROLS & SECURITY LOCK ENGINE
        function openAdminCreateModal() { 
            document.getElementById('adminCreateModal').classList.remove('hidden'); 
            document.body.classList.add('overflow-hidden'); 
            checkAdminEmployeeRequirement(); 
        }

        function closeAdminCreateModal() { 
            document.getElementById('adminCreateModal').classList.add('hidden'); 
            document.body.classList.remove('overflow-hidden'); 
        }

        function checkAdminEmployeeRequirement() {
            const empPrimary = document.getElementById('emp_primary').value;
            const empSecondary = document.getElementById('emp_secondary').value;
            
            const addBtn = document.getElementById('add_proposal_slot_btn');
            const hasEmployee = (empPrimary !== "" || empSecondary !== "");

            for (let i = 1; i <= 3; i++) {
                const rowBlock = document.getElementById(`date_trigger_block_${i}`);
                const rowBadge = document.getElementById(`slot_number_badge_${i}`);
                
                if (!rowBlock) continue;

                if (hasEmployee) {
                    rowBlock.className = "flex-1 flex items-center justify-between border border-gray-200 rounded-xl p-2.5 bg-gray-50/30 cursor-pointer hover:bg-gray-50 transition shadow-sm";
                    rowBlock.querySelector('.flex').className = "flex items-center space-x-4 text-xs font-semibold text-gray-700";
                    rowBlock.querySelectorAll('svg').forEach(svg => svg.className.baseVal = "w-4 h-4 text-gray-400");
                    rowBadge.className = "w-5 h-5 bg-[#011936] text-white flex items-center justify-center text-[10px] font-bold rounded-full shrink-0 transition";
                    
                    rowBlock.onclick = () => openAdminDatePickerModal(i);
                } else {
                    rowBlock.className = "flex-1 flex items-center justify-between border border-gray-200 rounded-xl p-2.5 bg-gray-100 opacity-60 cursor-not-allowed transition shadow-sm";
                    rowBlock.querySelector('.flex').className = "flex items-center space-x-4 text-xs font-semibold text-gray-400";
                    rowBlock.querySelectorAll('svg').forEach(svg => svg.className.baseVal = "w-4 h-4 text-gray-300");
                    rowBadge.className = "w-5 h-5 bg-gray-300 text-white flex items-center justify-center text-[10px] font-bold rounded-full shrink-0 transition";
                    
                    rowBlock.onclick = null;
                }
            }

            if (hasEmployee) {
                const totalVisibleRows = Array.from(document.querySelectorAll('.slot-row'))
                    .filter(row => !row.classList.contains('max-h-0')).length;
                if (totalVisibleRows < 3) {
                    addBtn.classList.remove('hidden');
                }
                
                for (let i = 1; i <= 3; i++) {
                    const savedDate = document.getElementById(`hidden_date_${i}`).value;
                    const savedSlot = document.getElementById(`hidden_time_slot_${i}`).value;
                    
                    if (savedDate && savedSlot) {
                        const parts = savedDate.split('-');
                        const checkDate = new Date(parts[0], parts[1] - 1, parts[2]);
                        const humanFormat = checkDate.toLocaleDateString('nl-NL', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
                        
                        if (i === activeSlotIndex) {
                            renderAdminSlots(savedDate, humanFormat);
                        }
                    }
                }
            } else {
                addBtn.classList.add('hidden');
                
                for(let i = 2; i <= 3; i++) {
                    removeProposalSlot(i);
                }
                
                document.getElementById(`hidden_date_1`).value = "";
                document.getElementById(`hidden_time_slot_1`).value = "";
                document.getElementById(`admin_display_date_1`).innerText = "Selecteer een datum...";
                document.getElementById(`admin_display_time_1`).innerText = "Kies tijdslot...";
                document.getElementById(`admin_display_date_1`).classList.add('text-gray-400');
                document.getElementById(`admin_display_time_1`).classList.add('text-gray-400');
            }
        }

        function openAdminDatePickerModal(slotIndex) {
            activeSlotIndex = slotIndex; 
            
            document.getElementById('adminDatePickerModal').classList.remove('hidden');
            document.getElementById('adminDatePickerModal').classList.add('flex');
            
            renderPickerCalendar();
            
            const existingDate = document.getElementById(`hidden_date_${slotIndex}`).value;
            
            if (existingDate) {
                adminSelectedDateStr = existingDate;
                const parts = existingDate.split('-');
                const checkDate = new Date(parts[0], parts[1] - 1, parts[2]);
                const humanFormat = checkDate.toLocaleDateString('nl-NL', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
                
                document.getElementById('pickerSelectedDateHuman').innerText = humanFormat;
                renderAdminSlots(existingDate, humanFormat);
                renderPickerCalendar(); 
            } else {
                adminSelectedDateStr = "";
                document.getElementById('pickerSelectedDateHuman').innerText = "Selecteer een datum";
                document.getElementById('adminTimeSlotsContainer').innerHTML = `<p class="text-xs text-gray-400 italic py-4 text-center my-auto">Kies links een datum.</p>`;
                document.getElementById('adminTimeSlotsContainer').classList.add('justify-center');
            }
            
            document.getElementById('pickerPrevMonth').onclick = () => { pickerNavDate.setMonth(pickerNavDate.getMonth() - 1); renderPickerCalendar(); };
            document.getElementById('pickerNextMonth').onclick = () => { pickerNavDate.setMonth(pickerNavDate.getMonth() + 1); renderPickerCalendar(); };
        }
        
        function closeAdminDatePickerModal() {
            document.getElementById('adminDatePickerModal').classList.add('hidden');
            document.getElementById('adminDatePickerModal').classList.remove('flex');
        }

        document.getElementById('adminPrevMonth').onclick = () => { currentAdminDate.setMonth(currentAdminDate.getMonth() - 1); renderAdminDashboardCalendar(); };
        document.getElementById('adminNextMonth').onclick = () => { currentAdminDate.setMonth(currentAdminDate.getMonth() + 1); renderAdminDashboardCalendar(); };

        function updateCharCount(textarea) {
            document.getElementById('char-counter').innerText = textarea.value.length;
        }

        // 3. DATUM PICKER ENGINE EN BESCHIKBAARHEIDSCHECK
        function renderPickerCalendar() {
            const year = pickerNavDate.getFullYear(); const month = pickerNavDate.getMonth();
            document.getElementById('pickerMonthYear').innerText = `${monthsNl[month]} ${year}`;
            
            const firstDayIndex = (new Date(year, month, 1).getDay() + 6) % 7;
            const lastDay = new Date(year, month + 1, 0).getDate();
            const daysGrid = document.getElementById('pickerDaysGrid');
            daysGrid.innerHTML = "";

            for (let i = 0; i < firstDayIndex; i++) daysGrid.innerHTML += `<div></div>`;

            const today = new Date(); today.setHours(0,0,0,0);

            for (let day = 1; day <= lastDay; day++) {
                const checkDate = new Date(year, month, day);
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                
                const dayBtn = document.createElement('button');
                dayBtn.type = "button"; dayBtn.innerText = day;
                dayBtn.className = "calendar-day-btn py-1.5 w-full text-center hover:bg-gray-100 rounded-full font-bold text-gray-700 relative flex items-center justify-center";
                
                if (checkDate < today || checkDate.getDay() === 0 || checkDate.getDay() === 6) {
                    dayBtn.disabled = true;
                } else {
                    dayBtn.innerHTML = `${day}<span class="absolute bottom-0.5 w-1 h-1 bg-[#011936] rounded-full"></span>`;
                    if (dateStr === adminSelectedDateStr) dayBtn.classList.add('active');
                    
                    dayBtn.onclick = () => {
                        document.querySelectorAll('#pickerDaysGrid .calendar-day-btn').forEach(b => b.classList.remove('active'));
                        dayBtn.classList.add('active');
                        adminSelectedDateStr = dateStr;
                        
                        const humanFormat = checkDate.toLocaleDateString('nl-NL', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
                        document.getElementById('pickerSelectedDateHuman').innerText = humanFormat;
                        renderAdminSlots(dateStr, humanFormat);
                    };
                }
                daysGrid.appendChild(dayBtn);
            }
        }

        function renderAdminSlots(dateStr, humanFormat) {
            const container = document.getElementById('adminTimeSlotsContainer');
            container.classList.remove('justify-center'); 
            container.innerHTML = "";

            const empPrimary = document.getElementById('emp_primary').value;
            const empSecondary = document.getElementById('emp_secondary').value;
            const activeEmpIds = [empPrimary, empSecondary].filter(id => id !== "");

            standardSlots.forEach(slot => {
                const slotBtn = document.createElement('button');
                slotBtn.type = "button"; slotBtn.innerText = slot;
                slotBtn.className = "time-slot-btn w-full text-left p-3 border border-gray-200 rounded-xl text-xs font-bold text-[#011936] hover:bg-gray-50 bg-white transition shadow-sm flex items-center justify-between";
                
                const statusSpan = document.createElement('span');
                statusSpan.className = "text-[10px] uppercase font-bold text-gray-400 tracking-wider";
                statusSpan.innerText = "Checken...";
                slotBtn.appendChild(statusSpan);
                container.appendChild(slotBtn);

                let isDuplicate = false;
                for (let i = 1; i <= 3; i++) {
                    if (i === activeSlotIndex) continue;
                    
                    const savedDate = document.getElementById(`hidden_date_${i}`).value;
                    const savedSlot = document.getElementById(`hidden_time_slot_${i}`).value;
                    
                    if (savedDate === dateStr && savedSlot === slot) {
                        isDuplicate = true;
                        break;
                    }
                }

                let conflictFound = false;
                let checksCompleted = 0;

                activeEmpIds.forEach(empId => {
                    fetch("{{ route('admin.appointments.check') }}", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": "{{ csrf_token() }}"
                        },
                        body: JSON.stringify({ employee_id: empId, date: dateStr, time_slot: slot })
                    })
                    .then(res => res.json())
                    .then(data => {
                        checksCompleted++;
                        if (data.status === 'conflict') conflictFound = true;

                        if (checksCompleted === activeEmpIds.length) {
                            if (conflictFound) {
                                slotBtn.disabled = true;
                                slotBtn.className = "w-full text-left p-3 border border-gray-100 bg-gray-50 text-gray-300 rounded-xl text-xs font-semibold flex items-center justify-between cursor-not-allowed opacity-60";
                                statusSpan.className = "text-[10px] text-red-500 font-bold tracking-wider";
                                statusSpan.innerText = "BEZET";
                            } else if (isDuplicate) {
                                slotBtn.disabled = true;
                                slotBtn.className = "w-full text-left p-3 border border-red-200 bg-red-50/50 text-red-400 rounded-xl text-xs font-semibold flex items-center justify-between cursor-not-allowed transition duration-150";
                                statusSpan.className = "text-[10px] text-red-600 font-bold tracking-wider bg-red-100 px-2 py-0.5 rounded border border-red-200";
                                statusSpan.innerText = "AL GEKOZEN";
                            } else {
                                statusSpan.className = "text-[10px] text-emerald-600 font-bold tracking-wider";
                                statusSpan.innerText = "VRIJ";
                                
                                slotBtn.onclick = () => {
                                    document.getElementById(`hidden_date_${activeSlotIndex}`).value = dateStr;
                                    document.getElementById(`hidden_time_slot_${activeSlotIndex}`).value = slot;
                                    
                                    const dateDisplay = document.getElementById(`admin_display_date_${activeSlotIndex}`);
                                    dateDisplay.innerText = humanFormat;
                                    dateDisplay.classList.remove('text-gray-400');
                                    
                                    const timeDisplay = document.getElementById(`admin_display_time_${activeSlotIndex}`);
                                    timeDisplay.innerText = slot;
                                    timeDisplay.classList.remove('text-gray-400');
                                    
                                    closeAdminDatePickerModal();
                                };
                            }
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        checksCompleted++;
                    });
                });
            });
        }

        // 4. ADD & REMOVE COMPACT SLOTS ENGINE
        function addProposalSlot() {
            const row2 = document.getElementById('slot_row_2');
            const row3 = document.getElementById('slot_row_3');
            
            if (row2.classList.contains('max-h-0')) {
                row2.classList.remove('max-h-0', 'opacity-0', 'pointer-events-none');
                row2.classList.add('max-h-[80px]', 'opacity-100');
            } else if (row3.classList.contains('max-h-0')) {
                row3.classList.remove('max-h-0', 'opacity-0', 'pointer-events-none');
                row3.classList.add('max-h-[80px]', 'opacity-100');
                document.getElementById('add_proposal_slot_btn').classList.add('hidden'); 
            }
        }

        function removeProposalSlot(index) {
            const row = document.getElementById(`slot_row_${index}`);
            
            row.classList.remove('max-h-[80px]', 'opacity-100');
            row.classList.add('max-h-0', 'opacity-0', 'pointer-events-none');
            
            document.getElementById(`hidden_date_${index}`).value = "";
            document.getElementById(`hidden_time_slot_${index}`).value = "";
            
            document.getElementById(`admin_display_date_${index}`).innerText = "Selecteer een datum...";
            document.getElementById(`admin_display_date_${index}`).classList.add('text-gray-400');
            document.getElementById(`admin_display_time_${index}`).innerText = "Kies tijdslot...";
            document.getElementById(`admin_display_time_${index}`).classList.add('text-gray-400');
            
            document.getElementById('add_proposal_slot_btn').classList.remove('hidden');
        }

        // 5. DETAIL VIEW MODAL LOGICA (VOLLEDIGE SYMMETRISCHE REPARATIE TEGEN VERVERSINGEN)
        function openAdminDetailModal(appointmentId) {
            const app = dbAppointments.find(a => a.id === appointmentId);
            if (!app) return;

            document.getElementById('modal_detail_title').innerText = app.title;
            
            // EXACT DEZELFDE TIJDZONE-PROOF LOGICA ALS DE CLIENTSIDE: RAUWE STRING INTERPRETATIE
            if (app.status === 'Voorstel' && (!app.start_time || app.options_count > 1)) {
                document.getElementById('modal_detail_datetime').innerText = "Datum nog te bepalen door de klant";
            } else {
                let dateObj = new Date(app.start_time);

                let hoursStart = String(dateObj.getHours()).padStart(2, '0');
                let minutesStart = String(dateObj.getMinutes()).padStart(2, '0');
                
                if (app.start_time.includes('Z') || app.start_time.includes('+')) {
                    // Browser verwerkt ISO direct correct
                } else if (app.start_time.includes('T')) {
                    const timePart = app.start_time.split('T')[1];
                    hoursStart = timePart.substring(0, 2);
                    minutesStart = timePart.substring(3, 5);
                } else if (app.start_time.includes(' ')) {
                    const timePart = app.start_time.split(' ')[1];
                    hoursStart = timePart.substring(0, 2);
                    minutesStart = timePart.substring(3, 5);
                }

                let hoursEnd = '00';
                let minutesEnd = '00';
                if (app.end_time) {
                    let endDateObj = new Date(app.end_time);
                    hoursEnd = String(endDateObj.getHours()).padStart(2, '0');
                    minutesEnd = String(endDateObj.getMinutes()).padStart(2, '0');
                    
                    if (!app.end_time.includes('Z') && !app.end_time.includes('+')) {
                        if (app.end_time.includes('T')) {
                            const endTimePart = app.end_time.split('T')[1];
                            hoursEnd = endTimePart.substring(0, 2);
                            minutesEnd = endTimePart.substring(3, 5);
                        } else if (app.end_time.includes(' ')) {
                            const endTimePart = app.end_time.split(' ')[1];
                            hoursEnd = endTimePart.substring(0, 2);
                            minutesEnd = endTimePart.substring(3, 5);
                        }
                    }
                }

                const pureDateStr = app.start_time.split(/[\sT]+/)[0];
                const [year, month, day] = pureDateStr.split('-');
                const localDate = new Date(year, month - 1, day);
                
                const humanDate = localDate.toLocaleDateString('nl-NL', { 
                    weekday: 'long', 
                    day: 'numeric', 
                    month: 'long', 
                    year: 'numeric' 
                });

                document.getElementById('modal_detail_datetime').innerText = `${humanDate} om ${hoursStart}:${minutesStart} - ${hoursEnd}:${minutesEnd} uur`;
            }

            document.getElementById('modal_detail_client').innerText = app.client ? app.client.name : 'Onbekend';
            document.getElementById('modal_detail_project').innerText = app.project ? app.project.name : 'Geen gekoppeld project';
            document.getElementById('modal_detail_type').innerText = app.type || 'Online';

            const statusLabel = document.getElementById('modal_detail_status');
            statusLabel.innerText = app.status;
            statusLabel.className = "text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md border ";
            if (app.status === 'Bevestigd') {
                statusLabel.classList.add('bg-emerald-50', 'text-emerald-700', 'border-emerald-100');
            } else if (app.status === 'Voorstel') {
                statusLabel.classList.add('bg-orange-50', 'text-orange-700', 'border-orange-100');
            } else {
                statusLabel.classList.add('bg-amber-50', 'text-amber-700', 'border-amber-100');
            }

            const descWrapper = document.getElementById('modal_detail_description_wrapper');
            if (app.description && app.description.trim() !== "") {
                document.getElementById('modal_detail_description').innerText = app.description;
                descWrapper.classList.remove('hidden');
            } else {
                descWrapper.classList.add('hidden');
            }

            const attendeesContainer = document.getElementById('modal_detail_attendees');
            attendeesContainer.innerHTML = "";
            const activeAttendees = app.attendees || app.employees || [];

            if (activeAttendees.length > 0) {
                activeAttendees.forEach(att => {
                    attendeesContainer.innerHTML += `
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200">
                            <span class="w-1.5 h-1.5 bg-[#011936] rounded-full mr-1.5"></span>
                            ${att.name}
                        </span>`;
                });
            } else {
                attendeesContainer.innerHTML = `<span class="text-xs text-gray-400 italic">Geen admins gekoppeld</span>`;
            }

            const modal = document.getElementById('adminDetailModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeAdminDetailModal() {
            const modal = document.getElementById('adminDetailModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    </script>

    <style>
        .calendar-day-btn:disabled { color: #d1d5db; cursor: not-allowed; background: transparent !important; }
        .calendar-day-btn.active { background-color: #011936 !important; color: white !important; border-radius: 9999px; }

/* Voeg deze animatie toe onder je .active klasse */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }
}
.animate-pulse-slow { animation: pulseSlow 3s infinite ease-in-out; }

    </style>
</x-app-layout>