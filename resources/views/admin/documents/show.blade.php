<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.projects.show', $document->project_id) }}" class="text-sm text-gray-500 hover:text-[#011936] transition">
                    &larr; Terug naar projectbeheer
                </a>
                <span class="text-gray-300">/</span>
                <h2 class="font-maven font-bold text-xl text-[#011936] leading-tight">
                    Beheer: {{ $document->name }}
                </h2>
            </div>
            
            <div>
                <span class="text-sm font-medium px-3 py-1.5 rounded-lg {{ $document->status == 'Akkoord' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-orange-50 text-orange-700 border border-orange-200' }}">
                    Klant Status: {{ $document->status }}
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
                            <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Geüpload bestand preview</span>
                            <a href="{{ asset('storage/' . $document->file_path) }}" target="_blank" class="text-xs text-[#011936] hover:underline font-semibold flex items-center">
                                Open origineel bestand &rarr;
                            </a>
                        </div>
                        
                        <div class="w-full h-[750px] bg-gray-50">
                            <iframe 
                                src="{{ asset('storage/' . $document->file_path) }}" 
                                class="w-full h-full border-0 shadow-inner"
                                style="min-height: 750px;">
                            </iframe>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-bold text-gray-900 text-lg">Digitale Goedkeuring Status</h3>
                                <p class="text-sm text-gray-500">Hieronder zie je of de klant dit specifieke document al officieel heeft geaccepteerd.</p>
                            </div>

                            <div>
                                @if($document->status === 'Akkoord')
                                    <span class="text-green-600 font-bold flex items-center bg-green-50 px-4 py-2.5 rounded-xl border border-green-200 shadow-sm text-sm">
                                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"></path>
                                        </svg>
                                        Goedgekeurd op {{ $document->approved_at ? $document->approved_at->translatedFormat('d F Y \o\m H:i') : $document->updated_at->translatedFormat('d F Y \o\m H:i') }}
                                    </span>
                                @else
                                    <span class="text-orange-600 font-bold flex items-center bg-orange-50 px-4 py-2.5 rounded-xl border border-orange-200 shadow-sm text-sm">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        In afwachting van akkoord klant
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 sticky top-24">
                    <h3 class="font-maven font-bold text-lg text-[#011936] mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12   c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        Klantfeedback Tijdlijn
                    </h3>

                    <div class="space-y-3 mb-6 overflow-y-auto pr-1" style="max-height: 570px;">
                        @forelse($document->comments as $comment)
                            <div class="p-3.5 rounded-xl text-xs flex flex-col space-y-1.5 {{ $comment->user->is_admin ? 'bg-blue-50/60 border border-blue-100' : 'bg-gray-50 border border-gray-200' }}">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-gray-800 flex items-center">
                                        {{ $comment->user->name }}
                                        @if($comment->user->is_admin)
                                            <span class="text-[9px] px-1.5 py-0.5 bg-blue-100 text-blue-600 rounded-md ml-1.5 font-semibold">Team GKR (Jij)</span>
                                        @else
                                            <span class="text-[9px] px-1.5 py-0.5 bg-gray-200 text-gray-600 rounded-md ml-1.5 font-semibold">Klant</span>
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
                                <p class="text-xs text-gray-400 italic">De klant heeft nog geen opmerkingen geplaatst bij dit document.</p>
                            </div>
                        @endforelse
                    </div>

                    <hr class="border-gray-100 mb-4">

                    <form action="{{ route('comments.store', $document) }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label for="content" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Reageer op de klant</label>
                            <textarea 
                                id="content"
                                name="content" 
                                rows="3" 
                                class="w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2 px-3 placeholder-gray-400" 
                                placeholder="Typ hier je reactie of toelichting op het nieuwe ontwerp..."
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
                            Reactie versturen
                        </button>
                    </form>
                </div>

            </div>

        </div>
    </div>
</x-app-layout>