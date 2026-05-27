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
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
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

            @if($appointments->where('status', 'In afwachting')->count() > 0)
                <div class="bg-amber-50/40 border border-amber-150 rounded-2xl p-6">
                    <h3 class="text-sm font-bold text-amber-800 uppercase tracking-wider mb-4 flex items-center">
                        <span class="w-2 h-2 bg-amber-500 rounded-full mr-2 animate-pulse"></span>
                        Nieuwe aanvragen die wachten op goedkeuring
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($appointments->where('status', 'In afwachting') as $req)
                            <div class="bg-white p-5 rounded-xl border border-amber-200 shadow-sm flex flex-col justify-between space-y-4">
                                <div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-700 px-2 py-0.5 rounded-md border border-amber-100">In afwachting</span>
                                        <span class="text-xs text-gray-400 font-medium">{{ $req->start_time->translatedFormat('d M Y') }}</span>
                                    </div>
                                    <h4 class="font-bold text-[#011936] text-sm mt-2">{{ $req->title }}</h4>
                                    <p class="text-xs text-gray-500 mt-1 font-medium">Klant: <span class="text-gray-700 font-semibold">{{ $req->client->name ?? 'Onbekend' }}</span></p>
                                    <p class="text-xs text-gray-500 font-medium">Project: <span class="text-[#011936] font-semibold">{{ $req->project->name ?? 'Geen project' }}</span></p>
                                    <p class="text-xs text-gray-400 mt-2 italic">Tijdslot: {{ $req->start_time->format('H:i') }} - {{ $req->end_time->format('H:i') }} ({{ ucfirst($req->type) }})</p>
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
                        <h3 id="adminCalendarTitle" class="text-lg font-bold text-[#011936] capitalize">September 2024</h3>
                        
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

    <div id="adminCreateModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-gray-900/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full shadow-2xl">
            <h3 class="text-base font-bold text-[#011936] mb-4">Plannen als beheerder</h3>
            <p class="text-xs text-gray-400 mb-4">Binnenkort bouwen we hier Hanly's volledige handmatige admin-planner. Voor nu gebruiken we de klantzijde om testafspraken in te schieten.</p>
            <button onclick="closeAdminCreateModal()" class="w-full py-2.5 bg-[#011936] text-white text-xs font-bold rounded-xl">Sluiten</button>
        </div>
    </div>

    <script>
        // Geef de database-afspraken mee aan JavaScript als een JSON array
        const dbAppointments = @json($appointments);

        let currentAdminDate = new Date();
        const monthsNl = ["januari", "februari", "maart", "april", "mei", "juni", "juli", "augustus", "september", "oktober", "november", "december"];

        document.addEventListener('DOMContentLoaded', () => {
            initAdminCalendar();
        });

        function initAdminCalendar() {
            renderAdminCalendar();
            document.getElementById('adminPrevMonth').onclick = () => { currentAdminDate.setMonth(currentAdminDate.getMonth() - 1); renderAdminCalendar(); };
            document.getElementById('adminNextMonth').onclick = () => { currentAdminDate.setMonth(currentAdminDate.getMonth() + 1); renderAdminCalendar(); };
        }

        function renderAdminCalendar() {
            const year = currentAdminDate.getFullYear();
            const month = currentAdminDate.getMonth();
            
            document.getElementById('adminCalendarTitle').innerText = `${monthsNl[month]} ${year}`;
            
            const firstDayIndex = (new Date(year, month, 1).getDay() + 6) % 7; // Maandag indexering
            const lastDay = new Date(year, month + 1, 0).getDate();
            
            const grid = document.getElementById('adminCalendarGrid');
            grid.innerHTML = "";

            // Lege vakken van de vorige maand grijs invullen (zoals in professionele agenda's)
            for (let i = 0; i < firstDayIndex; i++) {
                grid.innerHTML += `<div class="bg-gray-50/50 min-h-[110px] p-2 text-gray-300 font-semibold text-xs border-b border-r border-gray-100"></div>`;
            }

            // Genereer de dagen van deze maand
            for (let day = 1; day <= lastDay; day++) {
                const currentDateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                
                // Maak een dag-vakje aan
                const dayBox = document.createElement('div');
                dayBox.className = "bg-white min-h-[110px] p-2 border-b border-r border-gray-100 flex flex-col justify-between";
                
                // Voeg het dagnummer toe
                let dayHeader = `<span class="text-xs font-bold text-gray-700">${day}</span>`;
                let appointmentsContainer = `<div class="space-y-1.5 mt-2 flex-1 overflow-y-auto max-h-[75px] pr-0.5">`;

                // Zoek of er afspraken zijn op deze specifieke datum in de DB array
                dbAppointments.forEach(app => {
                    // Pak alleen de YYYY-MM-DD van de start_time string uit de database
                    const appDate = app.start_time.split('T')[0];
                    
                    if (appDate === currentDateStr) {
                        // Bepaal de kleur op basis van de status (Hanly's mockup kleurstelling)
                        let bgClass = "bg-gray-100 text-gray-750 border-gray-200"; // Default / Geannuleerd
                        if (app.status === 'Bevestigd') {
                            bgClass = "bg-[#011936] text-white border-[#011936]"; // Donkerblauw
                        } else if (app.status === 'In afwachting') {
                            bgClass = "bg-amber-50 text-amber-800 border-amber-200"; // Oranje/Amber
                        }

                        // Genereer het afspraak-blokje binnen de dag
                        appointmentsContainer += `
                            <div class="text-[10px] p-1.5 rounded-md font-bold border ${bgClass} truncate shadow-2xs cursor-pointer" title="${app.title}">
                                ${app.title}
                            </div>
                        `;
                    }
                });

                appointmentsContainer += `</div>`;
                dayBox.innerHTML = dayHeader + appointmentsContainer;
                grid.appendChild(dayBox);
            }
        }

        // Simpele Modal triggers voor de admin planner placeholder
        function openAdminCreateModal() { document.getElementById('adminCreateModal').classList.remove('hidden'); document.getElementById('adminCreateModal').classList.add('flex'); }
        function closeAdminCreateModal() { document.getElementById('adminCreateModal').classList.add('hidden'); document.getElementById('adminCreateModal').classList.remove('flex'); }
    </script>
</x-app-layout>