{{--
    Alle versies van een document, nieuwste eerst. Gebruikt op de documentpagina van de
    klant en die van GKR.

    Nodig:
    - $document  met de relaties versions en currentVersion geladen
    - $forAdmin  true op de pagina van GKR: dan staat er ook wie de versie uploadde
--}}
@php
    $forAdmin = $forAdmin ?? false;
    $currentId = $document->currentVersion?->id;
@endphp

@if($document->versions->isNotEmpty())
    <div id="versies" class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-gray-900 text-lg">Versies</h3>
            <span class="text-[11px] font-bold px-2.5 py-1 rounded-md bg-blue-50 text-blue-600 border border-blue-100">
                {{ $document->versions->count() }} {{ $document->versions->count() === 1 ? 'versie' : 'versies' }}
            </span>
        </div>

        <div class="divide-y divide-gray-100">
            @foreach($document->versions as $version)
                @php $isCurrent = $version->id === $currentId; @endphp
                <div class="py-3 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-[#011936] flex flex-wrap items-center gap-2">
                            Versie {{ $version->version_number }}
                            @if($isCurrent)
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-gray-50 text-gray-600 border border-gray-200">Huidig</span>
                            @endif
                            @if($version->approved_at)
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-green-50 text-green-700 border border-green-200">
                                    Goedgekeurd op {{ $version->approved_at->timezone(config('app.timezone'))->format('d-m-Y H:i') }}
                                </span>
                            @endif
                        </p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $version->created_at?->timezone(config('app.timezone'))->format('d-m-Y H:i') }}
                            @if($forAdmin && $version->uploader)
                                · geüpload door {{ $version->uploader->name }}
                            @endif
                        </p>
                        @if($version->change_note)
                            {{-- Tekst altijd ge-escaped; regeleinden blijven zichtbaar via CSS --}}
                            <p class="text-xs text-gray-600 mt-1" style="white-space: pre-line; overflow-wrap: anywhere;">{{ $version->change_note }}</p>
                        @endif
                    </div>

                    <a href="{{ route('documents.version.file', [$document, $version]) }}" target="_blank" rel="noopener"
                        class="shrink-0 text-xs text-[#011936] hover:underline font-semibold">
                        Bekijken &rarr;
                    </a>
                </div>
            @endforeach
        </div>
    </div>
@endif