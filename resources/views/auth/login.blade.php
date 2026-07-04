<x-guest-layout>
    <div class="overflow-hidden rounded-[1.35rem] border border-white/30 bg-white/90 shadow-[0_30px_90px_-34px_rgba(15,23,42,0.56)] backdrop-blur-xl">
        <div class="grid lg:min-h-[580px] lg:grid-cols-[1.04fr,0.96fr]">
            <section class="relative overflow-hidden bg-[linear-gradient(155deg,#f4fbf8_0%,#eef7ff_46%,#e7f4ef_100%)] p-4 sm:p-5 lg:p-6">
                <div class="absolute inset-0">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_18%_20%,rgba(16,185,129,0.16),transparent_26%),radial-gradient(circle_at_82%_16%,rgba(14,165,233,0.12),transparent_24%),linear-gradient(180deg,rgba(255,255,255,0.72)_0%,rgba(255,255,255,0.58)_100%)]"></div>
                    <div class="absolute inset-x-5 top-6 h-40 rounded-[1.15rem] border border-white/70 bg-white/50 shadow-[inset_0_1px_0_rgba(255,255,255,0.7)] backdrop-blur-sm sm:inset-x-6 lg:inset-x-7"></div>
                    <div class="absolute inset-x-8 top-11 h-3 rounded-full bg-slate-200/70 blur-sm sm:inset-x-12"></div>
                    <div class="absolute inset-x-10 top-20 h-3 rounded-full bg-slate-200/60 blur-sm sm:inset-x-16"></div>
                    <div class="absolute inset-x-8 top-[7.2rem] h-4 rounded-full bg-emerald-100/70 blur-sm sm:inset-x-14"></div>
                    <div class="absolute left-6 top-16 h-20 w-8 rounded-full bg-white/50 blur-xl sm:left-10"></div>
                    <div class="absolute right-6 top-16 h-20 w-8 rounded-full bg-white/45 blur-xl sm:right-10"></div>
                </div>

                <div class="relative flex h-full flex-col justify-between">
                    <div class="space-y-[1.125rem]">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <span class="flex h-12 w-12 items-center justify-center rounded-[0.9rem] bg-gradient-to-br from-emerald-600 to-emerald-500 text-white shadow-[0_14px_26px_-18px_rgba(5,150,105,0.9)]">
                                    <x-application-logo class="h-7 w-7" />
                                </span>

                                <div>
                                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.28em] text-emerald-700">Sistem Apotik</p>
                                    <h2 class="mt-1 text-[1.5rem] font-semibold tracking-tight text-emerald-800">{{ config('apotik.brand.name') }}</h2>
                                </div>
                            </div>

                            <div class="hidden rounded-full border border-emerald-200 bg-white/80 px-3 py-1.5 text-[0.64rem] font-semibold uppercase tracking-[0.2em] text-emerald-700 shadow-sm sm:inline-flex">
                                Internal Use
                            </div>
                        </div>

                        <div class="mx-auto max-w-sm pt-1 text-center lg:pt-2">
                            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-[1rem] bg-white/88 text-emerald-600 shadow-[0_18px_40px_-26px_rgba(16,185,129,0.45)] ring-1 ring-emerald-100/80">
                                <x-application-logo class="h-10 w-10" />
                            </div>

                            <div class="mt-4 space-y-2">
                                <h3 class="text-[1.5rem] font-semibold tracking-tight text-emerald-800 sm:text-[1.75rem]">
                                    Operasional apotik yang rapi, cepat, dan terhubung
                                </h3>
                                <p class="text-[0.82rem] leading-5 text-slate-600 sm:text-[0.88rem]">
                                    Kelola pembelian, kasir penjualan, stok batch, piutang pelanggan, dan lisensi aplikasi dari satu panel kerja yang nyaman dipakai setiap hari.
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-2.5 sm:grid-cols-3">
                            <div class="rounded-[0.9rem] border border-white/80 bg-white/75 p-3 shadow-[0_14px_28px_-24px_rgba(15,23,42,0.3)] backdrop-blur">
                                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-emerald-700">Pembelian</p>
                                <p class="mt-1 text-[0.76rem] leading-[1.15rem] text-slate-600">Faktur, retur pembelian, dan kontrol hutang supplier.</p>
                            </div>

                            <div class="rounded-[0.9rem] border border-white/80 bg-white/75 p-3 shadow-[0_14px_28px_-24px_rgba(15,23,42,0.3)] backdrop-blur">
                                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-sky-700">Stok Batch</p>
                                <p class="mt-1 text-[0.76rem] leading-[1.15rem] text-slate-600">Pantau batch, expired, kartu stok, dan saldo real-time.</p>
                            </div>

                            <div class="rounded-[0.9rem] border border-white/80 bg-white/75 p-3 shadow-[0_14px_28px_-24px_rgba(15,23,42,0.3)] backdrop-blur">
                                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-amber-700">Laporan</p>
                                <p class="mt-1 text-[0.76rem] leading-[1.15rem] text-slate-600">Laporan penjualan, laba rugi, dan lisensi aplikasi.</p>
                            </div>
                        </div>
                    </div>

                </div>
            </section>

            <section class="bg-[linear-gradient(180deg,#ffffff_0%,#f8fbfa_100%)] p-4 sm:p-5 lg:p-6">
                <div class="mx-auto flex h-full max-w-md items-center">
                    <div class="w-full rounded-[1.1rem] border border-slate-200/80 bg-white/92 p-4 shadow-[0_22px_48px_-34px_rgba(15,23,42,0.36)] ring-1 ring-white/60 backdrop-blur sm:p-5">
                        <div class="space-y-2 text-center">
                            <h3 class="text-[1.45rem] font-semibold tracking-tight text-emerald-700">Selamat Datang</h3>
                            <p class="text-sm leading-5 text-slate-500">Silakan masuk untuk melanjutkan ke sistem manajemen apotik.</p>
                            <div class="mx-auto h-1 w-14 rounded-full bg-[linear-gradient(90deg,#10b981_0%,#14b8a6_100%)]"></div>
                        </div>

                        @if (session('status'))
                            <div class="mt-4 rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm leading-6 text-emerald-700">
                                {{ session('status') }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login') }}" class="mt-4 space-y-4">
                            @csrf

                            <div>
                                <label for="username" class="mb-1.5 block text-sm font-semibold text-slate-700">Username</label>
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />
                                            <path d="M5 20a7 7 0 0 1 14 0" />
                                        </svg>
                                    </span>
                                    <input
                                        id="username"
                                        type="text"
                                        name="username"
                                        value="{{ old('username') }}"
                                        required
                                        autofocus
                                        autocomplete="username"
                                        class="block w-full rounded-2xl border border-slate-200 bg-white py-2.5 pl-11 pr-4 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-300 focus:ring-4 focus:ring-emerald-100"
                                        placeholder="Masukkan username Anda"
                                    />
                                </div>
                                <x-input-error :messages="$errors->get('username')" class="mt-2" />
                            </div>

                            <div>
                                <div class="mb-1.5">
                                    <label for="password" class="block text-sm font-semibold text-slate-700">Password</label>
                                </div>

                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="5" y="10" width="14" height="10" rx="2.25" />
                                            <path d="M8 10V7.75a4 4 0 1 1 8 0V10" />
                                        </svg>
                                    </span>
                                    <input
                                        id="password"
                                        type="password"
                                        name="password"
                                        required
                                        autocomplete="current-password"
                                        class="block w-full rounded-2xl border border-slate-200 bg-white py-2.5 pl-11 pr-4 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-300 focus:ring-4 focus:ring-emerald-100"
                                        placeholder="Masukkan password Anda"
                                    />
                                </div>
                                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                            </div>

                            <div class="flex items-center justify-between gap-4 pt-0.5">
                                <label for="remember_me" class="flex items-center gap-3 text-sm text-slate-600">
                                    <input
                                        id="remember_me"
                                        type="checkbox"
                                        name="remember"
                                        class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                    >
                                    <span>Ingat saya</span>
                                </label>

                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" class="text-[0.72rem] font-medium text-sky-700 transition hover:text-emerald-700">
                                        Lupa password?
                                    </a>
                                @endif
                            </div>

                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-700 px-5 py-3 text-sm font-semibold text-white shadow-[0_18px_36px_-24px_rgba(4,120,87,0.95)] transition hover:bg-emerald-800"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12h14" />
                                    <path d="m13 6 6 6-6 6" />
                                </svg>
                                <span>Masuk</span>
                            </button>
                        </form>

                        <div class="mt-4 border-t border-slate-200/80 pt-3">
                            <div class="rounded-[0.95rem] border border-emerald-100 bg-emerald-50/80 px-4 py-3">
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full bg-white text-emerald-700 shadow-sm">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 3l7 4v5c0 4.25-2.9 8.19-7 9-4.1-.81-7-4.75-7-9V7l7-4Z" />
                                            <path d="m9.5 12 1.75 1.75L14.5 10.5" />
                                        </svg>
                                    </span>

                                    <div>
                                        <p class="text-[0.82rem] font-semibold text-emerald-800">Akses aman untuk operasional internal</p>
                                        <p class="mt-1 text-[0.69rem] leading-[1.15rem] text-emerald-700/80">
                                            Hak akses admin dan superadmin dipisahkan untuk menjaga keamanan data transaksi dan lisensi.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <p class="mt-3 text-center text-[0.7rem] text-slate-400">
                                © {{ now()->year }} {{ config('apotik.brand.name') }}. Semua hak cipta dilindungi.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-guest-layout>
