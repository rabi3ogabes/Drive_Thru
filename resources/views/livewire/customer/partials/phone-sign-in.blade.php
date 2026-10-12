{{-- Phone + one-time code. Used inline at checkout and on the sign-in page. --}}
<div wire:key="otp-{{ $sends }}"
     x-data="{
        left: {{ (int) $resendIn }},
        timer: null,
        init() { this.timer = setInterval(() => { if (this.left > 0) this.left--; else clearInterval(this.timer) }, 1000) },
        destroy() { clearInterval(this.timer) },
     }">
    @if (! $codeSent)
        <form wire:submit="sendCode" class="space-y-3">
            <label class="block">
                <span class="text-sm font-medium text-ink-700">{{ __('guest.sign_in.phone') }}</span>
                <span class="mt-1.5 flex items-center rounded-2xl bg-white ring-1 ring-ink-950/10 focus-within:ring-2 focus-within:ring-[var(--brand)]" dir="ltr">
                    <span class="ps-4 text-[0.9375rem] font-medium text-ink-400">+974</span>
                    <input wire:model="phone" type="tel" inputmode="tel" autocomplete="tel-national" required maxlength="20" placeholder="5555 1234"
                           class="w-full border-0 bg-transparent px-3 py-3.5 text-[1.0625rem] tabular-nums tracking-wide text-ink-950 placeholder:text-ink-300 focus:ring-0">
                </span>
            </label>
            @if ($otpError)<p class="text-sm text-status-delayed" role="alert">{{ $otpError }}</p>@endif
            <button type="submit" wire:loading.attr="disabled" wire:target="sendCode"
                    class="flex w-full items-center justify-center rounded-full bg-ink-950 py-3.5 font-semibold text-white transition active:scale-[0.98] disabled:opacity-70">
                <span wire:loading.remove wire:target="sendCode">{{ __('guest.sign_in.send') }}</span>
                <span wire:loading wire:target="sendCode">{{ __('guest.loading') }}</span>
            </button>
            <p class="text-center text-xs text-ink-400">{{ __('guest.sign_in.privacy') }}</p>
        </form>
    @else
        <form wire:submit="verifyCode" class="space-y-3">
            <div class="flex items-center justify-between gap-2 text-sm">
                <span class="text-ink-600">{{ __('guest.sign_in.sent_to') }} <bdi dir="ltr" class="font-semibold tabular-nums text-ink-950">{{ $phone }}</bdi></span>
                <button type="button" wire:click="changePhone" class="font-medium text-[var(--brand)]">{{ __('guest.sign_in.change') }}</button>
            </div>
            <label class="block">
                <span class="sr-only">{{ __('guest.sign_in.code') }}</span>
                <input wire:model="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" required autofocus dir="ltr"
                       x-on:input="$el.value = $el.value.replace(/\D/g, ''); if ($el.value.length === 6) { $wire.set('code', $el.value, false); $wire.verifyCode() }"
                       placeholder="••••••"
                       class="w-full rounded-2xl border-0 bg-white py-4 text-center text-2xl font-semibold tabular-nums tracking-[0.5em] text-ink-950 ring-1 ring-ink-950/10 placeholder:text-ink-200 focus:ring-2 focus:ring-[var(--brand)]">
            </label>
            @if ($demoCode)
                <p class="rounded-xl bg-brass-50 px-3 py-2 text-center text-xs text-brass-900">{{ __('guest.sign_in.demo', ['code' => $demoCode]) }}</p>
            @endif
            @if ($otpError)<p class="text-sm text-status-delayed" role="alert">{{ $otpError }}</p>@endif
            <button type="submit" wire:loading.attr="disabled" wire:target="verifyCode"
                    class="flex w-full items-center justify-center rounded-full bg-ink-950 py-3.5 font-semibold text-white transition active:scale-[0.98] disabled:opacity-70">
                <span wire:loading.remove wire:target="verifyCode">{{ __('guest.sign_in.verify') }}</span>
                <span wire:loading wire:target="verifyCode">{{ __('guest.loading') }}</span>
            </button>
            <p class="text-center text-sm text-ink-500">
                <span x-show="left > 0">{{ __('guest.sign_in.resend_in') }} <span class="tabular-nums" x-text="left"></span></span>
                <button type="button" x-show="left <= 0" x-cloak wire:click="sendCode" class="font-medium text-[var(--brand)]">{{ __('guest.sign_in.resend') }}</button>
            </p>
        </form>
    @endif
</div>
