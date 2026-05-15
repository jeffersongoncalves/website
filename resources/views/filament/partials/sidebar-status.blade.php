<div class="editorial-sidebar-status px-6 py-4 mt-auto" style="border-top: 1px dashed var(--color-ink-700);">
    <div class="editorial-eyebrow mb-2">{{ __('admin.status.label') }}</div>
    <div class="flex items-center gap-2" style="font-family: var(--font-mono); font-size: 12px; color: var(--color-ink-300);">
        <span class="editorial-pulse"></span>
        <span>{{ config('app.env') === 'production' ? __('admin.status.production') : __('admin.status.local') }}</span>
        <span style="color: var(--color-ink-500);">·</span>
        <span style="color: var(--color-ink-500);">v{{ config('app.version', '0.1.0') }}</span>
    </div>
</div>
