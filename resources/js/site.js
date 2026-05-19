import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.data('terminalTyping', ({ target = '', delay = 600, speed = 60 } = {}) => ({
    typed: '',
    typingDone: false,
    init() {
        let i = 0;
        const tick = () => {
            if (i <= target.length) {
                this.typed = target.slice(0, i);
                i++;
                setTimeout(tick, speed);
            } else {
                this.typingDone = true;
            }
        };
        setTimeout(tick, delay);
    },
}));

Alpine.data('heatmap', ({ cells = [] } = {}) => ({
    cells,
    bgFor(v) {
        if (v === 0) return 'var(--surface-elevated-alt)';
        const isDark = document.documentElement.classList.contains('dark');
        // Light scheme: deeper amber tints for contrast over paper
        const r = isDark ? 245 : 217;
        const g = isDark ? 158 : 119;
        const b = isDark ? 11  : 6;
        return `rgba(${r}, ${g}, ${b}, ${v * 0.25})`;
    },
}));

Alpine.data('countUp', (initial = []) => ({
    stats: initial.map(s => ({ ...s, shown: '0' })),
    started: false,
    init() {
        this.$nextTick(() => {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !this.started) {
                        this.started = true;
                        this.run();
                        observer.disconnect();
                    }
                });
            }, { threshold: 0.3 });
            observer.observe(this.$el);
        });
    },
    run() {
        const dur = 1200;
        const start = performance.now();
        const tick = (now) => {
            const t = Math.min(1, (now - start) / dur);
            const eased = 1 - Math.pow(1 - t, 3);
            this.stats = this.stats.map(s => {
                const val = s.target * eased;
                const suffix = s.suffix || '';
                const shown = s.decimals
                    ? val.toFixed(s.decimals) + suffix
                    : Math.round(val) + suffix;
                return { ...s, shown };
            });
            if (t < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    },
}));

Alpine.data('markdownCopy', () => ({
    enhance() {
        const root = this.$el;
        const blocks = root.querySelectorAll('pre > code');
        blocks.forEach((code) => {
            const pre = code.parentElement;
            if (pre.dataset.copyEnhanced) return;
            pre.dataset.copyEnhanced = '1';

            const wrap = document.createElement('div');
            wrap.className = 'code-block';

            const bar = document.createElement('div');
            bar.className = 'code-block__bar';

            const lang = (Array.from(code.classList).find(c => c.startsWith('language-')) || '').replace('language-', '');
            const label = document.createElement('span');
            label.className = 'code-block__lang';
            label.textContent = lang || 'code';

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'code-block__copy';
            btn.textContent = 'copy';
            btn.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(code.innerText);
                    btn.textContent = 'copied';
                    btn.classList.add('is-copied');
                    setTimeout(() => {
                        btn.textContent = 'copy';
                        btn.classList.remove('is-copied');
                    }, 1500);
                } catch (e) {
                    btn.textContent = 'error';
                }
            });

            bar.appendChild(label);
            bar.appendChild(btn);

            pre.parentNode.insertBefore(wrap, pre);
            wrap.appendChild(bar);
            wrap.appendChild(pre);
        });
    },
}));

Alpine.data('stickyHeader', () => ({
    scrolled: false,
    init() {
        const onScroll = () => { this.scrolled = window.scrollY > 8; };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    },
}));

Alpine.data('onThisPage', () => ({
    open: false,
    headings: [],
    build() {
        this.$nextTick(() => {
            const body = document.querySelector('.markdown-body');
            if (!body) return;
            const slugify = (s) => s
                .toLowerCase()
                .normalize('NFD').replace(/[̀-ͯ]/g, '')
                .replace(/[^\w\s-]/g, '')
                .trim().replace(/\s+/g, '-');
            const used = new Set();
            const items = [];
            body.querySelectorAll('h2, h3, h4').forEach((h) => {
                const text = h.textContent.trim();
                if (!text) return;
                let id = h.id || slugify(text);
                let candidate = id;
                let i = 1;
                while (used.has(candidate)) candidate = `${id}-${++i}`;
                id = candidate;
                used.add(id);
                if (!h.id) h.id = id;
                items.push({ id, text, level: parseInt(h.tagName.slice(1), 10) });
            });
            this.headings = items;
        });
    },
}));

