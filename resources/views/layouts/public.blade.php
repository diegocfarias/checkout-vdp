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
    <header class="bg-gray-900">
        <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-center">
            <img src="/images/logo-vdp.png" alt="Voe de Primeira" class="w-36">
        </div>
    </header>

    <main class="flex-1 max-w-4xl mx-auto w-full px-4 py-8">
        @yield('content')
    </main>

    <footer class="bg-gray-900 border-t border-gray-800 mt-auto">
        <div class="max-w-4xl mx-auto px-4 py-4 text-center text-sm text-gray-400">
            &copy; {{ date('Y') }} Voe de Primeira
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
