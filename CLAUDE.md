# Drive-Thru Plus: project rules (loaded in every Claude session)

Multi-tenant, bilingual (Arabic RTL + English LTR) drive-thru ordering SaaS on Laravel.
Super Admin → Clients (restaurant owners) → Branches → Employees in Positions → Menu → Customers → Orders.

## Read first
- `docs/MASTER_PROMPT.md`: the full spec (features from the reference repo + improvements, stack, architecture, design system, permissions, Definition of Done).
- `docs/ROADMAP.md`: phases and checkboxes. Work on **one phase at a time**. Never start the next phase until the current one meets its "Done when".
- `docs/ARCHITECTURE.md`: tenancy, permissions, security, localization and design-system rules as built. Follow it.

## Stack
PHP 8.4, Laravel 13, Filament 5 (admin `/admin` + tenant panel `/app/{restaurant}`), Livewire 4 + Alpine + Tailwind 4 (website, customer PWA, ops screens), Reverb, Pest 5, Larastan (level 8), Pint.
Local: SQLite + database queue/cache/session. Production: MySQL + Redis + Horizon.
Composer is not on PATH on this machine: use `php C:/Users/user/composer/composer.phar`.
Always verify a vendor API in `vendor/` before using it (Filament 5 / Pest 5 are newer than training data).

## Non-negotiables
- Every customer-visible string is in `lang/ar` + `lang/en`. RTL uses logical CSS properties. Use Western digits (0-9) in both languages.
- Every tenant model uses `BelongsToRestaurant` (an arch test enforces it). Every feature has cross-tenant isolation tests.
- Permissions are enum cases (`PlatformPermission`, `RestaurantPermission`) with ar/en label + description. Add new ones there, never ad-hoc strings.
- New models: declare `$attributes` defaults for every column so fresh models are complete under strict mode.
- No third-party requests from the UI (self-hosted fonts, local avatars).
- Prices, tax, discounts, points and status transitions are computed on the server only. Money is stored in minor units.
- Order status changes only through the `TransitionOrder` action (state machine + history + event + broadcast).
- External providers are accessed only through drivers (Fake driver first). Never put secrets in code.
- Each feature ships with loading/empty/error/success states, policies, audit entries for sensitive actions, and Pest tests.
- No placeholders, no TODOs, no dead buttons, no N+1 queries, no console errors.
- Design tokens only (no hard-coded colors). The UI must look luxury, calm and modern, never like a default template.

## Workflow per phase
1. `brainstorming` → `writing-plans`: show the plan and wait for approval.
2. `test-driven-development` for implementation, `systematic-debugging` for bugs.
3. Design skills: `taste-skill`, `soft-skill`, `impeccable`, `frontend-design`, `ui-ux-pro-max`, `emil-design-eng`. Motion skills: `animate`, `apple-design`. Mobile: `mobile-native`. Charts: `dataviz`.
4. `verification-before-completion`: run tests, then check in the browser in AR/EN, light/dark, at 375/768/1280 px (plus TV size for ops screens). Report exactly what was verified.
5. Tick `docs/ROADMAP.md` and append to `docs/CHANGELOG.md`.

## Commands
- `composer setup` (first install) · `php artisan db:seed` (demo data) · `composer dev` · `vendor/bin/pest` · `vendor/bin/pint` · `vendor/bin/phpstan analyse --memory-limit=2G`
- After installing packages: `php artisan filament:upgrade` publishes Filament JS/CSS (runs automatically on composer install).
- First super admin: `php artisan platform:install`
- Billing cycle by hand: `php artisan billing:run` (it is also scheduled daily at 05:30 Asia/Qatar).

