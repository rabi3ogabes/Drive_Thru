<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MenuThemes\Pages;

use App\Domain\Themes\Actions\UploadThrottle;
use App\Domain\Themes\Exceptions\ThemeRejected;
use App\Domain\Themes\Models\MenuTheme;
use App\Domain\Themes\ThemeFile;
use App\Filament\Admin\Resources\MenuThemes\MenuThemeResource;
use App\Filament\Admin\Resources\MenuThemes\Tables\MenuThemesTable;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListMenuThemes extends ListRecords
{
    protected static string $resource = MenuThemeResource::class;

    public function getSubheading(): ?string
    {
        return __('themes.admin.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('uploadFile')
                ->label(__('themes.admin.actions.upload'))
                ->icon('heroicon-m-arrow-up-tray')
                ->color('gray')
                ->authorize('create')
                ->modalDescription(__('themes.admin.upload_body'))
                ->schema([
                    FileUpload::make('file')
                        ->label(__('themes.editor.file'))
                        ->acceptedFileTypes(['application/json', 'text/plain'])
                        ->maxSize(20)
                        ->storeFiles(false)
                        ->required(),
                ])
                ->action(function (array $data, Action $action): void {
                    $file = $data['file'] ?? null;

                    try {
                        $user = auth()->user();
                        if ($user instanceof User) {
                            UploadThrottle::hit($user);
                        }
                        if (! $file instanceof TemporaryUploadedFile) {
                            throw new ThemeRejected('not_theme');
                        }

                        $parsed = ThemeFile::parse((string) file_get_contents((string) $file->getRealPath()));
                    } catch (ThemeRejected $e) {
                        Notification::make()->danger()->title($e->translated())->send();
                        $action->halt();

                        return;
                    }

                    $base = Str::slug($parsed['name']['en']) ?: 'theme';
                    $key = Str::limit($base, 50, '');
                    for ($i = 2; MenuTheme::query()->where('key', $key)->exists(); $i++) {
                        $key = Str::limit($base, 50, '')."-{$i}";
                    }

                    $spec = $parsed['spec']->toArray();
                    unset($spec['cover']);

                    $theme = MenuTheme::query()->create([
                        'key' => $key,
                        'name' => $parsed['name'],
                        'description' => ['en' => '', 'ar' => ''],
                        'spec' => $spec,
                        'is_active' => false,
                        'sort' => (int) MenuTheme::query()->max('sort') + 1,
                    ]);
                    MenuThemesTable::audit($theme, 'menu_theme.created', 'Theme template uploaded from a file');

                    Notification::make()->success()->title(__('themes.admin.uploaded'))->body(implode(' ', [__('themes.admin.uploaded_body'), ...$parsed['notices']]))->send();
                }),
            CreateAction::make(),
        ];
    }
}
