<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GKR Agency | Klantportaal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased bg-slate-50 font-sans text-black">

    <nav class="bg-gkr-blue text-black shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20">
                <div class="flex items-center">
                    <span class="text-2xl font-black tracking-tighter uppercase">GKR <span class="text-gkr-orange">Agency</span></span>
                </div>
                
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#" class="hover:text-gkr-orange transition">Diensten</a>
                    <a href="#" class="hover:text-gkr-orange transition">Over ons</a>
                    <a href="#" class="hover:text-gkr-orange transition">Contact</a>
                    
                    @if (Route::has('login'))
                        <div class="flex items-center space-x-4 ml-4">
                            @auth
                                <a href="{{ url('/dashboard') }}" class="bg-white text-gkr-blue px-5 py-2 rounded-full font-bold hover:bg-gkr-orange hover:text-black transition">Dashboard</a>
                            @else
                                <a href="{{ route('login') }}" class="font-medium hover:text-gkr-orange">Inloggen</a>
                                <a href="{{ route('register') }}" class="bg-gkr-orange text-black px-5 py-2 rounded-full font-bold hover:bg-white hover:text-gkr-blue transition shadow-lg">Aanmelden</a>
                            @endauth
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <section class="relative bg-gkr-black py-24 lg:py-32 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 relative z-10">
            <div class="text-center">
                <h1 class="text-5xl lg:text-7xl font-extrabold text-black mb-6">
                    Real-time inzicht in <br> <span class="text-gkr-orange">jouw projecten.</span>
                </h1>
                <p class="text-xl text-black-100 max-w-2xl mx-auto mb-10 leading-relaxed">
                    Welkom bij het officiële GKR Klantportaal. Beheer je documenten, bekijk de voortgang en communiceer direct met ons team.
                </p>
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <a href="{{ route('login') }}" class="bg-gkr-orange text-black px-10 py-4 rounded-xl font-bold text-lg shadow-2xl hover:scale-105 transition">Start je sessie</a>
                    <a href="#info" class="bg-white/10 text-black border border-white/20 backdrop-blur-md px-10 py-4 rounded-xl font-bold text-lg hover:bg-white/20 transition">Hoe het werkt</a>
                </div>
            </div>
        </div>
        
        <div class="absolute top-0 right-0 -mr-20 -mt-20 w-96 h-96 bg-white/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-0 -ml-20 -mb-20 w-96 h-96 bg-gkr-orange/20 rounded-full blur-3xl"></div>
    </section>

    <section id="info" class="py-20">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white p-8 rounded-2xl shadow-sm border-b-4 border-gkr-blue">
                    <div class="w-12 h-12 bg-blue-100 text-gkr-blue rounded-lg flex items-center justify-center mb-6 text-2xl font-bold">1</div>
                    <h3 class="text-xl font-bold mb-3 text-gkr-blue">Inzicht</h3>
                    <p class="text-gray-600 italic">Volg de voortgang van je web-ontwikkeling van dag tot dag.</p>
                </div>
                <div class="bg-white p-8 rounded-2xl shadow-sm border-b-4 border-gkr-black">
                    <div class="w-12 h-12 bg-black-100 text-gkr-black rounded-lg flex items-center justify-center mb-6 text-2xl font-bold">2</div>
                    <h3 class="text-xl font-bold mb-3 text-gkr-black">Documenten</h3>
                    <p class="text-gray-600">Al je facturen, ontwerpen en contracten op één veilige plek.</p>
                </div>
                <div class="bg-white p-8 rounded-2xl shadow-sm border-b-4 border-gkr-orange">
                    <div class="w-12 h-12 bg-orange-100 text-gkr-orange rounded-lg flex items-center justify-center mb-6 text-2xl font-bold">3</div>
                    <h3 class="text-xl font-bold mb-3 text-gkr-orange">Direct Contact</h3>
                    <p class="text-gray-600 italic">Chat direct met Yusuf en de andere developers bij GKR.</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-slate-900 py-10 text-slate-400 border-t border-slate-800">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p>&copy; 2026 GKR Agency. Yusuf's Stageproject - HBO Stage Niveau.</p>
        </div>
    </footer>

</body>
</html>