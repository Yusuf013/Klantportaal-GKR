{{--
    Eén chatbericht.
    Staat in een eigen bestand, omdat de chat nieuwe berichten ook tussendoor ophaalt
    (zonder de hele pagina te herladen). De server maakt dan met ditzelfde bestand de
    opmaak van het nieuwe bericht. Zo is er maar één plek waar de opmaak staat, en gaat
    alle tekst van gebruikers altijd via {{ }} en @js(), dus nooit onbewerkt de pagina in.

    Nodig: $message en $showDate (true = datum boven dit bericht tonen).
--}}
@php
    $isMe = (int) $message->sender_id === (int) auth()->id();

    // Bij het eerste bericht van een dag tonen we de datum erboven
    $showDate = $showDate ?? false;
    $dateLabel = $message->created_at->isToday()
        ? 'Vandaag'
        : ($message->created_at->isYesterday() ? 'Gisteren' : $message->created_at->locale('nl')->isoFormat('D MMMM YYYY'));
    // Geen afzender meer? Dan was het een medewerker van GKR die niet meer bestaat
    $senderName = $message->sender->name ?? 'GKR';
    $senderInitials = mb_strtoupper(mb_substr($senderName, 0, 2));
@endphp

{{-- data-message-id: hiermee weet de chat welke berichten er al staan.
     data-mine: een eigen bericht (voor de leesstatus). --}}
<div data-message-id="{{ $message->id }}" data-mine="{{ $isMe ? '1' : '0' }}">
    @if($showDate)
        <div class="text-center mb-4">
            <span class="text-[9px] font-bold tracking-wider text-slate-400 uppercase bg-slate-100/80 px-2.5 py-0.5 rounded-full">{{ $dateLabel }}</span>
        </div>
    @endif
    <div class="flex gap-2.5 {{ $isMe ? 'flex-row-reverse' : 'flex-row' }} items-end">

        <div class="w-7 h-7 rounded-full {{ $isMe ? 'bg-[#011936] text-white' : 'bg-slate-200 text-slate-700' }} font-bold text-[9px] flex items-center justify-center shrink-0">
            {{ $isMe ? 'ME' : $senderInitials }}
        </div>

        <div class="flex flex-col {{ $isMe ? 'items-end' : 'items-start' }} max-w-[85%] md:max-w-xl">

            @if(!$isMe)
                <span class="text-[10px] font-bold text-slate-600 mb-0.5 pl-1">
                    {{ $senderName }} <span class="text-[9px] font-normal text-slate-400 ml-1">{{ $message->created_at->format('H:i') }}</span>
                </span>
            @endif

            {{-- Bericht Bubble --}}
            <div class="px-4 py-3 rounded-2xl text-xs leading-relaxed {{ $isMe ? 'bg-[#011936] text-white rounded-br-none shadow-sm' : 'bg-white border border-slate-200/80 text-slate-800 rounded-bl-none shadow-sm' }}">

                {{-- Tekstgedeelte --}}
                @if($message->body)
                    <p class="{{ $message->file_path ? 'mb-2.5' : '' }}">{{ $message->body }}</p>
                @endif

                {{-- Bijlage-kaart die de modal opent --}}
                @if($message->file_path)
                    @php
                        // Type van het OPGESLAGEN bestand (bepaald door Laravel), niet de naam van de gebruiker
                        $storedType = strtolower(pathinfo($message->file_path, PATHINFO_EXTENSION));
                        $previewUrl = \App\Http\Controllers\MessageController::canPreview($message->file_path)
                            ? route('chat.preview', $message)
                            : null;
                    @endphp
                    {{-- AANGEPAST (XSS-fix): alle waarden via @js() in plaats van '{{ }}'.
                         De bestandsnaam en de naam van de afzender kiest de gebruiker zelf;
                         met een apostrof erin kon je uit de JavaScript-tekst breken.
                         Het voorbeeld gaat nu via de beveiligde route, niet meer via /storage/... --}}
                    <div onclick="openDocumentModal(@js($message->file_name ?? 'Document'), @js($message->formatted_file_size), @js($senderName), @js($message->created_at->format('d M')), @js(route('chat.download', $message)), @js($previewUrl), @js($storedType), @js($message->body))" 
                         class="flex items-center justify-between gap-4 p-3 bg-white rounded-xl border border-slate-200/80 shadow-sm hover:shadow-md transition-all cursor-pointer group my-0.5">

                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-lg bg-red-50 text-red-500 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                            </div>

                            <div class="min-w-0">
                                <p class="text-xs font-bold text-slate-800 truncate group-hover:text-indigo-600 transition-colors">
                                    {{ $message->file_name ?? 'Document' }}
                                </p>
                                <p class="text-[10px] text-slate-400 mt-0.5">
                                    {{ $message->formatted_file_size }} • {{ strtoupper($storedType ?: 'bestand') }}
                                </p>
                            </div>
                        </div>

                        <div class="text-slate-400 group-hover:text-slate-700 transition-colors shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </div>
                    </div>
                @endif

            </div>

            {{-- Tijd + Gelezen status voor eigen berichten --}}
            @if($isMe)
                <div class="flex items-center gap-1 text-[9px] text-slate-400 mt-0.5 pr-1">
                    <span>{{ $message->created_at->format('H:i') }}</span>
                    <span>•</span>
                    {{-- data-read-status: de chat zet dit om naar "Gelezen" zodra de ander het bericht opent --}}
                    <span data-read-status class="{{ $message->read_at ? 'text-slate-600 font-semibold' : 'text-slate-400' }}">{{ $message->read_at ? 'Gelezen' : 'Verzonden' }}</span>
                </div>
            @endif
        </div>
    </div>
</div>