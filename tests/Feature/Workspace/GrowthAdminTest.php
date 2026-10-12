<?php

use App\Domain\Loyalty\Enums\LedgerType;
use App\Domain\Loyalty\Enums\RewardType;
use App\Domain\Loyalty\Models\LedgerEntry;
use App\Domain\Loyalty\Models\LoyaltyProgram;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Models\Tier;
use App\Domain\Menu\Models\Product;
use App\Domain\Promotions\Actions\GenerateCoupons;
use App\Domain\Promotions\Enums\PromotionType;
use App\Domain\Promotions\Models\Coupon;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Tenancy\TenantContext;
use App\Filament\App\Pages\LoyaltySettings;
use App\Filament\App\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\App\Resources\Promotions\Pages\EditPromotion;
use App\Filament\App\Resources\Promotions\Pages\ListPromotions;
use App\Filament\App\Resources\Promotions\RelationManagers\CouponsRelationManager;
use App\Filament\App\Resources\Rewards\Pages\ManageRewards;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

function growthWorkspace(?User $user = null, bool $features = true): array
{
    $restaurant = restaurant(slug: 'masar');
    $restaurant->forceFill(['feature_overrides' => ['loyalty' => $features, 'promotions' => $features]])->save();
    $user ??= ownerOf($restaurant);
    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($restaurant);
    app(TenantContext::class)->set($restaurant);

    return [$restaurant->fresh(), $user];
}

it('keeps the program off until every value is set, then saves tiers and boosts', function () {
    [$restaurant] = growthWorkspace();
    $product = Product::factory()->create(['restaurant_id' => $restaurant->id]);

    Livewire::test(LoyaltySettings::class)
        ->fillForm(['is_enabled' => true, 'earn_points_per_unit' => null])
        ->call('save')
        ->assertHasFormErrors(['earn_points_per_unit' => 'required', 'redeem_points' => 'required']);

    Livewire::test(LoyaltySettings::class)
        ->fillForm([
            'is_enabled' => true, 'earn_points_per_unit' => 2, 'redeem_points' => 100, 'redeem_value' => '1.5',
            'max_redeem_percent' => 40, 'min_redeem_points' => 200, 'expiry_months' => 12, 'tier_window_months' => 12,
            'tiers' => [['name' => ['en' => 'Silver', 'ar' => 'فضي'], 'threshold_points' => 500, 'multiplier' => 1.25, 'benefits' => ['en' => '', 'ar' => '']]],
            'rules' => [['scope' => 'product', 'scope_id' => $product->id, 'multiplier' => 2]],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $program = LoyaltyProgram::query()->sole();
    expect($program->isActive())->toBeTrue()->and($program->redeem_value_minor)->toBe(150)
        ->and(Tier::query()->sole())->threshold_points->toBe(500)->earn_multiplier_bp->toBe(12500)
        ->and(Activity::query()->where('event', 'loyalty.program_saved')->exists())->toBeTrue();
});

it('adjusts a guest’s points by hand with a reason in the audit log', function () {
    [$restaurant] = growthWorkspace();
    $guest = loyaltyCustomer($restaurant);
    $guest->forceFill(['phone' => '+97455551234'])->save();

    Livewire::test(LoyaltySettings::class)
        ->callAction('adjustPoints', ['phone' => '5555 1234', 'points' => 150, 'reason' => 'Cold fries'])
        ->assertHasNoActionErrors();

    expect($guest->fresh()->points_balance)->toBe(150)
        ->and(LedgerEntry::query()->sole())->type->toBe(LedgerType::Adjust)->note->toBe('Cold fries')
        ->and(Activity::query()->where('event', 'loyalty.points_adjusted')->exists())->toBeTrue();
});

it('creates and archives rewards', function () {
    [$restaurant] = growthWorkspace();

    Livewire::test(ManageRewards::class)
        ->mountAction('addReward')
        ->fillForm(['name' => ['en' => '10% off', 'ar' => 'خصم 10%'], 'type' => RewardType::PercentOff->value, 'percent' => 10, 'points_cost' => 500, 'valid_days' => 30, 'is_active' => true])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $reward = Reward::query()->sole();
    expect($reward->value)->toBe(1000)->and($reward->points_cost)->toBe(500);

    Livewire::test(ManageRewards::class)->callTableAction('archive', $reward);
    expect($reward->fresh()->archived_at)->not->toBeNull();
});

it('creates each type of promotion', function (PromotionType $type, array $fields, array $expected) {
    [$restaurant] = growthWorkspace();
    $products = Product::factory()->count(2)->create(['restaurant_id' => $restaurant->id]);
    $fields = array_map(fn ($v) => $v === 'P1' ? $products[0]->id : ($v === 'PS' ? $products->pluck('id')->all() : $v), $fields);

    Livewire::test(CreatePromotion::class)
        ->fillForm(['name' => ['en' => 'Offer', 'ar' => 'عرض'], 'type' => $type->value, ...$fields])
        ->call('create')
        ->assertHasNoFormErrors();

    $promotion = Promotion::query()->sole();
    foreach ($expected as $attribute => $value) {
        expect($promotion->{$attribute})->toBe($value === 'P1' ? $products[0]->id : $value);
    }
})->with([
    'percent' => [PromotionType::Percent, ['percent' => 15, 'time_from' => '18:00', 'time_to' => '20:00', 'days' => [4, 5]], ['value_bp' => 1500, 'time_from' => '18:00', 'days' => [4, 5]]],
    'fixed' => [PromotionType::Fixed, ['amount' => '7.5', 'min_subtotal' => '30'], ['value_minor' => 750, 'min_subtotal_minor' => 3000]],
    'bogo' => [PromotionType::Bogo, ['buy_qty' => 2, 'get_qty' => 1], ['buy_qty' => 2, 'get_qty' => 1]],
    'free item' => [PromotionType::FreeItem, ['free_product_id' => 'P1', 'first_order' => true], ['free_product_id' => 'P1', 'first_order' => true]],
    'combo' => [PromotionType::ComboPrice, ['combo_product_ids' => 'PS', 'combo_price' => '20'], ['combo_price_minor' => 2000]],
    'bonus' => [PromotionType::BonusPoints, ['bonus_points' => 50, 'birthday' => true], ['value_minor' => 50, 'birthday' => true]],
]);

it('generates unique upper-case codes and lets the owner add their own', function () {
    [$restaurant] = growthWorkspace();
    $promotion = Promotion::query()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'C', 'ar' => 'C'], 'type' => PromotionType::Fixed, 'value_minor' => 500, 'requires_coupon' => true]);

    Livewire::test(CouponsRelationManager::class, ['ownerRecord' => $promotion, 'pageClass' => EditPromotion::class])
        ->callTableAction('generate', data: ['count' => 50, 'prefix' => 'eid', 'length' => 8, 'usage_limit' => 1, 'per_customer_limit' => 1])
        ->callTableAction('addCode', data: ['code' => 'welcome10', 'per_customer_limit' => 1]);

    $codes = Coupon::query()->pluck('code');
    expect($codes)->toHaveCount(51)->and($codes->unique())->toHaveCount(51)
        ->and($codes->every(fn (string $c) => $c === mb_strtoupper($c)))->toBeTrue()
        ->and($codes->filter(fn (string $c) => str_starts_with($c, 'EID') && strlen($c) === 11))->toHaveCount(50)
        ->and($codes)->toContain('WELCOME10')
        ->and(fn () => app(GenerateCoupons::class)->single($promotion, 'Welcome10', null, null, null, null))->toThrow(InvalidArgumentException::class);
});

