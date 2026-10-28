<?php

declare(strict_types=1);

namespace App\Domain\Campaigns\Enums;

use App\Domain\Messaging\Channels\SmsAlaChannel;
use App\Domain\Messaging\Channels\WhatsAppCloudChannel;
use App\Support\Lang;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/** How a campaign reaches guests. WhatsApp falls back to SMS. */
enum CampaignChannel: string implements HasDescription, HasIcon, HasLabel
{
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';
    case Push = 'push';

    public function getLabel(): string
    {
        return Lang::text('campaigns.channels.'.$this->value);
    }

    public function getDescription(): string
    {
        return Lang::text('campaigns.channel_hints.'.$this->value);
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::WhatsApp => 'heroicon-o-chat-bubble-oval-left-ellipsis',
            self::Sms => 'heroicon-o-device-phone-mobile',
            self::Push => 'heroicon-o-bell-alert',
        };
    }

    /**
     * Messenger channels to try, in order (empty for push).
     *
     * @return list<string>
     */
    public function messengerChannels(): array
    {
        return match ($this) {
            self::WhatsApp => [WhatsAppCloudChannel::NAME, SmsAlaChannel::NAME],
            self::Sms => [SmsAlaChannel::NAME],
            self::Push => [],
        };
    }
}
