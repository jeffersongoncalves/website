<x-filament-panels::page class="fi-page-horizon">
    <div
        x-data="{
            resize() {
                const top = this.$refs.frame.getBoundingClientRect().top;
                this.$refs.frame.style.height = (window.innerHeight - top - 16) + 'px';
            },
        }"
        x-init="resize(); window.addEventListener('resize', () => resize())"
        class="-mx-4 md:-mx-6 lg:-mx-8"
    >
        <iframe
            x-ref="frame"
            src="{{ url('/horizon') }}"
            class="block w-full border-0 bg-white dark:bg-gray-900"
            style="height: 80vh;"
            loading="lazy"
            referrerpolicy="same-origin"
            title="Laravel Horizon"
        ></iframe>
    </div>
</x-filament-panels::page>
