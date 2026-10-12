<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Platform\Enums\AnnouncementAudience;
use App\Domain\Platform\Enums\AnnouncementLevel;
use App\Domain\Platform\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return [
            'title' => ['en' => 'Scheduled maintenance tonight', 'ar' => 'صيانة مجدولة الليلة'],
            'body' => ['en' => 'Ordering pauses 02:00–02:15.', 'ar' => 'تتوقف الطلبات من 02:00 إلى 02:15.'],
            'level' => AnnouncementLevel::Info,
            'audience' => AnnouncementAudience::Everyone,
            'is_active' => true,
        ];
    }
}
