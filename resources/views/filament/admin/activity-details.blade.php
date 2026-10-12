@php
    /** @var \Spatie\Activitylog\Models\Activity $activity */
    $properties = $activity->properties?->toArray() ?? [];
    $new = $properties['attributes'] ?? [];
    $old = $properties['old'] ?? [];
    $extra = collect($properties)->except(['attributes', 'old'])->all();
    $show = fn ($value) => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : (is_bool($value) ? ($value ? 'true' : 'false') : (string) ($value ?? '—'));
@endphp
<div class="space-y-4 text-sm">
    <dl class="grid grid-cols-2 gap-3 rounded-2xl bg-gray-50 p-4 dark:bg-white/5">
        <div>
            <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('audit.who') }}</dt>
            <dd class="font-medium text-gray-950 dark:text-white">{{ $activity->causer?->name ?? __('audit.system') }}</dd>
        </div>
        <div>
            <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('audit.when') }}</dt>
            <dd class="font-medium text-gray-950 dark:text-white" dir="ltr">{{ $activity->created_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i:s') }}</dd>
        </div>
    </dl>

    @if ($new !== [])
        <div class="overflow-hidden rounded-2xl ring-1 ring-gray-950/5 dark:ring-white/10">
            <table class="w-full text-start">
                <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2 text-start font-medium">{{ __('audit.field') }}</th>
                        <th class="px-4 py-2 text-start font-medium">{{ __('audit.before') }}</th>
                        <th class="px-4 py-2 text-start font-medium">{{ __('audit.after') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-950/5 dark:divide-white/5">
                    @foreach ($new as $field => $value)
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs text-gray-600 dark:text-gray-300">{{ $field }}</td>
                            <td class="px-4 py-2 text-gray-500 line-through decoration-gray-300 dark:text-gray-400"><pre class="whitespace-pre-wrap font-sans">{{ array_key_exists($field, $old) ? $show($old[$field]) : '—' }}</pre></td>
                            <td class="px-4 py-2 text-gray-950 dark:text-white"><pre class="whitespace-pre-wrap font-sans">{{ $show($value) }}</pre></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($extra !== [])
        <dl class="space-y-2 rounded-2xl bg-gray-50 p-4 dark:bg-white/5">
            @foreach ($extra as $key => $value)
                <div class="flex flex-wrap gap-x-3">
                    <dt class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $key }}</dt>
                    <dd class="text-gray-950 dark:text-white">{{ $show($value) }}</dd>
                </div>
            @endforeach
        </dl>
    @endif
</div>
