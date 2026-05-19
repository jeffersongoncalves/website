# Prompt — Configurar PWA com Web Push Notifications

Prompt reutilizável para implementar PWA completo (manifest, service worker,
install prompt, update toast, web push) num projeto Laravel + Filament.
Destilado da implementação real deste repositório — inclui as 4 armadilhas
que custaram debug em produção.

Cole o bloco abaixo num agente e siga fase por fase.

---

```
Configure a complete PWA with web push notifications on this Laravel + Filament app.

CONTEXT
- Laravel 13, Filament 5, Vite, Alpine.js. Public site + admin panel.
- Production behind Cloudflare + nginx + PHP-FPM (Docker). Dev on localhost/Herd.

DELIVER IN PHASES (commit + verify each before next):

PHASE 1 — Manifest + icons
- Serve /manifest.json from a controller/support class (NOT a static file) so fields stay config-driven.
- Required fields: name, short_name, description, start_url ("/?source=pwa"), scope ("/"), display ("standalone"), orientation, theme_color, background_color, lang, dir, categories, id ("/").
- Content-Type MUST be "application/manifest+json".
- Icons array needs 192px AND 512px PNG, plus a 512px maskable variant (purpose:"maskable", icon centered in ~60% safe zone on solid bg).
- <head>: theme-color meta, apple-touch-icon links, apple-mobile-web-app-capable, status-bar-style "black-translucent", viewport with "viewport-fit=cover".

PHASE 2 — Service worker
- Serve /sw.js from a controller rendering a blade view so a VERSION constant tracks the app version (cache-bust per release). Headers: Content-Type "application/javascript", "Service-Worker-Allowed: /", "Cache-Control: no-cache".
- CRITICAL nginx gotcha: scope any generic ".(css|js)$" static-asset location to ^/build/ and ^/vendor/ ONLY. A bare \.(css|js)$ block intercepts /sw.js and 404s it before PHP. Exact/anchored locations beat regex.
- Cache strategy: /build/* cache-first (immutable); HTML navigations network-first with /offline fallback; everything else stale-while-revalidate; PASS-THROUGH (no SW) for /admin, /livewire, /api, /sw.js, /manifest.json.
- Pre-cache "/", "/?source=pwa", "/offline". skipWaiting + clients.claim. On activate, postMessage clients {type:'pwa-updated', version}.
- Register SW from the bundle after window 'load'. Add data-cfasync="false" to Vite script tags (Cloudflare Rocket Loader breaks SW + Push timing).

PHASE 3 — Install prompt
- Alpine component: capture beforeinstallprompt, show an install button; on click call prompt(). Handle appinstalled. iOS (no event) → detect via UA + standalone check, show a Share→Add to Home Screen hint modal. Cookies: pwa_installed (1y), pwa_dismissed (7d).

PHASE 4 — Update toast
- Client listens to SW 'message'; compare incoming version vs localStorage; if different (and not first install) show a "new version, reload" toast. Conservative: no auto-reload.

PHASE 5 — Web Push
- composer require minishlink/web-push.
- VAPID keypair via an artisan command (webpush:vapid); store VAPID_PUBLIC_KEY/PRIVATE_KEY/SUBJECT in env + a services config block.
- push_subscriptions table: endpoint (text) + endpoint_hash (sha256, unique) + p256dh + auth + locale + nullable user_id + user_agent + last_used_at. Model with hashEndpoint().
- POST /push/subscribe + /push/unsubscribe (outside locale middleware, CSRF via meta token). Upsert on endpoint_hash.
- Inject <meta name="vapid-public-key"> only when env set; trim() the value.
- SW: 'push' handler (showNotification, 512 icon as icon+badge) + 'notificationclick' (focus existing tab else openWindow).
- Alpine bell button: detect support, requestPermission, pushManager.subscribe (pass applicationServerKey as ArrayBuffer not Uint8Array), POST subscription. base64url<->bytes helpers.
- Shared PushBroadcaster::send(title, body, url, tag) used by both an artisan push:send command AND any job. Prune expired (404/410) subscriptions off the report stream.
- BRAVE: detect navigator.brave.isBrave(). Brave blocks push by default — when subscribe throws "push service error" on Brave, show instructions to enable brave://settings/privacy → "Use Google services for push messaging". (Chrome/Edge/Android/Firefox work out of the box.)

PHASE 6 — Auto-notify
- On a domain event (e.g. record published), dispatch a queued job that calls PushBroadcaster. Guard with WithoutOverlapping + fire only on the real state transition (wasChanged on the status column), never on plain re-saves.

PHASE 7 — Audit
- Lighthouse PWA pass: manifest valid, SW registers, /offline returns 200 offline, all icon sizes, HTTPS, theme-color.

CONVENTIONS
- Run the build after any resources/ change and commit the build output.
- i18n every user-facing string across all supported locales.
- Tests for: manifest content-type + id + icons, /sw.js headers + body markers, /offline render, push subscribe upsert + unsubscribe, auto-notify job dispatch branches (Queue::fake).
- Static analysis + code style must pass before each commit.

KNOWN FAILURE MODES TO PRE-EMPT
1. /sw.js 404 → nginx generic .js location (scope it).
2. "push service error" → Brave flag OR Cloudflare Rocket Loader (data-cfasync).
3. Cloudflare caches a 404 → purge after the nginx fix deploys.
4. Stale app version in UI → don't cache the version lookup when it comes from env; bust on container start.
```

---

## Notas de produção

- **Brave**: bloqueia Web Push por padrão. Usuário precisa ligar
  `brave://settings/privacy` → "Use Google services for push messaging".
  Chrome/Edge/Android/Firefox funcionam sem config.
- **iOS Safari**: sem `beforeinstallprompt`. Instala via Compartilhar →
  Adicionar à Tela de Início. Push só funciona quando instalado como PWA
  (iOS 16.4+).
- **VAPID**: chave pública decodifica pra exatamente 65 bytes (P-256
  uncompressed point: `0x04` + 32 X + 32 Y). Comprimento errado = subscribe
  falha com erro genérico.
- **GHCR vs Docker Hub**: só Docker Hub expõe `pull_count` público pra
  métricas de download.
