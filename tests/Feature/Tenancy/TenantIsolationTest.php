<?php

use App\Domain\Access\Models\Position;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Concerns\BelongsToRestaurant;
use App\Domain\Tenancy\Exceptions\TenancyViolation;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('scopes tenant models to the current restaurant', function () {
    $masar = restaurant(['en' => 'Masar', 'ar' => 'مسار']);
    $sahra = restaurant(['en' => 'Sahra', 'ar' => 'صحراء']);

    app(TenantContext::class)->set($masar);

    expect(Position::query()->pluck('restaurant_id')->unique()->all())->toBe([$masar->id])
        ->and(Position::query()->count())->toBe(7)
        ->and(Position::find(Position::withoutTenancy()->where('restaurant_id', $sahra->id)->value('id')))->toBeNull();
});

it('sees every restaurant only when tenancy is explicitly lifted', function () {
    restaurant(['en' => 'Masar', 'ar' => 'مسار']);
    $sahra = restaurant(['en' => 'Sahra', 'ar' => 'صحراء']);

    app(TenantContext::class)->set($sahra);

    expect(Position::withoutTenancy()->count())->toBe(14);
});

it('fills the restaurant automatically when creating inside a tenant', function () {
    $masar = restaurant();

    $position = app(TenantContext::class)->run($masar, fn () => Position::create([
        'name' => ['en' => 'Drive-thru host', 'ar' => 'مضيف الدرايف ثرو'],
        'permissions' => [],
    ]));

    expect($position->restaurant_id)->toBe($masar->id);
});

it('refuses to create tenant data without a restaurant', function () {
    Position::create(['name' => ['en' => 'Orphan', 'ar' => 'يتيم'], 'permissions' => []]);
})->throws(TenancyViolation::class);

it('refuses to move a record to another restaurant', function () {
    $masar = restaurant(['en' => 'Masar', 'ar' => 'مسار']);
    $sahra = restaurant(['en' => 'Sahra', 'ar' => 'صحراء']);

    $position = Position::withoutTenancy()->where('restaurant_id', $masar->id)->firstOrFail();
    $position->restaurant_id = $sahra->id;
    $position->save();
})->throws(TenancyViolation::class);

it('restores the previous tenant after running as another one', function () {
    $masar = restaurant(['en' => 'Masar', 'ar' => 'مسار']);
    $sahra = restaurant(['en' => 'Sahra', 'ar' => 'صحراء']);
    $context = app(TenantContext::class);
    $context->set($masar);

    $inside = $context->run($sahra, fn () => $context->id());

    expect($inside)->toBe($sahra->id)->and($context->id())->toBe($masar->id);
});

it('keeps employees of one restaurant invisible to another', function () {
    $masar = restaurant(['en' => 'Masar', 'ar' => 'مسار']);
    $sahra = restaurant(['en' => 'Sahra', 'ar' => 'صحراء']);
    employeeAt($masar, 'cashier');
    employeeAt($sahra, 'kitchen');

    app(TenantContext::class)->set($masar);

    expect(Employee::query()->count())->toBe(1)
        ->and(Employee::query()->first()->restaurant_id)->toBe($masar->id);
});

/*
 * Architecture guard: any model whose table has a restaurant_id column must
 * use BelongsToRestaurant. Adding a tenant table without isolation fails CI.
 */
it('isolates every model that owns a restaurant_id column', function () {
    $models = collect(File::allFiles(app_path()))
        ->filter(fn ($file) => $file->getExtension() === 'php')
        ->map(fn ($file) => 'App\\'.Str::of($file->getRelativePathname())->replace(['/', '.php'], ['\\', ''])->toString())
        ->filter(fn (string $class) => class_exists($class) && is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract());

    expect($models)->not->toBeEmpty();

    $offenders = $models
        ->filter(fn (string $class) => Schema::hasColumn((new $class)->getTable(), 'restaurant_id'))
        ->reject(fn (string $class) => in_array(BelongsToRestaurant::class, class_uses_recursive($class), true))
        ->values();

    expect($offenders->all())->toBe([]);
});
