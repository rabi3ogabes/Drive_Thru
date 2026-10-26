{{-- The product sheet: options, meal choices, quantity and notes with a live
     price. The server re-checks and re-prices everything on add. --}}
<div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="sheet-title"
     wire:key="sheet-{{ $sheet['id'] }}"
     x-data="{
        p: @js($sheet),
        t: @js(['kcal' => __('public.menu.kcal'), 'sold_out' => __('guest.menu.sold_out'), 'required' => __('guest.menu.required'), 'choose_min' => __('guest.menu.choose_min'), 'up_to' => __('guest.menu.up_to'), 'optional' => __('guest.menu.optional'), 'add' => __('guest.menu.add'), 'choose_options' => __('guest.menu.choose_options'), 'unavailable' => __('guest.menu.unavailable')]),
        sel: {},
        combo: {},
        qty: 1,
        notes: '',
        busy: false,
        init() {
            const seed = (g) => { this.sel[g.id] = g.options.filter(o => o.default).map(o => o.id).slice(0, g.max || undefined) };
            this.p.groups.forEach(seed);
            Object.values(this.p.nested).forEach(seed);
            this.p.slots.forEach(s => { const d = s.options.find(o => o.default && o.available) ?? s.options.find(o => o.available); this.combo[s.id] = d?.id ?? null });
            if (! matchMedia('(prefers-reduced-motion: reduce)').matches) this.$refs.panel.animate([{ transform: 'translateY(100%)' }, { transform: 'translateY(0)' }], { duration: 360, easing: 'cubic-bezier(.32,.72,0,1)' });
            document.documentElement.style.overflow = 'hidden';
        },
        destroy() { document.documentElement.style.overflow = '' },
        active() {
            const list = [];
            this.p.groups.forEach(g => {
                list.push(g);
                g.options.forEach(o => { if (o.nested && this.p.nested[o.nested] && this.has(g.id, o.id)) list.push(this.p.nested[o.nested]) });
            });
            return list;
        },
        has(gid, oid) { return (this.sel[gid] ?? []).includes(oid) },
        toggle(g, o) {
            const cur = this.sel[g.id] ?? [];
            if (g.max === 1) { this.sel[g.id] = cur.includes(o.id) && g.min === 0 ? [] : [o.id]; return }
            if (cur.includes(o.id)) { this.sel[g.id] = cur.filter(i => i !== o.id); return }
            if (g.max && cur.length >= g.max) return;
            this.sel[g.id] = [...cur, o.id];
        },
        full(g) { return g.max > 1 && (this.sel[g.id] ?? []).length >= g.max },
        missing(g) { return (this.sel[g.id] ?? []).length < g.min },
        unit() {
            let total = this.p.price;
            this.active().forEach(g => g.options.forEach(o => { if (this.has(g.id, o.id)) total += o.price }));
            this.p.slots.forEach(s => { const o = s.options.find(o => o.id === this.combo[s.id]); if (o) total += o.price });
            return total;
        },
        valid() { return this.p.available && this.active().every(g => ! this.missing(g)) && this.p.slots.every(s => this.combo[s.id]) },
        payload() {
            const options = {};
            this.active().forEach(g => options[g.id] = this.sel[g.id] ?? []);
            return options;
        },
        async submit() {
            if (! this.valid()) { this.$refs.groups?.querySelector('[data-missing=true]')?.scrollIntoView({ behavior: 'smooth', block: 'center' }); return }
            if (this.busy) return;
            this.busy = true;
            try { await $wire.add(this.p.id, this.qty, this.payload(), this.combo, this.notes || null) } finally { this.busy = false }
        },
     }"
     x-on:keydown.escape.window="$wire.closeSheet()">
    <button type="button" class="absolute inset-0 bg-ink-950/45 backdrop-blur-[2px]" wire:click="closeSheet" aria-label="{{ __('guest.close') }}"></button>

    <div x-ref="panel" class="relative flex max-h-[92dvh] w-full max-w-xl flex-col overflow-hidden rounded-t-[2rem] bg-white shadow-2xl sm:rounded-[2rem]">
        {{-- Media --}}
        <div class="relative shrink-0">
            <div class="grid aspect-[16/9] place-items-center overflow-hidden bg-gradient-to-br from-ivory-100 to-ivory-200 text-6xl">
                <template x-if="p.image"><img :src="p.image" alt="" class="size-full object-cover"></template>
                <template x-if="! p.image"><span aria-hidden="true" x-text="p.icon"></span></template>
            </div>
            <div class="absolute inset-x-0 top-0 flex justify-between p-3">
                <button type="button" wire:click="toggleFavorite({{ $sheet['id'] }})" class="grid size-10 place-items-center rounded-full bg-white/90 text-ink-800 shadow-sm backdrop-blur"
                        aria-label="{{ __('guest.menu.favorite') }}" :aria-pressed="p.favorite">
                    <svg class="size-5" viewBox="0 0 24 24" :fill="p.favorite ? 'currentColor' : 'none'" :class="p.favorite && 'text-[var(--brand)]'" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 20s-7.5-4.6-7.5-10.1A4.4 4.4 0 0 1 12 7.3a4.4 4.4 0 0 1 7.5 2.6C19.5 15.4 12 20 12 20Z" stroke-linejoin="round"/></svg>
                </button>
                <button type="button" wire:click="closeSheet" class="grid size-10 place-items-center rounded-full bg-white/90 text-ink-800 shadow-sm backdrop-blur" aria-label="{{ __('guest.close') }}">
                    <svg class="size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 4 8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                </button>
            </div>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto overscroll-contain px-5 pb-6 pt-5" x-ref="groups">
            <h2 id="sheet-title" class="display text-[1.75rem] leading-tight text-ink-950" x-text="p.name"></h2>
            <p class="mt-1 tabular-nums" dir="ltr">
                <span x-show="p.base_price !== p.price" class="me-1.5 text-sm text-ink-400 line-through" x-text="money(p.base_price)"></span>
                <span class="font-semibold text-ink-950" x-text="money(p.price)"></span>
                <span x-show="p.calories" class="ms-2 text-sm text-ink-400" x-text="t.kcal.replace(':count', p.calories)"></span>
            </p>
            <p x-show="p.description" class="mt-3 leading-relaxed text-ink-600" x-text="p.description"></p>
            <p x-show="p.ingredients" class="mt-2 text-sm text-ink-500" x-text="p.ingredients"></p>

            <div x-show="p.tags.length || p.allergens.length" class="mt-3 flex flex-wrap gap-1.5">
                <template x-for="t in p.tags" :key="'t' + t.label"><span class="rounded-full bg-sage-100 px-2.5 py-1 text-xs font-medium text-teal-900" x-text="t.symbol + ' ' + t.label"></span></template>
                <template x-for="a in p.allergens" :key="'a' + a.label"><span class="rounded-full bg-ivory-100 px-2.5 py-1 text-xs font-medium text-ink-700" x-text="a.symbol + ' ' + a.label"></span></template>
            </div>

            <p x-show="! p.available" class="mt-4 rounded-2xl bg-ivory-100 px-4 py-3 text-sm font-medium text-ink-700" x-text="p.unavailable"></p>

            {{-- Meal choices --}}
            <template x-for="s in p.slots" :key="'s' + s.id">
                <fieldset class="mt-6">
                    <legend class="text-[0.9375rem] font-semibold text-ink-950" x-text="s.name"></legend>
                    <div class="mt-2 grid gap-2">
                        <template x-for="o in s.options" :key="o.id">
                            <label class="flex cursor-pointer items-center gap-3 rounded-2xl px-4 py-3 ring-1 transition"
                                   :class="[combo[s.id] === o.id ? 'bg-[color-mix(in_oklab,var(--brand)_7%,white)] ring-2 ring-[var(--brand)]' : 'ring-ink-950/8', ! o.available && 'opacity-50 cursor-not-allowed']">
                                <input type="radio" class="sr-only" :name="'slot-' + s.id" :value="o.id" :disabled="! o.available" x-model.number="combo[s.id]">
                                <span class="grid size-5 shrink-0 place-items-center rounded-full ring-1 ring-ink-950/20" :class="combo[s.id] === o.id && '!ring-[var(--brand)] ring-[6px]'"></span>
                                <span class="flex-1 font-medium text-ink-900" x-text="o.name"></span>
                                <span class="text-sm tabular-nums text-ink-500" dir="ltr" x-text="o.available ? (o.price_label ?? '') : t.sold_out"></span>
                            </label>
                        </template>
                    </div>
                </fieldset>
            </template>

            {{-- Options (nested follow-ups appear under the option that opens them) --}}
            <template x-for="g in active()" :key="'g' + g.id">
                <fieldset class="mt-6" :data-missing="missing(g)">
                    <legend class="flex w-full items-center justify-between gap-2">
                        <span class="text-[0.9375rem] font-semibold text-ink-950" x-text="g.name"></span>
                        <span class="rounded-full px-2.5 py-0.5 text-[0.6875rem] font-semibold"
                              :class="missing(g) ? 'bg-brass-50 text-brass-800' : 'bg-ivory-100 text-ink-500'"
                              x-text="g.min > 0 ? (g.max === 1 ? t.required : t.choose_min.replace(':count', g.min)) : (g.max > 1 ? t.up_to.replace(':count', g.max) : t.optional)"></span>
                    </legend>
                    <div class="mt-2 grid gap-2">
                        <template x-for="o in g.options" :key="o.id">
                            <button type="button" x-on:click="toggle(g, o)" :aria-pressed="has(g.id, o.id)" :disabled="! has(g.id, o.id) && full(g)"
                                    class="flex items-center gap-3 rounded-2xl px-4 py-3 text-start ring-1 transition active:scale-[0.99] disabled:opacity-45"
                                    :class="has(g.id, o.id) ? 'bg-[color-mix(in_oklab,var(--brand)_7%,white)] ring-2 ring-[var(--brand)]' : 'ring-ink-950/8'">
                                <span class="grid size-5 shrink-0 place-items-center ring-1 ring-ink-950/20 transition"
                                      :class="[g.max === 1 ? 'rounded-full' : 'rounded-md', has(g.id, o.id) && (g.max === 1 ? '!ring-[var(--brand)] ring-[6px]' : '!ring-[var(--brand)] bg-[var(--brand)] text-white')]">
                                    <svg x-show="g.max !== 1 && has(g.id, o.id)" class="size-3" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="m2.5 6.2 2.3 2.3 4.7-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                                <span class="flex-1 font-medium text-ink-900" x-text="o.name"></span>
                                <span x-show="o.calories" class="text-xs text-ink-400" x-text="t.kcal.replace(':count', (o.calories > 0 ? '+' : '') + o.calories)"></span>
                                <span class="text-sm tabular-nums text-ink-500" dir="ltr" x-text="o.price_label ?? ''"></span>
                            </button>
                        </template>
                    </div>
                </fieldset>
            </template>

            <label class="mt-6 block">
                <span class="text-[0.9375rem] font-semibold text-ink-950">{{ __('guest.menu.notes') }}</span>
                <textarea x-model="notes" rows="2" maxlength="200" placeholder="{{ __('guest.menu.notes_hint') }}"
                          class="mt-2 w-full resize-none rounded-2xl border-0 bg-ivory-50 px-4 py-3 text-[0.9375rem] text-ink-900 ring-1 ring-ink-950/8 placeholder:text-ink-400 focus:ring-2 focus:ring-[var(--brand)]"></textarea>
            </label>
        </div>

        {{-- Footer: quantity + add --}}
        @if (! ($ordering ?? true))
            <p class="shrink-0 border-t border-ink-950/5 bg-white px-5 pb-[max(1rem,env(safe-area-inset-bottom))] pt-4 text-center text-sm text-ink-500">{{ __('guest.menu.menu_only') }}</p>
        @else
        <div class="flex shrink-0 items-center gap-3 border-t border-ink-950/5 bg-white px-5 pb-[max(1rem,env(safe-area-inset-bottom))] pt-3">
            <div class="flex items-center rounded-full bg-ivory-100 p-1" x-show="p.available">
                <button type="button" x-on:click="qty = Math.max(1, qty - 1)" :disabled="qty <= 1" class="grid size-10 place-items-center rounded-full text-ink-800 transition disabled:opacity-35 enabled:active:bg-white" aria-label="{{ __('guest.menu.less') }}">
                    <svg class="size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
                <span class="w-7 text-center font-semibold tabular-nums text-ink-950" x-text="qty" aria-live="polite"></span>
                <button type="button" x-on:click="qty = Math.min(20, qty + 1)" :disabled="qty >= 20" class="grid size-10 place-items-center rounded-full text-ink-800 transition disabled:opacity-35 enabled:active:bg-white" aria-label="{{ __('guest.menu.more') }}">
                    <svg class="size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
            </div>
            <button type="button" x-on:click="submit()" :disabled="! p.available || busy"
                    class="flex min-w-0 flex-1 items-center justify-between gap-2 rounded-full py-3.5 pe-5 ps-5 font-semibold text-white shadow-lg transition active:scale-[0.98] disabled:opacity-60"
                    :class="valid() ? 'bg-[var(--brand)]' : 'bg-ink-700'">
                <span x-show="! busy" class="truncate whitespace-nowrap" x-text="p.available ? (valid() ? t.add : t.choose_options) : t.unavailable"></span>
                <span x-show="busy" x-cloak>{{ __('guest.loading') }}</span>
                <span class="shrink-0 tabular-nums" dir="ltr" x-show="p.available" x-text="money(unit() * qty)"></span>
            </button>
        </div>
        @endif
    </div>
</div>
