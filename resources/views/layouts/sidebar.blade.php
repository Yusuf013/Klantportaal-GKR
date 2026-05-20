<aside class="hidden md:flex flex-col w-64 bg-[#011936] text-white fixed left-0 top-16 bottom-0 border-r border-white/5 z-20">
    <div class="flex-1 flex flex-col pt-5 pb-4 overflow-y-auto">
        <div class="flex-1 px-4 space-y-1">
            <p class="text-xs font-semibold text-white uppercase tracking-wider px-3 mb-3">Project Management</p>

            <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" 
                class="w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-150 group {{ request()->routeIs('dashboard') ? 'bg-white/10 text-white font-bold' : 'text-gray-400 hover:bg-white/5 hover:text-white' }}">
                <svg class="w-5 h-5 mr-3 shrink-0 transition-colors {{ request()->routeIs('dashboard') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                {{ __('Dashboard') }}
            </x-nav-link>

            @if(auth()->user()->is_admin)

                <x-nav-link :href="route('admin.projects.index')" :active="request()->routeIs('admin.projects.*') && !request()->routeIs('admin.projects.create')" 
                    class="w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-150 group {{ request()->routeIs('admin.projects.*') && !request()->routeIs('admin.projects.create') ? 'bg-white/10 text-white font-bold' : 'text-gray-400 hover:bg-white/5 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3 shrink-0 transition-colors {{ request()->routeIs('admin.projects.*') && !request()->routeIs('admin.projects.create') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    {{ __('Projecten') }}
                </x-nav-link>

                <x-nav-link :href="route('admin.projects.create')" :active="request()->routeIs('admin.projects.create')" 
                    class="w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-150 group {{ request()->routeIs('admin.projects.create') ? 'bg-white/10 text-white font-bold' : 'text-gray-400 hover:bg-white/5 hover:text-white' }}">
                    <svg class="w-5 h-5 mr-3 shrink-0 transition-colors {{ request()->routeIs('admin.projects.create') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    {{ __('Nieuw Project +') }}
                </x-nav-link>
            @endif
        </div>
    </div>
</aside>