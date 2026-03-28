<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>@yield('title', 'Checkout') - VDP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        a, button, input, select, textarea, label, [role="button"], .cursor-pointer {
            touch-action: manipulation;
        }
    </style>
    @stack('styles')
    @stack('head')
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <header class="bg-gray-900 sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between">
            <a href="{{ route('search.home') }}" class="shrink-0">
                <img src="/images/logo-vdp.png" alt="Voe de Primeira" class="h-8">
            </a>

            {{-- Desktop nav --}}
            <nav class="hidden sm:flex items-center gap-6">
                <a href="{{ route('search.home') }}" class="text-sm text-gray-300 hover:text-white transition-colors">Passagens</a>
                <a href="{{ route('tracking.form') }}" class="text-sm text-gray-300 hover:text-white transition-colors">Meu pedido</a>
                @auth('customer')
                    <div class="relative" x-data="{ open: false }">
                        <button type="button" id="user-menu-btn" class="flex items-center gap-2 text-sm text-gray-300 hover:text-white transition-colors">
                            @if(auth('customer')->user()->avatar_url)
                                <img src="{{ auth('customer')->user()->avatar_url }}" alt="" class="w-6 h-6 rounded-full">
                            @else
                                <span class="w-6 h-6 rounded-full bg-emerald-600 flex items-center justify-center text-white text-xs font-semibold">{{ strtoupper(substr(auth('customer')->user()->name, 0, 1)) }}</span>
                            @endif
                            <span>{{ explode(' ', auth('customer')->user()->name)[0] }}</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div id="user-menu-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                            <a href="{{ route('customer.dashboard') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Minha conta</a>
                            <a href="{{ route('customer.orders') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Meus pedidos</a>
                            <a href="{{ route('customer.profile') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Meu perfil</a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <form method="POST" action="{{ route('customer.logout') }}">
                                @csrf
                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-50">Sair</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('customer.login') }}" class="text-sm bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-1.5 rounded-lg transition-colors">Entrar</a>
                @endauth
            </nav>

            {{-- Mobile hamburger --}}
            <button type="button" id="mobile-menu-btn" class="sm:hidden w-10 h-10 flex items-center justify-center text-gray-300 hover:text-white">
                <svg id="menu-icon-open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg id="menu-icon-close" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Mobile sidebar --}}
        <div id="mobile-sidebar-overlay" class="fixed inset-0 bg-black/50 z-40 hidden sm:hidden" style="top:56px"></div>
        <nav id="mobile-sidebar" class="fixed top-[56px] right-0 bottom-0 w-64 bg-gray-900 z-50 transform translate-x-full transition-transform duration-300 ease-in-out sm:hidden overflow-y-auto">
            <div class="flex flex-col py-4">
                <a href="{{ route('search.home') }}" class="px-6 py-3 text-gray-200 hover:bg-gray-800 hover:text-white transition-colors flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Passagens
                </a>
                <a href="{{ route('tracking.form') }}" class="px-6 py-3 text-gray-200 hover:bg-gray-800 hover:text-white transition-colors flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Meu pedido
                </a>
                @auth('customer')
                    <div class="border-t border-gray-800 my-2"></div>
                    <a href="{{ route('customer.dashboard') }}" class="px-6 py-3 text-gray-200 hover:bg-gray-800 hover:text-white transition-colors flex items-center gap-3">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Minha conta
                    </a>
                    <a href="{{ route('customer.orders') }}" class="px-6 py-3 text-gray-200 hover:bg-gray-800 hover:text-white transition-colors flex items-center gap-3">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        Meus pedidos
                    </a>
                    <a href="{{ route('customer.profile') }}" class="px-6 py-3 text-gray-200 hover:bg-gray-800 hover:text-white transition-colors flex items-center gap-3">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Meu perfil
                    </a>
                    <form method="POST" action="{{ route('customer.logout') }}">
                        @csrf
                        <button type="submit" class="w-full px-6 py-3 text-red-400 hover:bg-gray-800 hover:text-red-300 transition-colors flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Sair
                        </button>
                    </form>
                @else
                    <div class="border-t border-gray-800 my-2"></div>
                    <a href="{{ route('customer.login') }}" class="px-6 py-3 text-emerald-400 hover:bg-gray-800 hover:text-emerald-300 transition-colors flex items-center gap-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Entrar
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

    <main class="flex-1 @yield('container_class', 'max-w-4xl') mx-auto w-full px-4 py-8">
        @yield('content')
    </main>

    <footer class="bg-gray-900 border-t border-gray-800 mt-auto">
        <div class="max-w-4xl mx-auto px-4 py-4 text-center text-sm text-gray-400">
            &copy; {{ date('Y') }} Voe de Primeira
        </div>
    </footer>
    @include('partials._travel_loading')
    @stack('scripts')
</body>
</html>
