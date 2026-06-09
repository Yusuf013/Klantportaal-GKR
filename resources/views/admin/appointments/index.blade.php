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

    <div id="adminCreateModal" class="fixed inset-0 z-40 hidden overflow-y-auto" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div onclick="closeAdminCreateModal()" class="fixed inset-0 transition-opacity bg-gray-900/40 backdrop-blur-sm" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full border border-gray-100">
                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-xl font-bold text-[#011936] font-maven">Nieuwe afspraak inplannen</h2>
                    <button type="button" onclick="closeAdminCreateModal()" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <form action="{{ route('admin.appointments.store') }}" method="POST" class="p-8">
                    @csrf
                    
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-8">
                        
                        <div class="md:col-span-7 space-y-6">
                            <div>
                                <label class="block text-xs font-bold text-[#011936] uppercase tracking-wider mb-3">Afspraaktype *</label>
                                <div class="grid grid-cols-3 gap-4">
                                    <label class="flex flex-col items-center justify-center p-4 border border-gray-200 rounded-2xl cursor-pointer hover:bg-gray-50 transition bg-white text-center shadow-sm relative">
                                        <input type="radio" name="type" value="telefoon" required class="absolute top-3 left-3 text-[#011936] focus:ring-[#011936]">
                                        <span class="text-xs font-bold text-gray-700 mt-2">Telefoon</span>
                                    </label>
                                    <label class="flex flex-col items-center justify-center p-4 border border-gray-200 rounded-2xl cursor-pointer hover:bg-gray-50 transition bg-white text-center shadow-sm relative">
                                        <input type="radio" name="type" value="online" checked class="absolute top-3 left-3 text-[#011936] focus:ring-[#011936]">
                                        <span class="text-xs font-bold text-gray-700 mt-2">Online</span>
                                    </label>
                                    <label class="flex flex-col items-center justify-center p-4 border border-gray-200 rounded-2xl cursor-pointer hover:bg-gray-50 transition bg-white text-center shadow-sm relative">
                                        <input type="radio" name="type" value="fysiek" class="absolute top-3 left-3 text-[#011936] focus:ring-[#011936]">
                                        <span class="text-xs font-bold text-gray-700 mt-2">Fysiek</span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label for="title" class="block text-xs font-bold text-[#011936] uppercase tracking-wider mb-2">Onderwerp *</label>
                                <input type="text" name="title" id="title" required placeholder="Bijv. Project Voortgangsgesprek" class="w-full rounded-xl border border-gray-200 text-sm p-3.5 focus:border-[#011936] focus:ring-[#011936]">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-[#011936] uppercase tracking-wider mb-2">Kies een datum & tijd *</label>
                                <div onclick="openAdminDatePickerModal()" class="w-full flex items-center justify-between border border-gray-200 rounded-xl p-3.5 bg-gray-50/30 cursor-pointer hover:bg-gray-50 transition shadow-sm">
                                    <div class="flex items-center space-x-6 text-sm font-semibold text-gray-700">
                                        <span class="w-6 h-6 bg-[#011936] text-white flex items-center justify-center text-xs font-bold rounded-full">1</span>
                                        <span id="admin_display_date_text" class="text-gray-400 font-medium">Selecteer een datum...</span>
                                        <span id="admin_display_time_text" class="text-gray-400 font-medium border-l border-gray-200 pl-6">Kies tijdslot...</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-5 space-y-6">
                            <div>
                                <label for="client_id" class="block text-xs font-bold text-[#011936] uppercase tracking-wider mb-2">Klant *</label>
                                <select name="client_id" id="client_id" required class="w-full rounded-xl border border-gray-200 text-sm p-3.5 bg-white text-gray-700 shadow-sm">
                                    <option value="">Kies een klant...</option>
                                    @foreach($clients as $client)
                                        <option value="{{ $client->id }}">{{ $client->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="project_id" class="block text-xs font-bold text-[#011936] uppercase tracking-wider mb-2">Gekoppeld Project *</label>
                                <select name="project_id" id="project_id" required class="w-full rounded-xl border border-gray-200 text-sm p-3.5 bg-white text-gray-700 shadow-sm">
                                    <option value="">Kies een project...</option>
                                    @foreach($projects as $proj)
                                        <option value="{{ $proj->id }}">{{ $proj->name }} ({{ $proj->user->name ?? 'Geen klant' }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-[#011936] uppercase tracking-wider mb-2">Interne Deelnemers (GKR Collega's) *</label>
                                <div class="space-y-2 max-h-[160px] overflow-y-auto border border-gray-200 rounded-xl p-3 bg-gray-50/30">
                                    @foreach($gkrEmployees as $emp)
                                        <div class="flex items-center justify-between py-1 border-b border-gray-100/50 last:border-0">
                                            <label class="flex items-center space-x-3 text-sm font-medium text-gray-700 cursor-pointer flex-1">
                                                <input type="checkbox" name="employees[]" value="{{ $emp->id }}" onchange="checkAllEmployeesAvailability()" class="rounded text-[#011936] focus:ring-[#011936]">
                                                <span>{{ $emp->name }}</span>
                                            </label>
                                            
                                            <div id="status-employee-{{ $emp->id }}" class="ml-2"></div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <label for="description" class="block text-xs font-bold text-[#011936] uppercase tracking-wider mb-2">Aanvullende opmerking</label>
                                <textarea name="description" id="description" rows="2" placeholder="Doel van het gesprek..." class="w-full rounded-xl border border-gray-200 text-sm p-3 focus:border-[#011936] focus:ring-[#011936]"></textarea>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="date" id="admin_hidden_date" required>
                    <input type="hidden" name="time_slot" id="admin_hidden_time_slot" required>

                    <div class="mt-8 pt-6 border-t border-gray-100 flex items-center justify-end space-x-3">
                        <button type="button" onclick="closeAdminCreateModal()" class="px-5 py-3 border border-gray-200 text-gray-500 text-xs font-bold rounded-xl hover:bg-gray-50 transition">Annuleren</button>
                        <button type="submit" class="px-6 py-3 bg-[#011936] text-white text-xs font-bold rounded-xl shadow-md hover:bg-[#011936]/90 transition">Afspraak definitief boeken</button>
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
        const currentAdminId = {{ auth()->id() }}; // Geef ingelogde admin ID mee aan JS

        let currentAdminDate = new Date();
        let pickerNavDate = new Date();
        let adminSelectedDateStr = "";

        const monthsNl = ["januari", "februari", "maart", "april", "mei", "juni", "juli", "augustus", "september", "oktober", "november", "december"];
        const standardSlots = ["09:00 - 10:00", "10:00 - 11:00", "11:00 - 12:00", "13:00 - 14:00", "14:00 - 15:00", "15:00 - 16:00", "16:00 - 17:00"];

        document.addEventListener('DOMContentLoaded', () => {
            renderAdminDashboardCalendar();
            document.getElementById('adminPrevMonth').onclick = () => { currentAdminDate.setMonth(currentAdminDate.getMonth() - 1); renderAdminDashboardCalendar(); };
            document.getElementById('adminNextMonth').onclick = () => { currentAdminDate.setMonth(currentAdminDate.getMonth() + 1); renderAdminDashboardCalendar(); };
        });

        // 1. DASHBOARD OVERZICHTSAGENDA WITH JS FILTERING & INITIALS BADGES
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
                    if (app.start_time && app.start_time.split('T')[0] === currentDateStr) {
                        // Controleer of de ingelogde admin gekoppeld is aan deze afspraak (binnen attendees/employees)
                        const isAttendee = (app.attendees && app.attendees.some(att => att.id === currentAdminId)) || 
                                           (app.employees && app.employees.some(emp => emp.id === currentAdminId));
                        
                        // Sla rendering over als filter aan staat en admin geen deelnemer is
                        if (filterOn && !isAttendee) return;

                        let color = "bg-gray-150 text-gray-700";
                        if (app.status === 'Bevestigd') {
                            color = isAttendee ? "bg-[#011936] text-white border-[#011936]" : "bg-slate-200 text-slate-700 border-slate-300 opacity-60";
                        }
                        if (app.status === 'In afwachting') {
                            color = "bg-amber-50 text-amber-800 border-amber-200";
                        }

                        // Bouw initialen badges op voor alle gekoppelde medewerkers
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

        // 2. MODAL CONTROLS
        function openAdminCreateModal() { document.getElementById('adminCreateModal').classList.remove('hidden'); }
        function closeAdminCreateModal() { document.getElementById('adminCreateModal').classList.add('hidden'); }

        function openAdminDatePickerModal() {
            document.getElementById('adminDatePickerModal').classList.remove('hidden');
            document.getElementById('adminDatePickerModal').classList.add('flex');
            renderPickerCalendar();
            document.getElementById('pickerPrevMonth').onclick = () => { pickerNavDate.setMonth(pickerNavDate.getMonth() - 1); renderPickerCalendar(); };
            document.getElementById('pickerNextMonth').onclick = () => { pickerNavDate.setMonth(pickerNavDate.getMonth() + 1); renderPickerCalendar(); };
        }
        function closeAdminDatePickerModal() {
            document.getElementById('adminDatePickerModal').classList.add('hidden');
            document.getElementById('adminDatePickerModal').classList.remove('flex');
        }

        // 3. DATUM PICKER GENERATOR
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
                        document.getElementById('admin_hidden_date').value = dateStr;
                        
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
            container.classList.remove('justify-center'); container.innerHTML = "";

            standardSlots.forEach(slot => {
                const btn = document.createElement('button');
                btn.type = "button"; btn.innerText = slot;
                btn.className = "time-slot-btn w-full text-left p-3 border border-gray-200 rounded-xl text-xs font-bold text-[#011936] hover:bg-gray-50 bg-white transition";
                
                btn.onclick = () => {
                    document.getElementById('admin_hidden_time_slot').value = slot;
                    document.getElementById('admin_display_date_text').innerText = humanFormat;
                    document.getElementById('admin_display_date_text').classList.remove('text-gray-400');
                    document.getElementById('admin_display_time_text').innerText = slot;
                    document.getElementById('admin_display_time_text').classList.remove('text-gray-400');
                    closeAdminDatePickerModal();
                    
                    checkAllEmployeesAvailability();
                };
                container.appendChild(btn);
            });
        }

        // 4. LIVE AVAILABILITY API CHECK (MOCK DATA)
        function checkAllEmployeesAvailability() {
            const date = document.getElementById('admin_hidden_date').value;
            const timeSlot = document.getElementById('admin_hidden_time_slot').value;

            if (!date || !timeSlot) return;

            const employeeCheckboxes = document.querySelectorAll('input[name="employees[]"]');

            employeeCheckboxes.forEach(checkbox => {
                const employeeId = checkbox.value;
                const statusLabel = document.getElementById(`status-employee-${employeeId}`);
                
                if (!statusLabel) return;
                statusLabel.innerHTML = `<span class="text-gray-400 text-[10px] animate-pulse">Checken...</span>`;

                fetch("{{ route('admin.appointments.check') }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}"
                    },
                    body: JSON.stringify({
                        employee_id: employeeId,
                        date: date,
                        time_slot: timeSlot
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'conflict') {
                        statusLabel.innerHTML = `
                            <span class="inline-flex items-center text-[10px] font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded border border-red-100">
                                Bezet (conflict)
                            </span>`;
                    } else {
                        statusLabel.innerHTML = `
                            <span class="inline-flex items-center text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100">
                                Beschikbaar
                            </span>`;
                    }
                })
                .catch(error => {
                    console.error("Fout:", error);
                    statusLabel.innerHTML = "";
                });
            });
        }

        // 5. DETAIL MODAL LOGICA
function openAdminDetailModal(appointmentId) {
    // Zoek de juiste afspraak op binnen de reeds ingeladen dbAppointments array
    const app = dbAppointments.find(a => a.id === appointmentId);
    if (!app) return;

    // Vul de basisteksten in
    document.getElementById('modal_detail_title').innerText = app.title;
    
    // Formatteer datum en tijd handig voor de admin
    const dateObj = new Date(app.start_time);
    const humanDate = dateObj.toLocaleDateString('nl-NL', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const timeStart = app.start_time.split('T')[1].substring(0, 5);
    const timeEnd = app.end_time ? app.end_time.split('T')[1].substring(0, 5) : '';
    document.getElementById('modal_detail_datetime').innerText = `${humanDate} om ${timeStart} - ${timeEnd}`;

    document.getElementById('modal_detail_client').innerText = app.client ? app.client.name : 'Onbekend';
    document.getElementById('modal_detail_project').innerText = app.project ? app.project.name : 'Geen gekoppeld project';
    document.getElementById('modal_detail_type').innerText = app.type || 'Online';

    // Status Badge Stylen
    const statusLabel = document.getElementById('modal_detail_status');
    statusLabel.innerText = app.status;
    statusLabel.className = "text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md border ";
    if (app.status === 'Bevestigd') {
        statusLabel.classList.add('bg-emerald-50', 'text-emerald-700', 'border-emerald-100');
    } else {
        statusLabel.classList.add('bg-amber-50', 'text-amber-700', 'border-amber-100');
    }

    // Omschrijving tonen of verbergen
    const descWrapper = document.getElementById('modal_detail_description_wrapper');
    if (app.description && app.description.trim() !== "") {
        document.getElementById('modal_detail_description').innerText = app.description;
        descWrapper.classList.remove('hidden');
    } else {
        descWrapper.classList.add('hidden');
    }

    // Gekoppelde admins uittekenen als badges
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

    // Toon de modal
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
    </style>
</x-app-layout>