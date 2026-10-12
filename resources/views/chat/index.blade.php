<x-app-layout>
    {{-- Wrapper die precies de beschikbare hoogte van het venster vult, zodat de typbalk
         altijd zichtbaar is zonder te scrollen. Van de schermhoogte gaat af:
         de navigatiebalk bovenin (4rem = 64px) en de ruimte van <main> erboven en eronder
         (p-6 = 2 x 1,5rem = 3rem). Samen 7rem.
         100dvh is de hoogte die echt zichtbaar is op een telefoon (zonder de adresbalk);
         browsers die dat niet kennen, gebruiken de regel met 100vh ervoor. --}}
    <div class="w-full p-3 md:p-5 flex flex-col overflow-hidden bg-[#F8FAFC]"
         style="height: calc(100vh - 7rem); height: calc(100dvh - 7rem); min-height: 420px;">

        {{-- Eén grote naadloze witte kaart --}}
        <div class="flex-1 w-full flex overflow-hidden bg-white rounded-2xl border border-slate-200/80 shadow-sm">

            {{-- ADMIN ZIJDE: Linker kolom met gesprekken --}}
            @if(auth()->user()->isAdmin())
                <div class="w-full md:w-80 border-r border-slate-100 bg-white flex flex-col shrink-0 {{ request()->query('client_id') ? 'hidden md:flex' : 'flex' }}">
                    
                    {{-- Zoekbalk --}}
                    <div class="p-3.5 border-b border-slate-100 shrink-0">
                        <div class="relative">
                            <svg class="w-4 h-4 absolute left-3.5 top-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input type="text" id="chat-search" autocomplete="off" placeholder="Zoek op naam of e-mail..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200/80 rounded-xl text-xs text-slate-700 focus:outline-none focus:border-slate-400 transition-colors">
                        </div>
                    </div>

                    {{-- Klantenlijst --}}
                    <div class="flex-1 overflow-y-auto p-2 space-y-1 no-scrollbar">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider px-3 my-2">Directe Berichten</p>
                        
                        {{-- De lijst staat in chat/_clients. De chat ververst dit vak tussendoor. --}}
                        <div id="chat-client-list">
                            @include('chat._clients')
                        </div>
                    </div>
                </div>
            @endif

            {{-- RECHTER ZIJDE: Het gesprek --}}
            <div class="flex-1 w-full flex flex-col bg-white min-w-0 {{ auth()->user()->isAdmin() && !request()->query('client_id') ? 'hidden md:flex' : 'flex' }}">
                
                {{-- Header --}}
                <div class="h-16 px-6 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
                    <div class="flex items-center gap-3">
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('chat.index') }}" class="md:hidden p-1.5 -ml-2 text-slate-400 hover:text-slate-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </a>
                        @endif

                        @php
                            $headerUser = auth()->user()->isAdmin() ? $selectedClient : null;
                            $headerInitials = $headerUser ? mb_strtoupper(mb_substr($headerUser->name, 0, 2)) : 'GKR';
                        @endphp
                        <div class="w-9 h-9 rounded-full bg-indigo-100 text-indigo-900 font-bold text-xs flex items-center justify-center shrink-0">
                            {{ $headerInitials }}
                        </div>
                        <div>
                            <h3 class="text-xs md:text-sm font-bold text-slate-900">
                                {{ auth()->user()->isAdmin() ? ($selectedClient ? $selectedClient->name : 'Selecteer een klant') : 'GKR Digital Agency' }}
                            </h3>
                            {{-- Hier stond altijd "Online", ook als er niemand online was. Dat is weggehaald. --}}
                        </div>
                    </div>
                </div>

                {{-- NIEUW: foutmeldingen bij versturen (bijv. leeg bericht, verkeerd bestandstype, te groot) --}}
                @if ($errors->any())
                    <div class="mx-4 mt-3 p-3 bg-red-50 border border-red-150 text-red-700 rounded-xl text-xs font-medium">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                {{-- Foutmelding bij versturen zonder herladen. De tekst wordt met textContent gezet. --}}
                <div id="chat-error" role="alert" class="hidden mx-4 mt-3 p-3 bg-red-50 border border-red-150 text-red-700 rounded-xl text-xs font-medium"></div>

                {{-- Berichtenstroom --}}
                <div class="flex-1 overflow-y-auto p-4 md:p-6 space-y-4 bg-[#F8FAFC] no-scrollbar" id="message-container">
                    
                    @php $previousDay = null; @endphp

                    @forelse($messages as $message)
                        @php $day = $message->created_at->toDateString(); @endphp
                        @include('chat._message', ['message' => $message, 'showDate' => $day !== $previousDay])
                        @php $previousDay = $day; @endphp
                    @empty
                        {{-- id: de chat haalt deze tekst weg zodra het eerste bericht binnenkomt --}}
                        <div id="chat-empty" class="text-center text-slate-400 py-12 text-xs">
                            @if(auth()->user()->isAdmin() && empty($selectedClient))
                                Kies een klant om het gesprek te openen.
                            @else
                                Nog geen berichten. Typ hieronder om het gesprek te starten!
                            @endif
                        </div>
                    @endforelse
                </div>

                {{-- Invoerformulier met Paperclip & Bestand-preview --}}
                @php
                    // Een medewerker kan pas typen als er een klant gekozen is.
                    // Een klant kan altijd typen: het bericht komt in het eigen gesprek met GKR.
                    $isAdminUser = auth()->user()->isAdmin();
                    $canSend = $isAdminUser ? ! empty($selectedClient) : true;
                @endphp

                @if($canSend)
                    <div class="p-3 md:p-4 border-t border-slate-100 bg-white shrink-0">
                        
                        {{-- Formulier met enctype voor bestandsuploads --}}
                        <form id="chat-form" action="{{ route('chat.store') }}" method="POST" enctype="multipart/form-data" class="flex flex-col gap-2">
                            @csrf
                            {{-- Alleen een medewerker geeft op voor welke klant het bericht is.
                                 Bij een klant bepaalt de server het gesprek zelf. --}}
                            @if($isAdminUser)
                                <input type="hidden" name="receiver_id" value="{{ $selectedClient->id }}">
                            @endif
                            
                            {{-- Verborgen File Input --}}
                            <input type="file" name="files[]" id="file-input" class="hidden" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.zip" onchange="showFilePreview(this)">

                            {{-- Preview-balkje als er een bestand is gekozen --}}
                            <div id="file-preview" class="hidden items-center justify-between bg-slate-100 px-3 py-1.5 rounded-xl text-xs text-slate-700">
                                <span class="flex items-center gap-2 truncate">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                    </svg>
                                    <span id="file-name-text" class="font-medium truncate"></span>
                                </span>
                                <button type="button" onclick="clearFile()" class="text-slate-400 hover:text-red-500 ml-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

                            <div class="flex items-center gap-2 bg-slate-50 p-2 rounded-xl border border-slate-200/80 focus-within:border-slate-400 transition-colors">
                                
                                {{-- Paperclip Icoon Knop --}}
                                <button type="button" onclick="document.getElementById('file-input').click()" class="text-slate-400 hover:text-slate-600 p-1.5 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                    </svg>
                                </button>

                                {{-- Tekst Invoer --}}
                                <input type="text" 
                                       name="body" 
                                       id="chat-body"
                                       maxlength="2000"
                                       placeholder="Typ een bericht..." 
                                       autocomplete="off"
                                       class="flex-1 bg-transparent border-0 px-2 text-xs text-slate-800 focus:outline-none focus:ring-0 placeholder:text-slate-400">
                                
                                {{-- Verzendknop --}}
                                <button type="submit" id="chat-send" class="bg-[#011936] hover:bg-slate-800 text-white p-2.5 rounded-lg transition-all flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 transform rotate-90" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/>
                                    </svg>
                                </button>
                            </div>
                        </form>
                    </div>
                @endif

            </div>
        </div>
    </div>

    {{-- Document Preview Modal --}}
