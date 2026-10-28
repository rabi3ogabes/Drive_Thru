@php
    use App\Domain\Campaigns\Enums\CampaignChannel;
    use App\Support\Digits;
@endphp
{{-- What each guest receives, in their own language, updated as the owner types. --}}
<div class="rounded-2xl bg-gray-50 p-4 dark:bg-white/5">
    <p class="mb-3 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('campaigns.admin.preview.title') }}</p>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        @foreach (['ar' => 'rtl', 'en' => 'ltr'] as $locale => $dir)
            @php
                $text = $texts[$locale];
                $ucs = $text !== null && preg_match('/[^\x00-\x7F]/', $text) === 1;
                $length = $text === null ? 0 : mb_strlen($text);
                $parts = $length === 0 ? 0 : ($ucs ? ($length <= 70 ? 1 : (int) ceil($length / 67)) : ($length <= 160 ? 1 : (int) ceil($length / 153)));
            @endphp
            <div class="min-w-0">
                <p class="mb-1.5 text-[0.6875rem] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $locale === 'ar' ? 'العربية' : 'English' }}</p>
                @if ($text === null)
                    <p class="rounded-2xl border border-dashed border-gray-200 px-4 py-6 text-center text-sm text-gray-400 dark:border-white/10 dark:text-gray-500">{{ __('campaigns.admin.preview.empty') }}</p>
                @elseif ($channel === CampaignChannel::Push)
                    <div dir="{{ $dir }}" lang="{{ $locale }}" class="flex gap-3 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <x-public.restaurant-logo :restaurant="$restaurant" class="size-9 shrink-0" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $restaurant->getTranslation('name', $locale, true) }}</p>
                            <p class="mt-0.5 line-clamp-3 text-sm text-gray-600 dark:text-gray-300">{{ $text }}</p>
                        </div>
                    </div>
                @else
                    <div dir="{{ $dir }}" lang="{{ $locale }}" class="relative max-w-full rounded-2xl rounded-ss-md bg-white px-3.5 py-2.5 text-sm leading-relaxed text-gray-800 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-100 dark:ring-white/10">
                        <p class="whitespace-pre-line break-words [overflow-wrap:anywhere] [unicode-bidi:plaintext]">{{ $text }}</p>
                    </div>
                    @if ($channel === CampaignChannel::Sms || $channel === CampaignChannel::WhatsApp)
                        <p class="mt-1.5 text-xs tabular-nums text-gray-400 dark:text-gray-500">{{ trans_choice('campaigns.admin.preview.length', $parts, ['count' => Digits::number($length), 'parts' => Digits::number($parts)]) }}</p>
                    @endif
                @endif
            </div>
        @endforeach
    </div>
</div>
