<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-maven font-bold text-xl text-[#011936] leading-tight">
                {{ __('Kalender & Afspraken') }}
            </h2>
            
            <button onclick="openAppointmentModal()" class="inline-flex items-center px-4 py-2.5 bg-[#011936] hover:bg-[#011936]/90 text-white text-xs font-bold rounded-xl transition duration-150 shadow-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                </svg>
                Nieuwe afspraak plannen
            </button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @if(session('success'))
                <div class="mb-6 p-4 bg-emerald-50 border border-emerald-150 text-emerald-700 rounded-xl text-sm font-medium flex items-center">
                    <svg class="w-5 h-5 mr-2 shrink-0 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white rounded-2xl shadow-sm border border-gray-150 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/30 text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                                <th class="p-4 pl-6 w-1/4">Projectnaam</th>
                                <th class="p-4">Afspraak titel</th>
                                <th class="p-4 w-44">Datum & Tijd</th>
                                <th class="p-4 w-32">Status</th>
                                <th class="p-4 pr-6 w-16 text-right">Actie</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            @forelse($appointments as $appointment)
                                <tr class="hover:bg-gray-50/50 transition duration-150 group">
                                    
                                    <td class="p-4 pl-6 font-bold text-[#011936]">
                                        {{ $appointment->project->name ?? 'Geen gekoppeld project' }}
                                    </td>

                                    <td class="p-4">
                                        <div class="font-medium text-gray-700">{{ $appointment->title }}</div>
                                        <div class="text-[11px] font-semibold text-gray-400 uppercase mt-0.5 tracking-wider flex items-center">
                                            @if($appointment->type === 'online')
                                                <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-1.5"></span>Online meeting
                                            @elseif($appointment->type === 'fysiek')
                                                <span class="w-1.5 h-1.5 bg-amber-500 rounded-full mr-1.5"></span>Fysieke afspraak
                                            @else
                                                <span class="w-1.5 h-1.5 bg-purple-500 rounded-full mr-1.5"></span>Telefonisch
                                            @endif
                                        </div>
                                    </td>

                                    <td class="p-4 text-gray-650 font-medium">
                                        <div>{{ $appointment->start_time->translatedFormat('d M Y') }}</div>
                                        <div class="text-xs text-gray-400 mt-0.5 font-normal">
                                            {{ $appointment->start_time->format('H:i') }} - {{ $appointment->end_time->format('H:i') }}
                                        </div>
                                    </td>

                                    <td class="p-4">
                                        @if($appointment->status === 'Bevestigd')
                                            <span class="inline-flex items-center text-[11px] font-bold px-2.5 py-1 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-100 uppercase tracking-wider">
                                                {{ __('Bevestigd') }}
                                            </span>
                                        @elseif($appointment->status === 'In afwachting')
                                            <span class="inline-flex items-center text-[11px] font-bold px-2.5 py-1 rounded-md bg-amber-50 text-amber-700 border border-amber-100 uppercase tracking-wider">
                                                {{ __('In afwachting') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center text-[11px] font-bold px-2.5 py-1 rounded-md bg-gray-50 text-gray-500 border border-gray-150 uppercase tracking-wider">
                                                {{ $appointment->status }}
                                            </span>
                                        @endif
                                    </td>

                                    <td class="p-4 pr-6 text-right">
                                        <button class="p-1.5 text-gray-400 hover:text-[#011936] hover:bg-gray-100 rounded-lg transition">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-12 text-center text-sm text-gray-400 italic">
                                        U heeft momenteel geen geplande of aangevraagde afspraken staan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <div id="appointmentModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div onclick="closeAppointmentModal()" class="fixed inset-0 transition-opacity bg-gray-900/40 backdrop-blur-sm" aria-hidden="true"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full border border-gray-100">
                
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                    <h3 class="text-base font-bold text-[#011936]" id="modal-title">
                        Nieuw gesprek inplannen
                    </h3>
                    <button type="button" onclick="closeAppointmentModal()" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form action="{{ route('client.appointments.store') }}" method="POST" class="p-6">
                    @csrf
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        <div>
                            <label for="project_id" class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Voor welk project?</label>
                            <select name="project_id" id="project_id" required class="w-full rounded-xl border border-gray-200 text-sm text-gray-700 p-3 focus:border-[#011936] focus:ring-[#011936] bg-gray-50/50 @error('project_id') is-invalid @enderror">
                                <option value="">-- Selecteer uw project --</option>
                                @foreach($myProjects as $myProject)
                                    <option value="{{ $myProject->id }}" {{ old('project_id') == $myProject->id ? 'selected' : '' }}>{{ $myProject->name }}</option>
                                @endforeach
                            </select>
                            @error('project_id')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Type gesprek</label>
                            <div class="grid grid-cols-3 gap-3">
                                <label class="flex flex-col items-center justify-center p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50/50 transition bg-white text-center shadow-sm">
                                    <input type="radio" name="type" value="telefoon" required class="sr-only">
                                    <span class="text-xs font-bold text-[#011936]">Telefoon</span>
                                </label>
                                <label class="flex flex-col items-center justify-center p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50/50 transition bg-white text-center shadow-sm">
                                    <input type="radio" name="type" value="online" checked class="sr-only">
                                    <span class="text-xs font-bold text-[#011936]">Online</span>
                                </label>
                                <label class="flex flex-col items-center justify-center p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50/50 transition bg-white text-center shadow-sm">
                                    <input type="radio" name="type" value="fysiek" class="sr-only">
                                    <span class="text-xs font-bold text-[#011936]">Fysiek</span>
                                </label>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label for="title" class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Onderwerp gesprek</label>
                            <input type="text" name="title" id="title" required value="{{ old('title') }}" placeholder="Bijv. Bespreken van de nieuwe checkout flow" class="w-full rounded-xl border border-gray-200 text-sm p-3 focus:border-[#011936] focus:ring-[#011936] @error('title') is-invalid @enderror">
                            @error('title')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2 border border-gray-200 rounded-2xl overflow-hidden bg-white grid grid-cols-1 md:grid-cols-12">
                            
                            <div class="p-5 md:col-span-7 border-r border-gray-100">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center space-x-1">
                                        <span id="currentMonthYear" class="text-base font-bold text-[#011936] capitalize"></span>
                                    </div>
                                    <div class="flex space-x-2 text-gray-400">
                                        <button type="button" id="prevMonth" class="hover:text-[#011936] transition p-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path></svg>
                                        </button>
                                        <button type="button" id="nextMonth" class="hover:text-[#011936] transition p-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path></svg>
                                        </button>
                                    </div>
                                </div>

                                <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">
                                    <div>Ma</div><div>Di</div><div>Wo</div><div>Do</div><div>Vr</div><div>Za</div><div>Zo</div>
                                </div>

                                <div id="calendarDays" class="grid grid-cols-7 gap-1.5 text-center text-xs font-semibold text-gray-700">
                                    </div>

                                <div class="flex items-center space-x-2 mt-4 text-[11px] text-gray-500">
                                    <span class="w-2 h-2 bg-[#011936] rounded-full"></span>
                                    <span>Beschikbare dagen</span>
                                </div>
                            </div>

                            <div class="p-5 md:col-span-5 bg-gray-50/40 flex flex-col justify-between">
                                <div>
                                    <h4 class="text-sm font-bold text-[#011936] mb-1">Beschikbare tijden</h4>
                                    <p id="selectedDateHuman" class="text-[11px] text-gray-400 font-medium mb-4">Selecteer een datum</p>

                                    <div id="timeSlotsContainer" class="space-y-2 max-h-[200px] overflow-y-auto pr-1">
                                        <p class="text-xs text-gray-400 italic py-4 text-center">Kies links een beschikbare dag.</p>
                                    </div>
                                </div>

                                <div class="flex items-center space-x-2 text-[10px] text-gray-400 pt-3 border-t border-gray-100 mt-3">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span>Gesprekken zijn ~1 uur</span>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="date" id="hidden_date" required>
                        <input type="hidden" name="time_slot" id="hidden_time_slot" required>

                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Selecteer GKR Medewerker(s)</label>
                            <div class="space-y-2">
                                <select name="employees[]" required class="w-full rounded-xl border border-gray-200 text-sm p-3 focus:border-[#011936] focus:ring-[#011936] bg-white text-gray-700">
                                    <option value="">Kies medewerker...</option>
                                    @foreach($gkrEmployees as $employee)
                                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                    @endforeach
                                </select>
                                <select name="employees[]" class="w-full rounded-xl border border-gray-200 text-sm p-3 focus:border-[#011936] focus:ring-[#011936] bg-white text-gray-700">
                                    <option value="">Kies extra medewerker (optioneel)...</option>
                                    @foreach($gkrEmployees as $employee)
                                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="description" class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Aanvullende opmerking</label>
                            <textarea name="description" id="description" rows="3" maxlength="500" placeholder="Zijn er specifieke dingen die we moeten voorbereiden?" class="w-full rounded-xl border border-gray-200 text-sm p-3 focus:border-[#011936] focus:ring-[#011936]"></textarea>
                        </div>

                    </div>

                    <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-end space-x-3">
                        <button type="button" onclick="closeAppointmentModal()" class="px-4 py-2.5 border border-gray-200 hover:bg-gray-50 text-gray-500 text-xs font-bold rounded-xl transition">
                            Annuleren
                        </button>
                        <button type="submit" class="px-5 py-2.5 bg-[#011936] hover:bg-[#011936]/90 text-white text-xs font-bold rounded-xl transition shadow-sm">
                            Afspraak aanvragen
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <style>
        label:has(input:checked) {
            border-color: #011936;
            background-color: rgb(1 25 54 / 0.04);
            box-shadow: inset 0 0 0 1px #011936;
        }
        .calendar-day-btn:disabled {
            color: #d1d5db;
            cursor: not-allowed;
            background: transparent !important;
        }
        .calendar-day-btn.active {
            background-color: #011936 !important;
            color: white !important;
            border-radius: 9999px;
        }
        .time-slot-btn.active {
            border-color: #011936 !important;
            background-color: rgb(1 25 54 / 0.04) !important;
            color: #011936 !important;
        }
        .is-invalid {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
        }
    </style>

    <script>
        function openAppointmentModal() {
            document.getElementById('appointmentModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            initCalendar();
        }

        function closeAppointmentModal() {
            document.getElementById('appointmentModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // --- KALENDER LOGICA ---
        let currentNavDate = new Date();
        let selectedDateStr = "";

        const monthsNl = ["januari", "februari", "maart", "april", "mei", "juni", "juli", "augustus", "september", "oktober", "november", "december"];
        const standardSlots = ["09:00 - 10:00", "10:00 - 11:00", "11:00 - 12:00", "13:00 - 14:00", "14:00 - 15:00", "15:00 - 16:00"];

        function initCalendar() {
            renderCalendar();
            document.getElementById('prevMonth').onclick = () => { currentNavDate.setMonth(currentNavDate.getMonth() - 1); renderCalendar(); };
            document.getElementById('nextMonth').onclick = () => { currentNavDate.setMonth(currentNavDate.getMonth() + 1); renderCalendar(); };
        }

        function renderCalendar() {
            const year = currentNavDate.getFullYear();
            const month = currentNavDate.getMonth();
            
            document.getElementById('currentMonthYear').innerText = `${monthsNl[month]} ${year}`;
            
            const firstDayIndex = (new Date(year, month, 1).getDay() + 6) % 7;
            const lastDay = new Date(year, month + 1, 0).getDate();
            
            const daysContainer = document.getElementById('calendarDays');
            daysContainer.innerHTML = "";

            for (let i = 0; i < firstDayIndex; i++) {
                daysContainer.innerHTML += `<div></div>`;
            }

            const today = new Date();
            today.setHours(0,0,0,0);

            for (let day = 1; day <= lastDay; day++) {
                const checkDate = new Date(year, month, day);
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const isPast = checkDate < today;
                const isWeekend = checkDate.getDay() === 0 || checkDate.getDay() === 6;
                
                const dayBtn = document.createElement('button');
                dayBtn.type = "button";
                dayBtn.innerText = day;
                dayBtn.className = "calendar-day-btn py-1.5 w-full text-center hover:bg-gray-100 rounded-full transition relative flex items-center justify-center font-bold text-gray-700";
                
                if (isPast || isWeekend) {
                    dayBtn.disabled = true;
                } else {
                    dayBtn.innerHTML = `${day}<span class="absolute bottom-0.5 w-1 h-1 bg-[#011936] rounded-full"></span>`;
                    
                    if (dateStr === selectedDateStr) {
                        dayBtn.classList.add('active');
                    }
                    
                    dayBtn.onclick = () => {
                        document.querySelectorAll('.calendar-day-btn').forEach(b => b.classList.remove('active'));
                        dayBtn.classList.add('active');
                        selectDate(dateStr, checkDate);
                    };
                }
                daysContainer.appendChild(dayBtn);
            }
        }

        function selectDate(dateStr, dateObj) {
            selectedDateStr = dateStr;
            document.getElementById('hidden_date').value = dateStr;
            
            const options = { weekday: 'long', day: 'numeric', month: 'long' };
            document.getElementById('selectedDateHuman').innerText = dateObj.toLocaleDateString('nl-NL', options);
            
            fetchAvailableSlots(dateStr);
        }

        function fetchAvailableSlots(dateStr) {
            const container = document.getElementById('timeSlotsContainer');
            container.innerHTML = "";

            standardSlots.forEach(slot => {
                const slotBtn = document.createElement('button');
                slotBtn.type = "button";
                slotBtn.innerText = slot;
                slotBtn.className = "time-slot-btn w-full text-left p-3 border border-gray-200 rounded-xl text-xs font-bold text-[#011936] hover:bg-gray-50 transition bg-white";
                
                slotBtn.onclick = () => {
                    document.querySelectorAll('.time-slot-btn').forEach(b => b.classList.remove('active'));
                    slotBtn.classList.add('active');
                    document.getElementById('hidden_time_slot').value = slot;
                };
                
                container.appendChild(slotBtn);
            });
        }

        @if ($errors->any())
            window.addEventListener('DOMContentLoaded', () => { 
                openAppointmentModal(); 
            });
        @endif
    </script>
</x-app-layout>