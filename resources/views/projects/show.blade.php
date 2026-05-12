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
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100">
                <div class="p-8">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        
                        <div class="md:col-span-2">
                            <h3 class="font-maven font-bold text-2xl text-[#011936] mb-4">Status Update</h3>
                            <p class="font-inter text-gray-600 mb-6">
                                Hieronder vind je de actuele status van je project bij GKR. 
                                We werken momenteel aan de <strong>{{ $project->status }}</strong> fase.
                            </p>

                            <div class="relative mt-8">
                                <div class="absolute left-0 top-1/2 w-full h-1 bg-gray-100 -translate-y-1/2"></div>
                                <div class="relative flex justify-between">
                                    @foreach(['Strategie', 'Design', 'Development', 'Live'] as $step)
                                        <div class="flex flex-col items-center">
                                            <div class="w-8 h-8 rounded-full border-4 {{ $project->status == $step ? 'bg-[#011936] border-blue-200' : 'bg-white border-gray-200' }} z-10"></div>
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

            <div class="mt-8 bg-white p-8 rounded-lg border border-dashed border-gray-300 text-center">
                <p class="text-gray-400 font-inter italic">Hier komen binnenkort de gedeelde bestanden en documenten voor dit project.</p>
            </div>
        </div>
    </div>
</x-app-layout>