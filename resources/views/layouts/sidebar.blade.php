<aside class="hidden md:flex flex-col w-64 bg-[#011936] text-white fixed left-0 top-16 bottom-0 border-r border-white/10 z-20">
    <div class="flex-1 flex flex-col pt-5 pb-4 overflow-y-auto">
        <div class="flex-1 px-4 space-y-1">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 mb-2">Project Management</p>

            <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" 
                class="w-full flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-150 {{ request()->routeIs('dashboard') ? 'bg-purple-600 text-white font-semibold' : 'text-gray-300 hover:bg-white/5 hover:text-white' }}">
                {{ __('Dashboard') }}
            </x-nav-link>

            @if(auth()->user()->is_admin)
                <div class="pt-4 mt-4 border-t border-white/10">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-3 mb-2">Admin Beheer</p>
                </div>

                <x-nav-link :href="route('admin.projects.index')" :active="request()->routeIs('admin.projects.*') && !request()->routeIs('admin.projects.create')" 
                    class="w-full flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-150 {{ request()->routeIs('admin.projects.*') && !request()->routeIs('admin.projects.create') ? 'bg-purple-600 text-white font-semibold' : 'text-gray-300 hover:bg-white/5 hover:text-white' }}">
                    {{ __('Admin Paneel') }}
                </x-nav-link>

                <x-nav-link :href="route('admin.projects.create')" :active="request()->routeIs('admin.projects.create')" 
                    class="w-full flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors duration-150 {{ request()->routeIs('admin.projects.create') ? 'bg-purple-600 text-white font-semibold' : 'text-gray-300 hover:bg-white/5 hover:text-white' }}">
                    {{ __('Nieuw Project +') }}
                </x-nav-link>
            @endif
        </div>
    </div>
</aside>