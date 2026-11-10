# Dhawq Brand and Hero Slider Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rename the platform to ذوق / Dhawq with a new dhal-tile logo everywhere, and open the home page on a three-slide hero with live motion graphics, one slide per service.

**Architecture:**
- The name comes from `App\Support\Brand` (locale-aware) instead of `config('app.name')` or literal strings; language files use a `:brand` placeholder.
- The logo is one Blade SVG component. Static icons and the share image are generated from a master SVG by a committed script.
- The hero is a server-rendered Alpine carousel (`heroSlider`) built on today's `serviceShowcase` and the existing scene partials, which grow to hero size with three-beat choreography.

**Tech stack:** Laravel 13 Blade, Filament 5, Alpine, Tailwind 4, mPDF, Pest 5. Python with Pillow and Playwright, local only, for asset generation.

**Spec:** `docs/superpowers/specs/2026-11-10-dhawq-brand-and-hero-design.md`

## Global constraints

- **Names:**
  - Arabic: `ذوق`; Latin: `Dhawq`; both: `ذوق · Dhawq`; wordmark Latin: `DHAWQ`; `APP_NAME=Dhawq`.
  - The service "Drive-Thru" and restaurant names don't change.
- **Old names that must vanish** from `app/`, `lang/`, `resources/`, `config/`, `public/` (excluding `public/build`, `public/vendor`, `public/js/filament`, `public/css/filament`, `public/fonts`):
  - `Drive-Thru Plus`
  - `Drive-Thru+` (including the `Drive-Thru<span…>+</span>` markup)
  - `Drive⁠-⁠Thru Plus` (with word joiners)
  - `درايف ثرو بلس`
- **Colours:** unchanged.
  - Mark tile gradient: `#1E5D57` to `#082423`.
  - Brass stroke: `#D5B582`.
  - Hairline: white at 12%.
- **Motion:**
  - `transform`, `opacity` and `stroke-dashoffset` only;
  - easing `cubic-bezier(0.32,0.72,0,1)`;
  - 7 s per slide;
  - nothing moves under `prefers-reduced-motion`.
- **Copy:** every new string is in `lang/ar` and `lang/en` with Western digits. RTL uses logical properties, and arrow keys are mirrored in RTL.
- **No third-party requests;** no new npm or composer dependencies.

## Review focus

1. **Arabic page with Latin bits** (invoice numbers, URLs): the brand reads ذوق in Arabic flow, and `Brand::full()` keeps the order `ذوق · Dhawq` in both directions. Owned by Task 1 (`it keeps the bilingual brand in a fixed order`).
2. **The stored legal name was customised by the super admin:** the migration leaves it. Owned by Task 1.
3. **No JavaScript, or JavaScript is slow:** slide 1 is visible and the other slides are reachable. Owned by Task 3 (`it renders the first slide visible and the others inert without JavaScript`).
4. **Reduced motion:** no autoplay, final frames shown. Owned by Task 3 (a browser check and a CSS test asserting the media query is in the built CSS).
5. **Two marks on one page** (the header and the footer of the site) don't share a gradient id. Owned by Task 2 (`it gives each mark its own gradient id`).

---

### Task 1: Brand name everywhere

**Files:**
- Create:
  - `app/Support/Brand.php`
  - `tests/Feature/Brand/BrandNameTest.php`
  - migration `database/migrations/2026_11_10_100000_rename_platform_legal_name.php`
