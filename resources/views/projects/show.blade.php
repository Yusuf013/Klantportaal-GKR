<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-maven font-bold text-xl text-[#011936] leading-tight">
                Project: {{ $project->name }}
            </h2>
            <a href="{{ route('dashboard') }}" class="text-sm text-gray-500 hover:text-[#011936] transition">
                &larr; Terug naar overzicht
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 mb-8">
                <div class="p-8">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div class="md:col-span-2">
                            <h3 class="font-maven font-bold text-2xl text-[#011936] mb-4">Status Update</h3>
                            <p class="font-inter text-gray-600 mb-6">
                                Hieronder vind je de actuele status van je project bij GKR. 
                                We werken momenteel aan de <strong>{{ $project->status }}</strong> fase.
                            </p>

                            <div class="relative mt-12 mb-8">
                                <div class="absolute left-0 top-1/2 w-full h-1 bg-gray-100 -translate-y-1/2"></div>
                                <div class="relative flex justify-between">
                                    @foreach(['Strategie', 'Design', 'Development', 'Live'] as $step)
                                        <div class="flex flex-col items-center">
                                            <div class="w-8 h-8 rounded-full border-4 {{ $project->status == $step ? 'bg-[#011936] border-blue-200' : 'bg-white border-gray-200' }} z-10 transition-colors duration-500"></div>
                                            <span class="mt-2 text-xs font-bold font-maven {{ $project->status == $step ? 'text-[#011936]' : 'text-gray-400' }}">{{ $step }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 p-6 rounded-xl border border-gray-100">
                            <h4 class="font-maven font-bold text-[#011936] mb-4">Project Details</h4>
                            <ul class="space-y-4 text-sm font-inter">
                                <li class="flex justify-between">
                                    <span class="text-gray-500">Klant:</span>
                                    <span class="font-semibold">{{ auth()->user()->name }}</span>
                                </li>
                                <li class="flex justify-between">
                                    <span class="text-gray-500">Verwachte Deadline:</span>
                                    <span class="font-semibold text-red-600">{{ \Carbon\Carbon::parse($project->deadline)->format('d F, Y') }}</span>
                                </li>
                                <li class="flex justify-between">
                                    <span class="text-gray-500">Laatste Update:</span>
                                    <span class="font-semibold">{{ $project->updated_at->diffForHumans() }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-12">
                <h3 class="font-maven font-bold text-2xl mb-6 text-[#011936]">Documenten & Ontwerpen</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @forelse($project->documents as $document)
                        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 flex justify-between items-center hover:border-blue-300 transition">
                            <div class="flex items-center">
                                <div class="bg-blue-50 p-3 rounded-lg mr-4">
                                    <svg class="w-6 h-6 text-[#011936]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="C9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-bold text-[#011936]">{{ $document->name }}</h4>
                                    <p class="text-sm text-gray-500">Status: 
                                        <span class="font-semibold {{ $document->status == 'Akkoord' ? 'text-green-600' : 'text-orange-500' }}">
                                            {{ $document->status }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="flex items-center">
                                <a href="{{ asset('storage/' . $document->file_path) }}" target="_blank" class="bg-[#011936] text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-black transition">
                                    Bekijken
                                </a>
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