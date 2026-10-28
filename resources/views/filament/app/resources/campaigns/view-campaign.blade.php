@php
    use App\Domain\Campaigns\Enums\CampaignStatus;
    use App\Filament\App\Resources\Campaigns\CampaignResource;
    use App\Filament\App\Resources\Customers\CustomerResource;
    use App\Support\Bidi;
    use App\Support\Digits;
    use App\Support\Money;

    $status = $campaign->status;
    $started = in_array($status, [CampaignStatus::Sending, CampaignStatus::Sent], true) || ($status === CampaignStatus::Cancelled && $campaign->started_at !== null);
    $total = max(1, $campaign->recipients_count);
    $percent = (int) min(100, round($results['done'] / $total * 100));
    $conversion = $campaign->conversionPercent();
    $stats = [
        ['label' => __('campaigns.admin.results.recipients'), 'value' => Digits::number($campaign->recipients_count), 'hint' => null],
        ['label' => __('campaigns.admin.results.sent'), 'value' => Digits::number($campaign->sent_count), 'hint' => $campaign->sent_count > 0 ? __('campaigns.admin.results.delivered').' '.Digits::number($results['delivered']) : null],
        ['label' => __('campaigns.admin.results.orders'), 'value' => Digits::number($campaign->orders_count), 'hint' => $conversion !== null ? __('campaigns.admin.results.conversion').' '.Digits::number($conversion, 1).'%' : null],
        ['label' => __('campaigns.admin.results.revenue'), 'value' => Money::format($campaign->revenue_minor, $currency), 'hint' => null],
    ];
