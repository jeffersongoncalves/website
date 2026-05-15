<div aria-hidden="true" class="login-preview-bg">
    <iframe
        src="{{ env('FILAMENT_LOGIN_PREVIEW_URL', config('app.url')) }}"
        loading="lazy"
        tabindex="-1"
        scrolling="no"
        sandbox="allow-same-origin"
        class="login-preview-frame"
        onload="this.style.opacity='1'"
    ></iframe>

    <div class="login-preview-scrim"></div>
    <div class="login-preview-grain"></div>
</div>
