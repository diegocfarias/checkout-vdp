<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Checkout') - VDP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @stack('head')
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-4xl mx-auto px-4 py-4">
            <h1 class="text-xl font-bold text-gray-800">VDP Checkout</h1>
        </div>
    </header>

    <main class="flex-1 max-w-4xl mx-auto w-full px-4 py-8">
        @yield('content')
    </main>

    <footer class="bg-white border-t border-gray-200 mt-auto">
        <div class="max-w-4xl mx-auto px-4 py-4 text-center text-sm text-gray-500">
            &copy; {{ date('Y') }} VDP
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
