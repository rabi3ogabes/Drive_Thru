<?php

declare(strict_types=1);

namespace App\Livewire\Workspace;

use App\Domain\Branches\Links;
use App\Domain\Branches\Models\Branch;
use App\Domain\Menu\MenuCache;
use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Themes\Actions\DeleteBrandFont;
use App\Domain\Themes\Actions\ProcessThemeCover;
use App\Domain\Themes\Actions\UploadBrandFont;
use App\Domain\Themes\Actions\UploadThrottle;
use App\Domain\Themes\Contrast;
use App\Domain\Themes\Exceptions\ThemeRejected;
use App\Domain\Themes\FontCatalog;
use App\Domain\Themes\Models\BrandFont;
use App\Domain\Themes\Models\MenuTheme;
use App\Domain\Themes\ThemeFile;
use App\Domain\Themes\ThemePreview;
use App\Domain\Themes\ThemeResolver;
use App\Domain\Themes\ThemeSpec;
use App\Domain\Themes\ThemeTokens;
use App\Filament\App\Pages\MenuDesign;
use App\Filament\App\Workspace;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Theme tab of Menu design: pick a template, change its colours,
 * fonts, corners, logo and cover, watch the real menu update in a phone
 * preview, then save. Theme files can be exported and imported here.
 *
 * Nothing reaches guests until "Save theme"; the preview is a private draft.
 *
 * @property-read Restaurant $restaurant
 * @property-read Collection<int, MenuTheme> $templates
 * @property-read Collection<int, BrandFont> $brandFonts
 * @property-read array{ok: bool, errors: list<array{key: string, ratio: float}>, warnings: list<array{key: string, ratio: float}>}|null $contrast
 */
