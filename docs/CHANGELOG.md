# Changelog

All notable changes per phase. Newest first.

## 2026-10-28: Browser installer and MySQL/MariaDB readiness

### Built
- **`/install`:** a one-time installer for hosts without SSH.
  - It checks the server (PHP, extensions, writable folders).
  - It asks for a setup key that only someone with access to the server's files can read (`storage/app/install-key.txt`).
  - It tests the database, then saves the details, a new app key and production settings into `.env`. Every value is read back to prove it saved exactly.
  - It creates the tables and the super admin, then locks itself for good (`storage/app/installed.lock`) and deletes the key.
  - The page is in Arabic and English and works before the assets are built.

### Fixed (found by running everything on MariaDB, not just SQLite)
- 10 required date columns are now `dateTime`: on MariaDB/MySQL with older timestamp settings they failed to create, or silently reset to "now" on every update.
- Opening hours and menu schedules read MySQL `TIME` values (`07:00:00`) as `07:00`, so open/closed checks compare correctly.
- The product form's option-group ordering named its table, so the `sort` column is no longer ambiguous.

### Verified
- 659 tests pass on SQLite. The suite also ran on MariaDB 10.4, and every failure it found is fixed above.
- Migrations, demo seeding, the scheduled jobs and the installer itself ran end to end on MariaDB.

## 2026-10-27: Guests, campaigns and the counter stamp QR (Phase 7B, in progress)

### Built
- **Guests (CRM):** a guest list with segments, tier, visits and spend; a guest profile (orders, favourites, stamps, wallet, consent); a CSV export; "Guest rules" settings for the segment thresholds, quiet hours and the daily message limit.
- **Campaigns:**
  - Owners write an offer in Arabic and English and see a live preview of what each guest receives (with the SMS length).
  - They choose guest groups and see how many guests it reaches, add an optional gift, and send now or at a set time.
  - A test goes to the owner's own phone first.
  - Sending is gentle: only guests who agreed, never in quiet hours, at most one offer a day and three a week per guest, 30 a minute per restaurant, and within the daily limit.
  - Every message carries a stop link that works without signing in; a STOP / إيقاف reply on WhatsApp works too.
  - Results: sent, delivered, failed, skipped (and why), and the orders and revenue it brought back (its link, or within 7 days).
- **Counter stamp QR (Rewards):** a printable QR per branch. The guest scans, taps "Add my stamp", and the waiter approves it on the Stamps screen; the card fills live.
- **Stamp cards now match paper cards:** one circle per purchase, then a separate "Free" circle ("buy 9, the 10th is free").

### Verified
- 652 tests pass, Larastan level 8 clean, Pint clean.
- Browser: the campaign composer and campaign page in Arabic, the stop page and the stamp card on a phone.

## 2026-10-26: Services model, Order-Ready Call, stamp cards & the new website

### Built
- **Three services, sold separately:** Drive-Thru, Order-Ready Call and Rewards.
  - Each has a monthly price per branch and a free demo.
  - Taking two or three services applies a bundle discount.
  - Plans became tiers: branch and staff limits plus add-ons.
  - The admin adds services to clients: demo, activate, extend, change the price, end.
  - Owners try, keep, add and cancel from Billing.
  - Billing charges added services pro rata, settles demos daily and records every change in the audit log.
- **Order-Ready Call:**
  - A counter pager screen.
  - A guest pager page that rings, vibrates and keeps the screen awake.
  - WhatsApp/SMS when the order is ready.
  - A ready board for the waiting-area TV.
- **Stamp cards (Rewards):**
  - "Buy 5, the 6th is free", with a daily cap and carry-over.
  - Stamps come back off after a cancellation or refund.
  - Stamps can be added at the counter, by the guest's phone number.
  - Guests see their cards, and checkout shows "1 more to go".