## Local dev on Windows (learned the hard way)
- Composer isn't on PATH: `php /c/Users/user/composer/composer.phar …`.
- The first page load after clearing caches compiles hundreds of Blade views and can exceed PHP's 30 s limit. Run `composer warm` (`view:cache` + `filament:optimize`) after `optimize:clear`, and after editing views if pages hang.
- Dev server: use `.claude/launch.json` (`drive-thru`: `php -S 127.0.0.1:8020` with OPcache loaded via `-d zend_extension=opcache -d opcache.enable_cli=1`; without it every request recompiles the framework, about 1 s). The project-root `server.php` routes requests without depending on the current directory. Don't pass `-d` flags to `artisan serve`: they apply to its supervisor and kill it.
- PHP's built-in server is single-threaded and blocks on idle browser sockets (requests then wait 30–70 s). For browser checks, start `drive-thru-proxy` too and browse **http://localhost:8010** (`.claude/dev-proxy.cjs` forwards only complete requests). Keep no other tab on :8020. (Port 8000 is used by another local project, so Drive-Thru runs on 8020.)
- After pulling new migrations, run `php artisan migrate` on the local SQLite DB too: tests use their own database, so a missing table only shows up in the browser.
- Git Bash rewrites arguments that start with `/`; prefix with `MSYS_NO_PATHCONV=1` (e.g. `MSYS_NO_PATHCONV=1 php probe.php /admin`).
- New Blade folders that use Tailwind classes inside the panels must be added as an `@source` in `resources/css/filament/theme.css`, then `npm run build`.
- Values that must stay LTR inside Arabic text (phones, codes) go through `App\Support\Bidi::ltr()`.
- Don't add `use` imports with `sed`: Git Bash mangles backslashes (`\U` uppercases the rest of the line). Use the Edit tool or PowerShell `.Replace()`.
- Restaurant workspace pages: scope branch data with `App\Filament\App\Workspace::scopeToBranches()`. In `Workspace::branchIds()`, `null` means "all branches", not "none".
- Relationship repeaters with translatable rows need `->mutateRelationshipDataBeforeFillUsing(Bilingual::fillRelated(Model::class, [...]))`.
- Wall-clock time fields (opening hours) use `TimePicker::make()->timezone('UTC')`, or Filament shifts them into the display timezone.
- JS strings inside Alpine/HTML attributes must use `Illuminate\Support\Js::from()`, never `json_encode()` (raw double quotes break the attribute).
- Livewire JSON tests: `->withCredentials()` is needed for cookies to be sent on `getJson()`.
- Filament closures are injected **by parameter name**: query callbacks must name it `$query` (a `Builder $q` gets an empty container-built query → "hydrate() on null").
- Blade: never put an inline `@php(...)` before a `@php … @endphp` block in the same view; the block regex swallows everything in between. Use blocks.
- Cache: Laravel 13 only unserializes classes listed in `config/cache.php` `serializable_classes`. Caching new object types needs an entry there, and a test on a real store (`file`), because tests default to the array store.
- Guest (customer) pages: customer sign-in lives in `CustomerAuth` (session store, per restaurant), not Laravel auth. In tests, sign in by putting the token in the session and call `app()->forgetScopedInstances()` when switching guests, as a new request would.
- The Browser pane pauses animations and `requestAnimationFrame` while hidden: WAAPI sheets stay off-screen and `wire:navigate` never swaps. That is the pane, not the app; navigate directly or bring the pane forward.
- Realtime locally: start `reverb` from `.claude/launch.json` (or `composer dev`). `.env` has `BROADCAST_CONNECTION=reverb`; tests use the null broadcaster, so channel rules are tested through `ChannelAccess` and `/realtime/auth`.
- An `sr-only` (absolutely positioned) element inside an `overflow-x-auto` wrapper escapes it unless the wrapper is `relative`, and widens the whole page.
- PHP `use` aliases are case-insensitive: `Illuminate\Support\Facades\URL` and `Livewire\Attributes\Url` can't both be imported plainly (alias one).
- Don't name a Blade view variable `$component`: Blade's anonymous components use it internally.
- Livewire re-renders morph the DOM: one-time Alpine effects (celebrations, alerts) must render once on the server, not on every refresh.

- Windows PHP here has no OpenSSL config and no CA bundle: `webpush:keys` needs `OPENSSL_CONF=<php dir>\extras\ssl\openssl.cnf` in the environment (setting it at runtime doesn't work), and outgoing HTTPS needs `curl.cainfo`/`openssl.cafile` in php.ini (set on 2026-10-12 to Git for Windows' `ca-bundle.crt`; backup `php.ini.bak-2026-10-12`). Linux servers are unaffected.
- `php artisan tinker <file>` drops into the REPL after running the file and hangs a non-interactive shell. Use `tinker --execute="require '<path>';"`.
- Translation keys can't contain dots: an enum value like `order.created` must map to `order_created` in lang files.
- In Pest, Filament actions whose form includes a `CheckboxList` are driven with `mountAction()->fillForm()->callMountedAction()`; `callAction(data:)` fails with "mountedActionSchema0 not found".
- `ProviderCall` (and every tenant model) needs a `restaurant_id`: platform-level provider calls without a restaurant are not logged.
- Laravel passes no model class to class-level policy abilities (`viewAny`, `create`): one policy can't serve two models with different permissions. Use one policy per model (see `GrowthPolicy`).
- Arrow functions capture variables by value when they are created: a `fn () => … $array` helper won't see items appended later. Use a method that takes the array.
- Eloquent `firstOrCreate(['name->en' => …])` fails (mass assignment of the JSON path). Query with `where('name->en', …)` and create separately.
- Pest higher-order expectations can't reach a model attribute called `value` (`expect($m)->value` is the expectation's own value). Use `expect($m->value)`.
- Editing files with Python on Windows: open with `newline=''` (read and write), or text mode silently turns LF into CRLF. Pint fixes PHP files but not Blade.
- A Livewire public property wins over a view variable of the same name: never pass `'showing' => $model` to a view when the component has `public ?int $showing`.
- Product photos need `php artisan storage:link` locally (`public/storage` is git-ignored); seeder photos live in `database/seeders/images/` and go through `ProcessProductImage`.
- Services gate features: `Restaurant::hasService(Service)`; a `PlanFeature` with `service()` comes with that service, not the plan. Test restaurants from `restaurant()` get Drive-Thru; pass `services: [...]` or use `withService()`.
