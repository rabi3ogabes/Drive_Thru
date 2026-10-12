{{-- One webhook delivery: the exact body sent and what the receiver answered. --}}
<div class="space-y-4 text-sm">
    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-2xl bg-gray-50 px-4 py-3 dark:bg-white/5">
            <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('integrations.webhooks.fields.status') }}</dt>
            <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ __('integrations.webhooks.deliveries.status.'.$delivery->status) }}</dd>
        </div>
        <div class="rounded-2xl bg-gray-50 px-4 py-3 dark:bg-white/5">
            <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('integrations.webhooks.deliveries.response') }}</dt>
            <dd class="mt-1 font-medium tabular-nums text-gray-950 dark:text-white">{{ $delivery->response_status ?? '—' }}</dd>
        </div>
        <div class="rounded-2xl bg-gray-50 px-4 py-3 dark:bg-white/5">
            <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('integrations.webhooks.deliveries.attempts') }}</dt>
            <dd class="mt-1 font-medium tabular-nums text-gray-950 dark:text-white">{{ $delivery->attempts }}</dd>
        </div>
        <div class="rounded-2xl bg-gray-50 px-4 py-3 dark:bg-white/5">
            <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('integrations.webhooks.deliveries.sent') }}</dt>
            <dd class="mt-1 font-medium tabular-nums text-gray-950 dark:text-white" dir="ltr">{{ $delivery->created_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i:s') }}</dd>
        </div>
    </dl>

    @if (filled($delivery->response_excerpt))
        <div>
            <p class="mb-1.5 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('integrations.webhooks.deliveries.answer') }}</p>
            <pre class="max-h-32 overflow-auto rounded-2xl bg-gray-950 p-4 font-mono text-xs leading-relaxed text-white/80" dir="ltr">{{ $delivery->response_excerpt }}</pre>
        </div>
    @endif

    <div>
        <p class="mb-1.5 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('integrations.webhooks.deliveries.body') }}</p>
        <pre class="max-h-96 overflow-auto rounded-2xl bg-gray-950 p-4 font-mono text-xs leading-relaxed text-white/85" dir="ltr">{{ json_encode($delivery->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
</div>
