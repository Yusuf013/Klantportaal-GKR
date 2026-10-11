{{--
    De lijst met gesprekken van de medewerker: één regel per klant.
    Staat in een eigen bestand, omdat de chat deze lijst ook tussendoor ververst
    (zonder de hele pagina te herladen). Zo is er maar één plek waar de opmaak staat.

    Nodig: $clients, $overview en (als er een gesprek open staat) $selectedClient.
    ALLEEN voor medewerkers van GKR.
--}}
<ul class="space-y-1">
    @forelse($clients as $client)
        @php
            $isSelected = isset($selectedClient) && $selectedClient && $selectedClient->id === $client->id;
            // mb_substr: knipt niet midden in een letter als é
            $initials = mb_strtoupper(mb_substr($client->name, 0, 2));
            // Ongelezen berichten en het laatste bericht van dit gesprek (komt uit de controller)
            $summary = ($overview ?? [])[$client->id] ?? null;
            $unread = $summary['unread'] ?? 0;
        @endphp
        <li data-client-id="{{ $client->id }}" data-unread="{{ $unread }}" data-search="{{ mb_strtolower($client->name . ' ' . $client->email) }}">
            <a href="{{ route('chat.index', ['client_id' => $client->id]) }}" 
               class="flex items-center gap-3 p-3 rounded-xl transition-all duration-150 {{ $isSelected ? 'bg-slate-100/80 shadow-sm' : 'hover:bg-slate-50' }}">

                <div class="w-9 h-9 rounded-full bg-slate-200 text-slate-700 font-bold text-xs flex items-center justify-center shrink-0">
                    {{ $initials }}
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2">
                        <h4 class="text-xs font-bold text-slate-900 truncate">{{ $client->name }}</h4>
                        @if($summary)
                            <span class="text-[10px] text-slate-400 shrink-0">{{ $summary['last_time'] }}</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between gap-2 mt-0.5">
                        {{-- Laatste bericht; nog geen berichten? Dan het e-mailadres --}}
                        <p class="text-[11px] truncate {{ $unread > 0 ? 'text-slate-900 font-semibold' : 'text-slate-400' }}">{{ $summary['preview'] ?? $client->email }}</p>
                        @if($unread > 0)
                            {{-- Zelfde rode teller als in de zijbalk, maar dan per klant --}}
                            <span class="inline-flex items-center justify-center px-2 py-0.5 text-[10px] font-bold leading-none text-white bg-red-500 rounded-full shrink-0" title="Ongelezen berichten">{{ $unread > 99 ? '99+' : $unread }}</span>
                        @endif
                    </div>
                </div>
            </a>
        </li>
    @empty
        <li class="p-4 text-xs text-slate-400 text-center">Geen klanten gevonden.</li>
    @endforelse
</ul>