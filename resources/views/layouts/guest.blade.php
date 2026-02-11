<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Galeria') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans text-gray-900 antialiased">
    <div class="relative min-h-screen flex flex-col justify-center items-center pt-6 sm:pt-0 overflow-hidden">
        <div
            class="absolute inset-0 z-0 
                bg-black/30 
                bg-[url('https://images.unsplash.com/photo-1485470733090-0aae1788d5af?fm=jpg&q=60&w=3000&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1yZWxhdGVkfDI1fHx8ZW58MHx8fHx8')] 
                bg-cover bg-center bg-blend-multiply blur-sm scale-105">
        </div>
        <div class="flex justify-center pt-4  z-10 relative mb-4">
            <div class="flex items-center space-x-2">
                <img src="{{ asset(config('adminpanel.logo_image')) }}" alt="{{ config('adminpanel.logo_name') }}"
                    class="h-40 w-auto px-2" style="height: 40px;" />
                <span class="text-2xl font-mono font-semibold text-gray-200">Capture a essência </span>

            </div>
        </div>
        <div class="w-full sm:max-w-md mt-6 bg-white/20 backdrop-blur shadow-md sm:rounded-lg border-3 border-blue-600/50 fill-white drop-shadow-xl/50 
                bg-[url('https://images.unsplash.com/photo-1485470733090-0aae1788d5af?fm=jpg&q=60&w=3000&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1yZWxhdGVkfDI1fHx8ZW58MHx8fHx8')] 
                bg-cover bg-center bg-blend-multiply">

         <div>
            {{ $slot }}
            </div>
        </div>
    </div>
</body>

</html>
