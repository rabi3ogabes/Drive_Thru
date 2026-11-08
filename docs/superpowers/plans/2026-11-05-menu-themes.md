# Menu Themes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restaurants restyle every guest page from a curated template library (colours, fonts, corners, cover, logo, layout), with theme files, brand fonts and a live preview; the super admin curates the library.

**Architecture:**
- A theme is data. Templates (`menu_themes`) and a restaurant's overrides (`restaurants.menu_theme`) merge in `ThemeResolver` into a `ThemeSpec`.
- `ThemeCss` turns a spec into CSS custom properties that override the Tailwind tokens the guest views already use (`ink`, `ivory`, `brass`, `--brand`, fonts, radius), cached per restaurant.
- No guest view is duplicated. Only a few components move from fixed `rounded-*` classes to radius variables.

**Tech stack:** Laravel 13, Filament 5, Livewire 4 + Alpine, Tailwind 4, `@fontsource` packages, GD, Pest 5.

**Spec:** `docs/superpowers/specs/2026-11-05-menu-themes-design.md` (read it with this plan).

## Global constraints

- Every string in `lang/{ar,en}/themes.php` (guest-facing and admin), Western digits, logical CSS properties.
- `BrandFont` uses `BelongsToRestaurant` with `$attributes` defaults for every column; `MenuTheme` is platform-level (add it to the arch test's platform-model allow-list).
- No raw CSS, HTML or free text ever reaches the `<style>` block; colours are re-serialised as `#rrggbb`, font families come from the catalogue or `brand-{id}`.
- Contrast: text/background ≥ 4.5:1 required; ink-500/background ≥ 3:1 required; button text on brand chosen as the better of `#ffffff` and the text colour, warning (not refusal) below 4.5:1.
- Limits:
  - theme file ≤ 20 KB;
  - brand font WOFF2 ≤ 1 MB, signature `wOF2`, at most 4 per restaurant;
  - cover JPEG/PNG/WebP ≤ 5 MB, re-encoded to WebP at 800 and 1600 px;
  - uploads rate-limited to 20/hour/user;
  - preview drafts live 30 minutes.
- New permission `PlatformPermission::ManageThemes = 'themes.manage'` with ar/en label and description. The owner side uses the existing `RestaurantPermission::ManageMenu`.
- No third-party requests: fonts are self-hosted under `public/fonts/guest/`.
- Audit events (spatie `activity()`):
  - `theme.saved`, `theme.reset`, `theme.imported`;
  - `brand_font.uploaded`, `brand_font.deleted`;
  - `menu_theme.created`, `menu_theme.updated`, `menu_theme.default_changed`, `menu_theme.hidden`.

## Review focus

1. **Existing restaurants look unchanged after deploy:** the data migration maps `accent_color` and `menu_layout` into overrides, and `ivory-teal` reproduces today's tokens exactly. Owned by Task 3 (`it keeps today's look for existing restaurants`).
2. **A template the restaurant uses is hidden or edited by the admin:** the restaurant keeps rendering (a hidden template is still resolved; an edit changes the restaurant's look and clears its cache). Owned by Task 3 (`it still resolves a hidden template`) and Task 8 (`it clears the theme cache of restaurants using an edited template`).
3. **Theme file from another restaurant referencing `brand:` fonts, or crafted to inject CSS** (`"brand": "#fff;}body{display:none"`): fonts are dropped with a notice, and the injection is refused. Owned by Task 4.
4. **Preview token reused by another user or another restaurant:** they get the saved theme, never the draft. Owned by Task 5.
5. **A brand font in use is deleted, or the owner leaves with unsaved changes:** the delete is refused naming the role; the page prompts before leaving. Owned by Task 6 (`it refuses to delete a font the theme uses`) and Task 7 (a browser check of the leave prompt).

---

### Task 1: Theme spec, palette and contrast

**Files:**
- Create:
  - `app/Domain/Themes/ThemeSpec.php`
  - `app/Domain/Themes/Palette.php`
  - `app/Domain/Themes/Contrast.php`
  - `app/Domain/Themes/Exceptions/ThemeRejected.php`
- Test: `tests/Unit/Themes/PaletteTest.php`, `tests/Unit/Themes/ThemeSpecTest.php`

**Interfaces (produces):**
- `final readonly class ThemeSpec`:
  - `public function __construct(public string $mode, public array $colors, public array $fonts, public string $corners, public MenuLayout $layout, public ?string $cover = null)`.
  - `$colors` keys: `brand`, `accent`, `background`, `text`. `$fonts` keys: `heading`, `body`, each a catalogue pair key `latin+arabic` (for example `fraunces+alexandria`) or `brand:{id}`.
  - `static fromArray(array $data, ?self $base = null): self`: a partial array merged over `$base`; throws `ThemeRejected` with a translated `reason` key on bad values.
  - `toArray(): array`; `with(array $overrides): self`; `diff(self $base): array` (only the changed fields, used to store overrides).
  - Constants: `MODES = ['light','dark']`, `CORNERS = ['soft','rounded','sharp']`.
- `final class Palette`:
  - `static shades(string $hex, array $steps): array<int,string>`, the keys being Tailwind steps (50…950), values `#rrggbb`;
  - `static normalize(string $hex): string`, which throws `ThemeRejected('bad_color')` when the input doesn't match `^#[0-9a-fA-F]{6}$`.
- `final class Contrast`:
  - `static ratio(string $a, string $b): float` (WCAG 2.2 relative luminance);
  - `static onColor(string $background, string $text): string` (the better of `#ffffff` and `$text`);
  - `static check(ThemeSpec $spec): array{ok: bool, errors: list<array{key: string, ratio: float}>, warnings: list<array{key: string, ratio: float}>}`.
- `ThemeRejected extends RuntimeException`: `public readonly string $reason`, `public readonly array $context`.

- [ ] **Step 1: Write the failing tests:**
  - `it builds 11 deterministic shades in gamut`:
    - `Palette::shades('#0f3b3a', [50,100,…,950])` twice gives equal arrays, 11 entries, each matching `^#[0-9a-f]{6}$`;
    - the 500 shade's hue is within 3° of the input's OKLCH hue;
    - lightness falls monotonically from 50 to 950.
  - `it measures WCAG contrast`:
    - `ratio('#ffffff','#000000') ≈ 21.0`;
    - `ratio('#777777','#ffffff') ≈ 4.48` (±0.01).
  - `it picks readable button text`:
    - `onColor('#0f3b3a','#111827') === '#ffffff'`;
    - `onColor('#f5d76e','#111827') === '#111827'`.
  - `it refuses unreadable text`: a spec with text `#cccccc` on background `#ffffff` gives `check()['ok'] === false` and `errors[0]['key'] === 'text_on_background'`.
  - `it warns but allows a weak brand`: brand `#f0e68c` with text `#9a9a9a` yields a warning `button_text`.
  - `it rejects bad colours`: `ThemeSpec::fromArray(['colors' => ['brand' => '#fff;}body{']])` throws `ThemeRejected` with reason `bad_color`; `'red'` and `'#12345'` too.
  - `it rejects unknown modes and corners` and `it merges a partial over a base, and diff returns only changed fields`.
- [ ] **Step 2: Run** `vendor/bin/pest tests/Unit/Themes`. Expected: FAIL (classes missing).
- [ ] **Step 3: Implement.**
  - The palette works in OKLCH (sRGB → linear → OKLab → OKLCH and back), keeping hue.
  - Target lightness per step: `50:0.975, 100:0.95, 200:0.9, 300:0.83, 400:0.72, 500:0.6, 600:0.5, 700:0.42, 800:0.34, 900:0.27, 950:0.2`.
  - Chroma is the input's chroma scaled by `1 - |L - L_input| * 0.6`, reduced in 0.005 steps until in gamut.
- [ ] **Step 4: Run** the same command. Expected: PASS.
- [ ] **Step 5: Commit** `feat(themes): theme spec, OKLCH palette and contrast rules`.

### Task 2: Font catalogue and self-hosted guest fonts

**Files:**
- Modify: `package.json` (add `@fontsource-variable/playfair-display`, `@fontsource/cormorant-garamond`, `@fontsource/dm-serif-display`, `@fontsource-variable/manrope`, `@fontsource-variable/plus-jakarta-sans`, `@fontsource-variable/outfit`, `@fontsource/tajawal`, `@fontsource-variable/cairo`, `@fontsource-variable/readex-pro`, `@fontsource-variable/noto-kufi-arabic`, `@fontsource/el-messiri`, `@fontsource/amiri`).
- Create:
  - `scripts/copy-guest-fonts.mjs`: copies the needed WOFF2 subsets (latin, latin-ext, arabic) into `public/fonts/guest/{key}/`; runs in `npm run build` before Vite;
  - `app/Domain/Themes/FontCatalog.php`.
- Test: `tests/Unit/Themes/FontCatalogTest.php`.

**Interfaces (produces):**
- `final class FontCatalog`:
  - `static all(): array<string, array{family: string, script: 'latin'|'arabic', role: 'heading'|'body'|'both', faces: list<array{file: string, weight: string, style: string, unicodeRange: string}>}>`. The keys are the 16 fonts of the spec: `geist`, `fraunces`, `playfair-display`, `cormorant-garamond`, `dm-serif-display`, `manrope`, `plus-jakarta-sans`, `outfit`, `ibm-plex-sans-arabic`, `alexandria`, `tajawal`, `cairo`, `readex-pro`, `noto-kufi-arabic`, `el-messiri`, `amiri`.
  - `static pair(string $key): array{latin: string, arabic: string}`, which throws `ThemeRejected('unknown_font')`.
  - `static fontFaceCss(array $keys): string`: `@font-face` rules for only those fonts, URLs from `asset('fonts/guest/…')`, `font-display: swap`.
  - `static preloadUrl(string $key): string`.

- [ ] **Step 1: Write the failing tests:**
  - `it lists 16 fonts, 8 per script, each with files that exist in public/fonts/guest`;
  - `it emits font-face only for the requested fonts, all local, all swap`:
    - `fontFaceCss(['geist','tajawal'])` contains `font-family: 'Geist Variable'` and `'Tajawal'`;
    - it does not contain `Amiri`;
    - it has no `http` other than `asset()`'s own host, and contains `font-display: swap`.
  - `it rejects an unknown pair`: `pair('comic+sans')` throws `ThemeRejected`.
- [ ] **Step 2: Run** `vendor/bin/pest tests/Unit/Themes/FontCatalogTest.php`. Expected: FAIL.
- [ ] **Step 3: Install and implement.**
  - Run `npm i -E <packages>`, then write the copy script and add it to the `build` and `dev` scripts.
  - Commit `public/fonts/guest/` (the fonts are licensed OFL) so the server doesn't depend on the copy step.
  - Implement `FontCatalog`.
- [ ] **Step 4: Run** `npm run build`, then the tests. Expected: PASS, and `public/fonts/guest/*/` populated.
- [ ] **Step 5: Commit** `feat(themes): self-hosted font catalogue for guest pages`.

### Task 3: Template library, restaurant theme and resolver

**Files:**
- Create:
  - migration `2026_11_05_100000_create_menu_themes_table` (`key` unique, `name` json, `description` json, `spec` json, `cover_path` nullable, `is_built_in`, `is_active`, `is_default` bool, `sort` int, timestamps);
  - migration `2026_11_05_100100_add_menu_theme_to_restaurants` (`menu_theme` json nullable);
  - migration `2026_11_05_100200_install_menu_themes`: calls `ThemeCatalog::install()`, then for each restaurant writes `menu_theme = {template: 'ivory-teal', overrides: {colors: {brand: accent_color}, layout: menu_layout}}` when those are set;
  - `app/Domain/Themes/Models/MenuTheme.php`;
  - `app/Domain/Themes/ThemeCatalog.php`;
  - `app/Domain/Themes/ThemeResolver.php`;
  - `database/seeders/images/themes/*.webp` (6 covers).
- Modify:
  - `app/Domain/Tenancy/Models/Restaurant.php` (cast `menu_theme` to array, default null);
  - `app/Domain/Menu/MenuLayout.php` (`for()` reads `ThemeResolver::for($restaurant)->layout`);
  - `app/Domain/Access/Enums/PlatformPermission.php` plus the `lang/*/permissions.php` entry for `themes.manage`;
  - `app/Console/Commands/InstallPlatform.php` (call `ThemeCatalog::install()`);
  - the arch test allow-list.
- Test: `tests/Feature/Themes/ThemeResolverTest.php`.

**Interfaces:**
- Consumes `ThemeSpec`, `Contrast` and `FontCatalog` (Tasks 1–2).
- Produces:
  - `MenuTheme`: `spec(): ThemeSpec`, `scopeActive()`, `static default(): self`, `usedByCount(): int` (`Restaurant::where('menu_theme->template', key)`);
  - `ThemeCatalog`: `install(): void` (adds missing keys, never overwrites) and `templates(): array` (the 6 specs of the spec's table with ar/en names);
  - `ThemeResolver`:
    - `static for(Restaurant $restaurant): ThemeSpec`, memoised per request;
    - `static template(Restaurant $restaurant): MenuTheme`;
    - `static save(Restaurant $restaurant, string $templateKey, ThemeSpec $spec, ?string $coverPath): void` (stores `diff()` against the template, bumps `MenuCache` and `ThemeCss` caches).

- [ ] **Step 1: Write the failing tests:**
  - `it installs six built-in templates once, with one default, and never overwrites admin edits` (install, rename one, install again, the name is kept);
  - `it ships only readable templates`: `Contrast::check()` is ok for all 6;
  - `it merges template and overrides`: brand override `#7a1f1f` → resolved brand `#7a1f1f`, other colours equal to the template's;
  - `it still resolves a hidden template` (`is_active` false → its spec, not the default);
  - `it falls back to the default when the template is missing`;
  - `it keeps today's look for existing restaurants`:
    - after running the install migration's closure on a restaurant with `accent_color #224466` and `menu_layout card_style list`, the resolved brand is `#224466`;
    - the layout card style is `list`;
    - `ivory-teal`'s colours equal today's `teal-900`, `brass-500`, `ivory-50` and `ink-900` from `resources/css/tokens.css`;
  - `it lets MenuLayout read the theme layout`;
  - `it saves only the differences and clears caches` (`MenuCache::version` increments).
- [ ] **Step 2: Run** `vendor/bin/pest tests/Feature/Themes/ThemeResolverTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the migrations, model, catalogue (the 6 templates with hand-picked colours that pass the contrast rules; `ivory-teal` uses today's exact tokens), resolver and the `MenuLayout` change. Run `php artisan migrate` locally too.
- [ ] **Step 4: Run** the test file plus `tests/Feature/Menu`. Expected: PASS.
- [ ] **Step 5: Commit** `feat(themes): template library and per-restaurant theme`.

### Task 4: Theme file import and export

**Files:**
- Create: `app/Domain/Themes/ThemeFile.php`
- Test: `tests/Unit/Themes/ThemeFileTest.php`

**Interfaces:**
- Consumes `ThemeSpec`, `FontCatalog`, `Contrast`.
- Produces `final class ThemeFile`:
  - `static export(ThemeSpec $spec, array $name, string $base): string` (pretty JSON in the spec's format, `brand:` fonts kept);
  - `static parse(string $contents, ?Restaurant $for = null): array{spec: ThemeSpec, name: array{en: string, ar: string}, base: string, notices: list<string>}`, which throws `ThemeRejected` with these reasons:
    - `too_large`, `not_json`, `not_theme`, `newer_version`;
    - `bad_color`, `unknown_font`, `bad_value`;
    - `unreadable` (contrast, with `context.ratio`).

- [ ] **Step 1: Write the failing tests:**
  - `it round-trips a theme` (export, then parse, gives `toArray()` equal);
  - refusals:
    - `it refuses files over 20 KB`;
    - `it refuses non-JSON, other formats and newer versions` (`version: 2` → `newer_version`);
    - `it refuses CSS injection in colours` (`"#fff;}body{display:none}"` → `bad_color`);
    - `it strips tags from names and caps them at 60 characters` (`<script>x</script>Olive` → `Olive`);
  - `it ignores unknown fields`;
  - `it drops brand fonts from another restaurant with a notice`: `brand:99` where font 99 isn't the importer's → heading falls back to the base template's heading, notices contain `themes.import.font_dropped`;
  - `it refuses an unreadable theme with the measured ratio`.
- [ ] **Step 2: Run** `vendor/bin/pest tests/Unit/Themes/ThemeFileTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** `ThemeFile` (`json_decode` with `JSON_THROW_ON_ERROR`, depth 8; size checked before decoding).
- [ ] **Step 4: Run** it. Expected: PASS.
- [ ] **Step 5: Commit** `feat(themes): safe theme file import and export`.

### Task 5: Guest pages wear the theme, with draft preview

**Files:**
- Create:
  - `app/Domain/Themes/ThemeCss.php`
  - `app/Domain/Themes/ThemePreview.php`
  - `resources/views/components/customer/theme.blade.php` (the `<style>`, font links and meta)
- Modify:
  - `resources/views/components/layouts/customer.blade.php`: replace the `--brand` style line, the `theme-color` and the `color-scheme` meta with `<x-customer.theme :restaurant="$restaurant" />`; the `<body>` gets `data-theme-mode`;
  - `resources/css/app.css`: add `--radius-card` / `--radius-control` defaults and the `.theme-card` / `.theme-control` utilities;
  - the guest components that render cards and buttons in `resources/views/components/customer/*` and `livewire/customer/branch-menu.blade.php` (product card, primary button, sheet, inputs), moving them to those utilities;
  - `config/cache.php` (no objects cached: `ThemeCss` caches strings only).
- Test: `tests/Feature/Themes/GuestThemeTest.php`.

**Interfaces:**
- Consumes `ThemeResolver`, `Palette`, `Contrast`, `FontCatalog`.
- Produces:
  - `ThemeCss`:
    - `static for(Restaurant $restaurant, ?ThemeSpec $draft = null): string` (cached under `theme-css:{id}:{version}` unless a draft is given);
    - `static fontKeys(ThemeSpec $spec): list<string>`;
    - `static bump(int $restaurantId): void`.
  - `ThemePreview`:
    - `static put(Restaurant $restaurant, User $user, ThemeSpec $spec): string` (a 40-character token, cached for 30 minutes);
    - `static draft(Restaurant $restaurant, ?string $token): ?ThemeSpec` (null unless the token's restaurant and `auth()->id()` match).
  - Dark mode: `ink` shades run 950→50 (the darkest at 50), so `text-ink-900` stays the light text.

- [ ] **Step 1: Write the failing tests:**
  - `it prints the resolved tokens on every guest page`: for the home, branch menu, checkout, order status, rewards and sign-in pages, the HTML contains `--brand: #`, `--color-ink-900:`, `--color-ivory-50:`, `--font-sans:` and `--radius-card:`;
  - `it inverts ink for dark themes and sets color-scheme dark` (the `midnight-brass` template);
  - `it links only the chosen fonts and nothing remote`: the page has `fonts/guest/tajawal` for a theme using Tajawal; it has no `fonts/guest/amiri`; and no `https://` URL other than `config('app.url')` in `<link>`/`<style>`;
  - `it caches the css and refreshes on save` (the second call runs no query; after `ThemeResolver::save`, a new value);
  - `it shows a draft only to its author on the same restaurant`:
    - the author sees the draft brand and the "Preview" ribbon;
    - another signed-in user with the same token sees the saved brand;
    - the same token on another restaurant's page shows the saved brand;
  - `it marks preview pages noindex and no-store and disables ordering` (headers, plus the checkout button `disabled` with `themes.preview.no_orders`).
- [ ] **Step 2: Run** `vendor/bin/pest tests/Feature/Themes/GuestThemeTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement.**
  - The theme component and the CSS builder, with fixed template text.
  - Shade maps:
    - ink from `text`;
    - ivory 50–300 from `background` (50 = the background itself);
    - brass from `accent`;
    - `--brand-contrast` from `Contrast::onColor`.
  - Corners: `soft` = 1rem/0.75rem, `rounded` = 1.5rem/999px, `sharp` = 0.25rem/0.25rem.
  - The preview handling goes in the customer layout component and the `BranchMenu`/`Checkout` components (a `theme_preview` query parameter).
- [ ] **Step 4: Run** the test file and `tests/Feature/Customer`. Expected: PASS, with no guest test regressions.
- [ ] **Step 5: Commit** `feat(themes): guest pages use the restaurant theme, with author-only preview`.

### Task 6: Brand fonts and cover images

**Files:**
- Create:
  - migration `2026_11_05_100300_create_brand_fonts_table` (`restaurant_id` fk cascade, `name` string 60, `scripts` string (`latin`|`arabic`|`both`), `path`, `size_bytes`, timestamps);
  - `app/Domain/Themes/Models/BrandFont.php` (`BelongsToRestaurant`);
  - `app/Domain/Themes/Actions/UploadBrandFont.php`;
  - `app/Domain/Themes/Actions/DeleteBrandFont.php`;
  - `app/Domain/Themes/Actions/ProcessThemeCover.php`;
  - `app/Policies/BrandFontPolicy.php` (`ManageMenu`), registered in `AppServiceProvider`.
- Modify: `FontCatalog::fontFaceCss` and `ThemeCss` to accept `brand:{id}` (family `brand-{id}`, URL `Storage::disk('public')->url($path)`).
- Test: `tests/Feature/Themes/BrandFontTest.php`.

**Interfaces:**
- Produces:
  - `UploadBrandFont::handle(Restaurant $restaurant, User $user, UploadedFile $file, string $name, string $scripts): BrandFont`, which throws `ThemeRejected`:
    - `not_woff2` (the first 4 bytes aren't `wOF2`);
    - `font_too_large` (over 1 048 576 bytes);
    - `font_limit` (already 4);
  - `DeleteBrandFont::handle(BrandFont $font, User $user): void`, which throws `ThemeRejected('font_in_use', ['role' => 'heading'|'body'])`;
  - `ProcessThemeCover::handle(Restaurant $restaurant, UploadedFile $file): string`: returns the stored 1600 px WebP path and also writes the 800 px version; throws `ThemeRejected('bad_image')` or `image_too_large` (over 5 MB).
  - Both uploads are throttled with `RateLimiter` key `theme-upload:{user}`, 20 per 3600 s, throwing `ThemeRejected('slow_down')`.

- [ ] **Step 1: Write the failing tests:**
  - `it stores a real WOFF2 under the restaurant with a random name and audits it` (fixture `tests/Fixtures/fonts/sample.woff2`, a tiny OFL subset);
  - `it refuses a renamed TTF or PNG` (`not_woff2`) and `it refuses files over 1 MB`;
  - `it allows at most four fonts`;
  - `it refuses to delete a font the theme uses, naming the role`;
  - `it keeps fonts to their restaurant`: the other restaurant's owner gets 403 from the policy; `BrandFont::all()` under the other tenant is empty; using `brand:{id}` of another restaurant in a spec is refused by `ThemeSpec::fromArray(..., restaurant)` with `unknown_font`;
  - `it re-encodes a cover to WebP at 800 and 1600 and strips metadata` (a JPEG with EXIF → the stored file starts with `RIFF`/`WEBP`; there's no `Exif` in its bytes);
  - `it slows down after 20 uploads an hour`.
- [ ] **Step 2: Run** `vendor/bin/pest tests/Feature/Themes/BrandFontTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the actions, model, policy and migration. `ThemeSpec::fromArray` gets an optional `?Restaurant $restaurant` used to validate `brand:` ids.
- [ ] **Step 4: Run** it. Expected: PASS.
- [ ] **Step 5: Commit** `feat(themes): brand font uploads and theme cover images`.

### Task 7: Owner Theme tab

**Files:**
- Modify:
  - `app/Filament/App/Pages/MenuDesign.php`: the page becomes two tabs, `theme` (default) and `layout`, via `?tab=` (`#[Url]` property `$tab`); the layout form is unchanged, except that it now saves through `ThemeResolver::save` (layout overrides);
  - `app/Filament/App/Pages/RestaurantSettings.php`: remove the accent colour field, show the current colour with a link to `MenuDesign::getUrl(['tab' => 'theme'])`;
  - `lang/{ar,en}/guide.php`: the `menu_design` article gets these actions: Use this theme, Colours, Fonts, Upload a font, Cover image, Corners, Preview, Save theme, Reset to template, Export theme file, Import theme file. Plus one tip on the contrast check.
- Create:
  - `app/Livewire/Workspace/ThemeEditor.php` + `resources/views/livewire/workspace/theme-editor.blade.php` (rendered inside the Theme tab);
  - `lang/{ar,en}/themes.php` (all owner, guest-preview and admin strings).
- Test: `tests/Feature/Themes/ThemeEditorTest.php`.

**Interfaces:**
- Consumes `ThemeResolver`, `ThemePreview`, `ThemeFile`, `UploadBrandFont`, `DeleteBrandFont`, `ProcessThemeCover`, `Contrast`, `FontCatalog`.
- Produces the `ThemeEditor` Livewire component:
  - public state: `string $template`, `array $draft` (a `ThemeSpec` array), `?string $previewToken`, `bool $dirty`;
  - methods: `useTemplate(string $key, bool $confirmed = false)`, `updatedDraft()` (re-checks contrast and refreshes `previewToken`), `save()`, `resetToTemplate()`, `export()` (a `StreamedResponse`), `import(TemporaryUploadedFile $file)`, `applyImport()`, `uploadFont(...)`, `deleteFont(int $id)`, `uploadCover(...)`, `removeCover()`;
  - computed: `contrast()` (`Contrast::check`), `templates()` (active ones plus the current one even if hidden).

UI (soft-skill / emil-design-eng):
- **Gallery:** template cards with a live mini rendering, the current one marked; "Use this theme" with a confirmation when `dirty` or overrides exist.
- **Customise:**
  - colour inputs (native `<input type=color>` plus a hex field) with contrast badges;
  - font pickers showing «قائمة اليوم» / "Today's menu" in the face;
  - corner segmented control; cover and logo uploads with progress.
- **Preview:** a sticky phone frame `iframe src="{branch url}?theme_preview={token}"`, debounced 400 ms. Below 1024 px, it sits behind a "Preview" button opening a full-screen sheet.
- **Leaving:** Alpine `beforeunload` while `dirty`.
- **States:** loading shimmer on the iframe; empty gallery message; error toasts with translated `ThemeRejected` reasons; success toast.

- [ ] **Step 1: Write the failing tests:**
  - `it opens on the theme tab for a menu manager and is forbidden to a cashier`;
  - `it asks before replacing custom changes when picking a template` (`useTemplate('olive-grove')` with overrides → a confirmation event, nothing saved; `confirmed: true` → applied);
  - `it saves colours, fonts and corners, audits theme.saved and changes the guest page`;
  - `it refuses to save unreadable colours with the ratio in the message`;
  - `it refreshes the preview token on each change and the token shows the draft` (with Task 5's draft rules);
  - `it resets to the template and audits theme.reset`;
  - `it exports a theme file and imports it back after a preview` (`import` fills a pending state; `applyImport` fills the draft; the theme isn't saved until `save`);
  - `it uploads and deletes a brand font from the editor` and `it uploads a cover`;
  - `it moved the brand colour from restaurant settings` (the settings form has no `accent_color` field and links to the Theme tab).
  - Keep `GuideCatalogTest` green: the parity test catches a missing Arabic action.
- [ ] **Step 2: Run** `vendor/bin/pest tests/Feature/Themes/ThemeEditorTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the component, view, tabs, settings change and lang files. `npm run build` (the view sits under `resources/views/livewire/**`, already an `@source`).
- [ ] **Step 4: Run** `vendor/bin/pest tests/Feature/Themes tests/Feature/Guide tests/Feature/Menu`. Expected: PASS.
- [ ] **Step 5: Commit** `feat(themes): owner theme editor with live preview`.

### Task 8: Admin theme library

**Files:**
- Create: `app/Filament/Admin/Resources/MenuThemes/MenuThemeResource.php` with `Pages/ListMenuThemes.php`, `Pages/CreateMenuTheme.php`, `Pages/EditMenuTheme.php`, `Schemas/MenuThemeForm.php`, `Tables/MenuThemesTable.php`; `app/Policies/MenuThemePolicy.php` (`ManageThemes`).
- Test: `tests/Feature/Themes/AdminThemeLibraryTest.php`.

**Interfaces:**
- Consumes `MenuTheme`, `ThemeFile`, `ThemeSpec`, `Contrast`, `ProcessThemeCover` (cover stored under `themes/` for templates), `ThemeCss::bump`.
- Produces these admin actions:
  - list: swatch, name, mode, status, default, used by;
  - `duplicate`, `uploadFile` (preview, then create), `setDefault`, `hide` / `show`;
  - `delete`, visible only when `! is_built_in && usedByCount() === 0`.
  - Saving a template bumps the theme CSS cache of every restaurant using it (one query for their ids).

- [ ] **Step 1: Write the failing tests:**
  - `it lists templates with how many restaurants use each` (2 restaurants on `olive-grove` → "2"; one grouped query, asserted with `DB::enableQueryLog` count ≤ 4);
  - `it creates, duplicates and uploads a template from a theme file, and audits menu_theme.created`;
  - `it keeps exactly one default` (`setDefault` on another → the previous default cleared, `menu_theme.default_changed`);
  - `it hides but never deletes a template in use or a built-in one` (no delete action; a direct policy `delete` is false);
  - `it clears the theme cache of restaurants using an edited template` (the guest page shows the new brand after editing);
  - `it refuses an unreadable template`;
  - `it is limited to platform staff with Manage themes` (an owner gets 403, and staff without the permission don't see the nav item).
- [ ] **Step 2: Run** `vendor/bin/pest tests/Feature/Themes/AdminThemeLibraryTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the resource, reusing the colour, font and corner fields from a shared `App\Filament\Concerns\ThemeFields` trait also used by `ThemeEditor` where it applies.
- [ ] **Step 4: Run** it. Expected: PASS.
- [ ] **Step 5: Commit** `feat(themes): admin template library`.

### Task 9: Quality gate, docs, browser checks, deploy

- [ ] **Step 1: Gate.** Run:
  - `vendor/bin/pest --parallel` (all pass);
  - `vendor/bin/pint --test`;
  - `vendor/bin/phpstan analyse --memory-limit=2G` (0 errors);
  - `npm run build`;
  - `php artisan migrate` locally.
- [ ] **Step 2: Browser checks** (headless Playwright script in the scratchpad, like the guide's).
  - Pages:
    - the owner Theme tab: gallery, customise, live preview, import/export, font upload, leave prompt;
    - the admin library;
    - guest home, menu, checkout, order page and rewards, on `ivory-teal`, `midnight-brass` and `mono-bold`.
  - Conditions: Arabic and English, at 375, 768 and 1280 px.
  - Confirm no sideways scrolling, no page errors or console warnings, and no request to another host (the Playwright `request` listener).
- [ ] **Step 3: Docs:**
  - `docs/ARCHITECTURE.md`: a new §4.10 "Menu themes" covering tokens, resolver, cache, preview and uploads;
  - `docs/CHANGELOG.md` entry;
  - a `CLAUDE.md` gotcha: "Guest views must keep using the `ink`/`ivory`/`brass`/`--brand` tokens and `theme-card`/`theme-control` radius utilities, never fixed colours or radii, or themes won't reach them".
- [ ] **Step 4: Commit and push** `menu-themes`, and open a PR into `main` (it stacks on `phase-7c` until #5 is merged).
- [ ] **Step 5: Deploy** to `dhawq-qatar`, only after you confirm the source branch, since the live source is `phase-7c` today:
  - run `php artisan migrate --force` (this installs the templates and maps existing restaurants);
  - run `npm ci && npm run build`, `php artisan optimize` and `view:cache` as the site user;
  - check `/rabii` and `/app/rabii/menu/design?tab=theme` answer.