// Service Worker registration. Scoped at `/` so it controls the public site
// (admin/livewire/api are skipped inside sw.js itself). Registered after
// `load` so the first paint isn't competing with the SW install for the
// network — improves perceived performance on the very first visit.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
            // Registration can fail in private mode / older browsers — drop
            // silently, the site stays fully functional without offline.
        });
    });

    // Update flow — the SW posts `{type:'pwa-updated', version}` from its
    // `activate` handler after `clients.claim()`. We surface a toast only
    // when the version actually differs from the one the user has been
    // running, so the very first install (no `pwa.version` in storage)
    // stays silent. The toast hand-off uses a CustomEvent so the Alpine
    // component below can react without holding a reference to the SW.
    navigator.serviceWorker.addEventListener('message', (event) => {
        const data = event.data;
        if (!data || data.type !== 'pwa-updated') return;

        const incoming = String(data.version || '');
        const seen = window.localStorage.getItem('pwa.version');

        if (seen && seen !== incoming) {
            window.dispatchEvent(
                new CustomEvent('pwa-update-available', { detail: { version: incoming } }),
            );
        }

        if (incoming) {
            window.localStorage.setItem('pwa.version', incoming);
        }
    });
}

// Web Push subscribe / unsubscribe. The component only surfaces a button
// when the browser supports both Notification + PushManager, the VAPID
// public key meta is present, and the user hasn't blocked notifications
// at the OS level. Subscription handshake speaks JSON to our Laravel
// endpoints and uses the CSRF token from the layout's meta.
Alpine.data('pushPermission', () => ({
    supported: false,
    permission: 'default',
    subscribed: false,
    busy: false,
    lastError: '',
    isBrave: false,

    async init() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            console.info('[push] Browser missing Push API support');
            return;
        }
        const vapid = document.querySelector('meta[name="vapid-public-key"]');
        if (!vapid || !vapid.content) {
            console.warn('[push] meta[name="vapid-public-key"] missing — set VAPID_PUBLIC_KEY in env');
            return;
        }

        // Brave fingerprints itself with `navigator.brave.isBrave()`. The
        // shim returns a Promise that resolves true on Brave and is
        // missing entirely on Chrome/Edge/Firefox. Brave gates Web Push
        // behind a per-profile flag at `brave://settings/privacy` →
        // "Use Google services for push messaging" — when the flag is
        // off (default), `pushManager.subscribe()` throws the same
        // generic "AbortError: Registration failed - push service
        // error" we see on a broken Chrome install. We use this flag to
        // replace the generic alert with Brave-specific instructions.
        try {
            if (navigator.brave && typeof navigator.brave.isBrave === 'function') {
                this.isBrave = await navigator.brave.isBrave();
            }
        } catch (e) {
            // shim throwing is fine — treat as non-Brave.
        }

        // VAPID public key must decode to exactly 65 bytes — a P-256
        // uncompressed point (0x04 prefix + 32-byte X + 32-byte Y). If
        // the env value is malformed (truncated paste, swapped with
        // private key, trailing newline that wasn't trimmed) the
        // browser rejects subscribe() with a generic "push service
        // error". Log the actual length so the failure is obvious.
        try {
            const bytes = urlBase64ToUint8Array(vapid.content.trim());
            console.info(`[push] VAPID key length: ${bytes.length} bytes (expected 65)`);
            if (bytes.length !== 65) {
                console.error('[push] VAPID public key is malformed. Regenerate with `php artisan webpush:vapid` and paste the full PUBLIC half into VAPID_PUBLIC_KEY env.');
            }
        } catch (e) {
            console.error('[push] VAPID key failed to decode as base64url', e);
        }

        this.supported = true;
        this.permission = Notification.permission;

        try {
            const registration = await navigator.serviceWorker.ready;
            const existing = await registration.pushManager.getSubscription();
            this.subscribed = existing !== null;
        } catch (e) {
            console.warn('[push] getSubscription failed', e);
        }
    },

    async toggle() {
        if (!this.supported || this.busy) return;
        this.busy = true;
        this.lastError = '';
        try {
            if (this.subscribed) {
                await this._unsubscribe();
            } else {
                await this._subscribe();
            }
        } catch (e) {
            this.lastError = e && e.message ? e.message : String(e);
            console.error('[push] toggle failed', e);

            // Brave-specific: this exact error is what `pushManager
            // .subscribe()` throws when "Use Google services for push
            // messaging" is OFF in `brave://settings/privacy`. Replace
            // the generic alert with the actual fix instead of the
            // FCM-side error message that doesn't help the visitor.
            const looksLikeBraveGcmBlock = this.isBrave
                && this.lastError.toLowerCase().includes('push service error');
            if (looksLikeBraveGcmBlock) {
                window.alert(this._braveHint());
            } else {
                window.alert('Push: ' + this.lastError);
            }
        } finally {
            this.busy = false;
        }
    },

    async _subscribe() {
        const permission = await Notification.requestPermission();
        this.permission = permission;
        if (permission !== 'granted') {
            console.info('[push] permission not granted:', permission);
            return;
        }

        // Force the SW to update before subscribing so we never hand
        // pushManager.subscribe() a stale registration from a previous
        // deploy that lacked the `push` handler — Chrome sometimes
        // throws AbortError when the registration is in the middle of
        // a soft update.
        let registration = await navigator.serviceWorker.getRegistration();
        if (registration) {
            try { await registration.update(); } catch (_) { /* non-fatal */ }
        }
        registration = await navigator.serviceWorker.ready;

        const vapid = document.querySelector('meta[name="vapid-public-key"]').content.trim();

        // Pass the raw ArrayBuffer (not the Uint8Array view) — older
        // Chrome builds accept either, but stricter ones throw on the
        // typed-array form.
        const applicationServerKey = urlBase64ToUint8Array(vapid).buffer;

        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey,
        });

        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const response = await fetch('/push/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
                endpoint: subscription.endpoint,
                keys: {
                    p256dh: arrayBufferToBase64(subscription.getKey('p256dh')),
                    auth: arrayBufferToBase64(subscription.getKey('auth')),
                },
                locale: document.documentElement.lang || null,
            }),
        });

        if (!response.ok) {
            // Try to surface the validation message from Laravel so the
            // server-side cause is visible without DevTools.
            const text = await response.text();
            throw new Error(`HTTP ${response.status} — ${text.slice(0, 200)}`);
        }

        this.subscribed = true;
        console.info('[push] subscribed', subscription.endpoint);
    },

    // Build the Brave instructions in the page's current locale by
    // reading from `<html lang>`. Kept here as plain strings instead of
    // round-tripping through a server JSON endpoint — these only render
    // for Brave users on a subscribe failure, the carrying cost is tiny.
    _braveHint() {
        const lang = (document.documentElement.lang || 'pt').slice(0, 2);
        const messages = {
            pt: 'Brave bloqueia notificações push por padrão.\n\nPara ativar:\n1. Cole brave://settings/privacy na barra de endereço\n2. Ligue "Use Google services for push messaging"\n3. Reinicie o Brave\n4. Volte aqui e clique no sino de novo.',
            en: 'Brave blocks Web Push by default.\n\nTo enable it:\n1. Paste brave://settings/privacy into the address bar\n2. Turn on "Use Google services for push messaging"\n3. Restart Brave\n4. Come back here and click the bell again.',
            es: 'Brave bloquea las notificaciones push por defecto.\n\nPara activarlas:\n1. Pega brave://settings/privacy en la barra de direcciones\n2. Activa "Use Google services for push messaging"\n3. Reinicia Brave\n4. Vuelve aquí y haz clic en la campana de nuevo.',
        };
        return messages[lang] || messages.en;
    },

    async _unsubscribe() {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();
        if (!subscription) {
            this.subscribed = false;
            return;
        }

        const endpoint = subscription.endpoint;
        await subscription.unsubscribe();

        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        await fetch('/push/unsubscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ endpoint }),
        });

        this.subscribed = false;
    },
}));

