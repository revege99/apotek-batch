<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', config('apotik.brand.name')) }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    @php
        $isLoginPage = request()->routeIs('login');
    @endphp
    <body class="font-sans antialiased">
        <div class="relative min-h-screen overflow-hidden bg-slate-950">
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

            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.28),_transparent_24%),radial-gradient(circle_at_bottom_right,_rgba(14,165,233,0.22),_transparent_30%),linear-gradient(135deg,_#020617_0%,_#0f172a_45%,_#082f49_100%)]"></div>
            @unless ($isLoginPage)
                <div class="absolute inset-y-0 left-0 hidden w-1/2 bg-[radial-gradient(circle_at_20%_20%,_rgba(255,255,255,0.12),_transparent_22%),radial-gradient(circle_at_70%_30%,_rgba(16,185,129,0.16),_transparent_25%),radial-gradient(circle_at_40%_70%,_rgba(56,189,248,0.14),_transparent_28%)] lg:block"></div>
            @endunless

            <div class="relative z-10 flex min-h-screen">
                @unless ($isLoginPage)
                    <section class="hidden w-full max-w-xl flex-col justify-between px-10 py-12 text-white lg:flex xl:px-14">
                        <div class="space-y-8">
                            <a href="{{ route('login') }}" class="flex items-center gap-4">
                                <span class="flex h-16 w-16 items-center justify-center rounded-[1.6rem] bg-white/10 text-white shadow-lg shadow-slate-950/30 backdrop-blur">
                                    <x-application-logo class="h-9 w-9" />
                                </span>

                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-emerald-200/80">Sistem Apotik</p>
                                    <h1 class="mt-1 text-2xl font-semibold">{{ config('apotik.brand.name') }}</h1>
                                </div>
                            </a>

                            <div class="space-y-5">
                                <span class="inline-flex rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-emerald-100 backdrop-blur">
                                    Login Operator
                                </span>

                                <div class="space-y-4">
                                    <h2 class="max-w-lg text-5xl font-semibold tracking-tight text-white">
                                        Panel kerja apotik yang langsung siap dipakai.
                                    </h2>
                                    <p class="max-w-xl text-base leading-8 text-slate-200">
                                        Masuk untuk mengelola pembelian, penjualan, stok batch, hutang supplier, dan laporan operasional dari satu dashboard yang rapi.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="rounded-[1.6rem] border border-white/10 bg-white/10 p-4 backdrop-blur">
                                <p class="text-sm font-semibold text-white">Pembelian</p>
                                <p class="mt-2 text-sm leading-6 text-slate-200">Faktur, penerimaan barang, hutang supplier, dan retur.</p>
                            </div>

                            <div class="rounded-[1.6rem] border border-white/10 bg-white/10 p-4 backdrop-blur">
                                <p class="text-sm font-semibold text-white">Kasir</p>
                                <p class="mt-2 text-sm leading-6 text-slate-200">Transaksi penjualan harian yang cepat dan mudah dipantau.</p>
                            </div>

                            <div class="rounded-[1.6rem] border border-white/10 bg-white/10 p-4 backdrop-blur">
                                <p class="text-sm font-semibold text-white">Stok Batch</p>
                                <p class="mt-2 text-sm leading-6 text-slate-200">Monitoring expired, batch, opname, dan kartu stok.</p>
                            </div>
                        </div>
                    </section>
                @endunless

                <section @class([
                    'flex w-full items-center justify-center px-4 py-8 sm:px-6',
                    'lg:px-10 lg:ml-auto lg:w-[46rem]' => ! $isLoginPage,
                    'py-4 sm:py-5 lg:px-8 lg:py-4 xl:px-10 xl:py-5' => $isLoginPage,
                ])>
                    <div @class([
                        'w-full',
                        'max-w-xl' => ! $isLoginPage,
                        'max-w-[1120px]' => $isLoginPage,
                    ])>
                        {{ $slot }}
                    </div>
                </section>
            </div>
        </div>
    </body>
</html>
