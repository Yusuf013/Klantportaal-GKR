<aside class="hidden md:flex flex-col w-64 bg-[#011936] text-white fixed left-0 top-16 bottom-0 border-r border-white/5 z-20">
    <div class="flex-1 flex flex-col pt-5 pb-4 overflow-y-auto">
        <div class="flex-1 px-4 space-y-1">
            <p class="text-xs font-semibold text-white uppercase tracking-wider px-3 mb-3">Project Management</p>

            @php
                $dashboardRoute = auth()->user()->is_admin ? 'admin.dashboard' : 'dashboard';
                $isDashboardActive = request()->routeIs($dashboardRoute);
            @endphp

            <x-nav-link :href="route($dashboardRoute)" :active="$isDashboardActive" 
                class="w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-150 group {{ $isDashboardActive ? 'bg-white/10 text-white font-bold' : 'text-gray-400 hover:bg-white/5 hover:text-white' }}">
                
                <svg class="w-5 h-4 mr-3 shrink-0 transition-colors {{ $isDashboardActive ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" 
                     fill="none" 
                     viewBox="0 0 15 15"
                     xmlns="http://www.w3.org/2000/svg">
                     
                     <path fill="currentColor" d="M8.333 5V0H15v5zM0 8.333V0h6.667v8.333zM8.333 15V6.667H15V15zM0 15v-5h6.667v5zm1.667-8.333H5v-5H1.667zM10 13.333h3.333v-5H10zm0-10h3.333V1.667H10zm-8.333 10H5v-1.666H1.667z"/>
                     
                </svg>

                {{ __('Dashboard') }}
            </x-nav-link>

            @if(!auth()->user()->is_admin)
                @php
                    $isProjectsActive = request()->routeIs('client.projects.index');
                @endphp
                <x-nav-link :href="route('client.projects.index')" :active="$isProjectsActive" 
                    class="w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-150 group {{ $isProjectsActive ? 'bg-white/10 text-white font-bold' : 'text-gray-400 hover:bg-white/5 hover:text-white' }}">
                    
                    <svg class="w-5 h-5 mr-3 shrink-0 transition-colors {{ $isProjectsActive ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" 
                         fill="none" 
                         stroke="currentColor" 
                         stroke-width="2" 
                         viewBox="0 0 24 24" 
                         xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>

                    {{ __('Projecten') }}
                </x-nav-link>

                @php
                    $isDocsActive = request()->routeIs('documents.index');
                @endphp
                <x-nav-link :href="route('documents.index')" :active="$isDocsActive" 
                    class="w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-150 group {{ $isDocsActive ? 'bg-white/10 text-white font-bold' : 'text-gray-400 hover:bg-white/5 hover:text-white' }}">
                    
                    <svg class="w-5 h-5 mr-3 shrink-0 transition-colors {{ $isDocsActive ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" 
                         fill="none" 
                         stroke="currentColor" 
                         viewBox="0 0 24 24"
                         xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6a2 2 0 002-2V7M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2M8 7h8"></path>
                    </svg>

                    {{ __('Documenten') }}
                </x-nav-link>
            @endif

            @if(auth()->user()->is_admin)

                @php
                    /* Route aangepast naar admin.clients.index voor correcte actieve status */
                    $isClientsActive = request()->routeIs('admin.clients.index');
                @endphp
                <x-nav-link :href="route('admin.clients.index')" :active="$isClientsActive" 
                    class="w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-150 group {{ $isClientsActive ? 'bg-white/10 text-white font-bold' : 'text-gray-400 hover:bg-white/5 hover:text-white' }}">
                    
                    <svg class="w-5 h-5 mr-3 shrink-0 transition-colors {{ $isClientsActive ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" 
                         fill="none" 
                         stroke="currentColor" 
                         stroke-width="2" 
                         viewBox="0 0 24 24" 
                         xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>

                    {{ __('Klanten') }}
                </x-nav-link>

                <x-nav-link :href="route('admin.projects.index')" :active="request()->routeIs('admin.projects.*') && !request()->routeIs('admin.projects.create')" 
                    class="w-full flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-150 group {{ request()->routeIs('admin.projects.*') && !request()->routeIs('admin.projects.create') ? 'bg-white/10 text-white font-bold' : 'text-gray-400 hover:bg-white/5 hover:text-white' }}">
                    
                    <svg class="w-5 h-5 mr-3 shrink-0 transition-colors {{ request()->routeIs('admin.projects.*') && !request()->routeIs('admin.projects.create') ? 'text-white' : 'text-gray-400 group-hover:text-white' }}" 
                         fill="none" 
                         viewBox="0 0 19 16"
                         xmlns="http://www.w3.org/2000/svg">
                         
                         <path fill="currentColor" fill-rule="evenodd" d="M.49 15.344q.489.49 1.177.49h14.166v-1.667H1.667V3.333H0v10.834q0 .687.49 1.177" clip-rule="evenodd"/>
                         
                         <path fill="currentColor" d="M5 12.5q-.687 0-1.177-.49a1.6 1.6 0 0 1-.49-1.177V1.667q0-.688.49-1.177T5 0h4.167l1.666 1.667h5.834q.687 0 1.177.49.49.489.49 1.176v7.5q0 .688-.49 1.177t-1.177.49z"/>
                         
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