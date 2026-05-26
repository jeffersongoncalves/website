<x-filament-panels::page class="fi-page-horizon">
    <div
        x-data="{
            resize() {
                const top = this.$refs.frame.getBoundingClientRect().top;
                this.$refs.frame.style.height = (window.innerHeight - top - 16) + 'px';
            },
        }"
        x-init="resize(); window.addEventListener('resize', () => resize())"
        class="w-full"
    >
        <iframe
            x-ref="frame"
            src="{{ url('/horizon') }}"
            class="w-full rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"
            style="height: 80vh;"
            loading="lazy"
            referrerpolicy="same-origin"
            title="Laravel Horizon"
        ></iframe>
    </div>
</x-filament-panels::page>
