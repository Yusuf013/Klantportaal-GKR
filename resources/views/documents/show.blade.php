<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <a href="{{ route('projects.show', $document->project_id) }}" class="text-sm text-gray-500 hover:text-[#011936] transition">
                    &larr; Terug naar project
                </a>
                <span class="text-gray-300">/</span>
                <h2 class="font-maven font-bold text-xl text-[#011936] leading-tight">
                    {{ $document->name }}
                </h2>
            </div>
            
            <div>
                <span class="text-sm font-medium px-3 py-1.5 rounded-lg {{ $document->status == 'Akkoord' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-orange-50 text-orange-700 border border-orange-200' }}">
                    Status: {{ $document->status }}
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="bg-gray-100 p-4 border-b border-gray-200 flex justify-between items-center">
                            <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Voorbeeld weergave</span>
                            <a href="{{ asset('storage/' . $document->file_path) }}" target="_blank" class="text-xs text-[#011936] hover:underline font-semibold flex items-center">
                                Open in nieuw tabblad &rarr;
                            </a>
                        </div>
                        
                        <div class="aspect-[4/3] w-full bg-gray-50">
                            <iframe 
                                src="{{ asset('storage/' . $document->file_path) }}" 
                                class="w-full h-full border-0"
                                style="min-height: 500px;">
                            </iframe>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex justify-between items-center">
                        <div>
                            <h3 class="font-bold text-gray-900 text-lg">Voldoet het ontwerp aan de wensen?</h3>
                            <p class="text-sm text-gray-500">Geef direct digitaal akkoord of laat rechts je aanpassingen achter.</p>
                        </div>

                        <div>
                            @if($document->status !== 'Akkoord')
                                {{-- Alpine.js Modal Knop --}}
                                <div x-data="{ open: false }">
                                    <button @click="open = true" type="button" class="bg-green-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-green-700 transition shadow-md flex items-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Akkoord geven
                                    </button>

                                    <div x-show="open" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
                                        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                                            <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-75" @click="open = false"></div>
                                            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                                            <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full" x-show="open">
                                                <div class="px-6 py-8 bg-white sm:p-10 sm:pb-8">
                                                    <div class="sm:flex sm:items-start">
                                                        <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 mx-auto bg-green-100 rounded-full sm:mx-0 sm:h-10 sm:w-10">
                                                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                            </svg>
                                                        </div>
                                                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                                            <h3 class="text-xl font-bold leading-6 text-gray-900">Document goedkeuren</h3>
                                                            <div class="mt-3">
                                                                <p class="text-sm text-gray-500">
                                                                    Weet je zeker dat je akkoord gaat met dit document? Je bevestiging wordt officieel vastgelegd met de datum en tijd van nu.
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="px-6 py-4 bg-gray-50 sm:px-10 sm:flex sm:flex-row-reverse">
                                                    <form action="{{ route('documents.approve', $document->id) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="inline-flex justify-center w-full px-6 py-2.5 text-base font-bold text-white bg-green-600 border border-transparent rounded-xl shadow-sm hover:bg-green-700 sm:ml-3 sm:w-auto sm:text-sm">
                                                            Ja, ik ga akkoord
                                                        </button>
                                                    </form>
                                                    <button @click="open = false" type="button" class="inline-flex justify-center w-full px-6 py-2.5 mt-3 text-base font-semibold text-gray-700 bg-white border border-gray-300 rounded-xl shadow-sm hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                                        Annuleren
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <span class="text-green-600 font-bold flex items-center bg-green-50 px-4 py-2.5 rounded-xl border border-green-200 shadow-sm text-sm">
                                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"></path>
                                    </svg>
                                    Digitaal goedgekeurd op {{ $document->approved_at->translatedFormat('d F Y \o\m H:i') }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 sticky top-24">
                    <h3 class="font-maven font-bold text-lg text-[#011936] mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12   c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        Feedback & Opmerkingen
                    </h3>

                    <div class="space-y-3 mb-6 overflow-y-auto pr-1" style="max-height: 420px;">
                        @forelse($document->comments as $comment)
                            <div class="p-3.5 rounded-xl text-xs flex flex-col space-y-1.5 {{ $comment->user->is_admin ? 'bg-gray-50 border border-gray-200' : 'bg-blue-50/60 border border-blue-100' }}">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-gray-800 flex items-center">
                                        {{ $comment->user->name }}
                                        @if($comment->user->is_admin)
                                            <span class="text-[9px] px-1.5 py-0.5 bg-gray-200 text-gray-600 rounded-md ml-1.5 font-semibold">Team GKR</span>
                                        @else
                                            <span class="text-[9px] px-1.5 py-0.5 bg-blue-100 text-blue-600 rounded-md ml-1.5 font-semibold">Klant</span>
                                        @endif
                                    </span>
                                    <span class="text-[10px] text-gray-400">
                                        {{ $comment->created_at->diffForHumans() }}
                                    </span>
                                </div>
                                <p class="text-gray-600 text-[13px] leading-relaxed whitespace-pre-line">{{ $comment->content }}</p>
                            </div>
                        @empty
                            <div class="py-8 text-center">
                                <p class="text-xs text-gray-400 italic">Er zijn nog geen opmerkingen geplaatst. Typ hieronder om de eerste feedbackronde te starten!</p>
                            </div>
                        @endforelse
                    </div>

                    <hr class="border-gray-100 mb-4">

                    <form action="{{ route('comments.store', $document) }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label for="content" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Plaats een reactie</label>
                            <textarea 
                                id="content"
                                name="content" 
                                rows="3" 
                                class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2 px-3 placeholder-gray-400" 
                                placeholder="Geef specifiek aan wat er gewijzigd of aangepast moet worden..."
                                required
                            >{{ old('content') }}</textarea>
                            @error('content')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button 
                            type="submit" 
                            class="w-full inline-flex justify-center items-center px-4 py-2.5 bg-[#011936] rounded-xl font-bold text-xs text-white uppercase tracking-widest hover:bg-black transition duration-150 shadow-sm"
                        >
                            Verstuur feedback
                        </button>
                    </form>
                </div>

            </div>

        </div>
    </div>
</x-app-layout>