- **Website:**
  - A new home page, a page for each service and a pricing page.
  - A live price calculator fed by the admin's price list.
  - Plan tiers, FAQs and the demo form, which now records the services asked for.
  - Light, dark or "match my device", with no flash on load.
  - A sitemap with Arabic and English links, and structured data for search engines.
- **Karak House demo menu:** 17 photographed dishes, with photos stored and served by the app itself. The branch menu page was redesigned (photo hero, "Most loved" carousel, category chips that follow the scroll).
- **CRM foundations (7B task 1):** guest stats from picked-up orders, and an offers opt-in with proof of consent at checkout and on the account page.

### Fixed during verification
- **Floating ticket in the hero:** it covered the pager number, so it moved on desktop and is hidden on phones.
- **Brand name in Arabic:** "Drive-Thru Plus" broke at its hyphen, so it now stays on one line.
- **Pager counter:** a view variable clashed with a Livewire property of the same name and broke the screen.

## 2026-10-12: Phase 7A, Loyalty, tiers, rewards & promotions

### Built
- **Points program per restaurant**, with no default values: it stays off until the owner sets every one.
  - Points are earned at pick-up only, on what was actually paid, with earn boosts (product, category, branch) and the guest's tier multiplier.
  - Points come back off after a refund, in proportion, and expire after the chosen months, soonest-expiring first.
  - Guests can spend points at checkout up to the owner's cap, in whole steps.
- **Tiers:** Bronze to Platinum, or the owner's own levels, from points earned in a rolling window, with benefits shown to the guest. The nightly `loyalty:maintain` job moves guests between tiers and expires old points and rewards.
- **Rewards catalog and wallet:** free item, % off, amount off and bonus points, with expiry, stock and minimum order. Guests claim rewards with points; bonus points are only given.
- **Promotions engine:** % off, amount off, buy X get Y, free item, combo price and bonus points.
  - Limited to branches, categories or products, dates, days and hours in the restaurant's time zone (windows may cross midnight), first order, birthday (±3 days), minimum order, and total and per-guest limits.
  - Clear stacking rules: an offer that doesn't combine stands alone when it saves more.
- **Coupon codes:** your own or generated in batches of up to 500 (no look-alike characters), with limits, expiry and CSV download.
- **Guest experience:**
  - A savings card at checkout (code, wallet reward, a points switch showing exactly what it takes off).
  - Itemised discounts in the total and "you'll earn about N points".
  - A new Rewards page with balance, tier progress, wallet, catalog and points history.
  - Discounts and points on the order page and receipt.
