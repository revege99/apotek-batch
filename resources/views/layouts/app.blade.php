<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') === 'Laravel' ? config('apotik.brand.name') : config('app.name', config('apotik.brand.name')) }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-screen overflow-hidden font-sans antialiased">
        <div x-data="layoutShell" class="h-screen overflow-hidden">
            <div x-data x-cloak x-show="$store.loadingState.active" x-transition.opacity.duration.150ms class="app-loading-shell">
                <div class="app-loading-card">
                    <div class="app-loading-spinner"></div>
                    <div class="min-w-0">
                        <p class="text-[0.72rem] font-semibold uppercase tracking-[0.18em] text-emerald-600">Loading</p>
                        <p class="mt-1 truncate text-sm font-medium text-slate-700" x-text="$store.loadingState.message"></p>
                    </div>
                    <div class="app-loading-bar"></div>
                </div>
            </div>

            <div class="pointer-events-none fixed right-4 top-4 z-[70] w-full max-w-sm sm:right-6 sm:top-6">
                @include('layouts.toast')
            </div>

            <div class="flex h-screen overflow-hidden">
                <div
                    x-cloak
                    x-show="sidebarOpen"
                    x-transition.opacity
                    class="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-sm lg:hidden"
                    @click="sidebarOpen = false"
                ></div>

                <aside
                    x-cloak
                    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                    class="fixed inset-y-0 left-0 z-50 w-56 max-w-[calc(100vw-2rem)] transform border-r border-white/60 bg-white/95 shadow-2xl shadow-slate-900/20 transition duration-300 ease-out lg:static lg:z-auto lg:max-w-none lg:translate-x-0 lg:shadow-none"
                >
                    @include('layouts.navigation')
                </aside>

                <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
                    <div class="sticky top-0 z-30 border-b border-white/60 bg-white/80 backdrop-blur-xl lg:hidden">
                        <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                            <div class="flex items-center">
                                <button
                                    type="button"
                                    class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-emerald-200 hover:text-emerald-700 lg:hidden"
                                    @click="sidebarOpen = true"
                                >
                                    <span class="sr-only">Buka menu</span>
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                                        <path d="M4 7h16M4 12h16M4 17h16" />
                                    </svg>
                                </button>
                            </div>

                        </div>
                    </div>

                    <main x-ref="mainContent" class="min-h-0 flex-1 overflow-y-auto px-4 pb-6 pt-4 sm:px-6 sm:pb-7 sm:pt-5 lg:pl-2 lg:pr-[10px] lg:pb-8 lg:pt-5">
                        @isset($header)
                            <header class="mb-5">
                                {{ $header }}
                            </header>
                        @endisset

                        {{ $slot }}
                    </main>
                </div>
            </div>
        </div>
    </body>
</html>