class ThemeEditor extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $restaurantId;

    public string $template = '';

    /** @var array<string, mixed> The theme being edited (ThemeSpec as an array). */
    public array $draft = [];

    /** @var array<string, array{latin: string, arabic: string, brand: ?int}> */
    public array $fontChoice = [];

    public ?string $previewToken = null;

    public string $previewUrl = '';

    public bool $dirty = false;

    public ?string $pendingTemplate = null;

    /** @var array{spec: array<string, mixed>, name: array{en: string, ar: string}, base: string, notices: list<string>}|null */
    public ?array $pendingImport = null;

    public ?string $error = null;

    public ?TemporaryUploadedFile $importUpload = null;

    public ?TemporaryUploadedFile $fontUpload = null;

    public string $fontName = '';

    public string $fontScripts = 'both';

    public ?TemporaryUploadedFile $coverUpload = null;

    public ?TemporaryUploadedFile $logoUpload = null;

    public function mount(): void
    {
        abort_unless(MenuDesign::canAccess(), 403);

        $this->restaurantId = Workspace::restaurant()->id;
        $this->load(ThemeResolver::template($this->restaurant)->key, ThemeResolver::for($this->restaurant));
        $this->dirty = false;
    }

    #[Computed]
    public function restaurant(): Restaurant
    {
        return Restaurant::query()->findOrFail($this->restaurantId);
    }

    /** @return Collection<int, MenuTheme> */
    #[Computed]
    public function templates(): Collection
    {
        return MenuTheme::query()->where(fn ($query) => $query->where('is_active', true)->orWhere('key', $this->template))->orderBy('sort')->get();
    }

    /** @return Collection<int, BrandFont> */
    #[Computed]
    public function brandFonts(): Collection
    {
        return BrandFont::withoutTenancy()->where('restaurant_id', $this->restaurantId)->orderBy('name')->get();
    }

    /** @return array{ok: bool, errors: list<array{key: string, ratio: float}>, warnings: list<array{key: string, ratio: float}>}|null */
    #[Computed]
    public function contrast(): ?array
    {
        $spec = $this->spec();

        return $spec === null ? null : Contrast::check($spec);
    }

    /**
     * The three readability checks, measured on the draft, for the contrast badges.
     *
     * @return array<string, array{ratio: float, needs: float}>
     */
    #[Computed]
    public function ratios(): array
    {
        $spec = $this->spec();

        if ($spec === null) {
            return [];
        }

        $colors = $spec->colors;

        return [
            'text_on_background' => ['ratio' => round(Contrast::ratio($colors['text'], $colors['background']), 1), 'needs' => Contrast::TEXT],
            'muted_on_background' => ['ratio' => round(Contrast::ratio(ThemeTokens::muted($spec), $colors['background']), 1), 'needs' => Contrast::MUTED],
            'button_text' => ['ratio' => round(Contrast::ratio(ThemeTokens::onBrand($spec), $colors['brand']), 1), 'needs' => Contrast::TEXT],
        ];
    }

    public function useTemplate(string $key, bool $confirmed = false): void
    {
        $this->authorizeTheme();
        $template = $this->templates->firstWhere('key', $key) ?? abort(404);

        if (! $confirmed && $key !== $this->template && ($this->dirty || ($this->restaurant->menu_theme['overrides'] ?? []) !== [])) {
            $this->pendingTemplate = $key;
            $this->dispatch('open-modal', id: 'theme-replace');

            return;
        }

        $this->pendingTemplate = null;
        $this->load($template->key, $template->spec()->with(['layout' => $this->draft['layout'] ?? [], 'cover' => $this->restaurant->cover_path], $this->restaurant));
        $this->dirty = true;
        $this->dispatch('close-modal', id: 'theme-replace');
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'fontChoice')) {
            foreach (ThemeSpec::FONT_ROLES as $role) {
                $brand = $this->fontChoice[$role]['brand'] ?? null;
                $brand = filled($brand) ? (int) $brand : null;
                $latin = (string) ($this->fontChoice[$role]['latin'] ?? '');
                $arabic = (string) ($this->fontChoice[$role]['arabic'] ?? '');

                $this->fontChoice[$role] = ['latin' => $latin, 'arabic' => $arabic, 'brand' => $brand];
                $this->draft['fonts'][$role] = $brand !== null ? "brand:{$brand}" : "{$latin}+{$arabic}";
            }
        }

        if (str_starts_with($property, 'draft') || str_starts_with($property, 'fontChoice')) {
            $this->dirty = true;
            $this->refreshPreview();
        }
    }

    public function save(): void
    {
        $this->authorizeTheme();
        $spec = $this->spec();
        $check = $spec !== null ? Contrast::check($spec) : null;

        if ($spec === null || $check === null || ! $check['ok']) {
            $this->error ??= (new ThemeRejected('unreadable', ['ratio' => $check['errors'][0]['ratio'] ?? 0.0]))->translated();

            return;
        }

        ThemeResolver::save($this->restaurant, $this->template, $spec);
        $this->audit('theme.saved', 'Theme saved');
        $this->dirty = false;
        $this->error = null;

        Notification::make()->success()->title(__('themes.editor.saved'))->body(__('themes.editor.saved_body'))->send();
    }

    public function resetToTemplate(): void
    {
        $this->authorizeTheme();
        $template = MenuTheme::query()->where('key', $this->template)->firstOrFail();
        $spec = $template->spec()->with(['layout' => $this->draft['layout'] ?? [], 'cover' => $this->restaurant->cover_path], $this->restaurant);

        ThemeResolver::save($this->restaurant, $template->key, $spec);
        $this->audit('theme.reset', 'Theme reset to its template');
        $this->load($template->key, ThemeResolver::for($this->restaurant->fresh() ?? $this->restaurant));
        $this->dirty = false;
        $this->dispatch('close-modal', id: 'theme-reset');

        Notification::make()->success()->title(__('themes.editor.reset_done'))->send();
    }

    public function export(): ?StreamedResponse
    {
        $this->authorizeTheme();
        $spec = $this->spec();

        if ($spec === null) {
            return null;
        }

        $restaurant = $this->restaurant;
        $name = [
            'en' => Str::limit($restaurant->getTranslation('name', 'en', false) ?: $restaurant->slug, 50, '').' theme',
            'ar' => 'ثيم '.Str::limit($restaurant->getTranslation('name', 'ar', false) ?: $restaurant->slug, 50, ''),
        ];
        $contents = ThemeFile::export($spec, $name, $this->template);

        return response()->streamDownload(function () use ($contents): void {
            echo $contents;
        }, "{$restaurant->slug}-theme.dtheme.json", ['Content-Type' => 'application/json']);
    }

    public function updatedImportUpload(): void
    {
        $this->authorizeTheme();
        $this->pendingImport = null;
        $file = $this->importUpload;
        $this->importUpload = null;

        if ($file === null) {
            return;
        }

        try {
            UploadThrottle::hit(Workspace::user());

            if ((int) $file->getSize() > ThemeFile::MAX_BYTES) {
                throw new ThemeRejected('too_large');
            }

            $parsed = ThemeFile::parse((string) file_get_contents((string) $file->getRealPath()), $this->restaurant);
            $this->pendingImport = ['spec' => $parsed['spec']->toArray(), 'name' => $parsed['name'], 'base' => $parsed['base'], 'notices' => $parsed['notices']];
            $this->error = null;
        } catch (ThemeRejected $e) {
            $this->error = $e->translated();
        }
    }

    public function applyImport(): void
    {
        $this->authorizeTheme();

        $import = $this->pendingImport;

        if ($import === null) {
            return;
        }

        $spec = ThemeSpec::fromArray($import['spec'], null, $this->restaurant)
            ->with(['layout' => $this->draft['layout'] ?? [], 'cover' => $this->restaurant->cover_path], $this->restaurant);

        $this->load($import['base'], $spec);
        $this->audit('theme.imported', 'Theme file imported', ['file' => $import['name']['en']]);
        $this->pendingImport = null;
        $this->dirty = true;
    }

    public function cancelImport(): void
    {
        $this->pendingImport = null;
    }

    public function uploadFont(): void
    {
        $this->authorizeTheme();
        $this->validate([
            'fontUpload' => ['required', 'file', 'max:1024'],
            'fontName' => ['required', 'string', 'max:60'],
            'fontScripts' => ['required', 'in:latin,arabic,both'],
        ], attributes: [
            'fontUpload' => __('themes.fonts.file'),
            'fontName' => __('themes.fonts.name'),
            'fontScripts' => __('themes.fonts.scripts'),
        ]);

        try {
            /** @var TemporaryUploadedFile $file */
            $file = $this->fontUpload;
            app(UploadBrandFont::class)->handle($this->restaurant, Workspace::user(), $file, $this->fontName, $this->fontScripts);
            $this->reset('fontUpload', 'fontName', 'fontScripts');
            $this->error = null;
            unset($this->brandFonts);
            $this->dispatch('close-modal', id: 'theme-font-upload');

            Notification::make()->success()->title(__('themes.fonts.uploaded'))->send();
        } catch (ThemeRejected $e) {
            $this->addError('fontUpload', $e->translated());
        }
    }

    public function deleteFont(int $id): void
    {
        $this->authorizeTheme();
        $font = BrandFont::withoutTenancy()->where('restaurant_id', $this->restaurantId)->findOrFail($id);

        try {
            app(DeleteBrandFont::class)->handle($font, Workspace::user());
            unset($this->brandFonts);

            foreach (ThemeSpec::FONT_ROLES as $role) {
                if (($this->fontChoice[$role]['brand'] ?? null) === $id) {
                    $this->fontChoice[$role]['brand'] = null;
                    $this->updated("fontChoice.{$role}.brand");
                }
            }

            Notification::make()->success()->title(__('themes.fonts.deleted'))->send();
        } catch (ThemeRejected $e) {
            Notification::make()->danger()->title($e->translated())->send();
        }
    }

    public function updatedCoverUpload(): void
    {
        $this->authorizeTheme();
        $file = $this->coverUpload;
        $this->coverUpload = null;

        if ($file === null) {
            return;
        }

        try {
            $path = app(ProcessThemeCover::class)->handle($this->restaurant, Workspace::user(), $file);
            $this->restaurant->forceFill(['cover_path' => $path])->save();
            MenuCache::bump($this->restaurantId);
            $this->draft['cover'] = $path;
            $this->error = null;
            $this->refreshPreview();
        } catch (ThemeRejected $e) {
            $this->error = $e->translated();
        }
    }

    public function removeCover(): void
    {
        $this->authorizeTheme();
        $this->restaurant->forceFill(['cover_path' => null])->save();
        MenuCache::bump($this->restaurantId);
        $this->draft['cover'] = null;
        $this->refreshPreview();
    }

    public function updatedLogoUpload(): void
    {
        $this->authorizeTheme();
        $this->validate(['logoUpload' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048']], attributes: ['logoUpload' => __('themes.editor.logo')]);

        try {
            UploadThrottle::hit(Workspace::user());
        } catch (ThemeRejected $e) {
            $this->error = $e->translated();

            return;
        }

        /** @var TemporaryUploadedFile $file */
        $file = $this->logoUpload;
        $path = $file->storePublicly('restaurants/'.$this->restaurantId.'/brand', 'public');
        $this->logoUpload = null;
        $this->restaurant->forceFill(['logo_path' => $path])->save();
        MenuCache::bump($this->restaurantId);
        $this->refreshPreview();
    }

    public function render(): View
    {
        return view('livewire.workspace.theme-editor', [
            'fonts' => FontCatalog::all(),
            'faces' => FontCatalog::fontFaceCss(array_keys(FontCatalog::all())),
        ]);
    }

    private function load(string $template, ThemeSpec $spec): void
    {
        $this->template = $template;
        $this->draft = $spec->toArray();
        $fallback = MenuTheme::query()->where('key', $template)->first()?->spec()->fonts ?? [];

        foreach (ThemeSpec::FONT_ROLES as $role) {
            $value = $spec->fonts[$role];
            $brand = str_starts_with($value, 'brand:') ? (int) substr($value, 6) : null;
            $pair = FontCatalog::pair($brand !== null ? ($fallback[$role] ?? 'geist+ibm-plex-sans-arabic') : $value, $role);
            $this->fontChoice[$role] = ['latin' => $pair['latin'], 'arabic' => $pair['arabic'], 'brand' => $brand];
        }

        $this->error = null;
        $this->refreshPreview();
    }

    private function spec(): ?ThemeSpec
    {
        try {
            return ThemeSpec::fromArray($this->draft, null, $this->restaurant);
        } catch (ThemeRejected $e) {
            $this->error = $e->translated();

            return null;
        }
    }

    private function refreshPreview(): void
    {
        unset($this->contrast, $this->ratios);
        $spec = $this->spec();

        if ($spec === null) {
            return;
        }

        $this->error = null;
        $this->previewToken = ThemePreview::put($this->restaurant, Workspace::user(), $spec);
        $branch = Workspace::scopeToBranches(Branch::query()->where('restaurant_id', $this->restaurantId))->orderBy('sort')->first();
        $page = $branch !== null ? app(Links::class)->branch($this->restaurant, $branch) : route('public.restaurant', $this->restaurant);

        $this->previewUrl = $page.'?theme_preview='.$this->previewToken;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function audit(string $event, string $description, array $properties = []): void
    {
        activity()->performedOn($this->restaurant)->causedBy(Workspace::user())->event($event)
            ->withProperties(['template' => $this->template] + $properties)->log($description);
    }

    private function authorizeTheme(): void
    {
        abort_unless(MenuDesign::canAccess(), 403);
    }
}
