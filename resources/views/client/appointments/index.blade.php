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

            @if(isset($appointmentProposal) && $appointmentProposal->options->count() > 0)
            <div id="proposal-banner-container" class="mb-8 bg-white border border-gray-150 rounded-2xl p-6 shadow-sm transition-all duration-500 ease-in-out relative">
                
                <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-5">
                    <div>
                        <p class="text-xs font-semibold text-slate-400 tracking-wide uppercase">
                            {{ $appointmentProposal->project->name ?? 'E-commerce Ontwikkeling' }}
                        </p>
                        <h2 class="text-xl font-bold text-[#011936] font-maven mt-0.5">
                            {{ $appointmentProposal->title }}
                        </h2>
                    </div>
                    <div>
                        <span class="inline-flex items-center px-3 py-1 bg-orange-50 border border-orange-100 text-orange-600 text-[10px] font-bold uppercase tracking-wider rounded-full">
                            Keuze maken
                        </span>
                    </div>
                </div>

                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Kies een datum en tijdstip</h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                    @foreach($appointmentProposal->options as $index => $option)
                        <label class="relative block cursor-pointer group">
                            <input type="radio" name="selected_proposal_option" value="{{ $option->id }}" class="sr-only peer" 
                                   onclick="selectProposalCard(this, {{ $appointmentProposal->id }}, {{ $option->id }}, '{{ \Carbon\Carbon::parse($option->start_time)->translatedFormat('j F Y') }}', '{{ \Carbon\Carbon::parse($option->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($option->end_time)->format('H:i') }}')">
                            
                            <div class="border border-gray-200 rounded-xl p-5 bg-white text-center transition-all duration-200 hover:border-[#011936] hover:shadow-md peer-checked:border-[#011936] peer-checked:bg-slate-50/50 peer-checked:ring-1 peer-checked:ring-[#011936]">
                                <span class="block text-sm font-bold text-gray-800 transition group-hover:text-[#011936]">
                                    {{ \Carbon\Carbon::parse($option->start_time)->translatedFormat('j F Y') }}
                                </span>
                                <span class="block text-xs font-semibold text-gray-400 mt-1">
                                    {{ \Carbon\Carbon::parse($option->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($option->end_time)->format('H:i') }} uur
                                </span>
                            </div>
                        </label>
                    @endforeach
                </div>

                <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                    <div class="flex items-center">
                        <div class="flex items-center -space-x-2">
                            @foreach($appointmentProposal->attendees as $emp)
                                <div class="w-8 h-8 rounded-full bg-[#011936] border-2 border-white flex items-center justify-center text-[10px] font-bold text-white uppercase tracking-wider" title="{{ $emp->name }}">
                                    {{ substr($emp->name, 0, 2) }}
                                </div>
                            @endforeach
                        </div>
                        <span class="text-[11px] text-gray-400 font-medium ml-3">
                            Met: {{ $appointmentProposal->attendees->pluck('name')->join(', ') }}
                        </span>
                    </div>

                    <div class="flex items-center space-x-6">
                        @if(isset($appointmentProposal))
                        <button type="button" onclick="openAlternativeDatePicker({{ $appointmentProposal->id }}, {{ json_encode($appointmentProposal->attendees->pluck('id')) }}, '{{ $appointmentProposal->attendees->pluck('name')->join(' en ') }}')" class="text-xs font-bold text-gray-650 underline hover:text-[#011936] transition cursor-pointer">
                            Past geen van de tijden?
                        </button>
                        @endif
                        
                        <button type="button" id="confirm-proposal-btn" disabled onclick="submitSelectedSlot()" class="px-5 py-2.5 bg-slate-400 text-white text-xs font-bold rounded-xl transition cursor-not-allowed shadow-sm">
                            Bevestigen
                        </button>
                    </div>
                </div>
            </div>
            @endif

            <div class="space-y-3 mb-8">
    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider pl-1">Eerstvolgende bevestigde afspraken</h3>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-150 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/30 text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                        <th class="p-4 pl-6 w-1/4">Projectnaam</th>
                        <th class="p-4">Afspraak titel</th>
                        <th class="p-4 w-44">Datum & Tijd</th>
                        <th class="p-4 w-32">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($appointments->where('status', 'Bevestigd') as $appointment)
                        <tr onclick="openClientDetailModal({{ $appointment->id }})" class="hover:bg-gray-50/50 transition duration-150 group cursor-pointer" title="Klik voor details">
                            <td class="p-4 pl-6 font-bold text-[#011936]">{{ $appointment->project->name ?? 'Geen project' }}</td>
                            <td class="p-4">
                                <div class="font-medium text-gray-700">{{ $appointment->title }}</div>
                                <div class="text-[11px] font-semibold text-gray-400 uppercase mt-0.5 tracking-wider">
                                    {{ ucfirst($appointment->type) }}
                                </div>
                            </td>
                            <td class="p-4 text-gray-650 font-medium">
                                <div>{{ $appointment->start_time->translatedFormat('d M Y') }}</div>
                                <div class="text-xs text-gray-400 mt-0.5 font-normal">{{ $appointment->start_time->format('H:i') }} - {{ $appointment->end_time->format('H:i') }} uur</div>
                            </td>
                            <td class="p-4">
                                <span class="inline-flex items-center text-[11px] font-bold px-2.5 py-1 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-100 uppercase tracking-wider">Bevestigd</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-8 text-center text-xs text-gray-400 italic">U heeft momenteel geen definitief geplande afspraken staan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="space-y-3">
    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider pl-1">In afwachting van goedkeuring / Voorstellen</h3>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-150 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/30 text-[11px] font-bold text-gray-400 uppercase tracking-wider">
                        <th class="p-4 pl-6 w-1/4">Projectnaam</th>
                        <th class="p-4">Afspraak titel</th>
                        <th class="p-4 w-44">Voorkeursdatum</th>
                        <th class="p-4 w-32">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($appointments->whereIn('status', ['In afwachting', 'Voorstel']) as $appointment)
                        <tr onclick="openClientDetailModal({{ $appointment->id }})" class="hover:bg-gray-50/50 transition duration-150 group cursor-pointer" title="Klik voor details">
                            <td class="p-4 pl-6 font-bold text-[#011936]">{{ $appointment->project->name ?? 'Geen project' }}</td>
                            <td class="p-4">
                                <div class="font-medium text-gray-700">{{ $appointment->title }}</div>
                                <div class="text-[11px] font-semibold text-gray-400 uppercase mt-0.5 tracking-wider">{{ ucfirst($appointment->type) }}</div>
                            </td>
                            <td class="p-4 text-gray-650 font-medium">
                                <div>{{ $appointment->start_time->translatedFormat('d M Y') }}</div>
                                <div class="text-xs text-gray-400 mt-0.5 font-normal">
                                    @if($appointment->status === 'Voorstel')
                                        Tijd nog te bepalen
                                    @else
                                        {{ $appointment->start_time->format('H:i') }} - {{ $appointment->end_time->format('H:i') }} uur
                                    @endif
                                </div>
                            </td>
                            <td class="p-4">
                                <span class="inline-flex items-center text-[11px] font-bold px-2.5 py-1 rounded-md bg-amber-50 text-amber-700 border border-amber-100 uppercase tracking-wider">
                                    {{ $appointment->status === 'Voorstel' ? 'Voorstel Klant' : 'In afwachting' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-8 text-center text-xs text-gray-400 italic">Er zijn momenteel geen openstaande aanvragen of voorstellen.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

        </div>
    </div>

    <div id="appointmentModal" class="fixed inset-0 z-40 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div onclick="closeAppointmentModal()" class="fixed inset-0 transition-opacity bg-gray-900/40 backdrop-blur-sm" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full border border-gray-100">
                
                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-xl font-bold text-[#011936] font-maven">Nieuwe afspraak</h2>
                    <button type="button" onclick="closeAppointmentModal()" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <form action="{{ route('client.appointments.store') }}" method="POST" class="p-8">
                    @csrf
                    
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-8">
                        
                        <div class="md:col-span-7 space-y-6">
                            
                            <div>
                                <label for="project_id" class="block text-xs font-bold text-[#011936] uppercase tracking-wider mb-2">Voor welk project? *</label>
                                <select name="project_id" id="project_id" required class="w-full rounded-xl border border-gray-200 text-sm text-gray-700 p-3.5 focus:border-[#011936] focus:ring-[#011936] bg-gray-50/50 @error('project_id') is-invalid @enderror">
                                    <option value="">-- Selecteer uw project --</option>
                                    @foreach($myProjects as $myProject)
                                        <option value="{{ $myProject->id }}" {{ old('project_id') == $myProject->id ? 'selected' : '' }}>{{ $myProject->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-[#011936] uppercase tracking-wider mb-3">Afspraaktype *</label>
                                <div class="grid grid-cols-3 gap-4">
                                    <label class="flex flex-col items-center justify-center p-5 border border-gray-200 rounded-2xl cursor-pointer hover:bg-gray-50/50 transition bg-white text-center shadow-sm group">
                                        <input type="radio" name="type" value="telefoon" required class="sr-only">
                                        <svg class="w-6 h-6 text-gray-600 mb-2 group-hover:text-[#011936]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                        <span class="text-xs font-bold text-gray-700">Telefoon</span>
                                    </label>
                                    <label class="flex flex-col items-center justify-center p-5 border border-gray-200 rounded-2xl cursor-pointer hover:bg-gray-50/50 transition bg-white text-center shadow-sm group">
                                        <input type="radio" name="type" value="online" checked class="sr-only">
                                        <svg class="w-6 h-6 text-gray-600 mb-2 group-hover:text-[#011936]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                        <span class="text-xs font-bold text-gray-700">Online</span>
                                    </label>
                                    <label class="flex flex-col items-center justify-center p-5 border border-gray-200 rounded-2xl cursor-pointer hover:bg-gray-50/50 transition bg-white text-center shadow-sm group">
                                        <input type="radio" name="type" value="fysiek" class="sr-only">
                                        <svg class="w-6 h-6 text-gray-600 mb-2 group-hover:text-[#011936]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                        <span class="text-xs font-bold text-gray-700">Fysiek</span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label for="title" class="block text-xs font-bold text-[#011936] uppercase tracking-wider mb-2">Onderwerp *</label>
                                <div class="relative">
                                    <svg class="w-5 h-5 text-gray-400 absolute left-4 top-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    <input type="text" name="title" id="title" required value="{{ old('title') }}" placeholder="Bijv. Maandelijkse begrotingsreview" class="w-full rounded-xl border border-gray-200 text-sm pl-12 p-3.5 focus:border-[#011936] focus:ring-[#011936]">
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-xs font-bold text-[#011936] uppercase tracking-wider">Kies een datum *</label>
                                    <span class="text-[11px] text-gray-400 font-medium">Selecteer eerst een medewerker hiernaast</span>
                                </div>
                                
                                <div id="date_picker_trigger_button" class="w-full flex items-center justify-between border border-gray-200 rounded-xl p-3.5 bg-gray-100 opacity-60 cursor-not-allowed transition shadow-sm">
                                    <div class="flex items-center space-x-6 text-sm font-semibold text-gray-400">
                                        <span class="w-6 h-6 bg-gray-300 text-white flex items-center justify-center text-xs font-bold rounded-full">1</span>
                                        <div class="flex items-center space-x-2">
                                            <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            <span id="display_date_text" class="text-gray-400 font-medium">Selecteer een datum...</span>
                                        </div>
                                        <div class="flex items-center space-x-2 border-l border-gray-200 pl-6">
                                            <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            <span id="display_time_text" class="text-gray-400 font-medium">Kies tijdslot...</span>
                                        </div>
                                    </div>
                                    <button type="button" onclick="resetDateTime(event)" class="text-gray-300 hover:text-red-500 transition p-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-5 space-y-6 flex flex-col justify-between">
                            
                            <div class="space-y-4">
                                <div>
                                    <label for="emp_primary" class="block text-xs font-bold text-[#011936] uppercase tracking-wider mb-2">Eerste Medewerker *</label>
                                    <select name="employees[]" id="emp_primary" onchange="checkClientEmployeeAvailability()" class="w-full rounded-xl border border-gray-200 text-sm text-gray-700 p-3.5 focus:border-[#011936] focus:ring-[#011936] bg-gray-50/50">
                                        <option value="">-- Kies naam --</option>
                                        @foreach($gkrEmployees as $emp)
                                            <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="emp_secondary" class="block text-xs font-bold text-[#011936] uppercase tracking-wider mb-2">Extra Medewerker (Optioneel)</label>
                                    <select name="employees[]" id="emp_secondary" onchange="checkClientEmployeeAvailability()" class="w-full rounded-xl border border-gray-200 text-sm text-gray-700 p-3.5 focus:border-[#011936] focus:ring-[#011936] bg-gray-50/50">
                                        <option value="">-- Kies naam --</option>
                                        @foreach($gkrEmployees as $emp)
                                            <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label for="description" class="block text-xs font-bold text-[#011936] uppercase tracking-wider mb-2">Aanvullende opmerkingen</label>
                                <textarea name="description" id="description" rows="4" maxlength="500" oninput="updateCharCount(this)" placeholder="Beschrijf kort het doel van deze vergadering..." class="w-full rounded-2xl border border-gray-200 text-sm p-4 focus:border-[#011936] focus:ring-[#011936] bg-gray-50/20"></textarea>
                                <p class="text-right text-[10px] text-gray-400 mt-1"><span id="char-counter">0</span>/500</p>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="date" id="hidden_date" required>
                    <input type="hidden" name="time_slot" id="hidden_time_slot" required>

                    <div class="mt-8 pt-6 border-t border-gray-100 flex items-center justify-between">
                        <div class="flex items-center space-x-2 text-[11px] text-gray-400 font-medium">
                            <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            <span>Door dit verzoek in te dienen, gaat u akkoord met onze <span class="underline cursor-pointer">boekingsvoorwaarden</span>.</span>
                        </div>
                        <div class="flex items-center space-x-3 shrink-0">
                            <button type="button" onclick="closeAppointmentModal()" class="px-5 py-3 border border-gray-200 hover:bg-gray-50 text-gray-500 text-xs font-bold rounded-xl transition">
                                Annuleren
                            </button>
                            <button type="submit" class="px-6 py-3 bg-[#011936] hover:bg-[#011936]/90 text-white text-xs font-bold rounded-xl transition shadow-md flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                Afspraak aanvragen
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="datePickerModal" class="fixed inset-0 z-50 hidden overflow-y-auto flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div onclick="closeDatePickerModal()" class="fixed inset-0 transition-opacity bg-gray-950/20 backdrop-blur-[2px]" aria-hidden="true"></div>

        <div class="relative inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full border border-gray-100 grid grid-cols-1 md:grid-cols-12 z-50 h-[400px]">
            
            <div class="p-6 md:col-span-7 border-r border-gray-100 bg-white h-full flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-5">
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

                    <div id="calendarDays" class="grid grid-cols-7 gap-1.5 text-center text-xs font-semibold text-gray-700"></div>
                </div>

                <div class="flex items-center space-x-2 text-[11px] text-gray-500 pt-2">
                    <span class="w-1.5 h-1.5 bg-[#011936] rounded-full"></span>
                    <span>Beschikbare dagen</span>
                </div>
            </div>

            <div class="p-6 md:col-span-5 bg-gray-50/40 h-full flex flex-col justify-between">
                <div class="flex flex-col h-full overflow-hidden">
                    <div class="flex items-center justify-between mb-1 shrink-0">
                        <h4 class="text-sm font-bold text-[#011936]">Beschikbare tijden</h4>
                        <button type="button" onclick="closeDatePickerModal()" class="text-gray-400 hover:text-gray-600 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <p id="selectedDateHuman" class="text-[11px] text-gray-400 font-medium mb-4 shrink-0">Selecteer een datum</p>

                    <div id="timeSlotsContainer" class="space-y-2 overflow-y-auto pr-1 flex-1 flex flex-col max-h-[250px]">
                        <p class="text-xs text-gray-400 italic py-4 text-center my-auto">Kies links een beschikbare dag.</p>
                    </div>
                </div>

                <div class="flex items-center space-x-2 text-[10px] text-gray-400 pt-3 border-t border-gray-100 mt-3 shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Gesprekken zijn ~1 uur</span>
                </div>
            </div>

        </div>
    </div>

    <div id="clientDetailModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true">
        <div onclick="closeClientDetailModal()" class="fixed inset-0 bg-gray-950/40 backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl sm:max-w-md w-full border border-gray-100 z-50 overflow-hidden transform transition-all">
            
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                <h3 id="client_modal_status" class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md border"></h3>
                <button type="button" onclick="closeClientDetailModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div>
                    <h4 id="client_modal_title" class="text-base font-bold text-[#011936] font-maven"></h4>
                    <p id="client_modal_datetime" class="text-xs text-gray-400 font-semibold mt-1"></p>
                </div>

                <div class="border-t border-gray-100 pt-4 grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="block text-gray-400 font-bold uppercase tracking-wider text-[10px]">Project</span>
                        <span id="client_modal_project" class="text-[#011936] font-semibold"></span>
                    </div>
                    <div>
                        <span class="block text-gray-400 font-bold uppercase tracking-wider text-[10px]">Type Gesprek</span>
                        <span id="client_modal_type" class="text-gray-700 font-semibold capitalize"></span>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-4">
                    <span class="block text-gray-400 font-bold uppercase tracking-wider text-[10px] text-xs mb-2">Jouw GKR Contactpersonen</span>
                    <div id="client_modal_attendees" class="flex flex-wrap gap-1.5"></div>
                </div>

                <div id="client_modal_desc_wrapper" class="border-t border-gray-100 pt-4 hidden">
                    <span class="block text-gray-400 font-bold uppercase tracking-wider text-[10px] text-xs mb-1">Ingevulde opmerking</span>
                    <p id="client_modal_description" class="text-xs text-gray-600 bg-gray-50 p-3 rounded-xl italic border border-gray-100"></p>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/30 flex justify-end">
                <button type="button" onclick="closeClientDetailModal()" class="px-4 py-2 bg-[#011936] text-white text-xs font-bold rounded-xl shadow-sm hover:bg-[#011936]/90 transition">Sluiten</button>
            </div>
        </div>
    </div>

    <style>
        label:has(input:checked) {
            border-color: #011936 !important;
            background-color: rgb(1 25 54 / 0.04) !important;
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
    const dbAppointments = @json($appointments);

    // Geheugen voor het geselecteerde voorstel van de klant
    let currentSelection = {
        appointmentId: null,
        optionId: null,
        readableDate: '',
        timeSlot: ''
    };

    function openAppointmentModal() {
        document.getElementById('appointmentModal').classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeAppointmentModal() {
        document.getElementById('appointmentModal').classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    // --- UX LOGICA VOOR HET KIEZEN VAN EEN VOORSTEL-MOMENT (HANLY VU TRANG STIJL) ---
    function selectProposalCard(input, appointmentId, optionId, readableDate, timeSlot) {
        currentSelection.appointmentId = appointmentId;
        currentSelection.optionId = optionId;
        currentSelection.readableDate = readableDate;
        currentSelection.timeSlot = timeSlot;

        // Schakel de Bevestigingsknop in en verander styling naar de donkerblauwe GKR-kleur
        const btn = document.getElementById('confirm-proposal-btn');
        if (btn) {
            btn.disabled = false;
            btn.className = "px-5 py-2.5 bg-[#011936] hover:bg-[#011936]/90 text-white text-xs font-bold rounded-xl transition shadow-sm cursor-pointer transform active:scale-95";
        }
    }

    // Custom Bevestigingsmodal openen en vullen met data
    function submitSelectedSlot() {
        if (!currentSelection.optionId) return;

        // Vul de teksten in de custom modal met de actieve selectie data
        document.getElementById('modalTargetDate').innerText = currentSelection.readableDate;
        document.getElementById('modalTargetTimeText').innerText = currentSelection.timeSlot;

        const modal = document.getElementById('customConfirmModal');
        const box = document.getElementById('confirmModalBox');
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Timeout voor een vlekkeloze css fade/scale transitie
        setTimeout(() => {
            box.classList.remove('scale-95', 'opacity-0');
            box.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function closeCustomConfirmModal() {
        const modal = document.getElementById('customConfirmModal');
        const box = document.getElementById('confirmModalBox');
        
        box.classList.remove('scale-100', 'opacity-100');
        box.classList.add('scale-95', 'opacity-0');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 150);
    }

    // De daadwerkelijke AJAX-verwerking na akkoord in de modal
    function executeSlotConfirmation() {
        const executeBtn = document.getElementById('modalExecuteBtn');
        const btnText = document.getElementById('btnText');
        
        // Blokkeer de knop live tegen dubbele subits
        executeBtn.disabled = true;
        btnText.innerText = "Verwerken...";

        fetch(`/appointments/${currentSelection.appointmentId}/confirm-slot`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                "Accept": "application/json"
            },
            body: JSON.stringify({ option_id: currentSelection.optionId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                closeCustomConfirmModal();
                const banner = document.getElementById('proposal-banner-container');
                if (banner) {
                    banner.style.opacity = '0';
                }
                setTimeout(() => { window.location.reload(); }, 300);
            } else {
                alert(data.message || "Er is iets misgegaan bij het verwerken van uw keuze.");
                executeBtn.disabled = false;
                btnText.innerText = "Afspraak vastleggen";
            }
        })
        .catch(err => {
            console.error("Error confirming slot:", err);
            executeBtn.disabled = false;
            btnText.innerText = "Afspraak vastleggen";
        });
    }

    // --- ALGEMENE DATUMPICKER EN DETAIL MODAL CONTROLS ---
    function openDatePickerModal() {
        const picker = document.getElementById('datePickerModal');
        picker.classList.remove('hidden');
        picker.classList.add('flex'); 
        initCalendar();
    }

    function closeDatePickerModal() {
        const picker = document.getElementById('datePickerModal');
        picker.classList.add('hidden');
        picker.classList.remove('flex');
    }

    function openClientDetailModal(appointmentId) {
    const app = dbAppointments.find(a => a.id === appointmentId);
    if (!app) return;

    document.getElementById('client_modal_title').innerText = app.title;
    
    // --- TIJDZONE-PROOF DATUM EN TIJD PARSER ---
    if (app.status === 'Voorstel') {
        document.getElementById('client_modal_datetime').innerText = "Datum nog te bepalen (zie openstaand voorstel)";
    } else {
        // Maak een Date-object van de binnenkomende string
        let dateObj = new Date(app.start_time);

        // CHECK: Als Laravel er een UTC-string van heeft gemaakt, compenseert de browser 
        // dit lokaal. Als dat misgaat, dwingen we hier de juiste lokale uren af:
        let hoursStart = String(dateObj.getHours()).padStart(2, '0');
        let minutesStart = String(dateObj.getMinutes()).padStart(2, '0');
        
        // Mocht de browser alsnog verschuiven, kijken we naar de rauwe tekst in de string:
        if (app.start_time.includes('Z') || app.start_time.includes('+')) {
            // Als er een tijdzone-indicator in zit, klopt dateObj.getHours() direct via de browser
        } else if (app.start_time.includes('T')) {
            // Als er een T in zit zonder tijdzone (bijv: 2026-06-30T09:00:00.000000Z)
            const timePart = app.start_time.split('T')[1];
            hoursStart = timePart.substring(0, 2);
            minutesStart = timePart.substring(3, 5);
        }

        // Bepaal de eindtijd op exact dezelfde, veilige manier
        let hoursEnd = '00';
        let minutesEnd = '00';
        if (app.end_time) {
            let endDateObj = new Date(app.end_time);
            hoursEnd = String(endDateObj.getHours()).padStart(2, '0');
            minutesEnd = String(endDateObj.getMinutes()).padStart(2, '0');
            
            if (!app.end_time.includes('Z') && !app.end_time.includes('+') && app.end_time.includes('T')) {
                const endTimePart = app.end_time.split('T')[1];
                hoursEnd = endTimePart.substring(0, 2);
                minutesEnd = endTimePart.substring(3, 5);
            }
        }

        // Genereer de Nederlandse datum (dag en maand voluit)
        // We splitsen de pure datum om verschuiving naar de vorige dag te voorkomen!
        const pureDateStr = app.start_time.split(/[\sT]+/)[0];
        const [year, month, day] = pureDateStr.split('-');
        const localDate = new Date(year, month - 1, day);
        
        const humanDate = localDate.toLocaleDateString('nl-NL', { 
            weekday: 'long', 
            day: 'numeric', 
            month: 'long', 
            year: 'numeric' 
        });

        document.getElementById('client_modal_datetime').innerText = `${humanDate} om ${hoursStart}:${minutesStart} - ${hoursEnd}:${minutesEnd} uur`;
    }
    // --- EINDE DATUM REPARATIE ---

    document.getElementById('client_modal_project').innerText = app.project ? app.project.name : 'Algemeen';
    document.getElementById('client_modal_type').innerText = app.type || 'Online';

    const statusLabel = document.getElementById('client_modal_status');
    statusLabel.innerText = app.status;
    statusLabel.className = "text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md border ";
    if (app.status === 'Bevestigd') {
        statusLabel.classList.add('bg-emerald-50', 'text-emerald-700', 'border-emerald-100');
    } else if (app.status === 'In afwachting') {
        statusLabel.classList.add('bg-amber-50', 'text-amber-700', 'border-amber-100');
    } else {
        statusLabel.classList.add('bg-gray-50', 'text-gray-700', 'border-gray-150');
    }

    const descWrapper = document.getElementById('client_modal_desc_wrapper');
    if (app.description && app.description.trim() !== "") {
        document.getElementById('client_modal_description').innerText = app.description;
        descWrapper.classList.remove('hidden');
    } else {
        descWrapper.classList.add('hidden');
    }

    const attendeesContainer = document.getElementById('client_modal_attendees');
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
        attendeesContainer.innerHTML = `<span class="text-xs text-gray-400 italic">Nog geen medewerker toegewezen</span>`;
    }

    const modal = document.getElementById('clientDetailModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

    function closeClientDetailModal() {
        const modal = document.getElementById('clientDetailModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function updateCharCount(textarea) {
        document.getElementById('char-counter').innerText = textarea.value.length;
    }

    function resetDateTime(event) {
        event.stopPropagation(); 
        document.getElementById('hidden_date').value = "";
        document.getElementById('hidden_time_slot').value = "";
        document.getElementById('display_date_text').innerText = "Selecteer een datum...";
        document.getElementById('display_date_text').classList.add('text-gray-400');
        document.getElementById('display_time_text').innerText = "Kies tijdslot...";
        document.getElementById('display_time_text').classList.add('text-gray-400');
        selectedDateStr = "";
        
        const container = document.getElementById('timeSlotsContainer');
        container.classList.add('justify-center');
        container.innerHTML = `<p class="text-xs text-gray-400 italic py-4 text-center my-auto">Kies links een beschikbare dag.</p>`;
        document.getElementById('selectedDateHuman').innerText = "Selecteer een datum";
    }

    // --- INTERNE AGENDA AGENDA LOGICA ---
    let currentNavDate = new Date();
    let selectedDateStr = "";

    const monthsNl = ["januari", "februari", "maart", "april", "mei", "juni", "juli", "augustus", "september", "oktober", "november", "december"];
    const standardSlots = ["09:00 - 10:00", "10:00 - 11:00", "11:00 - 12:00", "13:00 - 14:00", "14:00 - 15:00", "15:00 - 16:00", "16:00 - 17:00"];

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
        
        const options = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
        const formattedHuman = dateObj.toLocaleDateString('nl-NL', options);
        document.getElementById('selectedDateHuman').innerText = formattedHuman;
        
        fetchAvailableSlots(dateStr, formattedHuman);
    }

    function checkClientEmployeeAvailability() {
        const selects = document.querySelectorAll('select[name="employees[]"]');
        const selectedValues = Array.from(selects).map(s => s.value).filter(val => val !== "");

        const triggerBtn = document.getElementById('date_picker_trigger_button');
        const triggerIconText = triggerBtn.querySelector('.flex');

        if (selectedValues.length > 0) {
            triggerBtn.classList.remove('bg-gray-100', 'opacity-60', 'cursor-not-allowed');
            triggerBtn.classList.add('bg-gray-50/30', 'cursor-pointer', 'hover:bg-gray-50');
            triggerIconText.classList.remove('text-gray-400');
            triggerIconText.classList.add('text-gray-700');
            triggerBtn.querySelector('.rounded-full').classList.remove('bg-gray-300');
            triggerBtn.querySelector('.rounded-full').classList.add('bg-[#011936]');
            
            triggerBtn.onclick = openDatePickerModal;
            
            const currentHiddenDate = document.getElementById('hidden_date').value;
            if(currentHiddenDate) {
                const dateObj = new Date(currentHiddenDate);
                const formattedHuman = dateObj.toLocaleDateString('nl-NL', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
                fetchAvailableSlots(currentHiddenDate, formattedHuman);
            }
        } else {
            triggerBtn.classList.add('bg-gray-100', 'opacity-60', 'cursor-not-allowed');
            triggerBtn.classList.remove('bg-gray-50/30', 'cursor-pointer', 'hover:bg-gray-50');
            triggerIconText.classList.add('text-gray-400');
            triggerIconText.classList.remove('text-gray-700');
            triggerBtn.querySelector('.rounded-full').classList.add('bg-gray-300');
            triggerBtn.querySelector('.rounded-full').classList.remove('bg-[#011936]');
            
            triggerBtn.onclick = null;
            resetDateTime(new Event('click'));
        }
    }

    function fetchAvailableSlots(dateStr, formattedHuman) {
        const container = document.getElementById('timeSlotsContainer');
        container.classList.remove('justify-center');
        container.innerHTML = "";

        const selects = document.querySelectorAll('select[name="employees[]"]');
        const selectedEmployeeIds = Array.from(selects).map(s => s.value).filter(val => val !== "");

        standardSlots.forEach(slot => {
            const slotBtn = document.createElement('button');
            slotBtn.type = "button";
            slotBtn.innerText = slot;
            slotBtn.className = "time-slot-btn w-full text-left p-3 border border-gray-200 rounded-xl text-xs font-bold text-[#011936] hover:bg-gray-50 transition bg-white flex items-center justify-between shadow-sm";
            
            const statusSpan = document.createElement('span');
            statusSpan.className = "text-[10px] uppercase font-bold text-gray-400 tracking-wider";
            statusSpan.innerText = "Checken...";
            slotBtn.appendChild(statusSpan);
            container.appendChild(slotBtn);

            let conflictFound = false;
            let checksCompleted = 0;

            selectedEmployeeIds.forEach(empId => {
                fetch("{{ route('client.appointments.check') }}", {
                    method: "POST",
                    headers: { 
                        "Content-Type": "application/json", 
                        "Accept": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('input[name="_token"]').value
                    },
                    body: JSON.stringify({ employee_id: empId, date: dateStr, time_slot: slot })
                })
                .then(res => res.json())
                .then(data => {
                    checksCompleted++;
                    if (data.status === 'conflict') {
                        conflictFound = true;
                    }

                    if (checksCompleted === selectedEmployeeIds.length) {
                        if (conflictFound) {
                            slotBtn.disabled = true;
                            slotBtn.className = "w-full text-left p-3 border border-gray-100 bg-gray-50 text-gray-300 rounded-xl text-xs font-semibold flex items-center justify-between cursor-not-allowed opacity-60";
                            statusSpan.className = "text-[10px] text-red-500 font-bold tracking-wider";
                            statusSpan.innerText = "BEZET";
                        } else {
                            statusSpan.className = "text-[10px] text-emerald-600 font-bold tracking-wider";
                            statusSpan.innerText = "VRIJ";
                            
                            slotBtn.onclick = () => {
                                document.querySelectorAll('.time-slot-btn').forEach(b => b.classList.remove('active'));
                                slotBtn.classList.add('active');
                                document.getElementById('hidden_time_slot').value = slot;
                                
                                const dateDisplay = document.getElementById('display_date_text');
                                dateDisplay.innerText = formattedHuman;
                                dateDisplay.classList.remove('text-gray-400');
                                
                                const timeDisplay = document.getElementById('display_time_text');
                                timeDisplay.innerText = slot;
                                timeDisplay.classList.remove('text-gray-400');

                                setTimeout(closeDatePickerModal, 200);
                            };
                        }
                    }
                })
                .catch(err => {
                    console.error("Fout tijdens check:", err);
                    checksCompleted++;
                });
            });
        });
    }

    @if ($errors->any())
        window.addEventListener('DOMContentLoaded', () => { 
            openAppointmentModal(); 
        });
    @endif



    // --- LOGICA VOOR HET INDIENEN VAN EEN ALTERNATIEF VOORSTEL ---
let altCalendarNavDate = new Date();
let altSelectedDateStr = "";
let altSelectedSlotStr = "";

let altProposalConfig = {
    appointmentId: null,
    employeeIds: [],
    employeeNames: ""
};

function openAlternativeDatePicker(appointmentId, employeeIds, employeeNames) {
    altProposalConfig.appointmentId = appointmentId;
    altProposalConfig.employeeIds = employeeIds;
    altProposalConfig.employeeNames = employeeNames;

    // Reset selecties
    altSelectedDateStr = "";
    altSelectedSlotStr = "";
    
    const submitBtn = document.getElementById('submitAltSlotBtn');
    submitBtn.disabled = true;
    submitBtn.className = "w-full py-2.5 bg-gray-300 text-gray-500 text-xs font-bold rounded-xl transition cursor-not-allowed text-center shadow-sm";

    // Update legenda tekst onder de kalender
    document.getElementById('altAttendeeLegendText').innerText = `Beschikbare dagen van ${employeeNames}`;

    document.getElementById('alternativeDatePickerModal').classList.remove('hidden');
    document.getElementById('alternativeDatePickerModal').classList.add('flex');
    
    initAltCalendar();
}

function closeAlternativeDatePickerModal() {
    document.getElementById('alternativeDatePickerModal').classList.add('hidden');
    document.getElementById('alternativeDatePickerModal').classList.remove('flex');
}

function initAltCalendar() {
    renderAltCalendar();
    document.getElementById('altPrevMonth').onclick = () => { altCalendarNavDate.setMonth(altCalendarNavDate.getMonth() - 1); renderAltCalendar(); };
    document.getElementById('altNextMonth').onclick = () => { altCalendarNavDate.setMonth(altCalendarNavDate.getMonth() + 1); renderAltCalendar(); };
}

function renderAltCalendar() {
    const year = altCalendarNavDate.getFullYear();
    const month = altCalendarNavDate.getMonth();
    
    document.getElementById('altCurrentMonthYear').innerText = `${monthsNl[month]} ${year}`;
    
    const firstDayIndex = (new Date(year, month, 1).getDay() + 6) % 7; 
    const lastDay = new Date(year, month + 1, 0).getDate();
    
    const daysContainer = document.getElementById('altCalendarDays');
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
            
            if (dateStr === altSelectedDateStr) {
                dayBtn.classList.add('active');
            }
            
            dayBtn.onclick = () => {
                document.querySelectorAll('#altCalendarDays .calendar-day-btn').forEach(b => b.classList.remove('active'));
                dayBtn.classList.add('active');
                
                altSelectedDateStr = dateStr;
                const options = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
                const formattedHuman = checkDate.toLocaleDateString('nl-NL', options);
                document.getElementById('altSelectedDateHuman').innerText = formattedHuman;
                
                fetchAltAvailableSlots(dateStr, formattedHuman);
            };
        }
        daysContainer.appendChild(dayBtn);
    }
}

function fetchAltAvailableSlots(dateStr, formattedHuman) {
    const container = document.getElementById('altTimeSlotsContainer');
    container.innerHTML = "";

    standardSlots.forEach(slot => {
        const slotBtn = document.createElement('button');
        slotBtn.type = "button";
        slotBtn.innerText = slot;
        slotBtn.className = "time-slot-btn w-full text-left p-3 border border-gray-200 rounded-xl text-xs font-bold text-[#011936] hover:bg-gray-50 transition bg-white flex items-center justify-between shadow-sm";
        
        const statusSpan = document.createElement('span');
        statusSpan.className = "text-[10px] uppercase font-bold text-gray-400 tracking-wider";
        statusSpan.innerText = "Checken...";
        slotBtn.appendChild(statusSpan);
        container.appendChild(slotBtn);

        let conflictFound = false;
        let checksCompleted = 0;

        altProposalConfig.employeeIds.forEach(empId => {
            fetch("{{ route('client.appointments.check') }}", {
                method: "POST",
                headers: { 
                    "Content-Type": "application/json", 
                    "Accept": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('input[name="_token"]').value
                },
                body: JSON.stringify({ employee_id: empId, date: dateStr, time_slot: slot })
            })
            .then(res => res.json())
            .then(data => {
                checksCompleted++;
                if (data.status === 'conflict') {
                    conflictFound = true;
                }

                if (checksCompleted === altProposalConfig.employeeIds.length) {
                    if (conflictFound) {
                        slotBtn.disabled = true;
                        slotBtn.className = "w-full text-left p-3 border border-gray-100 bg-gray-50 text-gray-300 rounded-xl text-xs font-semibold flex items-center justify-between cursor-not-allowed opacity-60";
                        statusSpan.className = "text-[10px] text-red-500 font-bold tracking-wider";
                        statusSpan.innerText = "BEZET";
                    } else {
                        statusSpan.className = "text-[10px] text-emerald-600 font-bold tracking-wider";
                        statusSpan.innerText = "VRIJ";
                        
                        if (slot === altSelectedSlotStr) {
                            slotBtn.classList.add('active');
                        }

                        slotBtn.onclick = () => {
                            document.querySelectorAll('#altTimeSlotsContainer .time-slot-btn').forEach(b => b.classList.remove('active'));
                            slotBtn.classList.add('active');
                            altSelectedSlotStr = slot;
                            
                            // Activeer de hoofdknop onderin de modal
                            const submitBtn = document.getElementById('submitAltSlotBtn');
                            submitBtn.disabled = false;
                            submitBtn.className = "w-full py-2.5 bg-[#011936] hover:bg-[#011936]/90 text-white text-xs font-bold rounded-xl transition shadow-sm cursor-pointer transform active:scale-95 text-center";
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

function executeAlternativeSlotSubmit() {
    if (!altSelectedDateStr || !altSelectedSlotStr) return;

    const submitBtn = document.getElementById('submitAltSlotBtn');
    submitBtn.disabled = true;
    submitBtn.innerText = "Verzenden...";

    // We schieten een POST-request naar een nieuwe endpoint om het alternatief op te slaan
    fetch(`/appointments/${altProposalConfig.appointmentId}/suggest-alternative`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            "Accept": "application/json"
        },
        body: JSON.stringify({ 
            date: altSelectedDateStr, 
            time_slot: altSelectedSlotStr 
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            closeAlternativeDatePickerModal();
            window.location.reload();
        } else {
            alert(data.message || "Er is iets misgegaan.");
            submitBtn.disabled = false;
            submitBtn.innerText = "Voorstel indienen";
        }
    })
    .catch(err => {
        console.error("Error submitting alternative slot:", err);
        submitBtn.disabled = false;
        submitBtn.innerText = "Voorstel indienen";
    });
}
</script>


    <div id="customConfirmModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true">
    <div onclick="closeCustomConfirmModal()" class="fixed inset-0 bg-gray-950/20 backdrop-blur-[3px] transition-opacity"></div>
    
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full border border-gray-100 p-6 z-50 transform transition-all scale-95 opacity-0 duration-300 ease-out" id="confirmModalBox">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-[#011936]/5 mb-4 text-[#011936]">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
            
            <h3 class="text-base font-bold text-[#011936] font-maven">Afspraak definitief inplannen?</h3>
            <p class="text-xs text-gray-400 mt-1.5 leading-relaxed">
                Je staat op het punt om de afspraak definitief vast te leggen op de volgende datum:
            </p>
            
            <div class="mt-4 p-3.5 bg-gray-50 rounded-xl border border-gray-150 text-left">
                <p id="modalTargetDate" class="text-xs font-bold text-gray-800"></p>
                <p id="modalTargetTime" class="text-[11px] font-semibold text-gray-400 mt-0.5 uppercase tracking-wider flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span id="modalTargetTimeText"></span> uur
                </p>
            </div>
        </div>
        
        <div class="mt-6 flex items-center space-x-3">
            <button type="button" onclick="closeCustomConfirmModal()" class="w-1/2 py-2.5 border border-gray-200 hover:bg-gray-50 text-gray-500 text-xs font-bold rounded-xl transition">
                Annuleren
            </button>
            <button type="button" id="modalExecuteBtn" onclick="executeSlotConfirmation()" class="w-1/2 py-2.5 bg-[#011936] hover:bg-[#011936]/90 text-white text-xs font-bold rounded-xl transition shadow-md flex items-center justify-center">
                <span id="btnText">Afspraak vastleggen</span>
            </button>
        </div>
    </div>
</div>

<div id="alternativeDatePickerModal" class="fixed inset-0 z-50 hidden overflow-y-auto flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div onclick="closeAlternativeDatePickerModal()" class="fixed inset-0 transition-opacity bg-gray-950/20 backdrop-blur-[2px]" aria-hidden="true"></div>

    <div class="relative inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full border border-gray-100 grid grid-cols-1 md:grid-cols-12 z-50 h-[450px]">
        
        <div class="p-6 md:col-span-7 border-r border-gray-100 bg-white h-full flex flex-col justify-between">
            <div>
                <div class="text-center mb-4">
                    <h3 class="text-base font-bold text-[#011936] font-maven">Geen van de tijden past?</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Kies hieronder een datum en tijdstip dat jou beter uitkomt.</p>
                </div>

                <div class="flex items-center justify-between mb-4">
                    <span id="altCurrentMonthYear" class="text-sm font-bold text-[#011936] capitalize"></span>
                    <div class="flex space-x-2 text-gray-400">
                        <button type="button" id="altPrevMonth" class="hover:text-[#011936] transition p-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path></svg>
                        </button>
                        <button type="button" id="altNextMonth" class="hover:text-[#011936] transition p-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">
                    <div>Ma</div><div>Di</div><div>Wo</div><div>Do</div><div>Vr</div><div>Za</div><div>Zo</div>
                </div>

                <div id="altCalendarDays" class="grid grid-cols-7 gap-1.5 text-center text-xs font-semibold text-gray-700"></div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                <div class="flex items-center space-x-2 text-[10px] text-gray-500">
                    <span class="w-1.5 h-1.5 bg-[#011936] rounded-full"></span>
                    <span id="altAttendeeLegendText">Beschikbare dagen</span>
                </div>
            </div>
        </div>

        <div class="p-6 md:col-span-5 bg-gray-50/40 h-full flex flex-col justify-between">
            <div class="flex flex-col h-full overflow-hidden">
                <div class="flex items-center justify-between mb-1 shrink-0">
                    <h4 class="text-sm font-bold text-[#011936]">Beschikbare tijden</h4>
                    <button type="button" onclick="closeAlternativeDatePickerModal()" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <p id="altSelectedDateHuman" class="text-[11px] text-gray-400 font-medium mb-4 shrink-0">Selecteer een datum</p>

                <div id="altTimeSlotsContainer" class="space-y-2 overflow-y-auto pr-1 flex-1 flex flex-col max-h-[220px]">
                    <p class="text-xs text-gray-400 italic py-4 text-center my-auto">Kies links een beschikbare dag.</p>
                </div>
            </div>

            <div class="pt-3 border-t border-gray-100 space-y-2 shrink-0">
                <button type="button" id="submitAltSlotBtn" disabled onclick="executeAlternativeSlotSubmit()" class="w-full py-2.5 bg-gray-300 text-gray-500 text-xs font-bold rounded-xl transition cursor-not-allowed text-center shadow-sm">
                    Voorstel indienen
                </button>
                <button type="button" onclick="closeAlternativeDatePickerModal()" class="w-full py-2 text-center text-xs font-bold text-gray-400 hover:text-gray-600 transition">
                    Annuleren
                </button>
            </div>
        </div>

    </div>
</div>

</x-app-layout>