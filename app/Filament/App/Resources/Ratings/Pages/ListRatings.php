<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Ratings\Pages;

use App\Domain\Feedback\RatingStats;
use App\Filament\App\Resources\Ratings\RatingResource;
use App\Filament\App\Workspace;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/** The last 30 days at a glance (average, scores, tags, trend), then every rating. */
class ListRatings extends ListRecords
{
    protected static string $resource = RatingResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('feedback.admin.subheading');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.app.resources.ratings.summary')->viewData(function (): array {
                $now = CarbonImmutable::now(Workspace::restaurant()->timezone);

                return ['stats' => RatingStats::for(Workspace::restaurant()->id, Workspace::branchIds(), $now->subDays(29)->startOfDay(), $now)];
            }),
            EmbeddedTable::make(),
        ]);
    }
}
