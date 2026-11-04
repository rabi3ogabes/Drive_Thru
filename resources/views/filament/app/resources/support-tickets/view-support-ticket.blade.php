@php
    use App\Domain\Support\Models\SupportTicket;
    use App\Filament\App\Resources\Customers\CustomerResource;
    use App\Support\Bidi;
    use App\Support\Money;

    $tz = $restaurant->timezone;
    $order = $ticket->order;
    $currency = $order->currency;
    $statusColor = match ($ticket->status) {
        SupportTicket::OPEN => 'warning',
        SupportTicket::ANSWERED => 'info',
        default => 'success',
    };
@endphp
<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- The conversation --}}
        <section class="min-w-0 rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 lg:col-span-2 dark:bg-gray-900 dark:ring-white/10" aria-labelledby="conversation-title">
            <header class="flex flex-wrap items-center gap-3 border-b border-gray-950/5 px-5 py-4 dark:border-white/10">
                <x-filament::icon :icon="$ticket->category->icon()" class="size-5 text-gray-400" />
                <h2 id="conversation-title" class="text-base font-semibold text-gray-950 dark:text-white">{{ $ticket->category->getLabel() }}</h2>
                <x-filament::badge :color="$statusColor">{{ __('support.admin.status.'.$ticket->status) }}</x-filament::badge>
                @if ($ticket->isOverdue())
                    <x-filament::badge color="danger" icon="heroicon-m-clock">{{ __('support.admin.overdue') }}</x-filament::badge>
                @endif
                <span class="ms-auto text-sm text-gray-500 dark:text-gray-400">{{ __('support.admin.opened_at', ['date' => $ticket->created_at->setTimezone($tz)->format('d/m/Y H:i')]) }}</span>
            </header>

            <ol class="space-y-4 px-5 py-5">
                @foreach ($ticket->messages as $message)
                    <li @class(['flex', 'justify-start' => $message->fromGuest(), 'justify-end' => ! $message->fromGuest()])>
                        <div @class(['max-w-[85%] rounded-2xl px-4 py-3 text-sm leading-relaxed',
                                     'rounded-ss-md bg-gray-100 text-gray-900 dark:bg-white/5 dark:text-gray-100' => $message->fromGuest(),
                                     'rounded-se-md bg-primary-600 text-white dark:bg-primary-500' => ! $message->fromGuest()])>
                            <p class="whitespace-pre-line break-words">{{ $message->body }}</p>
                            <p @class(['mt-1.5 flex flex-wrap items-center gap-x-1.5 text-xs', 'text-gray-500 dark:text-gray-400' => $message->fromGuest(), 'text-white/75' => ! $message->fromGuest()])>
                                <span>{{ $message->fromGuest() ? ($ticket->customer->name ?: __('support.admin.guest')) : ($message->user->name ?? __('support.admin.team')) }}</span>
                                <span aria-hidden="true">·</span>
                                <span dir="ltr">{{ $message->created_at->setTimezone($tz)->format('d/m H:i') }}</span>
                                @if (! $message->fromGuest())
                                    <span aria-hidden="true">·</span>
                                    <span>{{ $message->message_delivery_id ? __('support.admin.sent_to_phone') : __('support.admin.page_only') }}</span>
                                @endif
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>

            @if ($ticket->isOpen())
                <form wire:submit="sendReply" class="border-t border-gray-950/5 px-5 py-4 dark:border-white/10">
                    <label for="ticket-reply" class="text-sm font-medium text-gray-950 dark:text-white">{{ __('support.admin.reply_label') }}</label>
                    <textarea id="ticket-reply" wire:model="reply" rows="3" maxlength="{{ \App\Domain\Support\Models\TicketMessage::LENGTH }}" placeholder="{{ __('support.admin.reply_placeholder') }}"
                              class="mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-950 shadow-xs ring-1 ring-gray-950/10 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"></textarea>
                    @error('reply')<p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>@enderror
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('support.admin.reply_hint') }}</p>
                        <x-filament::button type="submit" icon="heroicon-m-paper-airplane" wire:target="sendReply">{{ __('support.admin.send') }}</x-filament::button>
                    </div>
                </form>
            @else
                <p class="flex items-center gap-2 border-t border-gray-950/5 px-5 py-4 text-sm text-gray-600 dark:border-white/10 dark:text-gray-300">
                    <x-filament::icon icon="heroicon-m-check-circle" class="size-5 text-success-500" />
                    {{ __('support.admin.resolved_by', ['name' => $ticket->resolver->name ?? '—', 'date' => $ticket->resolved_at?->setTimezone($tz)->format('d/m/Y H:i')]) }}
                </p>
            @endif
        </section>

        <div class="grid min-w-0 content-start gap-6">
            {{-- How it was put right --}}
            @if ($ticket->outcome)
                <section class="rounded-xl bg-success-50 px-5 py-4 ring-1 ring-success-600/15 dark:bg-success-500/10 dark:ring-success-400/20">
                    <p class="text-sm font-semibold text-success-800 dark:text-success-300">{{ __('support.admin.outcome.'.$ticket->outcome) }}</p>
                    <p class="mt-0.5 text-sm text-success-700 dark:text-success-400">
                        @if ($ticket->outcome === 'refund' && $ticket->refund)
                            {{ Money::format($ticket->refund->amount_minor, $currency) }} · {{ __('support.admin.refund_status.'.$ticket->refund->status) }}
                        @elseif ($ticket->walletReward)
                            {{ $ticket->walletReward->reward->name }}
                        @endif
                    </p>
                </section>
            @endif

            {{-- The order --}}
            <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" aria-labelledby="order-title">
                <div class="flex items-baseline justify-between gap-3">
                    <h3 id="order-title" class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('support.admin.order', ['number' => $order->displayNumber()]) }}</h3>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $ticket->branch->name }}</span>
                </div>
                <ul class="mt-3 divide-y divide-gray-950/5 dark:divide-white/10">
                    @foreach ($order->items as $item)
                        @php($reported = in_array($item->id, $ticket->items, true))
                        <li class="flex items-center justify-between gap-3 py-2 text-sm">
                            <span @class(['min-w-0 truncate', 'font-semibold text-danger-700 dark:text-danger-400' => $reported, 'text-gray-700 dark:text-gray-200' => ! $reported])>
                                <span class="tabular-nums">{{ $item->quantity }}×</span> {{ $item->localName() }}
                                @if ($reported)<span class="ms-1 text-xs font-medium">· {{ __('support.admin.reported') }}</span>@endif
                            </span>
                            <span class="shrink-0 tabular-nums text-gray-500 dark:text-gray-400" dir="ltr">{{ Money::format($item->line_total_minor, $currency) }}</span>
                        </li>
                    @endforeach
                </ul>
                <dl class="mt-3 space-y-1.5 border-t border-gray-950/5 pt-3 text-sm dark:border-white/10">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">{{ __('support.admin.total') }}</dt><dd class="font-semibold tabular-nums text-gray-950 dark:text-white" dir="ltr">{{ Money::format($order->total_minor, $currency) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">{{ __('support.admin.payment') }}</dt><dd class="text-gray-700 dark:text-gray-200">{{ $order->payment_method->getLabel() }}</dd></div>
                    @if ($order->picked_up_at)
                        <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">{{ __('support.admin.picked_up') }}</dt><dd class="text-gray-700 dark:text-gray-200" dir="ltr">{{ $order->picked_up_at->setTimezone($tz)->format('d/m H:i') }}</dd></div>
                    @endif
                    @if ($order->vehicle)
                        <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">{{ __('support.admin.car') }}</dt><dd class="truncate text-gray-700 dark:text-gray-200"><bdi>{{ $order->vehicle['label'] ?? $order->vehicle['plate'] ?? '' }}</bdi></dd></div>
                    @endif
                </dl>
            </section>

            {{-- The guest --}}
            <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" aria-labelledby="guest-title">
                <h3 id="guest-title" class="text-sm font-semibold text-gray-950 dark:text-white">{{ $ticket->customer->name ?: __('support.admin.guest') }}</h3>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400" dir="ltr">{{ $contacts ? $ticket->customer->phone : $ticket->customer->maskedPhone() }}</p>
                @if (CustomerResource::canAccess())
                    <x-filament::link :href="CustomerResource::getUrl('view', ['record' => $ticket->customer])" icon="heroicon-m-arrow-right" icon-position="after" class="mt-3 text-sm">{{ __('support.admin.open_profile') }}</x-filament::link>
                @endif
            </section>
        </div>
    </div>
</x-filament-panels::page>
