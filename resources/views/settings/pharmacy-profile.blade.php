<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
            <span>{{ $section }}</span>
            <span class="text-slate-300">/</span>
            <span class="text-slate-600">{{ $page['label'] }}</span>
        </div>
    </x-slot>

    <div class="space-y-5">
        <section class="panel-surface px-5 py-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-2">
                    <div class="inline-flex rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">
                        Kop Faktur Penjualan
                    </div>
                    <h3 class="section-title-lg">Profil apotik untuk dokumen cetak</h3>
                    <p class="content-copy max-w-3xl">
                        Data di halaman ini akan dipakai sebagai kop faktur penjualan PDF, jadi nama apotik, alamat, telepon, dan nomor izin bisa langsung tertarik dari database.
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-[0.74rem] text-slate-600">
                    <p class="font-semibold text-slate-900">{{ $profile->name ?: 'Profil belum diisi' }}</p>
                    <p class="mt-1">{{ $profile->city ?: 'Kota belum diatur' }}{{ $profile->province ? ', '.$profile->province : '' }}</p>
                </div>
            </div>
        </section>

        <form method="POST" action="{{ route('pengaturan.profil-apotik.update') }}" class="space-y-5">
            @csrf
            @method('PATCH')

            <section class="panel-surface p-0 overflow-hidden">
                <div class="border-b border-slate-200/80 px-5 py-4">
                    <h3 class="section-title">Identitas apotik</h3>
                </div>

                <div class="grid gap-5 px-5 py-5 lg:grid-cols-2">
                    <div class="space-y-4">
                        <div>
                            <label for="name" class="text-sm font-semibold text-slate-800">Nama Apotik</label>
                            <input id="name" name="name" type="text" value="{{ old('name', $profile->name) }}" class="mt-2 ui-select-control">
                            @error('name')
                                <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="owner_name" class="text-sm font-semibold text-slate-800">Penanggung Jawab / Owner</label>
                            <input id="owner_name" name="owner_name" type="text" value="{{ old('owner_name', $profile->owner_name) }}" class="mt-2 ui-select-control">
                            @error('owner_name')
                                <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="phone" class="text-sm font-semibold text-slate-800">Telepon</label>
                                <input id="phone" name="phone" type="text" value="{{ old('phone', $profile->phone) }}" class="mt-2 ui-select-control">
                                @error('phone')
                                    <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="email" class="text-sm font-semibold text-slate-800">Email</label>
                                <input id="email" name="email" type="email" value="{{ old('email', $profile->email) }}" class="mt-2 ui-select-control">
                                @error('email')
                                    <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="license_number" class="text-sm font-semibold text-slate-800">No. SIA / Izin</label>
                                <input id="license_number" name="license_number" type="text" value="{{ old('license_number', $profile->license_number) }}" class="mt-2 ui-select-control">
                                @error('license_number')
                                    <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="tax_number" class="text-sm font-semibold text-slate-800">NPWP / No. Pajak</label>
                                <input id="tax_number" name="tax_number" type="text" value="{{ old('tax_number', $profile->tax_number) }}" class="mt-2 ui-select-control">
                                @error('tax_number')
                                    <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label for="address" class="text-sm font-semibold text-slate-800">Alamat Lengkap</label>
                            <textarea id="address" name="address" rows="5" class="mt-2 w-full rounded-[1.35rem] border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-900 shadow-sm transition focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100">{{ old('address', $profile->address) }}</textarea>
                            @error('address')
                                <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="city" class="text-sm font-semibold text-slate-800">Kota</label>
                                <input id="city" name="city" type="text" value="{{ old('city', $profile->city) }}" class="mt-2 ui-select-control">
                                @error('city')
                                    <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="sm:col-span-2">
                                <label for="province" class="text-sm font-semibold text-slate-800">Provinsi</label>
                                <input id="province" name="province" type="text" value="{{ old('province', $profile->province) }}" class="mt-2 ui-select-control">
                                @error('province')
                                    <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="postal_code" class="text-sm font-semibold text-slate-800">Kode Pos</label>
                            <input id="postal_code" name="postal_code" type="text" value="{{ old('postal_code', $profile->postal_code) }}" class="mt-2 ui-select-control">
                            @error('postal_code')
                                <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="invoice_footer" class="text-sm font-semibold text-slate-800">Catatan / Footer Faktur</label>
                            <textarea id="invoice_footer" name="invoice_footer" rows="4" class="mt-2 w-full rounded-[1.35rem] border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-900 shadow-sm transition focus:border-emerald-300 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-100">{{ old('invoice_footer', $profile->invoice_footer) }}</textarea>
                            <p class="mt-2 text-[0.74rem] text-slate-500">Contoh: terima kasih, syarat retur, atau catatan footer lain untuk PDF penjualan.</p>
                            @error('invoice_footer')
                                <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-200/80 px-5 py-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-[0.74rem] text-slate-500">Setelah disimpan, kop PDF faktur penjualan akan langsung mengikuti data ini.</p>

                        <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-emerald-300 bg-emerald-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600">
                            Simpan profil apotik
                        </button>
                    </div>
                </div>
            </section>
        </form>
    </div>
</x-app-layout>
