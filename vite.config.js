import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { compression } from 'vite-plugin-compression2';

// Several favicon source files are byte-identical (e.g. android-icon-72x72 ==
// apple-icon-72x72, android-icon-144x144 == ms-icon-144x144). Rollup collapses
// identical assets into a single emitted file and names it after whichever
// source it happened to process first — and that winner is NOT stable between
// builds, so the default `[name]-[hash]` filename flipped every rebuild and
// churned the committed public/build. Naming favicons by content hash only
// (no source `[name]`) makes the emitted filename purely content-addressed:
// identical bytes always hash to the same name regardless of which source
// wins the dedup, so the output is deterministic. The manifest still resolves
// each `resources/favicon/...` key via Vite::asset(). Everything else keeps
// the default `[name]-[hash]` for readable, cache-busted assets.
function assetFileNames(assetInfo) {
    const sources = assetInfo.originalFileNames
        ?? (assetInfo.originalFileName ? [assetInfo.originalFileName] : []);

    const isFavicon = sources.some((path) => path.includes('resources/favicon/'));

    return isFavicon ? 'assets/favicon-[hash][extname]' : 'assets/[name]-[hash][extname]';
}

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/filament/admin/theme.css',
                'resources/css/site.css',
                'resources/js/app.js',
                'resources/js/site.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
        // Emit a build-time .gz next to every text asset so nginx `gzip_static`
        // serves a max-ratio pre-compressed file instead of re-gzipping at level
        // 5 per request. Brotli is intentionally omitted: Cloudflare already
        // serves brotli to clients at the edge, and the alpine nginx image ships
        // no brotli module — generating .br would be dead weight at the origin.
        compression({
            algorithms: ['gzip'],
            threshold: 1024,
            deleteOriginalAssets: false,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                assetFileNames,
            },
        },
    },
});
