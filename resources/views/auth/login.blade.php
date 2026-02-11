<x-guest-layout>

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.10.2/cdn.min.js" defer></script>

    <script>
        body {
            margin: 0;
            height: 100 vh;
            overflow: hidden; /* Remove a rolagem se você quer o fundo fixo */
        }
    </script>

    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#04a96d",
                        "background-light": "#f5f8f7",
                        "background-dark": "#0a0a0a",
                        "card-dark": "#121212",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    },
                },
            },
        }
    </script>


    <div class="glass-effect rounded-xl p-6 w-full max-w-md mx-auto shadow-2xl">
        <x-auth-session-status class="mb-4" :status="session('status')" />
        <!-- Background Hero Section -->



        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="space-y-4">
                <div class="flex flex-col gap-2 mt-4 mx-4">
                    <label for="email"
                        class="text-white/80 text-xs font-semibold uppercase tracking-wider ml-1">Email</label>
                    <div class="relative">
                        <input id="email" name="email" value="{{ old('email') }}" required autofocus
                            autocomplete="username"
                            class="w-full bg-white/0 h-10 text-[#ffffffff] placeholder:text-white/30 
                   border-t-0 border-l-0 border-r-0 border-b-2 border-white/70 
                   focus:ring-2 focus:border-primary focus:bg-white/0 transition-all shadow-none"
                            placeholder="name@studio.com" type="email" />
                    </div>
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div class="flex flex-col gap-2 mx-4">
                    <div class="flex justify-between items-center px-1">
                        <label for="password"
                            class="text-white/80 mt-2 text-xs font-semibold uppercase tracking-wider">Senha</label>

                    </div>
                    <div class="relative flex items-center">
                        <input id="password" name="password" required autocomplete="current-password"
                            class="w-full bg-white/0 h-10 text-[#ffffffff] placeholder:text-white/30 
                   border-t-0 border-l-0 border-r-0 border-b-2 border-white/70 
                   focus:ring-2 focus:border-primary focus:bg-white/0 transition-all shadow-none"
                            placeholder="Sua senha" type="password" />
                    </div>
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="block mt-4 mx-4">
                    <label for="remember_me" class="inline-flex items-center">
                        <input id="remember_me" type="checkbox"
                            class="rounded bg-white/5 border-white/10 text-[#04a96d] shadow-sm focus:ring-[#04a96d] focus:ring-offset-0"
                            name="remember">
                        <span class="ms-2 text-sm text-white/60">{{ __('Lembrar de mim') }}</span>
                    </label>
                    <span class="float-end text-sm">
                        @if (Route::has('password.request'))
                            <a class="text-primary/90 text-md font-medium hover:underline"
                                href="{{ route('password.request') }}">Esqueceu?</a>
                        @endif
                    </span>
                </div>

                <button type="submit"
                    class="w-full bg-[#13553d] hover:bg-[#0c442f] border border-white/70 text-white font-bold h-14 rounded-lg transition-all active:scale-[0.98] shadow-lg mt-4 mb-3">
                    Entrar na Galeria
                </button>

            </div>
        </form>
    </div>

</x-guest-layout>
