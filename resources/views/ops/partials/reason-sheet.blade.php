{{--
    One reason sheet for every action that needs a reason. Open it with:
    $dispatch('ops-reason', { method: 'cancel', args: [id], title: '…', tone: 'danger', action: '…', choices: [...] })
    It then calls $wire[method](...args, reason) on the screen's component.
    With pin: true (sensitive actions on a paired screen) it also asks for the
    person's PIN and calls $wire[method](...args, reason, pin).
--}}
<div x-data="{
        sheet: null,
        reason: '',
        pin: '',
        open(detail) { this.sheet = detail; this.reason = ''; this.pin = ''; this.$nextTick(() => this.$refs.reason?.focus()) },
        get ready() { return this.reason.trim().length >= 2 && (! this.sheet?.pin || /^[0-9]{4,6}$/.test(this.pin)) },
        confirm() {
            if (! this.sheet || ! this.ready) return;
            const { method, args, pin } = this.sheet;
            const extra = pin ? [this.pin] : [];
            this.sheet = null;
            this.pin = '';
            $wire.call(method, ...args, this.reason.trim(), ...extra);
        },
     }"
     x-on:ops-reason.window="open($event.detail)"
     x-on:keydown.escape.window="sheet = null">
    <div x-show="sheet" x-cloak x-transition.opacity class="fixed inset-0 z-50 grid place-items-end bg-ink-950/70 backdrop-blur-sm sm:place-items-center" role="dialog" aria-modal="true" aria-labelledby="reason-title">
        <form x-on:submit.prevent="confirm()" x-on:click.outside="sheet = null" class="w-full max-w-lg rounded-t-[2rem] bg-ink-900 p-6 ring-1 ring-white/10 sm:rounded-[2rem]">
            <h2 id="reason-title" class="text-2xl font-semibold" x-text="sheet?.title"></h2>
            <div class="mt-4 flex flex-wrap gap-2">
                <template x-for="choice in (sheet?.choices ?? [])">
                    <button type="button" x-on:click="reason = choice" class="min-h-12 rounded-full px-4 text-base ring-1 transition"
                            :class="reason === choice ? 'bg-white text-ink-950 ring-white' : 'bg-white/6 text-white/80 ring-white/12'" x-text="choice"></button>
                </template>
            </div>
            <label class="mt-4 block">
                <span class="sr-only">{{ __('ops.reason.label') }}</span>
                <input x-ref="reason" x-model="reason" type="text" maxlength="200" placeholder="{{ __('ops.reason.placeholder') }}"
                       class="w-full rounded-2xl border-0 bg-white/6 px-4 py-4 text-lg text-white ring-1 ring-white/10 placeholder:text-white/35 focus:ring-2 focus:ring-brass-300">
            </label>
            <label x-show="sheet?.pin" x-cloak class="mt-3 flex items-center gap-3 rounded-2xl bg-white/6 px-4 ring-1 ring-white/10 focus-within:ring-2 focus-within:ring-brass-300">
                <span class="shrink-0 text-base text-white/70">{{ __('ops.reason.pin') }}</span>
                <input x-model="pin" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="off" dir="ltr"
                       class="min-h-14 w-full border-0 bg-transparent text-center text-2xl tracking-[0.5em] text-white focus:ring-0">
                <span class="shrink-0 text-sm text-white/45">{{ __('ops.reason.pin_hint') }}</span>
            </label>
            <div class="mt-5 grid grid-cols-2 gap-3">
                <button type="button" x-on:click="sheet = null" class="min-h-14 rounded-2xl bg-white/8 text-lg font-semibold">{{ __('ops.actions.back') }}</button>
                <button type="submit" :disabled="! ready" class="min-h-14 rounded-2xl text-lg font-semibold disabled:opacity-40"
                        :class="sheet?.tone === 'danger' ? 'bg-status-delayed text-white' : 'bg-white text-ink-950'" x-text="sheet?.action"></button>
            </div>
        </form>
    </div>
</div>
