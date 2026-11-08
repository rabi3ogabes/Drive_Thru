<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Billing\Actions\StartSubscription;
use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\PlanCatalog;
use App\Domain\Customers\Models\Customer;
use App\Domain\Loyalty\Birthdays\BirthdaySettings;
use App\Domain\Loyalty\Models\Reward;
use App\Domain\Loyalty\Models\StampCard;
use App\Domain\Loyalty\Models\StampProgram;
use App\Domain\Menu\Models\Product;
use App\Domain\Services\Enums\Service;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Models\RestaurantService;
use App\Domain\Tenancy\Actions\CreateRestaurant;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use App\Support\Phone;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCrmSeeder;
use Database\Seeders\DemoFeedbackSeeder;
use Database\Seeders\DemoGrowthSeeder;
use Database\Seeders\DemoMenuSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;

/**
 * A restaurant full of sample data for one real owner to try every feature
 * (also on the live site): all services and features on, two branches, the
 * demo menu, guests with months of orders, a campaign, a QR sticker,
 * invites, ratings, support tickets, birthday gifts and badges. The owner's
 * own phone is a guest too, holding the support tickets and a fresh order
 * to rate. The made-up demo guests never receive anything: they don't
 * accept offers and birthday messages are off.
 */
final class CreateDemoRestaurant extends Command
{
    protected $signature = 'demo:restaurant
        {--name= : Owner name}
        {--email= : Owner email (the sign-in)}
        {--phone= : Owner mobile; also a guest account for the guest pages}
        {--slug=rabii : Restaurant address, /{slug}}
        {--restaurant= : Restaurant name in English (default: "{first name} Drive-Thru")}
        {--password= : Owner password (asked twice when left out)}
        {--force : Skip the confirmation}';

    protected $description = 'Create an owner and a demo restaurant with sample data for every feature';

    public function handle(): int
    {
        $name = trim((string) $this->option('name'));
        $email = mb_strtolower(trim((string) $this->option('email')));
        $phone = Phone::normalize((string) $this->option('phone'));
        $slug = Str::slug((string) $this->option('slug'));

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'phone' => $phone, 'slug' => $slug],
            ['name' => ['required', 'max:120'], 'email' => ['required', 'email', 'max:190'], 'phone' => ['required'], 'slug' => ['required', 'max:40']],
            ['phone.required' => 'That mobile number is not valid.'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        if (Restaurant::query()->where('slug', $slug)->exists()) {
            $this->components->error("A restaurant already uses /{$slug}. Pick another with --slug.");

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: $this->askPasswordTwice());

        if ($password === '') {
            $this->components->error('The two passwords do not match. Nothing was changed.');

            return self::FAILURE;
        }

        $strength = Validator::make(['password' => $password], ['password' => [Password::defaults()]]);

        if ($strength->fails()) {
            $this->components->error((string) $strength->errors()->first('password'));

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Create /{$slug} with sample data for {$email}?", true)) {
            return self::FAILURE;
        }

        // Sample data must not send emails or bell notifications while it is written.
        Notification::fake();

        $restaurant = DB::transaction(fn (): Restaurant => $this->restaurant($name, $email, (string) $phone, $slug, $password));

        $this->fill($restaurant, (string) $phone, $name);

        $this->components->info("Ready: {$restaurant->getTranslation('name', 'en')}");
        $this->components->twoColumnDetail('Workspace', url('/app/'.$restaurant->slug));
        $this->components->twoColumnDetail('Sign in', $email.' (the password you chose)');
        $this->components->twoColumnDetail('Guest pages', route('public.restaurant', $restaurant));
        $this->components->twoColumnDetail('Guest sign-in', Phone::format((string) $phone).' (a code is sent to it)');

        return self::SUCCESS;
    }

