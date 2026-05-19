{{-- External links → new tab inside the Filament admin. Livewire morphs the
     DOM constantly, so beyond the initial pass we watch for subtree changes
     and re-mark. Same rule as the public site: only cross-host http(s) links
     get target=_blank + rel="noopener noreferrer"; panel nav stays in-tab. --}}
<script data-cfasync="false">
    (function () {
        var host = window.location.hostname;

        function mark(root) {
            (root || document).querySelectorAll('a[href]').forEach(function (a) {
                if (a.dataset.extProcessed) return;
                var url;
                try {
                    url = new URL(a.href, window.location.href);
                } catch (e) {
                    return;
                }
                if (url.protocol !== 'http:' && url.protocol !== 'https:') return;
                if (url.hostname === host) return;

                a.target = '_blank';
                var rel = (a.rel || '').split(/\s+/).filter(Boolean);
                if (rel.indexOf('noopener') === -1) rel.push('noopener');
                if (rel.indexOf('noreferrer') === -1) rel.push('noreferrer');
                a.rel = rel.join(' ');
                a.dataset.extProcessed = '1';
            });
        }

        function boot() {
            mark();

            // Debounced re-scan on DOM mutations — Livewire/Filament swap
            // table rows, modals and panels in without a full reload.
            var scheduled = false;
            var observer = new MutationObserver(function () {
                if (scheduled) return;
                scheduled = true;
                requestAnimationFrame(function () {
                    scheduled = false;
                    mark();
                });
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', boot);
        } else {
            boot();
        }
    })();
</script>
