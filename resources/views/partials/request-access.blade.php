@php
    $plans = \App\Domain\Billing\Models\Plan::query()->available()->where('is_public', true)->get();
    $field = 'block w-full rounded-xl border-0 bg-white px-4 py-3 text-ink-950 shadow-[var(--shadow-hairline)] ring-1 ring-ink-950/10 transition placeholder:text-ink-400 focus:ring-2 focus:ring-teal-600';
@endphp
<section id="request" class="relative mx-auto max-w-6xl scroll-mt-24 px-4 pb-24 sm:px-6 lg:pb-32">
    <div class="grid items-start gap-12 lg:grid-cols-12">
        <div class="lg:col-span-5 lg:pt-6">
            <p class="eyebrow">
                <span class="size-1.5 rounded-full bg-brass-500"></span>
                {{ __('leads.form.eyebrow') }}
            </p>
            <h2 class="display mt-6 text-4xl leading-[1.08] text-ink-950 sm:text-5xl rtl:leading-[1.4]">{{ __('leads.form.title') }}</h2>
            <p class="mt-5 max-w-md text-lg leading-relaxed text-ink-600">{{ __('leads.form.lead') }}</p>

            <ol class="mt-10 space-y-5">
                @foreach (['one', 'two', 'three'] as $i => $step)
                    <li class="flex gap-4">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-teal-900 text-sm font-semibold text-brass-200 shadow-[var(--shadow-soft)]" dir="ltr">{{ $i + 1 }}</span>
                        <div>
                            <p class="font-semibold text-ink-900">{{ __("leads.form.steps.{$step}.title") }}</p>
                            <p class="mt-0.5 text-sm text-ink-600">{{ __("leads.form.steps.{$step}.text") }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="lg:col-span-7">
            <div class="bezel">
                <div class="bezel-core p-6 sm:p-9">
                    @if (session('lead_submitted'))
                        <div class="flex flex-col items-center py-10 text-center" role="status">
                            <span class="grid size-14 place-items-center rounded-2xl bg-teal-900 text-brass-200 shadow-[var(--shadow-lift)]">
                                <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="m5 12.5 4.5 4.5L19 7.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <h3 class="display mt-6 text-3xl text-ink-950">{{ __('leads.form.success_title') }}</h3>
                            <p class="mt-3 max-w-sm text-ink-600">{{ __('leads.form.success_text') }}</p>
                        </div>
                    @else
                        <form method="POST" action="{{ route('leads.store') }}" class="grid gap-5 sm:grid-cols-2" x-data="{ busy: false }" x-on:submit="busy = true" novalidate>
                            @csrf
                            {{-- Honeypot: invisible to people, irresistible to bots. --}}
                            <div class="hidden" aria-hidden="true">
                                <label for="company_website">Website</label>
                                <input id="company_website" name="company_website" type="text" tabindex="-1" autocomplete="off">
                            </div>

                            @foreach ([
                                ['restaurant_name', 'text', 'organization', true],
                                ['contact_name', 'text', 'name', true],
                                ['email', 'email', 'email', true],
                                ['phone', 'tel', 'tel', false],
                            ] as [$name, $type, $autocomplete, $required])
                                <div>
                                    <label for="{{ $name }}" class="block text-sm font-medium text-ink-800">
                                        {{ __("leads.form.attributes.{$name}") }}
                                        @unless ($required)<span class="font-normal text-ink-400">· {{ __('leads.form.optional') }}</span>@endunless
                                    </label>
                                    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name) }}" autocomplete="{{ $autocomplete }}" @if ($required) required @endif
                                           @if (in_array($type, ['email', 'tel'], true)) dir="ltr" @endif
                                           @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
                                           class="mt-1.5 {{ $field }} @error($name) ring-2 ring-red-500/60 @enderror">
                                    @error($name)
                                        <p id="{{ $name }}-error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach

                            <div>
                                <label for="branches_count" class="block text-sm font-medium text-ink-800">{{ __('leads.form.attributes.branches_count') }}</label>
                                <input id="branches_count" name="branches_count" type="number" min="1" max="500" inputmode="numeric" value="{{ old('branches_count', 1) }}" dir="ltr"
                                       class="mt-1.5 {{ $field }} @error('branches_count') ring-2 ring-red-500/60 @enderror">
                                @error('branches_count')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="plan" class="block text-sm font-medium text-ink-800">
                                    {{ __('leads.form.attributes.plan') }} <span class="font-normal text-ink-400">· {{ __('leads.form.optional') }}</span>
                                </label>
                                <select id="plan" name="plan" class="mt-1.5 {{ $field }} appearance-none">
                                    <option value="">{{ __('leads.form.plan_help') }}</option>
                                    @foreach ($plans as $plan)
                                        <option value="{{ $plan->key }}" @selected(old('plan') === $plan->key)>
                                            {{ $plan->name }} · {{ $plan->formattedPrice() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="sm:col-span-2">
                                <label for="message" class="block text-sm font-medium text-ink-800">
                                    {{ __('leads.form.attributes.message') }} <span class="font-normal text-ink-400">· {{ __('leads.form.optional') }}</span>
                                </label>
                                <textarea id="message" name="message" rows="3" maxlength="2000" class="mt-1.5 {{ $field }}" placeholder="{{ __('leads.form.message_placeholder') }}">{{ old('message') }}</textarea>
                            </div>

                            <div class="flex flex-col-reverse items-start justify-between gap-4 sm:col-span-2 sm:flex-row sm:items-center">
                                <p class="text-xs text-ink-500">{{ __('leads.form.privacy') }}</p>
                                <button type="submit" class="btn btn-primary" :disabled="busy" :class="busy && 'opacity-80'">
                                    <span>{{ __('leads.form.submit') }}</span>
                                    <span class="btn-icon" aria-hidden="true">
                                        <svg x-show="! busy" class="flip-rtl size-4" viewBox="0 0 16 16" fill="none"><path d="M3 8h9.5M8.5 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        <svg x-show="busy" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                                    </span>
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