// VAPID `applicationServerKey` must be a Uint8Array. The Push API spec
// hands the key in URL-safe base64 — decode it into raw bytes here.
function urlBase64ToUint8Array(base64) {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const normal = (base64 + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(normal);
    const out = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
}

// `subscription.getKey()` returns an ArrayBuffer; we POST it as base64
// so PHP can verify+forward it without a binary content-type.
function arrayBufferToBase64(buffer) {
    if (!buffer) return '';
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.length; i++) binary += String.fromCharCode(bytes[i]);
    return window.btoa(binary);
}

Alpine.data('pwaUpdateToast', () => ({
    open: false,
    nextVersion: '',

    init() {
        window.addEventListener('pwa-update-available', (event) => {
            this.nextVersion = (event && event.detail && event.detail.version) || '';
            this.open = true;
        });
    },

    reload() {
        // Hard reload bypasses the SW cache for this single request so the
        // user lands on the fresh HTML that pulls the new bundle hashes.
        window.location.reload();
    },

    dismiss() {
        this.open = false;
    },
}));

// PWA install prompt — the deferred event (`beforeinstallprompt`) is the
// only way Chrome / Edge / Android lets us trigger the native install UI
// on demand, so we stash it for the Alpine component to call later from
// a user gesture. The event won't fire at all if the site is already
// installed, on iOS Safari, or below Chrome's heuristic threshold.
let deferredInstallPrompt = null;
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    window.dispatchEvent(new CustomEvent('pwa-install-available'));
});

