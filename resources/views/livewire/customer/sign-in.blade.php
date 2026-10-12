<div>
    <x-customer.shell :restaurant="$restaurant" :customer="null" bare>
        <section class="mx-auto max-w-md pt-10">
            <div class="rounded-[2rem] bg-white p-1.5 shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/5">
                <div class="rounded-[calc(2rem-0.375rem)] bg-gradient-to-br from-ivory-100 to-white px-5 pb-6 pt-7">
                    <x-public.restaurant-logo :restaurant="$restaurant" class="size-14" />
                    <h1 class="display mt-5 text-3xl text-ink-950">{{ __('guest.sign_in.title') }}</h1>
                    <p class="mt-1.5 text-ink-500">{{ __('guest.sign_in.body', ['restaurant' => $restaurant->name]) }}</p>
                    <div class="mt-6">@include('livewire.customer.partials.phone-sign-in')</div>
                </div>
            </div>
            <a href="{{ $to }}" wire:navigate class="mt-6 block text-center text-sm font-medium text-ink-500">{{ __('guest.sign_in.later') }}</a>
        </section>
    </x-customer.shell>
</div>
