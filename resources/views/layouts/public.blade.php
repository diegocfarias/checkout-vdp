<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>@yield('title', 'Checkout') - VDP</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
            </nav>

            {{-- Mobile hamburger --}}
            <button type="button" id="mobile-menu-btn" class="sm:hidden w-10 h-10 flex items-center justify-center text-gray-300 hover:text-white">
                <svg id="menu-icon-open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg id="menu-icon-close" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Mobile sidebar --}}
        <div id="mobile-sidebar-overlay" class="fixed inset-0 bg-black/50 z-40 hidden sm:hidden" style="top:56px"></div>
        <nav id="mobile-sidebar" class="fixed top-[56px] right-0 bottom-0 w-64 bg-gray-900 z-50 transform translate-x-full transition-transform duration-300 ease-in-out sm:hidden">
            <div class="flex flex-col py-4">
                <a href="{{ route('search.home') }}" class="px-6 py-3 text-gray-200 hover:bg-gray-800 hover:text-white transition-colors flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Passagens
                </a>
                <a href="{{ route('tracking.form') }}" class="px-6 py-3 text-gray-200 hover:bg-gray-800 hover:text-white transition-colors flex items-center gap-3">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Meu pedido
                </a>
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
