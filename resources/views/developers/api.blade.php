@php
    use App\Domain\Integrations\Webhooks\WebhookSignature;
    $methodTone = fn (string $method): string => match ($method) {
        'GET' => 'bg-teal-900/8 text-teal-900 ring-teal-900/15',
        'POST' => 'bg-brass-400/15 text-ink-900 ring-brass-500/25',
        default => 'bg-ink-950/6 text-ink-800 ring-ink-950/12',
    };
@endphp
<x-layouts.site :title="__('developers.title').' · '.config('app.name')" :description="__('developers.description')" body-class="bg-ivory-50 text-ink-950">
    <header class="border-b border-ink-950/6">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-5 sm:px-6">
            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2 font-semibold">
                <x-brand.logo />
            </a>
            <div class="flex items-center gap-2 text-sm">
                <a href="{{ route('api.v1.openapi') }}" class="hidden min-h-10 items-center rounded-full px-4 text-ink-700 ring-1 ring-ink-950/10 transition hover:bg-white sm:inline-flex" dir="ltr">openapi.json</a>
                <a href="{{ route('locale.switch', app()->getLocale() === 'ar' ? 'en' : 'ar') }}" class="inline-flex min-h-10 items-center rounded-full px-4 text-ink-700 ring-1 ring-ink-950/10 transition hover:bg-white">{{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}</a>
            </div>
        </div>
    </header>

    <main id="main" class="mx-auto grid max-w-6xl gap-12 px-4 py-14 sm:px-6 lg:grid-cols-[13rem_1fr] lg:py-20">
        <nav aria-label="{{ __('developers.contents') }}" class="hidden lg:block">
            <ol class="sticky top-8 space-y-1 text-sm text-ink-600">
                @foreach (['start', 'auth', 'scopes', 'endpoints', 'webhooks', 'errors'] as $section)
                    <li><a href="#{{ $section }}" class="block rounded-xl px-3 py-2 transition hover:bg-white hover:text-ink-950">{{ __('developers.nav.'.$section) }}</a></li>
                @endforeach
            </ol>
        </nav>

        <div class="min-w-0 space-y-16">
            <section id="start" class="space-y-5">
                <span class="inline-flex rounded-full bg-white px-3 py-1 text-[11px] font-medium uppercase tracking-[0.2em] text-ink-500 ring-1 ring-ink-950/8 rtl:tracking-normal">API v1</span>
                <h1 class="font-display text-4xl leading-tight tracking-tight sm:text-5xl">{{ __('developers.title') }}</h1>
                <p class="max-w-2xl text-lg leading-relaxed text-ink-600">{{ __('developers.intro') }}</p>
                <div class="rounded-[1.75rem] bg-ink-950/[0.03] p-1.5 ring-1 ring-ink-950/6">
                    <dl class="grid gap-px overflow-hidden rounded-[calc(1.75rem-0.375rem)] bg-ink-950/6 sm:grid-cols-3">
                        <div class="bg-white p-5"><dt class="text-xs text-ink-500">{{ __('developers.facts.base') }}</dt><dd class="mt-1 break-all font-mono text-sm" dir="ltr">{{ $base }}</dd></div>
                        <div class="bg-white p-5"><dt class="text-xs text-ink-500">{{ __('developers.facts.format') }}</dt><dd class="mt-1 text-sm">{{ __('developers.facts.format_value') }}</dd></div>
                        <div class="bg-white p-5"><dt class="text-xs text-ink-500">{{ __('developers.facts.limit') }}</dt><dd class="mt-1 text-sm">{{ __('developers.facts.limit_value', ['count' => config('integrations.api.requests_per_minute')]) }}</dd></div>
                    </dl>
                </div>
            </section>

            <section id="auth" class="space-y-4">
                <h2 class="font-display text-3xl">{{ __('developers.nav.auth') }}</h2>
                <p class="max-w-2xl leading-relaxed text-ink-600">{{ __('developers.auth') }}</p>
                <pre class="overflow-x-auto rounded-3xl bg-ink-950 p-5 font-mono text-[13px] leading-relaxed text-ivory-50" dir="ltr"><code>curl {{ $base }}/orders?status=received,preparing \
  -H "Authorization: Bearer &lt;your key&gt;" \
  -H "Accept: application/json"</code></pre>
            </section>

            <section id="scopes" class="space-y-4">
                <h2 class="font-display text-3xl">{{ __('developers.nav.scopes') }}</h2>
                <ul class="grid gap-3 sm:grid-cols-2">
                    @foreach ($scopes as $scope)
                        <li class="rounded-3xl bg-white p-5 ring-1 ring-ink-950/6">
                            <p class="font-mono text-sm text-teal-900" dir="ltr">{{ $scope->value }}</p>
                            <p class="mt-1 font-medium">{{ $scope->getLabel() }}</p>
                            <p class="mt-1 text-sm text-ink-600">{{ $scope->getDescription() }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section id="endpoints" class="space-y-4">
                <h2 class="font-display text-3xl">{{ __('developers.nav.endpoints') }}</h2>
                <div class="space-y-3">
                    @foreach ($endpoints as $endpoint)
                        <details class="group rounded-3xl bg-white ring-1 ring-ink-950/6 open:shadow-[0_24px_48px_-32px_rgba(10,15,28,0.25)]">
                            <summary class="flex cursor-pointer list-none flex-wrap items-center gap-3 p-5 [&::-webkit-details-marker]:hidden" dir="ltr">
                                <span class="inline-flex min-w-16 justify-center rounded-full px-2.5 py-1 font-mono text-xs font-semibold ring-1 {{ $methodTone($endpoint['method']) }}">{{ $endpoint['method'] }}</span>
                                <code class="min-w-0 flex-1 break-all font-mono text-sm">{{ $endpoint['path'] }}</code>
                                <span class="font-mono text-xs text-ink-500">{{ $endpoint['scope'] }}</span>
                            </summary>
                            <div class="space-y-3 border-t border-ink-950/6 p-5 text-sm leading-relaxed text-ink-700" dir="ltr">
                                <p class="font-medium text-ink-950">{{ $endpoint['summary'] }}</p>
                                <p>{{ $endpoint['description'] }}</p>
                                @isset($endpoint['query'])
                                    <dl class="grid gap-x-6 gap-y-1.5 sm:grid-cols-[8rem_1fr]">
                                        @foreach ($endpoint['query'] as $name => $about)
                                            <dt class="font-mono text-xs text-teal-900">{{ $name }}</dt><dd class="text-ink-600">{{ $about }}</dd>
                                        @endforeach
                                    </dl>
                                @endisset
                                @isset($endpoint['body'])
                                    <pre class="overflow-x-auto rounded-2xl bg-ink-950 p-4 font-mono text-[13px] text-ivory-50"><code>{{ json_encode($endpoint['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                                @endisset
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>

            <section id="webhooks" class="space-y-4">
                <h2 class="font-display text-3xl">{{ __('developers.nav.webhooks') }}</h2>
                <p class="max-w-2xl leading-relaxed text-ink-600">{{ __('developers.webhooks') }}</p>
                <ul class="flex flex-wrap gap-2" dir="ltr">
                    @foreach ($events as $event)
                        <li class="rounded-full bg-white px-3 py-1.5 font-mono text-xs ring-1 ring-ink-950/8">{{ $event->value }}</li>
                    @endforeach
                </ul>
                <p class="max-w-2xl leading-relaxed text-ink-600">{{ __('developers.signature', ['header' => WebhookSignature::HEADER, 'minutes' => intdiv(WebhookSignature::TOLERANCE_SECONDS, 60)]) }}</p>
                <pre class="overflow-x-auto rounded-3xl bg-ink-950 p-5 font-mono text-[13px] leading-relaxed text-ivory-50" dir="ltr"><code>// PHP: verify a delivery
[$t, $v1] = sscanf($_SERVER['HTTP_X_DTP_SIGNATURE'], 't=%d,v1=%s');
$body = file_get_contents('php://input');
$expected = hash_hmac('sha256', $t . '.' . $body, $secret);
$valid = hash_equals($expected, $v1) &amp;&amp; abs(time() - $t) &lt;= {{ WebhookSignature::TOLERANCE_SECONDS }};

// Node.js
const [t, v1] = header.split(',').map(p =&gt; p.split('=')[1]);
const expected = crypto.createHmac('sha256', secret).update(`${t}.${rawBody}`).digest('hex');
const valid = crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(v1));</code></pre>
                <p class="max-w-2xl text-sm leading-relaxed text-ink-500">{{ __('developers.retries') }}</p>
            </section>

            <section id="errors" class="space-y-4">
                <h2 class="font-display text-3xl">{{ __('developers.nav.errors') }}</h2>
                <p class="max-w-2xl leading-relaxed text-ink-600">{{ __('developers.errors') }}</p>
                <pre class="overflow-x-auto rounded-3xl bg-ink-950 p-5 font-mono text-[13px] text-ivory-50" dir="ltr"><code>{ "error": { "code": "insufficient_scope", "message": "This key does not have the \"orders:write\" scope." } }</code></pre>
                <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[5rem_1fr]">
                    @foreach ([401, 403, 404, 409, 422, 429] as $code)
                        <dt class="font-mono text-teal-900" dir="ltr">{{ $code }}</dt><dd class="text-ink-600">{{ __('developers.status.'.$code) }}</dd>
                    @endforeach
                </dl>
            </section>
        </div>
    </main>
</x-layouts.site>
