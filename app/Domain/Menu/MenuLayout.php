<?php

declare(strict_types=1);

namespace App\Domain\Menu;

use App\Domain\Tenancy\Models\Restaurant;

/**
 * How the guest menu looks: card style, image shape, what to show and
 * whether popular items lead each section. Stored on the restaurant.
 */
final readonly class MenuLayout
{
    public const CARD_STYLES = ['grid', 'list'];

    public const IMAGE_STYLES = ['square', 'wide', 'none'];

    public function __construct(
        public string $cardStyle = 'grid',
        public string $imageStyle = 'square',
        public bool $showCalories = true,
        public bool $showDescriptions = true,
        public bool $popularFirst = true,
        public bool $showHighlights = true,
    ) {}

    public static function for(Restaurant $restaurant): self
    {
        return self::fromArray($restaurant->menu_layout ?? []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $card = (string) ($data['card_style'] ?? 'grid');
        $image = (string) ($data['image_style'] ?? 'square');

        return new self(
            cardStyle: in_array($card, self::CARD_STYLES, true) ? $card : 'grid',
            imageStyle: in_array($image, self::IMAGE_STYLES, true) ? $image : 'square',
            showCalories: (bool) ($data['show_calories'] ?? true),
            showDescriptions: (bool) ($data['show_descriptions'] ?? true),
            popularFirst: (bool) ($data['popular_first'] ?? true),
            showHighlights: (bool) ($data['show_highlights'] ?? true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'card_style' => $this->cardStyle,
            'image_style' => $this->imageStyle,
            'show_calories' => $this->showCalories,
            'show_descriptions' => $this->showDescriptions,
            'popular_first' => $this->popularFirst,
            'show_highlights' => $this->showHighlights,
        ];
    }
}