- Modify:
  - every `config('app.name')` in `resources/views/**` and `app/**` that a person sees (list it with `grep -rn "config('app.name')" app resources`): Filament `brandName` in `app/Filament/Concerns/ConfiguresPanel.php`, `components/layouts/public.blade.php`, `pdf/*.blade.php`, `home.blade.php` (JSON-LD), page titles in layouts, manifests and meta;
  - the language lines: `lang/{ar,en}/{access,billing,clients,developers,install,leads,site}.php` replace the brand with `:brand`, and their callers pass `'brand' => Brand::name()` (search each key's usages);
  - `app/Domain/Platform/PlatformSettings.php` (`billing.legal_name` default `Dhawq`);
  - `app/Domain/Integrations/Webhooks/DispatchWebhook.php` (`Dhawq is connected.`);
  - `public/sw.js` (the comment and the fallback title `Dhawq`);
  - code comments in `app/Domain/Services/Enums/Service.php` and `resources/css/tokens.css`;
  - `resources/views/install/layout.blade.php`;
  - `.env.example` (`APP_NAME=Dhawq`).

**Interfaces (produces):**
- `final class Brand`:
  - `static name(?string $locale = null): string` gives `ذوق` when the locale is `ar`, else `Dhawq`;
  - `static full(): string` gives `ذوق · Dhawq`;
  - `static latin(): string` gives `Dhawq`.
- Every `:brand` translation key requires its caller to pass `brand`.

- [ ] **Step 1: Write the failing tests** in `BrandNameTest.php`:
  - `it names the platform in each language`: under locale `ar`, `Brand::name()` is `ذوق`; under `en`, it is `Dhawq`; `Brand::name('ar')` gives `ذوق` whatever the locale.
  - `it keeps the bilingual brand in a fixed order`: `Brand::full()` is exactly `"ذوق · Dhawq"` in both locales.
  - `it no longer uses the old name anywhere`: scan the files under the paths and exclusions in the Global constraints with extensions php, js, json, css and svg. Assert that none matches `/Drive-Thru Plus|Drive-Thru\+|Drive-Thru<span[^>]*>\+|Drive\x{2060}-\x{2060}Thru Plus|درايف ثرو بلس/u`, and print the offending file:line.
  - `it greets invited people with the new brand`: `__('access.invitation.body', ['place' => 'Masar', 'brand' => Brand::name()])` contains `Dhawq` in `en` and `ذوق` in `ar`; repeat for `leads.whatsapp_greeting` and `clients.*.whatsapp_message`, using their real key paths.
  - `it shows the brand on the admin and workspace panels`: GET `/admin/login` and `/app/login` contain `Dhawq`.
  - `it renames the stored legal name only when it is the old default`: run the migration closure. A settings row `billing.legal_name = 'Drive-Thru Plus'` becomes `Dhawq`; a second case with `'Masar Holding W.L.L.'` is kept.
- [ ] **Step 2: Run** `vendor/bin/pest tests/Feature/Brand`. Expected: FAIL (`Brand` missing, old names found).
- [ ] **Step 3: Implement** `Brand` and every replacement in the Files list. Read the settings storage shape in `PlatformSettings` before writing the migration, which updates only an exact `Drive-Thru Plus`.
- [ ] **Step 4: Run** `vendor/bin/pest tests/Feature/Brand tests/Feature/Site tests/Feature/Access tests/Feature/Leads tests/Feature/Clients`. Expected: PASS. Then the full suite, since existing tests may assert the old name; update those assertions to `Dhawq`.
- [ ] **Step 5: Commit** `feat(brand): the platform is now ذوق / Dhawq`.

### Task 2: Logo, icons and PDFs

**Files:**
- Modify:
  - `resources/views/components/brand/mark.blade.php` (props: `class`, `animated` default false, `monochrome` default false);
  - `resources/views/components/brand/logo.blade.php` (the wordmark);
  - `resources/views/pdf/invoice.blade.php`, `pdf/qr-poster.blade.php`, `pdf/qr-sticker.blade.php` (an inline mark plus `Brand::full()`);
  - `resources/css/app.css` (the `.brand-dot-glow` keyframe, inside `prefers-reduced-motion: no-preference`);
  - the layouts' `<link rel="icon">`, `apple-touch-icon` and `og:image` tags.
- Create:
  - `resources/brand/dhawq-mark.svg` (the master, the same drawing as the component);
  - `scripts/brand-assets.py` (Pillow plus Playwright; renders the master to `public/favicon.svg` (copy), `favicon.ico` (16, 32, 48), `apple-touch-icon.png` (180), `public/icons/icon-192.png`, `icon-512.png`, `maskable-512.png` (safe zone 80%), and `public/images/site/og.png` (1200×630: the wordmark on ivory `#fcfbf8` with "Drive-Thru · Order-Ready Call · Rewards" in both languages));
  - `tests/Feature/Brand/LogoTest.php`.

**Interfaces:**
- Consumes `Brand` (Task 1).
- Produces:
  - `<x-brand.mark :animated="true" :monochrome="true" class="…" />`;
  - `<x-brand.logo subtitle="…" />` with an unchanged signature.

The drawing (40×40 viewBox):
```svg
<rect x="1" y="1" width="38" height="38" rx="12" fill="url(#{id})"/>
<rect x="1.5" y="1.5" width="37" height="37" rx="11.5" stroke="white" stroke-opacity=".12"/>
<path d="M14.5 15.5c5 1.2 9.8 3.9 11.2 8.6.9 3.1-.6 5.9-4.2 5.9H13" stroke="#D5B582" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
<circle cx="23.5" cy="10.5" r="2.4" fill="#D5B582"/>
```
Refine the dhal curve by eye at 16, 32 and 128 px before Step 4. `monochrome` drops the tile, and the stroke and dot use `currentColor`.

- [ ] **Step 1: Write the failing tests:**
  - `it gives each mark its own gradient id`: render `<x-brand.mark />` twice, and the two `id="dhawq-mark-…"` values differ.
  - `it shows the wordmark in the page language`: render `x-brand.logo` under `ar`, and `ذوق` comes before `DHAWQ` in the HTML; under `en`, `DHAWQ` comes first.
  - `it draws the mark in one colour for print`: the `monochrome` render contains `currentColor` and no `url(#`.
  - `it puts the brand on the invoice PDF and the QR poster`: the generated invoice HTML (the view rendered before mPDF) contains `Dhawq` and the mark's path `M14.5 15.5`; the poster contains `Brand::full()`.
  - `it ships the generated brand assets`: these files exist: `public/favicon.svg`, `favicon.ico`, `apple-touch-icon.png`, `icons/icon-192.png`, `icons/icon-512.png`, `icons/maskable-512.png` and `images/site/og.png`. `getimagesize` of `og.png` is 1200×630, and `favicon.svg` contains the dhal path.
  - `it points the site head at the new icons and share image`: GET `/` contains `apple-touch-icon.png` and `og.png` (absolute URL).
- [ ] **Step 2: Run** `vendor/bin/pest tests/Feature/Brand/LogoTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the components, the master SVG and the script, then run `python -I scripts/brand-assets.py`. Commit the generated files. The script is documented in the CLAUDE.md commands section.
- [ ] **Step 4: Run** the tests, then look at `favicon` at 16 px and the invoice PDF written to the scratchpad. Expected: PASS, and the mark is legible.
- [ ] **Step 5: Commit** `feat(brand): the dhal-tile logo, icons and PDFs`.

### Task 3: Hero slider with motion

**Files:**
- Create:
  - `resources/views/site/partials/hero.blade.php`;
  - `resources/js/hero.js` (imported in `resources/js/app.js` in place of `showcase.js`);
  - `tests/Feature/Site/HeroSliderTest.php`.
- Modify:
  - `resources/views/home.blade.php` (replace the hero section and the `@include('site.partials.showcase')` with `@include('site.partials.hero')`);
  - `resources/views/site/partials/scenes/{drive-thru,ready-call,rewards}.blade.php` (hero sizing and beat classes `.beat-1`/`.beat-2`/`.beat-3` with `--beat-delay`; the final frame is the static frame);
  - `resources/css/showcase.css` (rename the scope to `.hero-*`; beats play only when the slide has `.is-active` and `prefers-reduced-motion: no-preference`);
  - `lang/{ar,en}/site.php` (add `hero.slides.{drive_thru,ready_call,rewards}.{eyebrow,title,lead,cta,secondary,points.0,points.1,points.2}`, `hero.label`, `hero.pause`, `hero.play`, `hero.slide`; remove unused `hero.*`, `showcase.*` and `stage.*` keys).
- Delete:
  - `resources/views/site/partials/showcase.blade.php`;
  - `resources/js/showcase.js`;
  - any of their keys still used by service pages are kept: check `site/service.blade.php` before deleting.

**Interfaces:**
- Consumes `Service::forSale()` and `services` (`SiteController::shared()`), and the scene partials.
- Produces the Alpine component `heroSlider(count: number)` with:
  - state `active`, `stopped`, `hovered`, `focused`, `hidden`, `still` (reduced motion);
  - methods `show(i, {user})`, `next()`, `toggle()`, `key(e)` (Left/Right mirrored when `document.dir === 'rtl'`, Home/End), `swipeStart(e)`, `swipeEnd(e)`, `ended(e, i)`.

Copy (English; Arabic written natively, not translated word for word):

| Slide | eyebrow | title | lead |
|---|---|---|---|
| `drive_thru` | Drive-Thru | Orders that meet the car | Guests order and pay from their phone, and your window knows the moment they arrive. |
| `ready_call` | Order-Ready Call | A pager in every pocket | Hand out a QR, not a buzzer. Their phone rings the second the order is ready. |
| `rewards` | Rewards | Regulars, rewarded | Stamp cards and points that live on the guest’s phone, never lost and never faked. |

- `cta`: "Start free for :days days" (same for all).
- `secondary`: "See how it works".
- Three `points` per slide from that service's page features: reuse its `services.{key}.*` lines where they exist.

- [ ] **Step 1: Write the failing tests:**
  - `it opens the home page on three service slides`:
    - GET `/` in `en` has three `aria-roledescription="slide"` elements, with the three titles in order, and the region has `aria-roledescription="carousel"`;
    - `site.partials.showcase`'s old heading `__('site.services.title')` is gone, if that key was only used there;
    - in `ar` the three Arabic titles are present.
  - `it renders the first slide visible and the others inert without JavaScript`: the first slide has `is-active` and no `inert`; slides 2 and 3 have `inert`; each tab is an `<a href="#hero-{key}">` progressively enhanced into a button role.
  - `it sends each slide's main button to the request form with that service picked`: hrefs contain `#request` and `service=drive_thru`, `ready_call` and `rewards` respectively. When a service isn't for sale (use `Service::forSale()` mocked through the platform price setting), its main button goes to `#contact`.
  - `it keeps motion off for reduced-motion visitors`: the built `public/build/assets/app-*.css` contains `prefers-reduced-motion` with the hero beat rules (read the manifest to find the file).
- [ ] **Step 2: Run** `vendor/bin/pest tests/Feature/Site/HeroSliderTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement** the partial, copy, scenes, CSS and `hero.js`.
  - The layout follows the spec section 3 (desktop: text and scene side by side; phone: scene on top, compact pill tabs, 16 px gutter).
  - The headline and lead enter staggered by 80 ms after the slide becomes active.
  - Design with soft-skill and emil-design-eng: luxury, calm, generous whitespace, no default-template look.
  - `npm run build`.
- [ ] **Step 4: Run** `vendor/bin/pest tests/Feature/Site tests/Feature/Brand`. Expected: PASS.
- [ ] **Step 5: Commit** `feat(site): three-slide hero with motion, one slide per service`.

### Task 4: Quality gate, browser checks, docs, deploy

- [ ] **Step 1: Gate:**
  - `vendor/bin/pest --parallel` (all pass);
  - `vendor/bin/pint --test`;
  - `vendor/bin/phpstan analyse --memory-limit=2G` (0 errors);
  - `npm run build`.
- [ ] **Step 2: Browser checks** (a headless Playwright script in the scratchpad, against `http://localhost:8010`):
  - the home hero in `ar`/`en`, light/dark, at 375, 768 and 1280 px: screenshots of each slide;
  - autoplay advances after 7 s;
  - pause holds;
  - the Right key moves forward in `en` and back in `ar`;
  - `emulate_media(reduced_motion='reduce')` stops autoplay;
  - no sideways scrolling, no page errors or console warnings, no requests to other hosts;
  - also screenshot `/admin/login`, `/app/login`, the workspace header, a guest page footer ("powered by") and the invoice PDF as an image.
- [ ] **Step 3: Docs:**
  - `docs/CHANGELOG.md` entry;
  - `docs/ARCHITECTURE.md` §7 design system (the brand helper, the mark, the asset script, the hero);
  - `CLAUDE.md` lines: "Use `App\Support\Brand` for the platform name, never `config('app.name')` or a literal; `BrandNameTest` fails on the old name", and "Brand icons and the share image come from `python -I scripts/brand-assets.py`; edit `resources/brand/dhawq-mark.svg`, not the PNGs".
- [ ] **Step 4: Commit, push** `dhawq-brand`, and open a PR into `main`.
- [ ] **Step 5: Deploy** after the user merges:
  - set production `APP_NAME=Dhawq` with the panel's `set_env`;
  - deploy;
  - as the site user, run `php artisan migrate --force`, `npm ci && npm run build`, `php artisan optimize` and `view:cache`;
  - check that `https://www.dhawq.net/` shows `Dhawq`, the three slides, and the new favicon.
