<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    {{ __("You're logged in!") }}
                </div>
            </div>
        </div>
    </div>

    <div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <h2 class="font-maven font-bold text-2xl mb-6 text-[#011936]">Uw Projecten bij GKR</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($projects as $project)
                <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100 p-6">
                    <h3 class="font-maven font-bold text-lg text-gray-800">{{ $project->name }}</h3>
                    
                    <div class="mt-2">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-700">
                            {{ $project->status }}
                        </span>
                    </div>

                    <p class="text-sm text-gray-500 mt-4 font-inter">
                        Deadline: {{ \Carbon\Carbon::parse($project->deadline)->format('d-m-Y') }}
                    </p>

                    <div class="mt-6">
                        <a href="{{ route('projects.show', $project->id) }}" 
                           class="inline-block w-full text-center bg-[#011936] text-white py-2 rounded-lg font-bold hover:bg-black transition duration-200">
                            Bekijk Details
                        </a>
                    </div>
                </div>
            @empty
                <div class="col-span-full bg-white p-12 text-center rounded-xl border border-dashed border-gray-300">
                    <p class="text-gray-500 font-inter">Er zijn op dit moment geen actieve projecten aan uw account gekoppeld.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
</x-app-layout>
