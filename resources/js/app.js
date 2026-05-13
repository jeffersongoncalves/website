import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

const images = import.meta.glob(['../images/**', '../fonts/**', '../favicon/**'], { eager: true });

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

Alpine.data('heatmap', ({ weeks = 53, days = 7 } = {}) => ({
    cells: [],
    init() {
        const cells = [];
        for (let w = 0; w < weeks; w++) {
            for (let d = 0; d < days; d++) {
                const r = Math.random();
                let v = 0;
                if (r > 0.78) v = 4;
                else if (r > 0.55) v = 3;
                else if (r > 0.32) v = 2;
                else if (r > 0.15) v = 1;
                if (w < 6 && r < 0.7) v = Math.max(0, v - 2);
                cells.push(v);
            }
        }
        this.cells = cells;
    },
    bgFor(v) {
        return v === 0
            ? 'var(--ink-850)'
            : `rgba(245, 158, 11, ${v * 0.25})`;
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

Alpine.data('stickyHeader', () => ({
    scrolled: false,
    init() {
        const onScroll = () => { this.scrolled = window.scrollY > 8; };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    },
}));

Livewire.start();