<div id="document-preview-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full overflow-hidden flex flex-col md:flex-row min-h-[480px] max-h-[90vh] relative border border-slate-100">
        
        {{-- Linkerkolom: Document Preview --}}
        <div class="w-full md:w-1/2 bg-slate-100/80 p-6 md:p-8 flex items-center justify-center border-b md:border-b-0 md:border-r border-slate-200/60">
            <div id="modal-preview-container" class="w-full h-full max-h-[400px] flex items-center justify-center overflow-hidden rounded-xl shadow-lg bg-white border border-slate-200">
                <iframe id="modal-iframe-preview" class="w-full h-full border-0 hidden"></iframe>
                <img id="modal-img-preview" src="" alt="Voorbeeld van de bijlage" class="max-h-full max-w-full object-contain hidden">
                {{-- NIEUW: voor Word, Excel en zip is er geen voorbeeld --}}
                <p id="modal-no-preview" class="hidden text-xs text-slate-400 p-6 text-center">
                    Voor dit bestandstype is geen voorbeeld beschikbaar. Download het bestand om het te openen.
                </p>
            </div>
        </div>

        {{-- Rechterkolom: Details & Acties --}}
        <div class="w-full md:w-1/2 p-6 md:p-8 flex flex-col justify-between bg-white relative">
            
            <div>
                {{-- Titel + Sluitknop --}}
                <div class="flex items-start justify-between gap-4">
                    <h3 id="modal-file-name" class="text-lg md:text-xl font-bold text-slate-900 break-all leading-tight">
                        Document.pdf
                    </h3>
                    <button onclick="closeDocumentModal()" class="text-slate-400 hover:text-slate-700 transition-colors p-1 -mr-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </button>
                </div>

                {{-- Metadata --}}
                <p id="modal-file-meta" class="text-xs text-slate-400 mt-2 font-medium">
                    2.4 MB • Toegevoegd door...
                </p>

                <hr class="my-6 border-slate-100">

                {{-- Document Beschrijving --}}
                <div>
                    <h4 class="text-xs font-bold text-[#011936] uppercase tracking-wider mb-2">
                        DOCUMENT BESCHRIJVING
                    </h4>
                    <p id="modal-file-desc" class="text-xs md:text-sm text-slate-600 leading-relaxed">
                        Geen beschrijving beschikbaar.
                    </p>
                </div>
            </div>

            {{-- Actieknoppen (Onderaan) --}}
            <div class="flex items-center gap-3 mt-8">
                <a id="modal-download-btn" href="#" download class="flex-1 bg-[#011936] hover:bg-slate-800 text-white font-semibold py-3 px-4 rounded-xl text-xs md:text-sm flex items-center justify-center gap-2 transition-all shadow-md">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Downloaden
                </a>

                <button onclick="copyDocumentLink()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-3 px-4 rounded-xl text-xs md:text-sm flex items-center justify-center gap-2 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                    </svg>
                    <span id="share-text">Deel Bestand</span>
                </button>
            </div>

        </div>
    </div>
