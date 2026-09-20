<x-app-layout>
    {{-- Wrapper die de volledige hoogte van het venster benut --}}
    <div class="h-[calc(100vh-65px)] w-full p-3 md:p-5 flex flex-col overflow-hidden bg-[#F8FAFC]">

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
                            <input type="text" placeholder="Zoek gesprekken..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200/80 rounded-xl text-xs text-slate-700 focus:outline-none focus:border-slate-400 transition-colors">
                        </div>
                    </div>

                    {{-- Klantenlijst --}}
                    <div class="flex-1 overflow-y-auto p-2 space-y-1 no-scrollbar">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider px-3 my-2">Directe Berichten</p>
                        
                        <ul class="space-y-1">
                            @forelse($clients as $client)
                                @php
                                    $isSelected = isset($selectedClient) && $selectedClient->id === $client->id;
                                    $initials = strtoupper(substr($client->name, 0, 2));
                                @endphp
                                <li>
                                    <a href="{{ route('chat.index', ['client_id' => $client->id]) }}" 
                                       class="flex items-center gap-3 p-3 rounded-xl transition-all duration-150 {{ $isSelected ? 'bg-slate-100/80 shadow-sm' : 'hover:bg-slate-50' }}">
                                        
                                        <div class="w-9 h-9 rounded-full bg-slate-200 text-slate-700 font-bold text-xs flex items-center justify-center shrink-0">
                                            {{ $initials }}
                                        </div>

                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between">
                                                <h4 class="text-xs font-bold text-slate-900 truncate">{{ $client->name }}</h4>
                                            </div>
                                            <p class="text-[11px] text-slate-400 truncate mt-0.5">{{ $client->email }}</p>
                                        </div>
                                    </a>
                                </li>
                            @empty
                                <li class="p-4 text-xs text-slate-400 text-center">Geen klanten gevonden.</li>
                            @endforelse
                        </ul>
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
                            $headerInitials = $headerUser ? strtoupper(substr($headerUser->name, 0, 2)) : 'GKR';
                        @endphp
                        <div class="w-9 h-9 rounded-full bg-indigo-100 text-indigo-900 font-bold text-xs flex items-center justify-center shrink-0">
                            {{ $headerInitials }}
                        </div>
                        <div>
                            <h3 class="text-xs md:text-sm font-bold text-slate-900">
                                {{ auth()->user()->isAdmin() ? ($selectedClient ? $selectedClient->name : 'Selecteer een klant') : 'GKR Digital Agency' }}
                            </h3>
                            <p class="text-[10px] text-emerald-500 font-medium flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Online
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Berichtenstroom --}}
                <div class="flex-1 overflow-y-auto p-4 md:p-6 space-y-4 bg-[#F8FAFC] no-scrollbar" id="message-container">
                    
                    <div class="text-center my-1">
                        <span class="text-[9px] font-bold tracking-wider text-slate-400 uppercase bg-slate-100/80 px-2.5 py-0.5 rounded-full">
                            Berichtenverloop
                        </span>
                    </div>

                    @forelse($messages as $message)
                        @php
                            $isMe = $message->sender_id === auth()->id();
                            $senderInitials = strtoupper(substr($message->sender->name ?? 'U', 0, 2));
                        @endphp

                        <div class="flex gap-2.5 {{ $isMe ? 'flex-row-reverse' : 'flex-row' }} items-end">
                            
                            <div class="w-7 h-7 rounded-full {{ $isMe ? 'bg-[#011936] text-white' : 'bg-slate-200 text-slate-700' }} font-bold text-[9px] flex items-center justify-center shrink-0">
                                {{ $isMe ? 'ME' : $senderInitials }}
                            </div>

                            <div class="flex flex-col {{ $isMe ? 'items-end' : 'items-start' }} max-w-[85%] md:max-w-xl">
                                
                                @if(!$isMe)
                                    <span class="text-[10px] font-bold text-slate-600 mb-0.5 pl-1">
                                        {{ $message->sender->name }} <span class="text-[9px] font-normal text-slate-400 ml-1">{{ $message->created_at->format('H:i') }}</span>
                                    </span>
                                @endif

                                {{-- Bericht Bubble --}}
                                <div class="px-4 py-3 rounded-2xl text-xs leading-relaxed {{ $isMe ? 'bg-[#011936] text-white rounded-br-none shadow-sm' : 'bg-white border border-slate-200/80 text-slate-800 rounded-bl-none shadow-sm' }}">
                                    
                                    {{-- Tekstgedeelte --}}
                                    @if($message->body)
                                        <p class="{{ $message->file_path ? 'mb-2.5' : '' }}">{{ $message->body }}</p>
                                    @endif

                                    {{-- Bijlage Card (Design uit voorbeeldfoto) --}}
                                    @if($message->file_path)
                                        <a href="{{ route('chat.download', $message->id) }}" 
                                           class="flex items-center justify-between gap-4 p-3 bg-white rounded-xl border border-slate-200/80 shadow-sm hover:shadow-md transition-all group my-0.5">
                                            
                                            <div class="flex items-center gap-3 min-w-0">
                                                {{-- Rood PDF / Document Icoon --}}
                                                <div class="w-10 h-10 rounded-lg bg-red-50 text-red-500 flex items-center justify-center shrink-0">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                    </svg>
                                                </div>

                                                {{-- Bestandsinformatie --}}
                                                <div class="min-w-0">
                                                    <p class="text-xs font-bold text-slate-800 truncate group-hover:text-indigo-600 transition-colors">
                                                        {{ $message->file_name ?? 'Document' }}
                                                    </p>
                                                    <p class="text-[10px] text-slate-400 mt-0.5">
                                                        {{ $message->formatted_file_size }} • {{ strtoupper($message->file_type ?? 'PDF') }}
                                                    </p>
                                                </div>
                                            </div>

                                            {{-- Download Pijltje --}}
                                            <div class="text-slate-400 group-hover:text-slate-700 transition-colors shrink-0">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                                </svg>
                                            </div>
                                        </a>
                                    @endif

                                </div>

                                {{-- Tijd + Gelezen status voor eigen berichten --}}
                                @if($isMe)
                                    <div class="flex items-center gap-1 text-[9px] text-slate-400 mt-0.5 pr-1">
                                        <span>{{ $message->created_at->format('H:i') }}</span>
                                        <span>•</span>
                                        <span class="{{ $message->read_at ? 'text-slate-600 font-semibold' : 'text-slate-400' }}">
                                            {{ $message->read_at ? 'Gelezen' : 'Verzonden' }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-slate-400 py-12 text-xs">
                            Nog geen berichten. Typ hieronder om het gesprek te starten!
                        </div>
                    @endforelse
                </div>

                {{-- Invoerformulier met Paperclip & Bestand-preview --}}
                @php
                    $receiverId = auth()->user()->isAdmin() ? ($selectedClient?->id) : ($mainAdmin?->id);
                @endphp

                @if($receiverId)
                    <div class="p-3 md:p-4 border-t border-slate-100 bg-white shrink-0">
                        
                        {{-- Formulier met enctype voor bestandsuploads --}}
                        <form action="{{ route('chat.store') }}" method="POST" enctype="multipart/form-data" class="flex flex-col gap-2">
                            @csrf
                            <input type="hidden" name="receiver_id" value="{{ $receiverId }}">
                            
                            {{-- Verborgen File Input --}}
                            <input type="file" name="file" id="file-input" class="hidden" onchange="showFilePreview(this)">

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
                                       placeholder="Typ een bericht..." 
                                       autocomplete="off"
                                       class="flex-1 bg-transparent border-0 px-2 text-xs text-slate-800 focus:outline-none focus:ring-0 placeholder:text-slate-400">
                                
                                {{-- Verzendknop --}}
                                <button type="submit" class="bg-[#011936] hover:bg-slate-800 text-white p-2.5 rounded-lg transition-all flex items-center justify-center shrink-0">
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

    {{-- CSS & JS scripts --}}
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const container = document.getElementById('message-container');
            if(container) {
                container.scrollTop = container.scrollHeight;
            }
        });

        function showFilePreview(input) {
            if (input.files && input.files[0]) {
                document.getElementById('file-name-text').innerText = input.files[0].name;
                document.getElementById('file-preview').classList.remove('hidden');
                document.getElementById('file-preview').classList.add('flex');
            }
        }

        function clearFile() {
            const input = document.getElementById('file-input');
            input.value = '';
            document.getElementById('file-preview').classList.add('hidden');
            document.getElementById('file-preview').classList.remove('flex');
        }
    </script>
</x-app-layout>