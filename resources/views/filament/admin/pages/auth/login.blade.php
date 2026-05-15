<x-filament-panels::page.simple>
    <div class="login-terminal-wrapper">
        <div class="editorial-eyebrow login-eyebrow">
            01 · {{ __('admin.login.eyebrow') }}
        </div>

        <div class="login-terminal">
            <div class="login-terminal-bar">
                <span class="login-terminal-dot" style="background: #E26B5C;"></span>
                <span class="login-terminal-dot" style="background: #FBBF24;"></span>
                <span class="login-terminal-dot" style="background: #86C682;"></span>
                <span style="margin-left: 8px;">~/admin — zsh</span>
                <span style="margin-left: auto; color: var(--color-ink-600);">{{ now()->format('H:i') }}</span>
            </div>

            <div class="login-terminal-body">
                <div class="login-terminal-line login-terminal-line-1">
                    <span class="login-prompt">$</span>
                    <span style="color: var(--color-ink-300);">whoami</span>
                </div>
                <div class="login-terminal-line login-terminal-line-2" style="color: var(--color-ink-400); margin-bottom: 14px;">
                    {{ __('admin.login.whoami_unauth') }}
                </div>

                <div class="login-terminal-line login-terminal-line-3 login-caret">
                    <span class="login-prompt">$</span>
                    <span style="color: var(--color-ink-300);">login --required</span>
                </div>

                <form wire:submit="authenticate" class="login-form login-terminal-line login-terminal-form">
                    {{ $this->form }}

                    <div style="margin-top: 20px;">
                        {{ $this->getAuthenticateFormAction() }}
                    </div>
                </form>

                <div class="login-terminal-status">
                    <span class="editorial-pulse"></span>
                    <span>{{ __('admin.login.secure_connection') }} · {{ request()->getHost() }}</span>
                </div>
            </div>
        </div>

        <div class="login-footer">
            <a href="{{ config('app.url') }}">← {{ __('admin.login.back_to_site') }}</a>
            <span class="sep">·</span>
            <span>{{ config('app.name') }} · Assis/SP</span>
        </div>
    </div>
</x-filament-panels::page.simple>
