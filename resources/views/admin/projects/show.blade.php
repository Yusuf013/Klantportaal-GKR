<x-app-layout>
@if ($errors->any())
    <div class="bg-red-100 text-red-700 p-4 rounded mb-4">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

    <x-slot name="header">
        <h2 class="font-maven font-bold text-xl text-[#011936] leading-tight">
            Beheer Project: {{ $project->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <div class="bg-white p-8 rounded-xl shadow-sm border mb-8 border-gray-100">
                <h3 class="text-lg font-bold mb-4 font-maven text-[#011936]">Project Informatie</h3>
                <p class="text-sm text-gray-600 mb-1"><strong>Klant:</strong> {{ $project->user->name }}</p>
                <p class="text-sm text-gray-600"><strong>Status:</strong> {{ $project->status }}</p>
            </div>

            <div class="bg-gray-50 p-8 rounded-xl border border-dashed border-gray-300 mb-12">
                <h3 class="font-maven font-bold text-lg mb-4 text-[#011936]">Bestand uploaden voor deze klant</h3>
                
                <form action="{{ route('admin.projects.upload', $project->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Naam van het document</label>
                        <input type="text" name="document_name" placeholder="bijv. Concept Ontwerp V1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Bestand</label>
                        <input type="file" name="file" class="mt-1 block w-full text-sm text-gray-500">
                    </div>

                    <button type="submit" class="bg-[#011936] text-white px-6 py-2.5 rounded-xl font-bold hover:bg-black transition shadow-sm">
                        Uploaden naar Portaal
                    </button>
                </form>
            </div>

            <div class="mt-8">
                <h3 class="font-maven font-bold text-2xl mb-6 text-[#011936]">Geüploade Documenten & Ontwerpen</h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @forelse($project->documents as $document)
                        <div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-200 flex flex-col hover:shadow-md hover:border-blue-300 transition-all duration-300 group">
                            
                            <div class="bg-gradient-to-br from-blue-50/50 to-gray-100 h-44 flex items-center justify-center border-b border-gray-100 relative group-hover:from-blue-50 transition-colors">
                                <div class="bg-white p-4 rounded-xl shadow-sm text-[#011936]">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                </div>
                                
                                <div class="absolute top-3 right-3">
                                    @if($document->status === 'Akkoord')
                                        <span class="text-[11px] font-bold px-2.5 py-1 rounded-md shadow-sm bg-white text-green-600 border border-green-100">
                                            Akkoord
                                        </span>
                                    @else
                                        <span class="text-[11px] font-bold px-2.5 py-1 rounded-md shadow-sm bg-white text-orange-500 border border-orange-100">
                                            {{ $document->status }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            
                            <div class="p-5 flex-grow flex flex-col justify-between">
                                <div class="mb-4">
                                    <h4 class="font-bold text-[#011936] text-base leading-snug group-hover:text-indigo-600 transition-colors">
                                        {{ $document->name }}
                                    </h4>
                                    
                                    @if($document->status === 'Akkoord')
                                        <p class="text-[10px] text-gray-400 mt-1">
                                            Gezien op: {{ $document->approved_at ? $document->approved_at->translatedFormat('d F Y H:i') : $document->updated_at->translatedFormat('d F Y H:i') }}
                                        </p>
                                    @else
                                        <p class="text-[10px] text-gray-400 mt-1">
                                            Laatste update: {{ $document->updated_at->diffForHumans() }}
                                        </p>
                                    @endif
                                </div>
                                
                                <div class="flex justify-end pt-2 border-t border-gray-50">
                                    <a href="{{ route('admin.documents.show', $document->id) }}" class="inline-flex items-center bg-indigo-600 text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-indigo-700 active:bg-indigo-800 transition shadow-sm group-hover:scale-[1.02] transform duration-200">
                                        Beheer & Feedback
                                        <svg class="w-3.5 h-3.5 ml-1.5 transform group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </a>
                                </div>
                            </div>

                        </div>
                    @empty
                        <div class="col-span-full bg-white p-12 rounded-xl border border-dashed border-gray-300 text-center">
                            <p class="text-gray-500 italic">Er zijn nog geen documenten geüpload voor dit project.</p>
                        </div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</x-app-layout>