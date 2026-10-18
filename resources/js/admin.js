import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';

Alpine.plugin(focus);
Alpine.plugin(collapse);

/* ---------- Theme: light / dark / system (remembered per browser) ---------- */
const THEME_KEY = 'lw-admin-theme';

function readTheme() {
    try {
        return localStorage.getItem(THEME_KEY) || 'system';
    } catch {
        return 'system';
    }
}

function applyTheme(mode) {
    const dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
}

Alpine.store('theme', {
    mode: readTheme(),
    set(mode) {
        this.mode = mode;
        try {
            localStorage.setItem(THEME_KEY, mode);
        } catch {
            /* private mode — still applies for this page */
        }
        applyTheme(mode);
    },
    cycle() {
        this.set({ system: 'light', light: 'dark', dark: 'system' }[this.mode] || 'system');
    },
});
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => applyTheme(Alpine.store('theme').mode));

/* ---------- Toasts ---------- */
Alpine.store('toasts', {
    items: [],
    push(message, tone = 'ok') {
        const id = Date.now() + Math.random();
        this.items.push({ id, message, tone });
        setTimeout(() => this.dismiss(id), 5000);
    },
    dismiss(id) {
        this.items = this.items.filter((t) => t.id !== id);
    },
});

/* ---------- ⌘K command palette ---------- */
Alpine.data('palette', (searchUrl, shortcuts) => ({
    open: false,
    query: '',
    results: [],
    loading: false,
    active: 0,
    timer: null,
    get items() {
        if (!this.query.trim()) return shortcuts;
        return this.results;
    },
    init() {
        window.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                this.toggle();
            }
            if (e.key === '/' && !this.open && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
                e.preventDefault();
                this.show();
            }
        });
        window.addEventListener('open-palette', () => this.show());
    },
    show() {
        this.open = true;
        this.active = 0;
        this.$nextTick(() => this.$refs.input?.focus());
    },
    toggle() {
        this.open ? this.close() : this.show();
    },
    close() {
        this.open = false;
        this.query = '';
        this.results = [];
    },
    search() {
        clearTimeout(this.timer);
        this.active = 0;
        if (!this.query.trim()) {
            this.results = [];
            return;
        }
        this.loading = true;
        this.timer = setTimeout(async () => {
            try {
                const res = await fetch(`${searchUrl}?q=${encodeURIComponent(this.query.trim())}`, {
                    headers: { Accept: 'application/json' },
                });
                this.results = res.ok ? await res.json() : [];
            } catch {
                this.results = [];
            } finally {
                this.loading = false;
            }
        }, 180);
    },
    move(step) {
        const n = this.items.length;
        if (!n) return;
        this.active = (this.active + step + n) % n;
        this.$nextTick(() => document.getElementById(`palette-item-${this.active}`)?.scrollIntoView({ block: 'nearest' }));
    },
    go(item) {
        if (item?.url) window.location.href = item.url;
    },
}));

/* ---------- Bar chart hover tooltip ---------- */
Alpine.data('barChart', () => ({
    tip: null,
    show(event, label, value) {
        const box = this.$el.getBoundingClientRect();
        const bar = event.target.getBoundingClientRect();
        this.tip = { label, value, x: bar.left - box.left + bar.width / 2, y: bar.top - box.top };
    },
    hide() {
        this.tip = null;
    },
}));

window.Alpine = Alpine;
Alpine.start();

/* Clickable table rows (keyboard users use the link inside the row). */
document.addEventListener('click', (e) => {
    const row = e.target.closest('tr[data-href]');
    if (!row || e.target.closest('a, button, input, select, textarea, label, form')) return;
    if (e.metaKey || e.ctrlKey) window.open(row.dataset.href, '_blank');
    else window.location.href = row.dataset.href;
});
