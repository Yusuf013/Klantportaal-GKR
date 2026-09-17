<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Berichten & Support') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="grid grid-cols-1 md:grid-cols-4 h-[600px]">

                    {{-- ADMIN ZIJDE: Klantenlijst --}}
                    @if(auth()->user()->isAdmin())
                        <div class="md:col-span-1 border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 overflow-y-auto">
                            <div class="p-4 border-b border-gray-200 dark:border-gray-700 font-semibold text-gray-700 dark:text-gray-300">
                                Gesprekken met Klanten
                            </div>
                            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($clients as $client)
                                    <li>
                                        <a href="{{ route('chat.index', ['client_id' => $client->id]) }}" 
                                           class="block p-4 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition duration-150 ease-in-out {{ isset($selectedClient) && $selectedClient->id === $client->id ? 'bg-indigo-50 dark:bg-gray-700 font-medium' : '' }}">
                                            <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                                {{ $client->name }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $client->email }}
                                            </div>
                                        </a>
                                    </li>
                                @empty
                                    <li class="p-4 text-xs text-gray-500">Geen klanten gevonden.</li>
                                @endforelse
                            </ul>
                        </div>
                    @endif

                    {{-- CHAT BOKS --}}
                    <div class="{{ auth()->user()->isAdmin() ? 'md:col-span-3' : 'md:col-span-4' }} flex flex-col justify-between h-full bg-white dark:bg-gray-800">
                        
                        {{-- Chat Header --}}
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center bg-gray-50/50 dark:bg-gray-800">
                            @if(auth()->user()->isAdmin())
                                <span class="font-semibold text-gray-800 dark:text-gray-200">
                                    {{ $selectedClient ? 'Chat met ' . $selectedClient->name : 'Selecteer een klant' }}
                                </span>
                            @else
                                <span class="font-semibold text-gray-800 dark:text-gray-200">
                                    Chat met GKR Digital Agency
                                </span>
                            @endif
                        </div>

                        {{-- Berichtenstroom --}}
                        <div class="p-4 overflow-y-auto flex-1 space-y-4" id="message-container">
                            @forelse($messages as $message)
                                @php
                                    $isMe = $message->sender_id === auth()->id();
                                @endphp

                                <div class="flex flex-col {{ $isMe ? 'items-end' : 'items-start' }}">
                                    {{-- Afzender naam tonen bij ontvangen bericht --}}
                                    @if(!$isMe)
                                        <span class="text-[11px] text-gray-500 dark:text-gray-400 mb-1 px-1">
                                            {{ $message->sender->name }}
                                        </span>
                                    @endif

                                    <div class="max-w-md px-4 py-2 rounded-lg text-sm {{ $isMe ? 'bg-indigo-600 text-white rounded-br-none' : 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white rounded-bl-none' }}">
                                        {{ $message->body }}
                                    </div>

                                    <div class="flex items-center space-x-1 text-[10px] text-gray-400 mt-1">
                                        <span>{{ $message->created_at->format('H:i') }}</span>
                                        @if($isMe)
                                            <span>•</span>
                                            <span class="{{ $message->read_at ? 'text-blue-400 font-semibold' : 'text-gray-400' }}">
                                                {{ $message->read_at ? 'Gelezen' : 'Verzonden' }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-gray-400 py-10 text-sm">
                                    Nog geen berichten in dit gesprek. Typ hieronder een bericht om het gesprek te starten!
                                </div>
                            @endforelse
                        </div>

                        {{-- Invoerformulier --}}
                        @php
                            $receiverId = auth()->user()->isAdmin() ? ($selectedClient?->id) : ($mainAdmin?->id);
                        @endphp

                        @if($receiverId)
                            <form action="{{ route('chat.store') }}" method="POST" class="p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50/30 dark:bg-gray-800/50 flex items-center space-x-2">
                                @csrf
                                <input type="hidden" name="receiver_id" value="{{ $receiverId }}">
                                
                                <input type="text" 
                                       name="body" 
                                       placeholder="Typ een bericht..." 
                                       required 
                                       autocomplete="off"
                                       class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                
                                <x-primary-button>
                                    {{ __('Verstuur') }}
                                </x-primary-button>
                            </form>
                        @endif

                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- Auto-scroll naar onderen --}}
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const container = document.getElementById('message-container');
            if(container) {
                container.scrollTop = container.scrollHeight;
            }
        });
    </script>
</x-app-layout>