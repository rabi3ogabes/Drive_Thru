<?php

declare(strict_types=1);

namespace App\Domain\Themes\Actions;

use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Themes\Exceptions\ThemeRejected;
use App\Domain\Themes\Models\BrandFont;
use App\Domain\Themes\ThemeResolver;
use App\Domain\Themes\ThemeSpec;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Removes a brand font and its file, unless the saved theme still uses it.
 */
final class DeleteBrandFont
{
    public function handle(BrandFont $font, User $user): void
    {
        $restaurant = Restaurant::query()->findOrFail($font->restaurant_id);
        $fonts = ThemeResolver::for($restaurant)->fonts;

        foreach (ThemeSpec::FONT_ROLES as $role) {
            if ($fonts[$role] === $font->key()) {
                throw new ThemeRejected('font_in_use', ['role' => __("themes.fonts.{$role}")]);
            }
        }

        Storage::disk('public')->delete($font->path);
        $font->delete();

        activity()->performedOn($font)->causedBy($user)->event('brand_font.deleted')
            ->withProperties(['restaurant_id' => $restaurant->id, 'name' => $font->name])->log('Brand font deleted');
    }
}