</div>

    {{-- CSS & JS scripts --}}
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>

    @php
        // Instellingen voor de chat zonder herladen. Alleen adressen en nummers, geen teksten van gebruikers.
        $chatConfig = [
            'messagesUrl'      => route('chat.messages'),
            // Alleen bij een medewerker: van welke klant het gesprek open staat
            'clientId'         => auth()->user()->isAdmin() ? ($selectedClient->id ?? null) : null,
            'conversationOpen' => $canSend,
            // Het hoogste berichtnummer dat al op de pagina staat
            'lastId'           => (int) ($messages->max('id') ?? 0),
            // Zo vaak kijkt de chat of er iets nieuws is (in milliseconden)
            'pollMs'           => 5000,
        ];
    @endphp

    <script>
        // ------------------------------------------------------------------
        // CHAT ZONDER HERLADEN
        // De pagina vraagt om de paar seconden aan de server of er iets nieuws is.
        // De server stuurt de opmaak van nieuwe berichten kant-en-klaar terug
        // (gemaakt met chat/_message, dus met dezelfde beveiliging als de pagina zelf).
        // Werkt JavaScript niet? Dan werkt het formulier nog gewoon met herladen.
        // ------------------------------------------------------------------
        (function () {
            const config = @js($chatConfig);

            const container = document.getElementById('message-container');
            const form = document.getElementById('chat-form');
            const errorBox = document.getElementById('chat-error');
            const clientList = document.getElementById('chat-client-list');
            const search = document.getElementById('chat-search');

            if (!container) return;

            let lastId = Number(config.lastId) || 0;
            let busy = false;      // er loopt al een verzoek
            let again = false;     // tijdens dat verzoek kwam er nog een vraag bij

            function scrollToBottom() {
                container.scrollTop = container.scrollHeight;
            }

            // Staat de gebruiker (bijna) onderaan? Alleen dan schuiven we mee bij een nieuw bericht.
            // Wie omhoog heeft gescrold om iets terug te lezen, laten we met rust.
            function nearBottom() {
                return container.scrollHeight - container.scrollTop - container.clientHeight < 150;
            }

            function showError(text) {
                if (!errorBox) return;
                errorBox.textContent = text; // textContent: wordt nooit als HTML gelezen
                errorBox.classList.remove('hidden');
            }

            function hideError() {
                if (!errorBox) return;
                errorBox.textContent = '';
                errorBox.classList.add('hidden');
            }

            // Zoekbalk: laat alleen klanten zien waarvan naam of e-mail de zoektekst bevat
            function applySearch() {
                if (!clientList) return;
                const query = (search ? search.value : '').trim().toLowerCase();

                clientList.querySelectorAll('li[data-client-id]').forEach(function (item) {
                    const text = item.getAttribute('data-search') || '';
                    item.classList.toggle('hidden', query !== '' && !text.includes(query));
                });
            }

            // De rode teller in de zijbalk
            function updateBadge(count) {
                const badge = document.getElementById('chat-unread-badge');
                if (!badge || typeof count !== 'number') return;

                badge.textContent = count > 99 ? '99+' : String(count);
                badge.style.display = count > 0 ? '' : 'none';
            }

            // Nieuwe berichten onderaan toevoegen. Een bericht dat er al staat slaan we over.
            function addMessages(html) {
                if (!html) return false;

                const template = document.createElement('template');
                template.innerHTML = html; // opmaak van onze eigen server, gemaakt door Blade

                let added = false;

                template.content.querySelectorAll('[data-message-id]').forEach(function (node) {
                    const id = node.getAttribute('data-message-id');

                    if (container.querySelector('[data-message-id="' + id + '"]')) {
                        node.remove();
                    } else {
                        added = true;
                    }
                });

                if (added) {
                    const empty = document.getElementById('chat-empty');
                    if (empty) empty.remove();

                    container.appendChild(template.content);
                }

                return added;
            }

            // Eigen berichten die de ander heeft geopend krijgen "Gelezen"
            function markRead(upTo) {
                if (!upTo) return;

                container.querySelectorAll('[data-mine="1"]').forEach(function (node) {
                    if (Number(node.getAttribute('data-message-id')) > upTo) return;

                    const status = node.querySelector('[data-read-status]');

                    if (status && status.textContent.trim() !== 'Gelezen') {
                        status.textContent = 'Gelezen';
                        status.className = 'text-slate-600 font-semibold';
                    }
                });
            }

            function apply(data) {
                const wasNearBottom = nearBottom();

                if (addMessages(data.html) && wasNearBottom) {
                    scrollToBottom();
                }

                if (Number(data.last_id) > lastId) {
                    lastId = Number(data.last_id);
                }

                markRead(Number(data.read_up_to) || 0);

                if (clientList && typeof data.clients_html === 'string') {
                    clientList.innerHTML = data.clients_html; // opmaak van onze eigen server
                    applySearch();
                }

                updateBadge(data.unread_total);
            }

            // Vraag de server of er iets nieuws is
            async function refresh() {
                if (busy) {
                    again = true;
                    return;
                }

                busy = true;

                try {
                    const params = new URLSearchParams();
                    params.set('after', String(lastId));
                    // Alleen als de pagina echt in beeld is, telt een nieuw bericht als gelezen
                    params.set('seen', document.visibilityState === 'visible' && config.conversationOpen ? '1' : '0');

                    if (config.clientId) {
                        params.set('client_id', String(config.clientId));
                    }

                    const response = await fetch(config.messagesUrl + '?' + params.toString(), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });

                    // Niet meer ingelogd of sessie verlopen: pagina herladen, dan volgt het inlogscherm
                    if (response.status === 401 || response.status === 419) {
                        window.location.reload();
                        return;
                    }

                    if (response.ok) {
                        apply(await response.json());
                    }
                } catch (error) {
                    // Even geen verbinding: de volgende ronde proberen we het opnieuw
                } finally {
                    busy = false;

                    if (again) {
                        again = false;
                        refresh();
                    }
                }
            }

            // Versturen zonder herladen
            if (form) {
                form.addEventListener('submit', async function (event) {
                    event.preventDefault();
                    hideError();

                    const button = document.getElementById('chat-send');
                    const body = document.getElementById('chat-body');

                    if (button) button.disabled = true;

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            body: new FormData(form), // bevat ook de beveiligingscode (_token) en de bestanden
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });

                        if (response.status === 401 || response.status === 419) {
                            window.location.reload();
                            return;
                        }

                        // De server weigert het verzoek omdat het in totaal te groot is
                        if (response.status === 413) {
                            showError('De bestanden zijn samen te groot om te versturen. Verstuur ze los of kies kleinere bestanden.');
                            return;
                        }

                        let data = {};
                        try { data = await response.json(); } catch (error) { data = {}; }

                        if (!response.ok) {
                            const firstError = data.errors ? Object.values(data.errors)[0] : null;

                            showError((firstError && firstError[0]) || data.message || 'Het bericht kon niet worden verstuurd. Probeer het opnieuw.');
                            return;
                        }

                        if (body) body.value = '';
                        if (typeof clearFile === 'function') clearFile();

                        await refresh();
                        scrollToBottom();
                    } catch (error) {
                        showError('Geen verbinding. Controleer je internet en probeer het opnieuw.');
                    } finally {
                        if (button) button.disabled = false;
                        if (body) body.focus();
                    }
                });
            }

            if (search) {
                search.addEventListener('input', applySearch);
            }

            // Bij het openen: naar het laatste bericht
            scrollToBottom();

            // Om de paar seconden kijken, maar alleen als de pagina in beeld is
            setInterval(function () {
                if (document.visibilityState === 'visible') {
                    refresh();
                }
            }, Number(config.pollMs) || 5000);

            // Kom je terug op dit tabblad? Dan meteen bijwerken
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'visible') {
                    refresh();
                }
            });
        })();

        function showFilePreview(input) {
            const previewContainer = document.getElementById('file-preview');
            const fileNameText = document.getElementById('file-name-text');

            if (input.files && input.files.length > 0) {
                if (input.files.length === 1) {
                    fileNameText.innerText = input.files[0].name;
                } else {
                    fileNameText.innerText = `${input.files.length} bestanden geselecteerd`;
                }

                previewContainer.classList.remove('hidden');
                previewContainer.classList.add('flex');
            }
        }

        function clearFile() {
            const input = document.getElementById('file-input');
            input.value = '';
            
            const previewContainer = document.getElementById('file-preview');
            previewContainer.classList.add('hidden');
            previewContainer.classList.remove('flex');
        }

        let currentDownloadUrl = '';

        // AANGEPAST: previewUrl is de beveiligde voorbeeld-route, of null als er geen voorbeeld kan
        function openDocumentModal(fileName, fileSize, senderName, date, downloadUrl, previewUrl, fileType, description) {
            currentDownloadUrl = downloadUrl;

            // Alle tekst via innerText: wordt nooit als HTML uitgevoerd
            document.getElementById('modal-file-name').innerText = fileName;
            document.getElementById('modal-file-meta').innerText = `${fileSize} • Toegevoegd door ${senderName} op ${date}`;
            document.getElementById('modal-file-desc').innerText = description && description.trim() !== '' ? description : 'Geen aanvullende beschrijving bijgevoegd.';
            document.getElementById('modal-download-btn').href = downloadUrl;

            const iframe = document.getElementById('modal-iframe-preview');
            const img = document.getElementById('modal-img-preview');
            const noPreview = document.getElementById('modal-no-preview');

            iframe.classList.add('hidden');
            img.classList.add('hidden');
            noPreview.classList.add('hidden');

            if (!previewUrl) {
                noPreview.classList.remove('hidden');
            } else if (['jpg', 'jpeg', 'png'].includes(String(fileType).toLowerCase())) {
                img.src = previewUrl;
                img.classList.remove('hidden');
            } else {
                iframe.src = previewUrl;
                iframe.classList.remove('hidden');
            }

            // Modal openen & scrollen op achtergrond blokkeren
            const modal = document.getElementById('document-preview-modal');
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        // AANGEPAST: deze functie stond er twee keer in. De tweede versie zette het scrollen
        // niet terug aan, waardoor de pagina na het sluiten van de popup niet meer scrolde.
        function closeDocumentModal() {
            const modal = document.getElementById('document-preview-modal');
            modal.classList.add('hidden');
            document.getElementById('modal-iframe-preview').src = '';
            document.getElementById('modal-img-preview').src = '';
            document.body.classList.remove('overflow-hidden');
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === "Escape") {
                closeDocumentModal();
            }
        });

        // Sluiten door op de donkere achtergrond te klikken
        document.getElementById('document-preview-modal')?.addEventListener('click', function(event) {
            if (event.target === this) {
                closeDocumentModal();
            }
        });

        function copyDocumentLink() {
            if (currentDownloadUrl) {
                // De link werkt alleen voor wie is ingelogd en toegang heeft tot dit gesprek
                navigator.clipboard.writeText(currentDownloadUrl);
                const shareText = document.getElementById('share-text');
                shareText.innerText = 'Link gekopieerd!';
                setTimeout(() => {
                    shareText.innerText = 'Deel Bestand';
                }, 2000);
            }
        }
    </script>
</x-app-layout>