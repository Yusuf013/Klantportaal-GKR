<x-app-layout>
    <x-slot name="header">
        <h2 class="font-maven font-bold text-xl text-[#011936] leading-tight">
            Beheer Project: {{ $project->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-8 rounded-xl shadow-sm border mb-8">
                <h3 class="text-lg font-bold mb-4">Project Informatie</h3>
                <p><strong>Klant:</strong> {{ $project->user->name }}</p>
                <p><strong>Status:</strong> {{ $project->status }}</p>
            </div>

            <div class="bg-gray-50 p-8 rounded-xl border border-dashed border-gray-300">
                <h3 class="font-maven font-bold text-lg mb-4 text-[#011936]">Bestand uploaden voor deze klant</h3>
                
                <form action="{{ route('admin.projects.upload', $project->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Naam van het document</label>
                        <input type="text" name="document_name" placeholder="bijv. Concept Ontwerp V1" class="mt-1 block w-full rounded-md border-gray-300">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Bestand</label>
                        <input type="file" name="file" class="mt-1 block w-full text-sm text-gray-500">
                    </div>

                    <button type="submit" class="bg-[#011936] text-white px-6 py-2 rounded-lg font-bold hover:bg-black transition">
                        Uploaden naar Portaal
                    </button>
                </form>
            </div>

            <div class="mt-8">
                <h3 class="font-maven font-bold text-lg mb-4">Reeds geüploade documenten</h3>
                <ul class="bg-white rounded-lg border divide-y">
                    @foreach($project->documents as $document)
                        <li class="p-4 flex justify-between items-center">
                            <span>{{ $document->name }}</span>
                            <span class="text-sm px-2 py-1 rounded bg-blue-100 text-blue-800">{{ $document->status }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>