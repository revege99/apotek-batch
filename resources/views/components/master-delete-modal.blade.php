<template x-teleport="body">
    <div
        x-cloak
        x-show="deleteModalOpen"
        x-transition.opacity.duration.150ms
        class="fixed inset-0 z-[90] overflow-y-auto bg-slate-950/55 backdrop-blur-sm"
        @click.self="closeDeleteDialog()"
        @wheel.prevent
        @touchmove.prevent
    >
        <div class="flex min-h-full items-center justify-center p-4 sm:p-6">
            <div
                class="panel-surface relative z-[91] w-full max-w-lg overflow-hidden p-0"
                role="dialog"
                aria-modal="true"
            >
                <div class="border-b border-rose-100 bg-gradient-to-br from-rose-50 via-white to-amber-50 px-5 py-5">
                    <div class="flex items-start gap-4">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-rose-200 bg-white text-rose-600 shadow-sm">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 9v4" />
                                <path d="M12 17h.01" />
                                <path d="M10.3 3.83 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.83a2 2 0 0 0-3.4 0Z" />
                            </svg>
                        </div>

                        <div>
                            <p class="text-[0.66rem] font-semibold uppercase tracking-[0.18em] text-rose-500">Konfirmasi Hapus</p>
                            <h3 class="mt-1 text-base font-semibold text-slate-950" x-text="deleteTarget?.title ?? 'Hapus data ini?'"></h3>
                            <p class="mt-1.5 text-xs leading-5 text-slate-600" x-text="deleteTarget?.description ?? ''"></p>
                        </div>
                    </div>
                </div>

                <div class="px-5 py-4">
                    <div class="rounded-[1.35rem] border border-slate-200/80 bg-slate-50/90 p-4">
                        <p class="text-[0.66rem] font-semibold uppercase tracking-[0.18em] text-slate-500">Target Hapus</p>
                        <div class="mt-3 flex flex-wrap items-start gap-2">
                            <template x-if="deleteTarget?.code">
                                <span
                                    class="inline-flex rounded-full border border-slate-200 bg-white px-3 py-1 text-[0.68rem] font-semibold text-slate-600"
                                    x-text="deleteTarget?.code"
                                ></span>
                            </template>
                            <p class="min-w-0 flex-1 break-words text-sm font-semibold text-slate-900" x-text="deleteTarget?.name"></p>
                        </div>
                    </div>

                    <div
                        class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-[0.72rem] leading-5 text-amber-800"
                        x-text="deleteTarget?.warning ?? ''"
                    ></div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button
                            x-ref="cancelDeleteButton"
                            type="button"
                            class="ui-action-btn ui-action-btn--neutral"
                            @click="closeDeleteDialog()"
                        >
                            Tidak
                        </button>

                        <form method="POST" x-bind:action="deleteFormAction">
                            @csrf
                            @method('DELETE')

                            <button
                                type="submit"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-rose-600 bg-rose-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:border-rose-700 hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-200"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 6h18" />
                                    <path d="M8 6V4.75A1.75 1.75 0 0 1 9.75 3h4.5A1.75 1.75 0 0 1 16 4.75V6" />
                                    <path d="M19 6l-.82 11.47A2 2 0 0 1 16.19 19H7.81a2 2 0 0 1-1.99-1.53L5 6" />
                                </svg>
                                <span x-text="deleteTarget?.confirm_label ?? 'Ya, hapus data'"></span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
