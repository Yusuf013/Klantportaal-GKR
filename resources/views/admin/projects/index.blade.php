<x-app-layout>
    <x-slot name="header">
        <h2 class="font-maven font-bold text-xl text-[#011936] leading-tight">
            Projecten Beheren (Admin)
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 rounded-xl shadow-sm border">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b">
                            <th class="pb-3 font-bold text-[#011936]">Projectnaam</th>
                            <th class="pb-3 font-bold text-[#011936]">Klant</th>
                            <th class="pb-3 font-bold text-[#011936]">Status</th>
                            <th class="pb-3 text-right">Actie</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($projects as $project)
                            <tr>
                                <td class="py-4">{{ $project->name }}</td>
                                <td class="py-4">{{ $project->user->name }}</td>
                                <td class="py-4">
                                    <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">{{ $project->status }}</span>
                                </td>
                                <td class="py-4 text-right">
                                    <a href="{{ route('admin.projects.show', $project->id) }}" class="text-blue-600 font-bold hover:underline">
                                        Beheren & Uploaden &rarr;
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>