    private function restaurant(string $name, string $email, string $phone, string $slug, string $password): Restaurant
    {
        app(PlanCatalog::class)->install();

        $owner = User::query()->firstOrNew(['email' => $email]);
        $owner->forceFill([
            'name' => $name,
            'password' => $password,
            'phone' => $phone,
            'locale' => $owner->locale ?? 'ar',
            'email_verified_at' => $owner->email_verified_at ?? now(),
            'is_active' => true,
        ])->save();

        $first = Str::of($name)->explode(' ')->first() ?: $name;
        $english = (string) ($this->option('restaurant') ?: $first.' Drive-Thru');
        $restaurant = app(CreateRestaurant::class)->handle(['en' => $english, 'ar' => 'درايف ثرو '.$first], owner: $owner, slug: $slug);

        $restaurant->forceFill([
            'accent_color' => '#0f3b3a',
            'contact_email' => $email,
            'contact_phone' => Phone::format($phone),
            'legal_name' => $english.' W.L.L.',
            // Every paid feature, so nothing is locked while testing.
            'feature_overrides' => collect(PlanFeature::cases())->mapWithKeys(fn (PlanFeature $f): array => [$f->value => true])->all(),
        ])->save();

        app(StartSubscription::class)->handle($restaurant, Plan::query()->where('key', 'growth')->firstOrFail(), 2, 30);

        foreach (Service::cases() as $service) {
            RestaurantService::withoutTenancy()->updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'service' => $service->value],
                ['status' => ServiceStatus::Active, 'unit_price_minor' => 0, 'started_at' => now()],
            );
        }

        app(DatabaseSeeder::class)->branches($restaurant, [
            ['Lusail Marina', 'لوسيل مارينا', 'Lusail', 'لوسيل', [[6, 24]], 9],
            ['West Bay', 'الخليج الغربي', 'Doha', 'الدوحة', [[6, 24]], 11],
        ]);

        return $restaurant->fresh() ?? $restaurant;
    }

    private function fill(Restaurant $restaurant, string $phone, string $name): void
    {
        app(DemoMenuSeeder::class)->run($restaurant);
        app(DemoGrowthSeeder::class)->run($restaurant);
        app(DemoCrmSeeder::class)->run($restaurant->fresh() ?? $restaurant);

        // The owner as a guest: the latest orders, so the sample support tickets land on them.
        $me = app(DemoCrmSeeder::class)->ownGuest($restaurant, $phone, $name, [96, 60, 30, 20, 10]);
        app(DemoFeedbackSeeder::class)->run($restaurant->fresh() ?? $restaurant);

        // And one order picked up an hour ago, still waiting for a rating.
        app(DemoCrmSeeder::class)->ownGuest($restaurant, $phone, $name, [1]);

        $this->stampCard($restaurant, $me);

        // Nothing may reach the made-up numbers of the other demo guests.
        Customer::withoutTenancy()->where('restaurant_id', $restaurant->id)->whereKeyNot($me->id)
            ->update(['offers_consent_at' => null, 'notification_prefs' => json_encode(['offers' => false])]);

        $restaurant = $restaurant->fresh() ?? $restaurant;
        BirthdaySettings::save($restaurant, ['send_message' => false] + BirthdaySettings::for($restaurant)->toArray());
    }

    /** A drinks card (9 stamps, then a free drink) with 6 stamps already on the owner's guest card. */
    private function stampCard(Restaurant $restaurant, Customer $me): void
    {
        $drink = Product::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('is_available', true)->orderBy('price_minor')->first();
        $reward = Reward::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('name->en', 'Free drink')->first();

        if ($drink === null || $reward === null) {
            return;
        }

        $program = StampProgram::withoutTenancy()->create([
            'restaurant_id' => $restaurant->id,
            'name' => ['en' => 'Drinks card', 'ar' => 'بطاقة المشروبات'],
            'target_type' => 'product',
            'target_id' => $drink->id,
            'stamps_needed' => 9,
            'reward_id' => $reward->id,
            'daily_cap' => 2,
        ]);

        StampCard::withoutTenancy()->create([
            'restaurant_id' => $restaurant->id,
            'customer_id' => $me->id,
            'stamp_program_id' => $program->id,
            'stamps' => 6,
            'last_stamped_at' => now()->subDay(),
        ]);
    }

    private function askPasswordTwice(): string
    {
        $password = password('Password', required: true, hint: 'At least 10 characters, upper and lower case letters and a number.');

        return password('Password again', required: true) === $password ? $password : '';
    }
}
