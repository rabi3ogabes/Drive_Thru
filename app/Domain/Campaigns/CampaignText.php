<?php

declare(strict_types=1);

namespace App\Domain\Campaigns;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Tenancy\Models\Restaurant;

/**
 * What a guest receives, in their language: the restaurant's name, the
 * offer, a link to order (tagged with the campaign, for attribution) and the
 * "stop offers" link. WhatsApp gets the same pieces as template parameters.
 */
final readonly class CampaignText
{
    public function __construct(
        public string $restaurant,
        public string $offer,
        public string $orderUrl,
        public string $stopUrl,
        public string $locale,
    ) {}

    public static function make(Campaign $campaign, Restaurant $restaurant, string $locale, string $stopUrl): self
    {
        $locale = in_array($locale, ['ar', 'en'], true) ? $locale : 'ar';
        $offer = trim((string) $campaign->getTranslation('message', $locale, true));

        return new self(
            restaurant: (string) $restaurant->getTranslation('name', $locale, true),
            offer: $offer,
            orderUrl: route('public.restaurant', $restaurant).'?'.http_build_query(['campaign' => $campaign->link_code, 'channel' => $campaign->channel->value]),
            stopUrl: $stopUrl,
            locale: $locale,
        );
    }

    /** The SMS (and the WhatsApp fallback). */
    public function sms(): string
    {
        return $this->restaurant.': '.$this->offer."\n"
            .$this->orderUrl."\n"
            .__('campaigns.message.stop', ['url' => $this->stopUrl], $this->locale);
    }

    /**
     * Body parameters of the approved WhatsApp marketing template.
     *
     * @return list<string>
     */
    public function whatsappParameters(): array
    {
        return [$this->restaurant, $this->offer, $this->orderUrl, $this->stopUrl];
    }

    /**
     * @return array{title: string, body: string, url: string, tag: string}
     */
    public function push(string $tag): array
    {
        return ['title' => $this->restaurant, 'body' => $this->offer, 'url' => $this->orderUrl, 'tag' => $tag];
    }
}