window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    document.cookie = 'pwa_installed=true; max-age=31536000; path=/; SameSite=Lax';
    window.dispatchEvent(new CustomEvent('pwa-installed'));
});

Alpine.data('installPrompt', () => ({
    available: false,
    iosHintOpen: false,
    iosEligible: false,

    init() {
        // Don't surface the button in already-installed standalone runs —
        // the OS launcher icon is doing the job; another button is noise.
        const isStandalone =
            window.matchMedia('(display-mode: standalone)').matches ||
            window.navigator.standalone === true ||
            document.cookie.split('; ').some((c) => c.startsWith('pwa_installed=true'));

        if (isStandalone) return;

        // The user explicitly dismissed before — respect that for the
        // session. Cookie expires in 7 days so the prompt eventually
        // gets a second chance.
        const dismissed = document.cookie
            .split('; ')
            .some((c) => c.startsWith('pwa_dismissed=true'));

        if (dismissed) return;

        // Chrome / Edge / Android path: we already cached the event if
        // it fired before Alpine booted, so check + listen for late ones.
        if (deferredInstallPrompt) {
            this.available = true;
        }
        window.addEventListener('pwa-install-available', () => {
            this.available = true;
        });
        window.addEventListener('pwa-installed', () => {
            this.available = false;
        });

        // iOS Safari path: there is no install event — detect iOS by
        // user-agent + lack of standalone, then surface a "how to" hint
        // pointing the user at Share → Add to Home Screen.
        const ua = window.navigator.userAgent || '';
        const isIos = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
        if (isIos && !window.navigator.standalone) {
            this.iosEligible = true;
        }
    },

    async install() {
        if (!deferredInstallPrompt) return;
        deferredInstallPrompt.prompt();
        const choice = await deferredInstallPrompt.userChoice;
        deferredInstallPrompt = null;
        this.available = false;
        if (choice && choice.outcome === 'dismissed') {
            // 7 days — long enough that the user isn't pestered, short
            // enough that someone who later wants the app can find it again.
            document.cookie = 'pwa_dismissed=true; max-age=604800; path=/; SameSite=Lax';
        }
    },

    dismiss() {
        this.available = false;
        this.iosEligible = false;
        document.cookie = 'pwa_dismissed=true; max-age=604800; path=/; SameSite=Lax';
    },

    showIosHint() {
        this.iosHintOpen = true;
    },

    closeIosHint() {
        this.iosHintOpen = false;
    },
}));

Alpine.start();