@endphp
<x-filament-panels::page>
    <div class="grid gap-6" @if ($status === CampaignStatus::Sending) wire:poll.10s.visible @endif>
        {{-- Status and results --}}
        <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 sm:p-6 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <x-filament::badge :color="$status->getColor()" :icon="$status->getIcon()">{{ $status->getLabel() }}</x-filament::badge>
                <span class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                    <x-filament::icon :icon="$campaign->channel->getIcon()" class="size-4" />
                    {{ $campaign->channel->getLabel() }}
                </span>
                @if ($status === CampaignStatus::Scheduled && $campaign->scheduled_at)
                    <span class="text-sm text-gray-500 dark:text-gray-400">· {{ __('campaigns.admin.results.scheduled_for', ['time' => Bidi::ltr($campaign->scheduled_at->timezone($tz)->format('Y-m-d H:i'))]) }}</span>
                @elseif ($status === CampaignStatus::Draft && CampaignResource::isForLater($campaign))
                    <span class="text-sm text-gray-500 dark:text-gray-400">· {{ Bidi::ltr($campaign->scheduled_at?->timezone($tz)->format('Y-m-d H:i')) }}</span>
                @endif
            </div>

            @if ($started)
                <dl class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    @foreach ($stats as $stat)
                        <div class="rounded-2xl bg-gray-50 p-4 dark:bg-white/5">
                            <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</dt>
                            <dd class="mt-1 whitespace-nowrap text-lg font-semibold tabular-nums text-gray-950 dark:text-white">{{ $stat['value'] }}</dd>
                            @if ($stat['hint'])
                                <dd class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{{ $stat['hint'] }}</dd>
                            @endif
                        </div>
                    @endforeach
                </dl>

                @if ($status === CampaignStatus::Sending)
                    <div class="mt-5">
                        <div class="flex items-baseline justify-between gap-3 text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ __('campaigns.admin.results.progress', ['done' => Digits::number($results['done']), 'total' => Digits::number($campaign->recipients_count)]) }}</span>
                            <span class="tabular-nums" dir="ltr">{{ $percent }}%</span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}">
                            <div class="h-full rounded-full bg-primary-500 transition-[inline-size] duration-700 ease-[cubic-bezier(0.32,0.72,0,1)]" style="inline-size: {{ max(2, $percent) }}%"></div>
                        </div>
                        @if ($hold)
                            <p class="mt-3 flex items-center gap-2 text-sm text-warning-700 dark:text-warning-400">
                                <x-filament::icon icon="heroicon-m-moon" class="size-4 shrink-0" />
                                {{ __('campaigns.admin.results.'.$hold, ['time' => Bidi::ltr($campaign->next_attempt_at->timezone($tz)->format('H:i'))]) }}
                            </p>
                        @endif
                    </div>
                @endif
                <p class="mt-4 text-xs text-gray-400 dark:text-gray-500">{{ __('campaigns.admin.results.conversion_hint') }}</p>
            @else
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ __('campaigns.admin.results.not_started') }}</p>
            @endif
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
            <div class="grid min-w-0 grid-cols-1 content-start gap-6 lg:col-span-3">
                {{-- The message, as guests receive it --}}
                <x-filament::section icon="heroicon-o-chat-bubble-left-right" :heading="__('campaigns.admin.preview.title')">
                    <div dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}" class="max-w-md rounded-2xl rounded-ss-md bg-gray-50 px-4 py-3 text-sm leading-relaxed text-gray-800 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-100 dark:ring-white/10">
                        <p class="whitespace-pre-line break-words [overflow-wrap:anywhere] [unicode-bidi:plaintext]">{{ $text }}</p>
                    </div>
                    @if ($campaign->reward)
                        <p class="mt-4 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <x-filament::icon icon="heroicon-m-gift" class="size-4 shrink-0 text-primary-500" />
                            {{ $campaign->reward->name }}
                        </p>
                    @endif
                </x-filament::section>

                {{-- Latest recipients --}}
                @if ($results['recent']->isNotEmpty())
                    <x-filament::section icon="heroicon-o-users" :heading="__('campaigns.admin.recent')">
                        <ul class="-my-3 divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($results['recent'] as $row)
                                <li class="flex items-center gap-3 py-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm text-gray-800 dark:text-gray-100">{{ $row->customer ? CustomerResource::displayName($row->customer) : '—' }}</p>
                                        <p class="text-xs text-gray-400 dark:text-gray-500">@if ($row->customer)<bdi dir="ltr">{{ $row->customer->maskedPhone() }}</bdi>@endif @if ($row->sent_at) · {{ Bidi::ltr($row->sent_at->timezone($tz)->format('m-d H:i')) }}@endif</p>
                                    </div>
                                    @if ($row->order_id)
                                        <x-filament::badge color="success" size="sm" icon="heroicon-m-shopping-bag">{{ __('campaigns.guest.ordered') }}</x-filament::badge>
                                    @elseif ($row->status === 'failed')
                                        <x-filament::badge color="danger" size="sm">{{ __('campaigns.admin.results.failed') }}</x-filament::badge>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </x-filament::section>
                @endif
            </div>

            <div class="grid min-w-0 grid-cols-1 content-start gap-6 lg:col-span-2">
                {{-- Audience --}}
                <x-filament::section icon="heroicon-o-user-group" :heading="__('campaigns.admin.sections.audience')">
                    <p class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ CampaignResource::audienceLabel($campaign) }}</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ trans_choice('campaigns.admin.audience.count', $audienceCount, ['count' => Digits::number($audienceCount)]) }}</p>
                </x-filament::section>

                {{-- Skipped, and why --}}
                @if ($results['skips'] !== [] || $campaign->failed_count > 0)
                    <x-filament::section icon="heroicon-o-information-circle" :heading="__('campaigns.admin.skips')">
                        <ul class="grid gap-2 text-sm">
                            @foreach ($results['skips'] as $reason => $count)
                                <li class="flex items-baseline justify-between gap-3">
                                    <span class="text-gray-600 dark:text-gray-300">{{ __('campaigns.skip_reasons.'.$reason) }}</span>
                                    <span class="tabular-nums text-gray-950 dark:text-white">{{ Digits::number($count) }}</span>
                                </li>
                            @endforeach
                            @if ($campaign->failed_count > 0)
                                <li class="flex items-baseline justify-between gap-3">
                                    <span class="text-danger-600 dark:text-danger-400">{{ __('campaigns.admin.results.failed') }}</span>
                                    <span class="tabular-nums text-gray-950 dark:text-white">{{ Digits::number($campaign->failed_count) }}</span>
                                </li>
                            @endif
                        </ul>
                    </x-filament::section>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