- **Server-side safety:** checkout re-prices everything when the order is placed. Points, the last use of a code and wallet rewards are locked, so two orders can't spend the same thing; the loser sees "your total changed". Cancelling gives back the points, the reward and the code use.
- **Owner workspace:** a new "Loyalty & offers" group with Loyalty (program, tiers, earn boosts, live example, adjusting a guest's points with a reason), Rewards and Promotions (with a Codes tab). Everything is audited, gated by plan features and the loyalty/promotions permissions.
- **Integrations:** orders in the API and webhooks now include their discount lines and points.
- **Quality:** 536 Pest tests (up from 470), including property-style tests on stacking (500 random carts), the points ledger (300 random operations), pricing (300 random carts) and earn/full-refund round trips. Larastan level 8 with 0 errors, Pint.

### Fixed during verification
- **Points were capped on the amount before a wallet reward**, letting guests use more points than the owner's cap allowed. The cap now applies to what is left after the code and the reward.
- **The rewards catalog loaded each free item's product separately** (N+1, refused by the app). It's now loaded up front.
- **An order could be charged a total the guest never saw** when an automatic offer ended (or a price changed) while they were on the checkout page. Checkout now sends the total it showed, and a different server total is refused with "your total changed"; the next tap places it at the new total.

## 2026-10-11: Phase 6, Payments, notifications & integrations

### Built
- **SADAD payments (Qatar):** each restaurant connects its own merchant account (encrypted, write-only keys, test/live). Signed web checkout; payments confirmed by a checksum-verified callback and webhook; refunds; status lookup; fees and provider transaction recorded. Unpaid orders are checked with SADAD before they're cancelled. A payment that arrives after an order was cancelled is refunded automatically. The Fake gateway stays for demos outside production.
- **WhatsApp + SMS:** login codes, "order received", "order ready" and receipts on WhatsApp Cloud API templates, with SMSala as the SMS fallback. Every attempt is logged with a masked number and its delivery status. Guests can turn order updates and receipts off.
- **Alerts for the team:** late orders, guests waiting at the window, an overloaded kitchen, failed refunds and webhooks switched off. They go to the people who run that branch, in each person's language: the bell with a live chime and toast, web push per device, WhatsApp (opt-in) and email for critical ones. Restaurants set **quiet hours**; each person picks channels under **Account → Alerts**.
- **Web push:** guests can get "your order is ready" with the page closed; staff get alerts on every browser where they turned them on.
- **Refunds at the live board**, and a **staff PIN on shared screens** for refunds and status overrides. The action is recorded under the person, and wrong PINs lock the screen for 5 minutes.
- **Open to other systems:** signed **webhooks** (retries up to 12 h, delivery log with the exact body and answer, replay, test ping, automatic switch-off), a **public REST API v1** (API keys per system with scopes, rate limits, OpenAPI JSON and a bilingual `/developers/api` page), and a generic **POS connection** that pushes accepted orders and keeps the POS reference on the order.
- **Printing:** 80 mm kitchen tickets and receipts through PrintNode or the browser, with auto-print on accept per branch.
- **Platform Health** for the super admin: success rate, speed and last error per provider, platform setup checks, and an alert when a provider keeps failing.
- **New permission:** "Integrations & API" (owners have it; general managers don't by default).
- **Quality:** 470 Pest tests (up from 401), Larastan level 8 with 0 errors, Pint.

### Fixed during verification
- Webhook event names contain dots, so their labels showed raw translation keys. Labels now use dot-free keys, and a test covers every event and scope in both languages.
- A webhook address that can't be reached only showed a passing toast. It's now an inline error on the field.
- The developer page header wrapped the logo on phones. The optional `openapi.json` link is hidden on small screens.
- Counter payments could have been sent to the restaurant's online gateway for a refund. They now never reach a provider.
- The overloaded-kitchen check never fired on SQLite (a bound number was compared as text), and it read a stale cached queue in long-running workers. Both fixed and tested.

## 2026-10-11: Phase 5, Live operations

### Built
- **Order state machine:** a held **Scheduled** status for later starts and "when I'm on my way"; arrival and "on the way" as recorded facts; status times; **compare-and-set** saves so a double tap or a stale screen fails cleanly; a **database guard** on status values; override, re-enable and reset as new with a reason and an audit entry. Held orders are released at their start time by a queued job, with an `orders:release` sweep every minute.
- **Realtime with Reverb:** branch, restaurant, order and platform channels with id-only payloads. Screens and guests are signed off at `/realtime/auth`, staff at `/broadcasting/auth`. Every screen resyncs after a reconnect and keeps a slow poll as a safety net.
- **Kitchen display**, **kitchen stations**, **live board** (cashier/manager), **pickup window** (QR scan or code → Paid ✓ Verified ✓ Ready ✓ → complete), **TV command center** and **all-branches screen**. They run on paired devices (`/screen`) and for signed-in staff (`/ops/…`), linked from a new **Live operations** sidebar group shown per permission.
- **Guest live page:** realtime updates, chime and vibration, a **full-screen Ready alert** with the pickup code, optional notification, **I'm here** with lane, **opt-in live location** with distance and ETA, an approach alert that starts the kitchen, and **auto-arrival** inside the branch radius. Signed staff links can follow an order live.
- **Dynamic prep estimate and busy level** from the last 3 hours of real prep times, the live queue and busy mode, used everywhere a wait is shown.
- **Super Admin:** "Live data connected" with live order activity across restaurants.
- **Quality:** 401 Pest tests (up from 263), including every status pair and override pair, the compare-and-set race, the database guard, channel access across restaurants, station completion, pickup verification and auto-arrival with simulated coordinates. Larastan level 8 with 0 errors, Pint.

### Measured in the browser (dev server)
- Guest taps "Place order" → the kitchen and cashier tabs receive the event in **968 ms**, including placing the order on the single-threaded PHP dev server; the cashier board shows it about 0.6 s later.
- Kitchen taps Start / Ready → the guest's page receives the event in **509 / 517 ms** and shows the new status at **882 / 885 ms**; the Ready alert opens.

### Fixed during verification
- **The live board overflowed on phones and tablets.** A visually hidden table label is absolutely positioned and escaped its scroll container; the scroll wrappers are now positioned.
- **The guest page threw a script error on live refreshes.** The one-time "Order placed" celebration was rendered again on every refresh; it now plays once.
- **The overview screen was too open.** Anyone with "view orders" across all branches could see every branch's sales; it now needs reports access and coverage of every branch.
- **Unpaid counter orders read "Pay at the window · Pay at pickup".** Staff now see "To collect".
- **Guests reaching pages outside the panels** (ops screens, invoice PDFs) are sent to the restaurant sign-in instead of an error.

## 2026-10-11: Phase 4, Customer ordering app (PWA)

### Built
- **Guest app** on the restaurant's own links (`/{restaurant}`, `/{restaurant}/{branch}`, `/o/{code}`), mobile-first with a bottom tab bar (Home, Menu, Orders, Account) and the restaurant's colour.
- **Home:** greeting, the live order first, **"Your usual?"** in one tap, favourites, popular and new, and every branch with open state, wait, busy level, distance (opt-in), call and directions.
- **Menu and product sheet:** search, section chips, hours panel, live-priced options with validation, nested follow-ups, meal slots, notes, favourites, then **make it a meal** (with savings) or **complete your meal**.
- **Checkout on one page:** bag with per-line problems, "goes well with", inline **phone code sign-in** (only the first time), car, lane, **now / later / when I'm on my way / scheduled**, payment, notes, server totals. Place order is **idempotent**. A full kitchen suggests the next free time in one tap; a closed branch offers scheduling only.
- **Payments** through the Fake gateway: card with a 3-D Secure page, Apple Pay, Google Pay, pay at the window; retry after a failure.
- **Order page:** success animation, timeline, ready time, **pickup code + QR**, share link, "I'm on the way", cancel within the window, order again, receipt. Shared links show progress only.
- **Orders history, account** (name, birthday, language, cars, devices, favourites) and a printable bilingual **receipt**.
- **PWA:** per-restaurant manifest, service worker that never caches pages, bilingual offline page, polite install prompt after the first order.
- **Quality:** 263 Pest tests (up from 224), including the full new-guest and returning-guest flows, double tap, repricing, sold out, closed branch, full kitchen, code limits, payments, cancel window and cross-restaurant isolation. Larastan level 8 with 0 errors, Pint.

### Fixed during verification
- **Capacity suggestions could be refused.** The next free slot could be less than 15 minutes away, which scheduled orders don't accept. The suggestion now moves to the first free slot the server accepts, and the message shows that time (test added).
- **Guest sign-in failed inside Livewire tests.** `CustomerAuth` read the session from the injected request; it now uses the session store directly.
- **Short links typed in lowercase** returned 404; `/o/{code}` now accepts both cases.
- **The branch page lost its opening hours and directions** when the menu replaced it; they are back in a compact panel.
- **"Add to bag" wrapped onto two lines** at 375 px; sheets now skip their slide-in for reduced-motion users.
- **The payment line repeated itself** for pay-at-pickup orders ("Pay at the window · Pay at pickup").
- **Local environment:** the dev database was missing the ordering tables (tests use their own database), and the dev server ran without OPcache (about 1 s per request) and stalled on idle browser sockets. Both are fixed and documented in CLAUDE.md, with a small proxy for browser checks.

## 2026-10-11: Phase 3, Menu engine

### Built
- **Catalog:** sections (emoji, image, schedule, drag to reorder), items (bilingual text, price with discount and a live "Guests pay…" line, photo, calories, prep time, allergens with icons, tags, highlights, SKU), reusable **option groups** (choices or "remove ingredients", required/single/multiple rules, defaults, price and calorie changes, nested follow-ups), **meals** with slots, upcharges and per-branch savings, "make it a meal" links and "goes well with" suggestions.
- **Schedules:** breakfast, late night and any weekly window, including past midnight.
- **Branch menu:** a branch-first screen for the shift: out of stock today (back at local midnight by itself), daily stock that refills every morning with a once-a-day low-stock alert, branch price, serve/don't serve, and bulk actions. Branch managers stay within their branches.
- **Bulk tools:** price update by % or amount with friendly rounding and a full preview; duplicate; move; switch on or off.
- **Excel:** export, template and all-or-nothing import that lists every problem by row.
- **Photos** become 400/800 px WebP and AVIF automatically.
- **Menu design** settings and **Preview as guest**.
- **Guest menu** on every branch page: sticky section chips, instant bilingual search, discounts, meal savings, allergen and tag icons, and greyed sold-out or "not now" items. Sections that can't be ordered right now move to the end.
- **Caching:** the catalog is cached per restaurant under a version that any menu change bumps; branch stock is always read live.
- **Demo menu:** 50 bilingual items with option groups, meals, a breakfast schedule and branch-specific stock.
- **Quality:** 224 Pest tests (up from 196), including time-travel tests across each branch's local midnight, schedules past midnight and an Excel round trip. Larastan level 8 with 0 errors, Pint.

### Fixed during verification
- **The cached menu came back broken in development.** Laravel 13 refuses to unserialize cached objects unless their classes are allow-listed, and tests had used the non-serializing array cache.
  - Fix: `cache.serializable_classes` now lists exactly the menu classes.
  - A regression test on the file store fails without the fix.
- **The guest page failed to render.** An inline `@php(...)` followed later by a `@php … @endphp` block made Blade swallow everything between them. The inline form is now a block.
- **The item editor crashed.** Filament injects closure arguments by name: a `Builder $q` parameter received an empty container-built query. Renamed to `$query`.
- **Polish:** hid the grouping controls and the empty photo column, and widened item names on the busy tables.

## 2026-10-11: Phase 2, Restaurant workspace

### Built
- **Branches:**
  - live cards showing open / busy / paused / closed / holiday, prep time, lanes, screens and "closes at"
  - a four-tab slide-over editor: details, hours (split and past-midnight shifts, closures), pickup & kitchen (lanes, curbside spots, stations, capacity), arrival tracking
  - "use my current location"
  - **live controls:** pause, busy mode, resume, switch off. Each is one tap and audited.
- **`BranchSchedule`:** one source of truth for a branch's state, closing time, next opening and "taking orders again at", always in the restaurant's timezone.
- **Team:**
  - invite by email or WhatsApp link into a position and one or many branches; suspend, reactivate or remove (signs out everywhere); resend invite or password link; screen PINs (4–6 digits, unique, hashed, weak PINs refused)
  - **escalation guard:** nobody grants or edits more power than they hold, and branch managers stay inside their branches
- **Positions:** tune or restore the 7 built-ins; build custom ones from grouped, described permissions. Permissions you don't hold are shown but locked.
- **Screens:**
  - one-time 6-digit pairing at `/pair`, then a long-lived device sign-in
  - the standby view keeps the screen awake, sends a heartbeat and reconnects on its own
  - revoke or pair a replacement; online / offline / waiting status
- **Restaurant settings:** brand (logo, cover, accent), region (language, timezone, currency), ordering rules (tax, service fee, menu-link mode, cancellation window, loyalty rate, override toggle).
- **Links & QR:** copy/open every link; QR codes generated locally as PNG, SVG or a bilingual A5 print poster.
- **Public pages:** restaurant and branch pages behind every link (live status, hours, closures, directions, tap-to-call), plus friendly not-found and temporarily-unavailable pages. Platform paths can never be shadowed by a restaurant slug.
- **Dashboard:** live stats and a go-live checklist with a progress ring, computed from real data.
- **Plan limits:** branches and team members are enforced in the domain, with usage in page headers and a friendly upgrade prompt.
- **Demo data:**
  - Masar has 3 branches (one with split shifts), a team spread across branches, PINs and a paired kitchen tablet
  - Sahra and Karak have their own branches
- **Quality:** 196 Pest tests (up from 148), Larastan level 8 with 0 errors, Pint.

### Fixed during verification
- Owners saw an empty branch list: "all branches" (`null`) was being turned into "no branches" (`[]`). Caught by the isolation test and fixed in `Workspace::branchIds()`.
- Opening hours displayed 3 hours late: the time picker converted wall-clock times between timezones. Fixed by pinning the picker to UTC; a regression test fails without the fix.
- The "use my current location" button broke Alpine with a double-quoted message inside an HTML attribute. Fixed by switching to `Js::from()`.
- A paused branch showed its next shift instead of the end of the pause. Fixed with the new `reopensAt()`, which handles a pause that outlasts the shift.
- Letter-spacing broke the joined Arabic letters on the QR poster. Spacing now applies to Latin text only.
- The setup checklist and the restaurant hero were cramped or bled colour. Fixed with container queries and `overflow-hidden`.
- Asking for a fresh pairing code on an unpaired screen showed an unnecessary confirmation. It is now instant.

## 2026-10-10: Phase 1, Super Admin & SaaS core

### Built
- **Clients:**
  - card grid with status tabs and a three-step onboarding wizard (restaurant → owner → plan)
  - client page with subscription, owner, billing details, features and notes, plus Team, Invoices and Activity tabs
  - change plan, issue invoice, suspend/reactivate, resend owner invitation (email + WhatsApp link)
- **View as owner:** reason required, floating "Return to platform" pill, sensitive account pages blocked, both ends audited.
- **Billing:**
  - plans (Starter / Growth / Enterprise) with limits, trials and feature flags
  - subscriptions with a daily billing cycle: trial → active → past due → grace → suspended; paying reactivates
  - gap-free invoice numbers and bilingual PDF invoices by email
  - owner Billing page in the workspace
- **Growth:** lead pipeline with notes, assignee, follow-ups, WhatsApp, and one-click **Convert to client**. Landing-page "Request access" form with honeypot, rate limits and dedupe; notifies sales and acknowledges the visitor in their language.
- **Platform team:** staff invitations, custom roles with a permission matrix, suspend/reactivate, unlock sign-in, reset 2FA.
- **Platform settings:** billing rules, invoice details, bank details, order override, OTP channels, maintenance banner.
- **Announcements:** scheduled and targeted; owners can dismiss them in their workspace.
- **Security center:** alerts inbox with unlock, platform-wide active sessions, and an audit log viewer with filters and readable diffs.
- **Authorization:** a policy for every admin resource. `Gate::before` was removed, so the policies are the single source of truth.
- **Quality:** 148 Pest tests (up from 70), Larastan level 8 with 0 errors, Pint. A translation-key test scans the code for missing ar/en keys.

### Fixed during verification
- Admin navigation groups showed Arabic on English pages, because Filament reads enum labels once at boot. Fixed with lazily resolved group labels and a regression test.
- Workspace notices sat flush under the top bar, because the panel theme didn't scan Livewire views for Tailwind classes. Fixed.
- Phone numbers were mirrored on Arabic pages ("6330 5492 974+"). They are now wrapped in Unicode LTR isolates via `App\Support\Bidi`; copying still gets the raw number.
- The "convert lead" uniqueness check targeted the wrong table inside the record modal. Fixed.
- The impersonation banner covered page content. Fixed by turning it into a floating pill.
- Billing tiles were cramped, money values wrapped mid-word, and the announcements title column was too narrow. All re-laid out.
- The Windows dev server timed out while compiling views on first load. Fixed with `composer warm` and a project-root `server.php` for PHP's built-in server.

## 2026-10-10: Phase 0, Foundation

### Built
- **Laravel 13 + Filament 5 + Livewire 4** project with two panels: `/admin` (platform) and `/app/{restaurant}` (tenant workspace).
- **Multi-tenancy:** `Restaurant` tenant, `TenantContext`, `BelongsToRestaurant` (query scope, auto-fill, guard against cross-tenant moves), plus an architecture test that fails CI if any tenant model skips isolation.
- **Access model:** `PlatformPermission` (11) and `RestaurantPermission` (23) enums with Arabic/English labels and descriptions; `PlatformRole` for platform staff; `Position` with 7 built-in positions created for every restaurant; owner flag; gates for every permission.
- **Security:**
  - brute-force lockout per email and per IP, with hashed keys
  - security alerts with a bell notification to super admins
  - optional authenticator-app 2FA with recovery codes, encrypted at rest
  - Devices & sessions page (sign out one device or all others)
  - `platform:install` command, the only route to super admin
  - strict Eloquent in development, HTTPS and strong passwords in production
- **Localization:** Arabic (default, RTL) and English; remembered switcher; Western digits everywhere (Arabic-Indic input converted); translated validation messages and error pages; ar/en key-parity test.
- **Design system:** shared tokens (midnight ink, ice, ivory, deep teal, brass, sage, order-status semantics, radii, layered shadows, spring easings); self-hosted Geist / IBM Plex Sans Arabic / Fraunces / Alexandria; luxury Filament theme (floating midnight rail, brass active state, glass topbar, sign-in canvas with grain); bilingual landing page; branded error pages; brand mark and favicon; local initials avatars.
- **Dashboards with live data:**
  - Platform: live restaurants, team accounts, security alerts, locked sign-ins
  - Restaurant: your role, active team, positions, account status
  - Both greet the user by time of day in the restaurant's timezone
- **Quality:** 70 Pest tests, Larastan level 8 with 0 errors, Pint, GitHub Actions CI.
- **Docs:** README, ARCHITECTURE.

### Fixed during verification
- Filament JS assets were never published (an installer step timed out), so buttons and fields were inert. Fixed by publishing them and adding `filament:upgrade` to the Composer hook, plus a regression test.
- Arabic headline lines collided. Fixed with Arabic-specific line-height and sizes.
- Permission keys containing dots broke translation lookups. Fixed by reading the scope array directly.
- Freshly created users failed under strict mode because default attributes weren't loaded. Fixed with complete model defaults.
- 404 pages ignored the visitor's language. Fixed by running locale detection globally.
- Avatars called the third-party ui-avatars.com, leaking staff initials. Replaced with local SVG initials.
- Stats widgets flashed an empty card. Fixed by rendering them eagerly. Stats use 2 columns on phones.

## 2026-10-10: Planning
- Audited the reference repository `alsraiyagroupcom5/drive-thru-plus` (TanStack Start + React + Supabase, built in Lovable) and listed every built, partial and missing feature, plus 11 defects to avoid.
- Wrote `docs/MASTER_PROMPT.md` (full Laravel spec with improvements), `docs/ROADMAP.md` (12 phases with kickoff prompts) and `CLAUDE.md` (project rules).
