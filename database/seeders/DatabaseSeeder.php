<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Access\Enums\PlatformPermission;
use App\Domain\Access\Models\PlatformRole;
use App\Domain\Access\Models\Position;
use App\Domain\Billing\Actions\MarkInvoicePaid;
use App\Domain\Billing\Actions\StartSubscription;
use App\Domain\Billing\Enums\PlanFeature;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\PlanCatalog;
use App\Domain\Branches\Actions\CreateBranch;
use App\Domain\Branches\Actions\CreateDevice;
use App\Domain\Branches\Actions\PairDevice;
use App\Domain\Branches\Enums\DeviceKind;
use App\Domain\Branches\Enums\LaneKind;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\BranchClosure;
use App\Domain\Branches\Models\BranchHour;
use App\Domain\Branches\Models\Device;
use App\Domain\Branches\Models\Lane;
use App\Domain\Branches\Models\Station;
use App\Domain\Growth\Enums\LeadSource;
use App\Domain\Growth\Enums\LeadStatus;
use App\Domain\Growth\Models\Lead;
use App\Domain\Platform\Enums\AnnouncementLevel;
use App\Domain\Platform\Models\Announcement;
use App\Domain\Staff\Actions\SetEmployeePin;
use App\Domain\Staff\Enums\EmployeeStatus;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Actions\CreateRestaurant;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

