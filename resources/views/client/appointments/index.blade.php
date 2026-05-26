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

            <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full border border-gray-100">
                
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
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        
                        <div class="space-y-4">
                            <div>
                                <label for="project_id" class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Voor welk project?</label>
                                <select name="project_id" id="project_id" required class="w-full rounded-xl border border-gray-200 text-sm text-gray-700 p-3 focus:border-[#011936] focus:ring-[#011936] bg-gray-50/50">
                                    <option value="">-- Selecteer uw project --</option>
                                    @foreach($myProjects as $myProject)
                                        <option value="{{ $myProject->id }}">{{ $myProject->name }}</option>
                                    @endforeach
                                </select>
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

                            <div>
                                <label for="title" class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Onderwerp gesprek</label>
                                <input type="text" name="title" id="title" required placeholder="Bijv. Bespreken van de nieuwe checkout flow" class="w-full rounded-xl border border-gray-200 text-sm p-3 focus:border-[#011936] focus:ring-[#011936]">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="date" class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Datum</label>
                                    <input type="date" name="date" id="date" min="{{ date('Y-m-d') }}" required class="w-full rounded-xl border border-gray-200 text-sm p-3 focus:border-[#011936] focus:ring-[#011936] text-gray-600">
                                </div>
                                <div>
                                    <label for="time_slot" class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Tijdslot (~1 uur)</label>
                                    <select name="time_slot" id="time_slot" required class="w-full rounded-xl border border-gray-200 text-sm p-3 focus:border-[#011936] focus:ring-[#011936] text-gray-600 bg-white">
                                        <option value="09:00 - 10:00">09:00 - 10:00</option>
                                        <option value="10:00 - 11:00">10:00 - 11:00</option>
                                        <option value="11:00 - 12:00">11:00 - 12:00</option>
                                        <option value="13:00 - 14:00">13:00 - 14:00</option>
                                        <option value="14:00 - 15:00">14:00 - 15:00</option>
                                        <option value="15:00 - 16:00">15:00 - 16:00</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4 flex flex-col justify-between">
                            <div>
                                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Selecteer GKR Medewerker(s)</label>
                                <div class="space-y-3">
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
                                <textarea name="description" id="description" rows="4" maxlength="500" placeholder="Zijn er specifieke dingen die we moeten voorbereiden?" class="w-full rounded-xl border border-gray-200 text-sm p-3 focus:border-[#011936] focus:ring-[#011936]"></textarea>
                                <p class="text-right text-[10px] text-gray-400 mt-1">Max. 500 tekens</p>
                            </div>
                        </div>

                    </div>

                    <div class="mt-8 pt-4 border-t border-gray-100 flex items-center justify-end space-x-3">
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
        /* Highlight invoervelden die een fout bevatten */
        .is-invalid {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
        }
    </style>

    <script>
        function openAppointmentModal() {
            document.getElementById('appointmentModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeAppointmentModal() {
            document.getElementById('appointmentModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // Als Laravel validatiefouten terugstuuurt, open de modal dan direct opnieuw!
        @if ($errors->any())
            window.addEventListener('DOMContentLoaded', (event) => {
                openAppointmentModal();
            });
        @endif
    </script>
</x-app-layout>