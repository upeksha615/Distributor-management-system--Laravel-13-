import Alpine from 'alpinejs';

/**
 * Line items editor for purchase orders, sales orders and invoices.
 * priceField: which product price to prefill ('cost_price' for purchases, 'selling_price' for sales).
 */
Alpine.data('lineItems', (config) => ({
    items: config.items?.length ? config.items : [],
    priceField: config.priceField ?? 'selling_price',
    withDiscount: config.withDiscount ?? true,
    discount: Number(config.discount ?? 0),
    taxPercent: Number(config.taxPercent ?? 0),
    lookupUrl: config.lookupUrl,
    warehouseId: config.warehouseId ?? '',
    customerLevels: config.customerLevels ?? {},
    priceLevel: config.priceLevel ?? 'retail',
    query: '',
    results: [],
    open: false,
    highlighted: 0,

    async search() {
        if (this.query.trim().length < 1) {
            this.results = [];
            this.open = false;
            return;
        }
        const params = new URLSearchParams({ q: this.query, warehouse_id: this.warehouseId || '' });
        const res = await fetch(`${this.lookupUrl}?${params}`, { headers: { Accept: 'application/json' } });
        this.results = await res.json();
        this.highlighted = 0;
        this.open = true;
    },

    add(product) {
        const existing = this.items.find((i) => Number(i.product_id) === product.id);
        if (existing) {
            existing.quantity = Number(existing.quantity) + 1;
        } else {
            this.items.push({
                product_id: product.id,
                label: `${product.sku} - ${product.name}`,
                stock: product.stock,
                quantity: 1,
                unit_price: this.priceOf(product),
                unit_cost: Number(product.cost_price ?? 0),
                discount_percent: this.withDiscount ? Number(product.discount_percent ?? 0) : 0,
                tax_percent: Number(product.tax_rate ?? 0),
                stock_level: product.stock_level,
                incoming: product.incoming,
            });
        }
        this.query = '';
        this.results = [];
        this.open = false;
        this.$nextTick(() => this.$refs.search?.focus());
    },

    /** Dealers get the wholesale price when one is set; everyone else the retail price. */
    priceOf(product) {
        if (this.priceField === 'selling_price' && this.priceLevel === 'wholesale' && Number(product.wholesale_price) > 0) {
            return Number(product.wholesale_price);
        }
        return Number(product[this.priceField] ?? 0);
    },

    /** Red = no stock, yellow = low, green = in stock (threshold in Settings). */
    stockClass(level) {
        return { out: 'bg-red-100 text-red-700 ring-red-200', low: 'bg-yellow-100 text-yellow-800 ring-yellow-300', ok: 'bg-green-100 text-green-800 ring-green-300' }[level] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
    },

    /** The number when the user may see quantities, otherwise just the availability word. */
    stockText(p) {
        if (p.stock !== null && p.stock !== undefined) return p.stock;
        return { out: 'Out of stock', low: 'Low', ok: 'In stock' }[p.stock_level] ?? '';
    },

    pickHighlighted() {
        if (this.results[this.highlighted]) this.add(this.results[this.highlighted]);
    },

    remove(index) {
        this.items.splice(index, 1);
    },

    price(item) {
        return Number(this.priceField === 'cost_price' ? item.unit_cost : item.unit_price) || 0;
    },

    lineTotal(item) {
        const gross = Number(item.quantity || 0) * this.price(item);
        return this.withDiscount ? gross * (1 - Number(item.discount_percent || 0) / 100) : gross;
    },

    get subtotal() {
        return this.items.reduce((sum, i) => sum + this.lineTotal(i), 0);
    },

    /** Tax per line at the product's rate, after spreading the bill discount over the lines. */
    get tax() {
        const ratio = this.subtotal > 0 ? Math.max(0, this.subtotal - Math.min(this.discount, this.subtotal)) / this.subtotal : 1;
        return this.items.reduce((sum, i) => sum + this.lineTotal(i) * ratio * Number(i.tax_percent ?? this.taxPercent ?? 0) / 100, 0);
    },

    get total() {
        return this.subtotal - Math.min(this.discount, this.subtotal) + this.tax;
    },

    money(value) {
        return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
}));

/**
 * Live parts search (top bar and dashboard): results appear while typing.
 * Reps see availability words, never quantities (the lookup returns stock = null for them).
 */
Alpine.data("partSearch", (config) => ({
    query: config.query ?? "",
    results: [],
    open: false,
    loading: false,
    highlighted: -1,
    controller: null,

    async search() {
        const q = this.query.trim();
        if (q.length < 2) {
            this.results = [];
            this.open = false;
            return;
        }
        this.controller?.abort();
        this.controller = new AbortController();
        this.loading = true;
        try {
            const res = await fetch(`${config.lookupUrl}?${new URLSearchParams({ q })}`, { headers: { Accept: "application/json" }, signal: this.controller.signal });
            this.results = (await res.json()).slice(0, 8);
            this.highlighted = this.results.length ? 0 : -1;
            this.open = true;
        } catch (e) {
            if (e.name !== "AbortError") { this.results = []; }
        } finally {
            this.loading = false;
        }
    },

    move(step) {
        if (!this.open || !this.results.length) return;
        this.highlighted = (this.highlighted + step + this.results.length) % this.results.length;
    },

    /** Enter opens the highlighted part; with nothing highlighted it shows the full results list. */
    enter(event) {
        if (this.open && this.results[this.highlighted]) {
            event.preventDefault();
            this.go(this.results[this.highlighted]);
        }
    },

    go(product) {
        window.location = config.showUrl.replace("__ID__", product.id);
    },

    stockClass(level) {
        return { out: "bg-red-100 text-red-700", low: "bg-yellow-100 text-yellow-800", ok: "bg-green-100 text-green-800" }[level] ?? "bg-slate-100 text-slate-600";
    },

    stockText(p) {
        if (p.stock !== null && p.stock !== undefined) return `${p.stock} ${p.unit ?? ""}`.trim();
        return { out: "Out of stock", low: "Low stock", ok: "In stock" }[p.stock_level] ?? "";
    },
}));

/**
 * List pages: the search box in a GET filter form filters while typing (after a short pause),
 * and drop-downs / dates apply as soon as they change. Focus returns to the search box after reload.
 */
function liveFilters() {
    const key = "liveSearchFocus";
    let saved = null;
    try { saved = JSON.parse(sessionStorage.getItem(key) || "null"); sessionStorage.removeItem(key); } catch (e) {}

    document.querySelectorAll("form[method=GET], form[method=get]").forEach((form) => {
        const box = form.querySelector("input[type=search]:not([data-no-live])");
        if (!box) return;

        const submit = (focusBox) => {
            if (focusBox) {
                try { sessionStorage.setItem(key, JSON.stringify({ path: location.pathname, name: box.name })); } catch (e) {}
            }
            form.requestSubmit ? form.requestSubmit() : form.submit();
        };

        let timer;
        box.addEventListener("input", () => {
            clearTimeout(timer);
            const v = box.value.trim();
            if (v.length === 1) return; // wait for at least 2 characters (or an empty box to clear)
            timer = setTimeout(() => submit(true), 450);
        });
        form.querySelectorAll("select, input[type=date], input[type=checkbox]").forEach((el) => el.addEventListener("change", () => submit(false)));

        if (saved && saved.path === location.pathname && saved.name === box.name) {
            box.focus();
            const end = box.value.length;
            try { box.setSelectionRange(end, end); } catch (e) {}
        }
    });
}
document.addEventListener("DOMContentLoaded", liveFilters);
window.Alpine = Alpine;
Alpine.start();
