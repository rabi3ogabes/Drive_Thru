# Owner Guide Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An in-workspace guide, in Arabic and English, that explains every page, every button and every branch screen, with a "?" help button on each page that opens that page's article.

**Architecture:**
- `App\Domain\Guide\GuideCatalog` lists every article: a key, its sidebar section, the page or resource class it explains, the permission and service it needs, and its route.
- The words live in `lang/{ar,en}/guide.php` under `articles.<key>`: `title`, `summary`, `when`, `actions` (label → what it does), `tips`.
- A Filament page `Guide` (`/app/{restaurant}/guide`) shows "Start here", searchable sections and articles.
- A Livewire `GuidePanel` slide-over opens one article from the "?" button. The button is rendered by a `PAGE_HEADER_ACTIONS_AFTER` render hook that looks up the current page in the catalog.

**Tech stack:** Filament 5 pages and render hooks, Livewire 4, Alpine, Tailwind 4, Pest 5.

**Spec:** the design approved in chat on 2026-11-05. It is:
1. a Guide page with "Start here", grouped articles (what, when, every button, tips, who can use it, open this page) and instant search;
2. a "?" on every page opening its article in a side panel;
3. Arabic and English with RTL;
4. a test that fails when a workspace page has no article in both languages;
5. articles for features the restaurant doesn't have show "Not on your plan";
6. text in lang files only.

## Global constraints

- Every string in `lang/ar/guide.php` and `lang/en/guide.php`. Both files have the same keys (a test compares them). Western digits.
- Design tokens only; light and dark; 375/768/1280 px; logical CSS properties.
- No N+1: the catalog is static PHP and the search runs in Alpine on the rendered list.
- The guide is visible to anyone who can open the workspace. Each article shows the permission label (from `RestaurantPermission::getLabel()`), never raw keys.

## Review focus

1. **A page added later without an article:** the completeness test lists every `Filament::getPanel('app')` page and resource and fails, naming the class.
2. **Arabic and English key drift:** the parity test flattens both files and fails on any key present in only one of them.
3. **Search in Arabic with or without diacritics or alef forms** (أ/ا/إ, ة/ه): normalised in the Alpine search, so "اعاده" finds "إعادة".
4. **A "?" on a page the catalog maps to an article the user can't open:** the panel still explains it, and its "Open this page" button is hidden when the user can't access the page.
5. **The slide-over over the tab bar or under modals:** it uses Filament's own slide-over modal, so its stacking order is correct.

---

### Task 1: Catalog, words, completeness and parity tests

**Files:**
- Create: `app/Domain/Guide/GuideCatalog.php`.
- Create: `app/Domain/Guide/GuideArticle.php` (readonly value object).
- Create: `lang/en/guide.php`, `lang/ar/guide.php`.
- Test: `tests/Feature/Guide/GuideCatalogTest.php`.

**Interfaces (produces):**
- `GuideArticle`: `key`, `section` (one of `start`, `operations`, `menu`, `growth`, `branches`, `team`, `restaurant`, `screens`, `guests`), `?class-string $page`, `?RestaurantPermission $permission`, `?Service $service`, `?PlanFeature $feature`.
  - `title()`, `summary()`, `when()`, `actions(): array<string,string>`, `tips(): list<string>`, `url(Restaurant): ?string`, `availableTo(Restaurant): bool`.
- `GuideCatalog::all(): list<GuideArticle>` (display order).
- `GuideCatalog::sections(): array<string, list<GuideArticle>>`.
- `GuideCatalog::forPage(string $class): ?GuideArticle`. Resource page classes resolve to their resource's article.
- `GuideCatalog::find(string $key): ?GuideArticle`.

- [ ] **Step 1: Write the failing tests:**
  - `it('has an article for every workspace page and resource')`: iterate the app panel's `getPages()` and `getResources()` (skipping the Guide page itself) and assert `GuideCatalog::forPage()` returns an article. On failure, print the missing classes.
  - `it('has every article in Arabic and English with title, summary and at least one action')`.
  - `it('has the same keys in both language files')`: flatten with `Arr::dot`.
  - `it('covers the branch screens and the guest pages')`: sections `screens` and `guests` are not empty.
- [ ] **Step 2: Run them**, expect FAIL.
- [ ] **Step 3: Write** the catalog (one entry per page and resource, plus the screens and guest-page articles and the start-here steps) and both lang files. The wording comes from the real labels each page uses.
- [ ] **Step 4: Run them**, expect PASS. Commit `feat(guide): catalog and bilingual articles for every workspace page`.

### Task 2: Guide page and contextual "?" panel

**Files:**
- Create: `app/Filament/App/Pages/Guide.php`.
- Create: `resources/views/filament/app/pages/guide.blade.php`.
- Create: `resources/views/filament/app/guide/article.blade.php` (shared partial).
- Create: `app/Livewire/GuidePanel.php` and `resources/views/livewire/guide-panel.blade.php`.
- Modify: `app/Providers/Filament/AppPanelProvider.php` (register the page, the Livewire component, and the `PAGE_HEADER_ACTIONS_AFTER` hook rendering `filament.app.guide.help-button`).
- Test: `tests/Feature/Guide/GuidePageTest.php`.

- [ ] **Step 1: Write the failing tests:**
  - `it('shows start here, every section and articles in the owner language')`: AR and EN.
  - `it('marks articles for services the restaurant does not have as not on your plan')`.
  - `it('shows a help button on workspace pages that opens that page article')`:
    - GET the Ratings list page and see the button with `data-guide="ratings"`;
    - `Livewire::test(GuidePanel::class)->call('open', 'ratings')` shows its title and actions.
  - `it('hides open this page when the user cannot open it')`: a cashier and Billing.
  - `it('keeps the guide to signed-in workspace users')`: a guest gets redirected to login.
- [ ] **Step 2: Run them**, expect FAIL.
- [ ] **Step 3: Implement** the page and panel:
  - **Guide page:** navigation in the Restaurant group, last, icon `heroicon-o-book-open`.
    - Hero with search; a "Start here" path of 7 numbered steps, each linking to its page.
    - Section cards, and an article accordion (one open at a time, deep-linkable `#key`).
    - The search normalises Arabic (strips tashkeel, unifies alef, ta marbuta, ya) and shows an empty state.
  - **GuidePanel:** listens for `open-guide` with a key and opens a Filament slide-over with the article partial and a "Read the full guide" link.
- [ ] **Step 4: Run them**, expect PASS. Then Pint, Larastan and `npm run build`; the theme `@source` already covers `resources/views/filament/**`.
- [ ] **Step 5: Commit** `feat(guide): workspace guide page and help panel on every page`.

### Task 3: Browser checks, docs, deploy

- [ ] **Step 1: Browser checks:**
  - the Guide page and the "?" panel on 4 pages (Dashboard, Ratings, a ticket, Loyalty) in AR and EN, light and dark, at 375/768/1280;
  - search in Arabic (with alef variants) and English;
  - no overflow, no console errors.
- [ ] **Step 2: Docs:** CHANGELOG entry; CLAUDE.md line ("every new workspace page needs a GuideCatalog entry and guide.php text, or GuideCatalogTest fails").
- [ ] **Step 3: Gate:** full suite, Pint, Larastan, build. Commit, push `phase-7c`.
- [ ] **Step 4: Deploy:**
  - deploy `dhawq-qatar` from `phase-7c`;
  - run `npm ci && npm run build`, `php artisan optimize`, `view:cache` as the site user;
  - check https://www.dhawq.net/app/rabii/guide answers.