/**
 * Demo data so every screen looks alive on first open. All demo accounts use
 * config('app.demo_password'); seeding is refused in production.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('Demo seeding is disabled in production.');

            return;
        }

        // Demo data must not send real emails or bell notifications.
        Notification::fake();

        $password = (string) config('app.demo_password');

        app(PlanCatalog::class)->install();

        User::query()->updateOrCreate(['email' => 'admin@drivethru.test'], [
            'name' => 'Noor Al-Thani',
            'password' => $password,
            'locale' => 'en',
            'email_verified_at' => now(),
        ])->forceFill(['is_super_admin' => true, 'last_login_at' => now()])->save();

        $roles = $this->platformRoles();

        User::query()->updateOrCreate(['email' => 'support@drivethru.test'], [
            'name' => 'Lina Haddad',
            'password' => $password,
            'email_verified_at' => now(),
        ])->forceFill(['platform_role_id' => $roles['support']->id, 'last_login_at' => now()->subHours(3)])->save();

        User::query()->updateOrCreate(['email' => 'sales@drivethru.test'], [
            'name' => 'Yousef Al-Marri',
            'password' => $password,
            'email_verified_at' => now(),
        ])->forceFill(['platform_role_id' => $roles['sales']->id, 'last_login_at' => now()->subDay()])->save();

        $growth = Plan::query()->where('key', 'growth')->firstOrFail();
        $starter = Plan::query()->where('key', 'starter')->firstOrFail();

        $masar = $this->restaurant('masar', ['ar' => 'مسار', 'en' => 'Masar Grill'], 'owner@masar.test', 'Khalid Al-Mansoori', $growth, branches: 3, trialDays: 0, accent: '#0f3b3a');
        $this->branches($masar, [
            ['Lusail Marina', 'لوسيل مارينا', 'Lusail', 'لوسيل', [[7, 11], [16, 2]], 9],
            ['West Bay', 'الخليج الغربي', 'Doha', 'الدوحة', [[7, 23]], 11],
            ['Al Wakra', 'الوكرة', 'Al Wakra', 'الوكرة', [[11, 24]], 12],
        ]);
        $this->team($masar, $password);
        $this->screens($masar);
        app(DemoMenuSeeder::class)->run($masar);
        app(DemoGrowthSeeder::class)->run($masar);

        // A paid invoice so billing screens show real history.
        Invoice::withoutTenancy()->where('restaurant_id', $masar->id)->get()
            ->each(fn (Invoice $invoice) => app(MarkInvoicePaid::class)->handle($invoice, 'bank_transfer', 'TRX-'.$invoice->id));

        $sahra = $this->restaurant('sahra', ['ar' => 'صحراء برجر', 'en' => 'Sahra Burgers'], 'owner@sahra.test', 'Mariam Al-Kuwari', $starter, branches: 1, trialDays: 9, accent: '#775a37');
        $this->branches($sahra, [['The Pearl', 'اللؤلؤة', 'The Pearl', 'اللؤلؤة', [[10, 24]], 10]]);

        $karak = $this->restaurant('karak', ['ar' => 'بيت الكرك', 'en' => 'Karak House'], 'owner@karak.test', 'Hamad Al-Sulaiti', $starter, branches: 2, trialDays: 0, accent: '#5a6d59');
        $this->branches($karak, [
            ['Al Sadd', 'السد', 'Doha', 'الدوحة', [[6, 12], [15, 1]], 6],
            ['Al Rayyan', 'الريان', 'Al Rayyan', 'الريان', [[6, 24]], 6],
        ]);
        app(DemoCafeMenuSeeder::class)->run($karak);

        $this->leads();

        Announcement::query()->exists() || Announcement::query()->create([
            'title' => ['en' => 'Arrival alerts are coming', 'ar' => 'تنبيهات الوصول قادمة قريبًا'],
            'body' => ['en' => 'Customers will soon tell your team the moment they reach the drive-thru.', 'ar' => 'قريبًا سيخبر زبائنك فريقك لحظة وصولهم إلى الدرايف ثرو.'],
            'level' => AnnouncementLevel::Success,
        ]);

        $this->command?->info('Demo ready. Sign in with any *@drivethru.test or *@masar.test account and the demo password.');
    }

    /**
     * @return array{support: PlatformRole, sales: PlatformRole}
     */
    private function platformRoles(): array
    {
        $support = PlatformRole::query()->updateOrCreate(['key' => 'support'], [
            'name' => ['en' => 'Support', 'ar' => 'الدعم'],
            'description' => ['en' => 'Helps restaurants day to day.', 'ar' => 'يساعد المطاعم في عملها اليومي.'],
            'permissions' => [PlatformPermission::ViewClients, PlatformPermission::ImpersonateOwners, PlatformPermission::ViewSecurity],
            'is_system' => true,
        ]);

        $sales = PlatformRole::query()->updateOrCreate(['key' => 'sales'], [
            'name' => ['en' => 'Sales', 'ar' => 'المبيعات'],
            'description' => ['en' => 'Turns website requests into new clients.', 'ar' => 'يحوّل طلبات الموقع إلى عملاء جدد.'],
            'permissions' => [PlatformPermission::ViewClients, PlatformPermission::ManageClients, PlatformPermission::ManageLeads],
            'is_system' => true,
        ]);

        PlatformRole::query()->updateOrCreate(['key' => 'finance'], [
            'name' => ['en' => 'Finance', 'ar' => 'المالية'],
            'description' => ['en' => 'Invoices, payments and plans.', 'ar' => 'الفواتير والمدفوعات والباقات.'],
            'permissions' => [PlatformPermission::ViewClients, PlatformPermission::ManageBilling, PlatformPermission::ViewReports],
            'is_system' => true,
        ]);

        return ['support' => $support, 'sales' => $sales];
    }

    /**
     * @param  array{ar: string, en: string}  $name
     */
    private function restaurant(string $slug, array $name, string $ownerEmail, string $ownerName, Plan $plan, int $branches, int $trialDays, string $accent): Restaurant
    {
        $existing = Restaurant::query()->where('slug', $slug)->first();

        if ($existing !== null) {
            return $existing;
        }

        $owner = User::query()->updateOrCreate(['email' => $ownerEmail], [
            'name' => $ownerName,
            'password' => (string) config('app.demo_password'),
            'locale' => 'ar',
            'email_verified_at' => now(),
        ]);
        $owner->forceFill(['last_login_at' => now()->subHours(2)])->save();

        $restaurant = app(CreateRestaurant::class)->handle($name, owner: $owner, slug: $slug);
        $restaurant->forceFill([
            'accent_color' => $accent,
            'contact_email' => $ownerEmail,
            'contact_phone' => '+974 5'.random_int(100, 999).' '.random_int(1000, 9999),
            'legal_name' => $name['en'].' W.L.L.',
        ])->save();

        app(StartSubscription::class)->handle($restaurant, $plan, $branches, $trialDays);

        return $restaurant->fresh() ?? $restaurant;
    }

    /**
     * Branches with real-looking hours (split and past-midnight shifts), a
     * curbside spot next to the lane, and kitchen stations where the plan has them.
     *
     * @param  list<array{0: string, 1: string, 2: string, 3: string, 4: list<array{0: int, 1: int}>, 5: int}>  $branches
     */
    private function branches(Restaurant $restaurant, array $branches): void
    {
        if (Branch::withoutTenancy()->where('restaurant_id', $restaurant->id)->exists()) {
            return;
        }

        $stations = $restaurant->hasFeature(PlanFeature::KitchenStations);

        foreach ($branches as $index => [$en, $ar, $cityEn, $cityAr, $shifts, $prep]) {
            $branch = app(CreateBranch::class)->handle($restaurant, [
                'name' => ['en' => $en, 'ar' => $ar],
                'city' => ['en' => $cityEn, 'ar' => $cityAr],
                'address' => ['en' => "{$en} drive-thru, next to the main roundabout", 'ar' => "درايف ثرو {$ar}، بجوار الدوار الرئيسي"],
                'phones' => ['+974 4'.random_int(100, 999).' '.random_int(1000, 9999)],
                'latitude' => 25.28 + $index * 0.05,
                'longitude' => 51.52 + $index * 0.03,
                'avg_prep_minutes' => $prep,
                'capacity_per_slot' => 12,
            ]);

            foreach (range(0, 6) as $weekday) {
                foreach ($shifts as [$opens, $closes]) {
                    BranchHour::withoutTenancy()->create([
                        'restaurant_id' => $restaurant->id,
                        'branch_id' => $branch->id,
                        'weekday' => $weekday,
                        'opens_at' => sprintf('%02d:00', $opens),
                        'closes_at' => sprintf('%02d:00', $closes % 24),
                    ]);
                }
            }

            Lane::withoutTenancy()->create([
                'restaurant_id' => $restaurant->id,
                'branch_id' => $branch->id,
                'name' => ['en' => 'Curbside 1', 'ar' => 'الموقف 1'],
                'kind' => LaneKind::Curbside,
                'sort' => 1,
            ]);

            if ($stations) {
                foreach ([['Grill', 'الشواية'], ['Fryer', 'القلاية'], ['Drinks', 'المشروبات']] as $sort => [$stationEn, $stationAr]) {
                    Station::withoutTenancy()->create([
                        'restaurant_id' => $restaurant->id,
                        'branch_id' => $branch->id,
                        'name' => ['en' => $stationEn, 'ar' => $stationAr],
                        'sort' => $sort,
                    ]);
                }
            }
        }

        // A public holiday a few weeks out, so the closures UI has something to show.
        $first = Branch::withoutTenancy()->where('restaurant_id', $restaurant->id)->orderBy('sort')->first();
        if ($first !== null) {
            BranchClosure::withoutTenancy()->create([
                'restaurant_id' => $restaurant->id,
                'branch_id' => $first->id,
                'starts_on' => now()->addWeeks(3)->toDateString(),
                'ends_on' => now()->addWeeks(3)->addDay()->toDateString(),
                'reason' => ['en' => 'Public holiday', 'ar' => 'عطلة رسمية'],
            ]);
        }
    }

    private function team(Restaurant $restaurant, string $password): void
    {
        $codes = Branch::withoutTenancy()->where('restaurant_id', $restaurant->id)->pluck('id', 'code');

        foreach ([
            ['cashier@masar.test', 'Aisha Rahman', 'cashier', ['lusail-marina', 'west-bay'], '2580'],
            ['kitchen@masar.test', 'Omar Siddiqui', 'kitchen', ['west-bay'], '7391'],
            ['manager@masar.test', 'Fatima Al-Kuwari', 'branch_manager', ['lusail-marina'], null],
            ['gm@masar.test', 'Yara Haddad', 'general_manager', [], null],
        ] as [$email, $name, $positionKey, $branchCodes, $pin]) {
            $user = User::query()->updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => $password,
                'email_verified_at' => now(),
            ]);

            $employee = Employee::withoutTenancy()->updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'user_id' => $user->id],
                [
                    'position_id' => Position::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('key', $positionKey)->value('id'),
                    'all_branches' => $branchCodes === [],
                    'status' => EmployeeStatus::Active,
                    'joined_at' => now(),
                ],
            );

            $employee->branches()->sync(collect($branchCodes)->map(fn (string $code): int => (int) $codes[$code])->all());

            if ($pin !== null) {
                $employee->forceFill(['pin_hash' => SetEmployeePin::hash($restaurant->id, $pin)])->save();
            }
        }
    }

    /** One paired kitchen tablet (online), one TV waiting for its code. */
    private function screens(Restaurant $restaurant): void
    {
        if (Device::withoutTenancy()->where('restaurant_id', $restaurant->id)->exists()) {
            return;
        }

        $westBay = Branch::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('code', 'west-bay')->first();
        $lusail = Branch::withoutTenancy()->where('restaurant_id', $restaurant->id)->where('code', 'lusail-marina')->first();

        if ($westBay === null || $lusail === null) {
            return;
        }

        $tablet = app(CreateDevice::class)->handle($westBay, DeviceKind::Kitchen, 'Grill tablet', Station::withoutTenancy()->where('branch_id', $westBay->id)->value('id'));
        app(PairDevice::class)->handle($tablet->code, '127.0.0.1', 'Demo tablet');

        app(CreateDevice::class)->handle($lusail, DeviceKind::Tv, 'Lobby order TV');
    }

    private function leads(): void
    {
        if (Lead::query()->exists()) {
            return;
        }

        $sales = User::query()->where('email', 'sales@drivethru.test')->value('id');
        $growth = Plan::query()->where('key', 'growth')->value('id');

        foreach ([
            ['Al Wakra Shawarma', 'Saeed Al-Hajri', 'saeed@wakra.test', 4, LeadStatus::New, null, 2],
            ['Corniche Coffee', 'Dana Yousef', 'dana@corniche.test', 2, LeadStatus::New, null, 30],
            ['Lusail Bites', 'Faisal Al-Naimi', 'faisal@lusail.test', 6, LeadStatus::Contacted, $sales, 26],
            ['Pearl Juice Bar', 'Reem Al-Ansari', 'reem@pearl.test', 1, LeadStatus::DemoBooked, $sales, 72],
            ['Souq Grill', 'Tariq Mahmoud', 'tariq@souq.test', 3, LeadStatus::Lost, $sales, 240],
        ] as [$restaurant, $contact, $email, $branches, $status, $assignee, $hoursAgo]) {
            $lead = Lead::query()->create([
                'restaurant_name' => $restaurant,
                'contact_name' => $contact,
                'email' => $email,
                'phone' => '+974 5'.random_int(100, 999).' '.random_int(1000, 9999),
                'branches_count' => $branches,
                'plan_id' => $branches > 2 ? $growth : null,
                'message' => 'We run a busy drive-thru and want customers to order ahead.',
                'locale' => 'ar',
                'source' => LeadSource::Website,
                'status' => $status,
                'assigned_to' => $assignee,
                'lost_reason' => $status === LeadStatus::Lost ? 'Not ready this year' : null,
                'follow_up_at' => $status === LeadStatus::Contacted ? now()->subHours(2) : null,
                'contacted_at' => $status !== LeadStatus::New ? now()->subHours($hoursAgo - 1) : null,
            ]);
            $lead->forceFill(['created_at' => now()->subHours($hoursAgo)])->saveQuietly();
        }
    }
}
