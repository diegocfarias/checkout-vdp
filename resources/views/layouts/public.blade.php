<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>@yield('title', 'Checkout') - VDP</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
                    },
                },
            },
        }
    </script>
    <style>
        a, button, input, select, textarea, label, [role="button"], .cursor-pointer {
            touch-action: manipulation;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
            border-color: #2563eb;
        }

        details > summary::-webkit-details-marker { display: none; }
        details > summary { list-style: none; }
        details[open] .details-open-rotate { transform: rotate(180deg); }

        .bg-white { transition: box-shadow 0.2s ease, border-color 0.2s ease; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fadeIn { animation: fadeIn 0.3s ease-out; }

        @keyframes pulse-slow {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .animate-pulse-slow { animation: pulse-slow 2s ease-in-out infinite; }
    </style>
    @stack('styles')
    @stack('head')
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <header class="bg-white sticky top-0 z-40 border-b border-gray-100 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between">
            <a href="{{ route('search.home') }}" class="shrink-0">
                <img src="/images/logo-vdp.png?v={{ filemtime(public_path('images/logo-vdp.png')) }}" alt="Voe de Primeira" class="h-8">
            </a>

            {{-- Desktop nav --}}
            <nav class="hidden sm:flex items-center gap-1">
                <a href="{{ route('search.home') }}" class="text-sm font-medium text-gray-600 hover:text-blue-700 hover:bg-blue-50 px-3 py-2 rounded-lg transition-all flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Passagens
                </a>
                <a href="{{ route('tracking.form') }}" class="text-sm font-medium text-gray-600 hover:text-blue-700 hover:bg-blue-50 px-3 py-2 rounded-lg transition-all flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Meu pedido
                </a>

                <div class="w-px h-6 bg-gray-200 mx-2"></div>

                @auth('customer')
                    <div class="relative">
                        <button type="button" id="user-menu-btn" class="flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-blue-700 hover:bg-blue-50 pl-2 pr-3 py-1.5 rounded-lg transition-all">
                            @if(auth('customer')->user()->avatar_url)
                                <img src="{{ auth('customer')->user()->avatar_url }}" alt="" class="w-7 h-7 rounded-full ring-2 ring-blue-100">
                            @else
                                <span class="w-7 h-7 rounded-full bg-blue-600 flex items-center justify-center text-white text-xs font-semibold ring-2 ring-blue-100">{{ strtoupper(substr(auth('customer')->user()->name, 0, 1)) }}</span>
                            @endif
                            <span>{{ explode(' ', auth('customer')->user()->name)[0] }}</span>
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div id="user-menu-dropdown" class="hidden absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-xl border border-gray-100 py-1.5 z-50">
                            <div class="px-4 py-2 border-b border-gray-100 mb-1">
                                <p class="text-sm font-semibold text-gray-900">{{ auth('customer')->user()->name }}</p>
                                <p class="text-xs text-gray-400 truncate">{{ auth('customer')->user()->email }}</p>
                            </div>
                            <a href="{{ route('customer.dashboard') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                Minha conta
                            </a>
                            <a href="{{ route('customer.orders') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                Meus pedidos
                            </a>
                            @if(auth('customer')->user()->isAffiliate())
                            <a href="{{ route('customer.referrals') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-emerald-700 transition-colors">
                                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                                Indicações
                            </a>
                            @endif
                            <a href="{{ route('customer.support.index') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                                Atendimentos
                            </a>
                            <a href="{{ route('customer.profile') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-blue-700 transition-colors">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                Meu perfil
                            </a>
                            <div class="border-t border-gray-100 my-1.5"></div>
                            <form method="POST" action="{{ route('customer.logout') }}">
                                @csrf
                                <button type="submit" class="flex items-center gap-2.5 w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-red-50 hover:text-red-600 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    Sair
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('customer.login') }}" class="text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Entrar
                    </a>
                @endauth
            </nav>

            {{-- Mobile hamburger --}}
            <button type="button" id="mobile-menu-btn" class="sm:hidden w-10 h-10 flex items-center justify-center text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
                <svg id="menu-icon-open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg id="menu-icon-close" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Mobile sidebar --}}
        <div id="mobile-sidebar-overlay" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-40 hidden sm:hidden" style="top:56px"></div>
        <nav id="mobile-sidebar" class="fixed top-[56px] right-0 bottom-0 w-72 bg-white z-50 transform translate-x-full transition-transform duration-300 ease-in-out sm:hidden overflow-y-auto shadow-2xl">
            <div class="flex flex-col p-4 gap-1">
                @auth('customer')
                    <div class="flex items-center gap-3 px-3 py-3 mb-2 bg-gray-50 rounded-xl">
                        @if(auth('customer')->user()->avatar_url)
                            <img src="{{ auth('customer')->user()->avatar_url }}" alt="" class="w-10 h-10 rounded-full ring-2 ring-blue-100">
                        @else
                            <span class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-semibold ring-2 ring-blue-100">{{ strtoupper(substr(auth('customer')->user()->name, 0, 1)) }}</span>
                        @endif
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ auth('customer')->user()->name }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ auth('customer')->user()->email }}</p>
                        </div>
                    </div>
                @endauth

                <a href="{{ route('search.home') }}" class="px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition-colors flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Passagens
                </a>
                <a href="{{ route('tracking.form') }}" class="px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition-colors flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Meu pedido
                </a>

                @auth('customer')
                    <div class="h-px bg-gray-100 my-2"></div>
                    <a href="{{ route('customer.dashboard') }}" class="px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition-colors flex items-center gap-3">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Minha conta
                    </a>
                    <a href="{{ route('customer.orders') }}" class="px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition-colors flex items-center gap-3">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        Meus pedidos
                    </a>
                    @if(auth('customer')->user()->isAffiliate())
                    <a href="{{ route('customer.referrals') }}" class="px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 rounded-lg transition-colors flex items-center gap-3">
                        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                        Indicações
                    </a>
                    @endif
                    <a href="{{ route('customer.support.index') }}" class="px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition-colors flex items-center gap-3">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                        Atendimentos
                    </a>
                    <a href="{{ route('customer.profile') }}" class="px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-lg transition-colors flex items-center gap-3">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Meu perfil
                    </a>
                    <div class="h-px bg-gray-100 my-2"></div>
                    <form method="POST" action="{{ route('customer.logout') }}">
                        @csrf
                        <button type="submit" class="w-full px-3 py-2.5 text-sm font-medium text-red-500 hover:bg-red-50 hover:text-red-600 rounded-lg transition-colors flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Sair
                        </button>
                    </form>
                @else
                    <div class="h-px bg-gray-100 my-2"></div>
                    <a href="{{ route('customer.login') }}" class="mx-1 text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Entrar ou criar conta
                    </a>
                @endauth
            </div>
        </nav>
    </header>

    <script>
    (function() {
        var btn = document.getElementById('mobile-menu-btn');
        var sidebar = document.getElementById('mobile-sidebar');
        var overlay = document.getElementById('mobile-sidebar-overlay');
        var iconOpen = document.getElementById('menu-icon-open');
        var iconClose = document.getElementById('menu-icon-close');
        var isOpen = false;

        function toggle() {
            isOpen = !isOpen;
            if (isOpen) {
                sidebar.classList.remove('translate-x-full');
                sidebar.classList.add('translate-x-0');
                overlay.classList.remove('hidden');
                iconOpen.classList.add('hidden');
                iconClose.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            } else {
                sidebar.classList.remove('translate-x-0');
                sidebar.classList.add('translate-x-full');
                overlay.classList.add('hidden');
                iconOpen.classList.remove('hidden');
                iconClose.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        }

        btn.addEventListener('click', toggle);
        overlay.addEventListener('click', toggle);
    })();

    (function() {
        var menuBtn = document.getElementById('user-menu-btn');
        var dropdown = document.getElementById('user-menu-dropdown');
        if (!menuBtn || !dropdown) return;
        menuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('hidden');
        });
        document.addEventListener('click', function() {
            dropdown.classList.add('hidden');
        });
    })();
    </script>

    <main class="flex-1 @yield('container_class', 'max-w-5xl') mx-auto w-full px-4 sm:px-6 py-8">
        @yield('content')
    </main>

    <footer class="bg-white mt-auto">
        <div class="max-w-6xl mx-auto px-6">
            <div class="h-px bg-gradient-to-r from-transparent via-amber-400/60 to-transparent"></div>
        </div>

        <div class="max-w-6xl mx-auto px-6 py-10">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                <div>
                    <img src="/images/logo-vdp.png?v={{ filemtime(public_path('images/logo-vdp.png')) }}" alt="Voe de Primeira" class="h-8 mb-4">
                    <p class="text-sm text-gray-500 leading-relaxed">Passagens aéreas com preços exclusivos usando milhas. Emissão rápida e segura.</p>
                </div>

                <div>
                    <h4 class="text-sm font-semibold text-gray-900 mb-3">Navegação</h4>
                    <ul class="space-y-2">
                        <li><a href="{{ route('search.home') }}" class="text-sm text-gray-500 hover:text-blue-600 transition-colors">Buscar passagens</a></li>
                        <li><a href="{{ route('tracking.form') }}" class="text-sm text-gray-500 hover:text-blue-600 transition-colors">Acompanhar pedido</a></li>
                        @auth('customer')
                            <li><a href="{{ route('customer.dashboard') }}" class="text-sm text-gray-500 hover:text-blue-600 transition-colors">Minha conta</a></li>
                        @else
                            <li><a href="{{ route('customer.login') }}" class="text-sm text-gray-500 hover:text-blue-600 transition-colors">Entrar / Criar conta</a></li>
                        @endauth
                    </ul>
                </div>

                <div>
                    <h4 class="text-sm font-semibold text-gray-900 mb-3">Atendimento</h4>
                    <ul class="space-y-2">
                        @php $footerWa = \App\Models\Setting::get('whatsapp_number', ''); @endphp
                        @if($footerWa)
                            <li>
                                <a href="https://wa.me/{{ preg_replace('/\D/', '', $footerWa) }}" target="_blank" rel="noopener"
                                   class="text-sm text-gray-500 hover:text-green-600 transition-colors flex items-center gap-2">
                                    <svg class="w-4 h-4 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.5.5 0 00.612.612l4.458-1.495A11.952 11.952 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.387 0-4.592-.838-6.313-2.236l-.44-.364-3.19 1.07 1.07-3.19-.364-.44A9.956 9.956 0 012 12C2 6.486 6.486 2 12 2s10 4.486 10 10-4.486 10-10 10z"/></svg>
                                    WhatsApp
                                </a>
                            </li>
                        @endif
                        <li class="text-sm text-gray-400">Seg a Sex, 9h às 18h</li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-sm font-semibold text-gray-900 mb-3">Pagamento</h4>
                    <div class="flex flex-wrap gap-2">
                        <span class="text-xs bg-gray-100 text-gray-600 px-2.5 py-1 rounded font-medium">PIX</span>
                        <span class="text-xs bg-gray-100 text-gray-600 px-2.5 py-1 rounded font-medium">Visa</span>
                        <span class="text-xs bg-gray-100 text-gray-600 px-2.5 py-1 rounded font-medium">Master</span>
                        <span class="text-xs bg-gray-100 text-gray-600 px-2.5 py-1 rounded font-medium">Elo</span>
                        <span class="text-xs bg-gray-100 text-gray-600 px-2.5 py-1 rounded font-medium">Amex</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-3">Parcele em até 12x no cartão</p>
                </div>
            </div>

            <div class="border-t border-gray-200 mt-8 pt-6 text-center">
                <p class="text-xs text-gray-400">&copy; {{ date('Y') }} Voe de Primeira. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>
    @include('partials._travel_loading')
    @stack('scripts')
</body>
</html>
