<x-app-layout>
    <x-slot name="header">
        <h2 class="font-maven font-bold text-xl text-gray-800 leading-tight">
            Nieuw Project Koppelen voor GKR
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-8 border border-gray-100">
                <form action="{{ route('admin.projects.store') }}" method="POST" class="space-y-6">
                    @csrf
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 font-inter">Selecteer Klant</label>
                        <select name="user_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#94b8ff] focus:ring-[#94b8ff]">
                            <option value="">-- Kies een klant --</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}">{{ $client->name }} ({{ $client->email }})</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-gray-500 italic">Alleen gebruikers met de rol 'client' verschijnen hier.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 font-inter">Project Naam</label>
                        <input type="text" name="name" placeholder="bijv. Website herontwerp" required 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#94b8ff] focus:ring-[#94b8ff]">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 font-inter">Huidige Fase</label>
                        <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#94b8ff] focus:ring-[#94b8ff]">
                            <option value="Strategie">Strategie</option>
                            <option value="Design">Design</option>
                            <option value="Development">Development</option>
                            <option value="Live">Live</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 font-inter">Verwachte Deadline</label>
                        <input type="date" name="deadline" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#94b8ff] focus:ring-[#94b8ff]">
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="w-full bg-[#011936] text-white py-3 rounded-lg font-maven font-bold hover:bg-black transition duration-200">
                            Project Opslaan & Koppelen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>