it('refuses the pages to a cashier and when the plan lacks the features', function () {
    [$restaurant] = growthWorkspace();
    $cashier = employeeAt($restaurant, 'cashier', ['all_branches' => true]);
    $this->actingAs($cashier);

    $this->get(LoyaltySettings::getUrl(tenant: $restaurant))->assertForbidden();
    $this->get(ListPromotions::getUrl(tenant: $restaurant))->assertForbidden();
    $this->get(ManageRewards::getUrl(tenant: $restaurant))->assertForbidden();

    $this->actingAs(ownerOf($restaurant));
    $restaurant->forceFill(['feature_overrides' => ['loyalty' => false, 'promotions' => false]])->save();
    $this->get(LoyaltySettings::getUrl(tenant: $restaurant))->assertForbidden();
    $this->get(ListPromotions::getUrl(tenant: $restaurant))->assertForbidden();
});

it('never shows one restaurant’s promotions to another', function () {
    [$restaurant] = growthWorkspace();
    $other = restaurant(slug: 'other');
    $theirs = Promotion::withoutTenancy()->create(['restaurant_id' => $other->id, 'name' => ['en' => 'Theirs', 'ar' => 'Theirs'], 'type' => PromotionType::Fixed, 'value_minor' => 100]);
    $mine = Promotion::query()->create(['restaurant_id' => $restaurant->id, 'name' => ['en' => 'Mine', 'ar' => 'Mine'], 'type' => PromotionType::Fixed, 'value_minor' => 100]);

    Livewire::test(ListPromotions::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$theirs]);
    $this->get(EditPromotion::getUrl(['record' => $theirs], tenant: $restaurant))->assertNotFound();
});
