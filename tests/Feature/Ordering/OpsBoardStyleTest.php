<?php

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Access\Models\PlatformRole;
use App\Domain\Ordering\Enums\OpsBoardStyle;
use App\Filament\Admin\Resources\Restaurants\Pages\EditRestaurant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

it('defaults every restaurant to the classic overview', function () {
    expect(restaurant()->fresh()->ops_board)->toBe(OpsBoardStyle::Classic);
});

it('lets a platform admin switch a restaurant to the pass board, and logs it', function () {
    $restaurant = restaurant(slug: 'tea-time');
    $this->actingAs(User::factory()->superAdmin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(EditRestaurant::class, ['record' => $restaurant->getRouteKey()])
        ->assertFormFieldExists('ops_board')
        ->fillForm(['ops_board' => 'pass'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($restaurant->fresh()->ops_board)->toBe(OpsBoardStyle::Pass);

    $logged = Activity::query()->where('subject_type', $restaurant->getMorphClass())->where('subject_id', $restaurant->id)->latest('id')->first();
    expect($logged?->attribute_changes['attributes']['ops_board'] ?? null)->toBe('pass');
});

it('keeps the setting away from platform staff who cannot manage clients', function () {
    $restaurant = restaurant(slug: 'tea-time');
    $viewer = User::factory()->create();
    $viewer->forceFill(['platform_role_id' => PlatformRole::factory()->granting([PlatformPermission::ViewClients])->create()->id])->save();

    $this->actingAs($viewer)->get(EditRestaurant::getUrl(['record' => $restaurant->getRouteKey()], panel: 'admin'))->assertForbidden();
});

it('labels and describes both styles in both languages', function () {
    foreach (['ar', 'en'] as $locale) {
        app()->setLocale($locale);
        foreach (OpsBoardStyle::cases() as $style) {
            expect($style->getLabel())->not->toStartWith('ops.')
                ->and($style->getDescription())->not->toStartWith('ops.');
        }
    }
});
