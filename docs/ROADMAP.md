# Drive-Thru Plus: Phased Roadmap

> Follow the phases **in order**. Each phase ends with a working, tested, demoable system.
> At the start of each Claude session, paste the phase's **Kickoff prompt**. At the end, Claude ticks the boxes here and writes `docs/CHANGELOG.md`.
> Legend: `[ ]` todo · `[~]` in progress · `[x]` done and verified in the browser.

| # | Phase | Outcome you can demo | Est. sessions |
|---|---|---|---|
| 0 | Foundation | Bilingual app shell, design system, auth, tenancy skeleton, CI | 2-3 |
| 1 | Super Admin & SaaS core | Clients, plans, platform staff, leads, impersonation, security | 3-4 |
| 2 | Restaurant workspace | Onboarding, branches, positions, employees, devices, branding | 3-4 |
| 3 | Menu engine | Categories, products, modifiers, combos, availability, stock, layout | 3-4 |
| 4 | Customer ordering app | QR → menu → cart → OTP → vehicle → pay → confirmation | 4-5 |
| 5 | Live operations | State machine, KDS, cashier board, tracking, arrival, pickup QR, TV | 4-5 |
| 6 | Payments, notifications & integrations | Real gateway, WhatsApp/SMS, push, webhooks, POS API, printing | 3-4 |
| 7 | Loyalty, promotions & growth | Points, tiers, wallet, coupons, referral, CRM, campaigns, QR analytics | 3-4 |
| 8 | Reports, finance & intelligence | Dashboards, accounting, exports, scheduled reports, alerts, insights | 3-4 |
| 9 | Marketing website & CMS | Luxury public site, 2 themes, CMS, lead funnel, SEO | 2-3 |
| 10 | AI & smart features | Recommendations, smart search, AI assistant, AI menu tools, AI insights | 2-3 |
| 11 | Hardening & launch | Security audit, performance, accessibility, backups, deploy, docs | 2-3 |

---

## Phase 0: Foundation

**Goal:** a clean, bilingual, tested skeleton that every later phase builds on.

- [x] Laravel 13 / PHP 8.4 project, `.env.example` complete, `composer setup` script. Reverb package installed (wired up in Phase 5).
  - [ ] **Deferred to Phase 11:** MySQL, Redis, Horizon and Pulse. Redis isn't installed on this machine and XAMPP's MariaDB 10.4 is too old for Laravel 13, so local dev uses SQLite + database queue/cache/session.
- [x] Domain folder structure (`app/Domain/*`), conventions documented in `docs/ARCHITECTURE.md`.
- [x] Tooling: Pest 5, Larastan **level 8 (0 errors)**, Pint, GitHub Actions CI (lint + static analysis + tests).
  - [ ] Pre-commit hook. Will add once the repo is under git.
- [x] Design tokens (Tailwind 4 `@theme`): color, type, radius, shadow, motion. Light/dark. Self-hosted fonts: **Geist** (replaces Inter, which the luxury design skill rejects as generic), Fraunces, IBM Plex Sans Arabic, Alexandria.
- [x] Filament 5 with **two panels** (`/admin`, `/app/{restaurant}`), shared custom theme (floating midnight rail, brass accents), RTL verified in the browser.
- [x] Localization: `ar` (default) / `en`, remembered switcher, `dir` on `<html>`, Western digits in and out, Carbon locale, Arabic validation messages, translated 404s, ar/en key-parity test.
- [x] Auth: staff login, lockout (5 fails per email / 20 per IP in 15 min → 30 min, HMAC-hashed keys), security alert + super-admin bell notification, password reset, optional TOTP 2FA with recovery codes, Devices & sessions page.
- [x] Tenancy: `Restaurant`, `TenantContext`, `BelongsToRestaurant` (scope + auto-fill + cross-tenant move guard), **arch test**, isolation tests.
- [x] Permissions: enum-backed `PlatformPermission` / `RestaurantPermission`, `PlatformRole`, `Position` with 7 built-in positions per restaurant. Replaces spatie/permission teams; reasons in ARCHITECTURE §4.
- [x] `php artisan platform:install` creates or promotes the super admin. `is_super_admin` can't be mass-assigned (tested).
- [x] Error pages (401/403/404/419/429/500/503) in both languages, matching the design.
- [x] Demo seeder: super admin, Support staff, Masar Grill with owner, branch manager, cashier and kitchen.

**Done when:** CI is green, both panels open in AR/EN light/dark at all breakpoints, and the lockout + tenancy tests pass.

**Kickoff prompt**
```
Read CLAUDE.md, docs/MASTER_PROMPT.md and docs/ROADMAP.md fully.
Execute PHASE 0 (Foundation) exactly as listed in ROADMAP.md, following every rule in MASTER_PROMPT.md sections 0, 4, 5, 7 and 9.
First use brainstorming + writing-plans to produce a short implementation plan for this phase and show it to me. After I approve, implement it with test-driven-development.
Use taste-skill, soft-skill and impeccable for the design tokens and Filament theme.
Finish with verification-before-completion: run all tests, open the app in the browser in Arabic and English, light and dark, at 375/768/1280 px, and report exactly what you verified. Then tick the boxes in ROADMAP.md and update docs/CHANGELOG.md.
```

---

## Phase 1: Super Admin & SaaS core

**Goal:** the platform owner can run the business: clients, plans, staff, leads, security.

- [x] Super Admin dashboard: live clients (with trials), **MRR**, overdue invoices, open leads, a **Needs attention** list (overdue invoices, trials ending, stale leads, suspended clients, unread security alerts) and recent leads.
  - [ ] **Deferred to Phase 8:** order KPIs (24h revenue, 24h orders, in progress, ready, completed, cancelled). Orders don't exist yet.
  - [x] "Live data connected" indicator with live order activity (Phase 5, over the `platform.dashboard` channel).
- [x] **Platform staff sub-accounts:**
  - roles with a grouped permission matrix (Support, Sales and Finance seeded; any custom role)
  - invite by link (email and WhatsApp)
  - suspend and reactivate, unlock sign-in, reset 2FA
  - last seen and 2FA status in the list
  - 2FA is enforced for all platform staff with `SECURITY_REQUIRE_PLATFORM_MFA=true`; switch it on in production (Phase 11 checklist).
- [x] **Clients as luxury cards:** initials, bilingual name, plan, branches, team, subscription status and next invoice. Status tabs with counts, search, filters and sort.
- [x] Create-client wizard: restaurant (bilingual names, slug with a live link preview, language, timezone, contact) → owner (invite link by email/WhatsApp) → plan, trial and billing details. One transaction, fully audited.
  - [ ] **Deferred to Phase 2:** "first branch", logo and accent-color steps. Branches and branding are built in Phase 2.
- [x] **Client page** (`/admin/restaurants/{slug}`):
  - overview of subscription, owner, contact & billing details, features and internal notes
  - Team, Invoices and Activity tabs
  - actions: change plan, issue invoice, suspend, reactivate, resend invitation
  - Branches, menu, orders and branding tabs arrive with their phases.
- [x] **View as owner (impersonation):**
  - mandatory reason
  - floating brass pill with "Return to platform"
  - profile, sessions and 2FA pages are blocked while impersonating
  - start and end are both written to the audit log
- [x] **Plans** (Starter 349 / Growth 649 / Enterprise custom, per branch per month, editable) with trial days, branch limits and feature flags; per-client feature overrides.
- [x] **Subscriptions** (trialing → active → past due → suspended, cancelled), driven by the daily `billing:run` at 05:30 Asia/Qatar, which catches up on missed periods.
- [x] **Invoices:**
  - gap-free numbers `DTP-YYYY-00001`
  - bilingual PDF (mPDF, Arabic shaping) emailed on issue
  - mark paid (reactivates billing suspensions only), void, download
  - the owner's Billing page shows plan, amount due and bank details
- [x] Suspend/reactivate a client with a reason, owner email notices and a workspace notice. Only the owner can enter a suspended workspace.
  - [x] Done in Phase 2: restaurant and branch links of a suspended client show a polite "temporarily unavailable" page (HTTP 503).
- [x] **Leads pipeline:**
  - stages: New → Contacted → Demo booked → Won → Lost
  - assignee ("assign to me"), notes timeline, follow-up date, WhatsApp shortcut
  - **Convert to client** in one action, prefilled from the lead
  - the landing-page form (honeypot, rate limits, 24h dedupe) notifies sales staff and sends the visitor a bilingual acknowledgement
- [x] Platform settings: billing rules (trial, payment terms, grace period, tax, currency), invoice company details & bank details, order status override, OTP channel order, maintenance **workspace banner**, **global announcements** (tone, audience, schedule, dismissible).
- [x] Security center: security alerts inbox (mark read / all read, unlock the locked account from the alert), active sessions across the platform (sign out any device).
- [x] Audit log viewer: filters for person, record type and date; readable before/after details.
- [x] Authorization: one policy per platform resource. Super admin is granted everything via the permission checks, not via `Gate::before`, so policies stay authoritative and testable.
- [x] Quality: 148 Pest tests, Larastan level 8 with 0 errors, Pint. Verified in the browser in AR/EN, light/dark, at 375 and 800+ px.
**Done when:** a lead converts to a working client in one action, impersonation is audited, a suspended client's customer app shows the unavailable page, and all policies are covered by tests.

**Kickoff prompt**
```
Read CLAUDE.md, docs/MASTER_PROMPT.md (especially 2.7, 3.1, 8) and docs/ROADMAP.md.
Execute PHASE 1 (Super Admin & SaaS core). Plan first (writing-plans), show me the plan, then build with TDD.
Every Filament resource must use the custom luxury theme, be fully bilingual RTL/LTR, and have policy + cross-tenant tests.
Verify in the browser as super admin and as a Support staff account with limited permissions. Report what you verified, tick ROADMAP.md, update CHANGELOG.md.
```

---

## Phase 2: Restaurant workspace (owner, branches, positions, employees)

**Goal:** the owner sets up the restaurant and the team without help.

- [x] **Owner dashboard:** live stats (branches open now, team, screens online, account) and a **go-live checklist with a progress ring**.
  - The checklist is computed from real data: logo → first branch → hours → team → paired screen → printed QR.
  - Each step links straight to where it gets done, and the checklist can be dismissed once complete.
  - Menu and test-order steps join in Phases 3–4. Order and sales KPIs arrive in Phase 8.
- [x] **Branches** as live cards, edited in a slide-over with four tabs:
  - **Details:** bilingual name, city and address; link code; phones (tap-to-call); Google Maps link; lat/lng with **"Use my current location"**; branch logo or the restaurant logo.
  - **Hours:** weekly hours with **split shifts** and **past-midnight shifts**, a "7:00–23:00 every day" shortcut, and **holidays/closures** with a bilingual reason.
  - **Pickup & kitchen:** average prep minutes, **capacity per 15-min slot**, **lanes and curbside spots**, and **kitchen stations** (Growth plan and above).
  - **Arrival tracking:** enabled, arrival/approach radius, ETA speed, ping seconds, auto-arrival. Plan-gated.
  - **Live controls:** **pause orders** (15/30/60 min or until closing), **busy mode** (+X min for a while), resume, and switching a branch off or on. All are audited.
  - `BranchSchedule` is the single source of truth for open / busy / paused / closed / holiday, in the restaurant's timezone. It also answers "closes at", "opens at" and "taking orders again at".
  - [x] Capacity per slot is enforced at checkout (Phase 4).
  - [x] The tracking settings drive live arrival and approach alerts (Phase 5).
- [x] **Positions:**
  - the 7 built-in positions can be tuned or **restored to their originals**; **custom positions** are built from grouped permission checkboxes with plain-language descriptions
  - **escalation guard:** nobody can grant, edit or assign a permission they don't hold
  - built-in positions and positions in use can't be deleted
- [x] **Team:**
  - invite by email with a **WhatsApp share link**; people choose their own password
  - position plus **one or many branches** (or "all branches")
  - status invited / active / suspended; suspending or removing signs the person out everywhere
  - resend invite or send a password link
  - **4–6 digit screen PIN**: unique per restaurant, keyed hash, weak PINs refused
  - last signed in
  - **branch managers manage only their own branches' people**
  - [ ] **Deferred to Phase 6:** signing in with the PIN on shared ops screens (PINs can be set now).
- [x] **Screen pairing (`/pair`):**
  - create a Kitchen / Cashier / Pickup / TV screen for a branch (and station) → **one-time 6-digit code** (15 min, hashed, burned on use)
  - the screen stays signed in with a long-lived token cookie, keeps itself awake (**Wake Lock**), sends a heartbeat every 30 s and **reconnects with backoff**, showing a connection banner
  - **revoke** or **pair a replacement**; online / offline / waiting status in the list
  - a paired screen shows a premium standby view (branch, clock, live state, quoted wait)
  - [x] Paired screens now show the kitchen display, live board, pickup window or TV by kind (Phase 5).
- [x] **Restaurant settings:** bilingual name, logo (+ "use on all branches"), cover image, accent colour, default language, timezone, currency, tax %, service fee %, **menu-link mode** (one link / per branch), order override toggle, cancellation window, loyalty points per unit. Basis points and minor units on the server.
- [x] **Links & QR center:** restaurant and branch links with copy/open and **QR downloads as PNG, SVG and a bilingual A5 PDF poster**. QR codes are generated locally; the first download ticks the setup step.
  - **Public restaurant and branch pages** (`/{restaurant}`, `/{restaurant}/{branch}`) show live open/closed state, hours, closures, directions, tap-to-call and pickup points, plus friendly "restaurant not found", "branch not found" and "temporarily unavailable" pages. Online ordering joins these same links in Phase 4.
- [x] **Plan limits** for branches and team members, enforced in the domain (row-locked) with usage shown in page headers and a **friendly upgrade prompt** instead of the Add button.
- [x] Tests:
  - every built-in position sees exactly the right pages and menu items (owner + 7 positions × 7 pages)
  - a branch manager is confined to their branches
  - restaurant B gets 404 on every restaurant A page
  - a screen stays in its own restaurant
  - the full owner journey (2 branches → 3 invited people in different positions → paired kitchen screen) passes end to end
**Done when:** an owner can go from empty account to 2 branches + 3 employees in different positions + a paired kitchen tablet, and each employee sees only what their position allows (tested).

**Kickoff prompt**
```
Read CLAUDE.md, docs/MASTER_PROMPT.md (2.6, 3.2, 8) and docs/ROADMAP.md.
Execute PHASE 2 (Restaurant workspace). Plan → approve → TDD.
Pay special attention to the positions/permissions model and multi-branch employees; write tests proving each built-in position sees exactly the right menus and actions, and that restaurant B can never access restaurant A.
Use impeccable + emil-design-eng for the onboarding wizard UX. Verify in the browser as owner, branch manager and cashier. Tick ROADMAP.md, update CHANGELOG.md.
```

---

## Phase 3: Menu engine

**Goal:** a flexible, fast menu that the owner manages easily and customers love.

- [x] **Sections:** bilingual name and description, emoji icon, image, drag-and-drop order, visibility, optional schedule.
- [x] **Items:**
  - bilingual name, description and ingredients; price in minor units with a live "Guests pay…" line; discount % (original price struck through)
  - photo auto-converted to **WebP and AVIF** at 400/800 px
  - calories, prep minutes, **allergens with icons**, tags (spicy, vegetarian, vegan, kids, high protein, gluten-free, lighter), flags (Popular, New, Limited), SKU
- [x] **Reusable option groups:**
  - choices or "remove ingredients", min/max (required, single, multiple), default option, price and calorie change, per-option availability
  - **nested follow-up groups** ("Large drink" → "Which drink?")
- [x] **Meals:**
  - combo slots (Main/Side/Drink) with default choices and upcharges
  - **savings computed per branch** ("Save 7.00 QAR")
  - **"Make it a meal"** link from an item to its meal
  - up to 3 "goes well with" recommendations per item
  - [x] The "make it a meal" prompt after adding to the bag shipped in Phase 4.
- [x] **Per branch:**
  - serve or don't serve, branch price override
  - **out of stock today** (back automatically at the branch's local midnight)
  - **daily stock count** that refills each local day, sold out at 0, **low-stock alert** once a day to the people who run that branch
  - `consume()` is ready for orders in Phase 4
- [x] **Menu schedules:** breakfast or late night, any weekdays, windows past midnight (the after-midnight tail belongs to the previous day). Sections with nothing to order right now move to the end of the guest menu.
- [x] **Menu design:** one/two columns, square/wide/no photos, show descriptions/calories/highlights, popular first, and **Preview as guest**, which opens the real branch menu.
- [x] **Branch menu (branch-first view):**
  - branch tiles with a live "items off" count → item list with price here, status now and stock today
  - one-tap out of stock / back in stock, daily stock, branch price, serve/don't serve, plus bulk actions
  - branch managers see only their branches and can't change prices
- [x] **Bulk tools:**
  - **bulk price update** (% or fixed, rounding to friendly steps) with a live preview of every change
  - duplicate (with options and meal slots, switched off for review), move to section, put on or take off the menu
- [x] **Excel:**
  - export, template and **import**
  - all-or-nothing, with errors listed by row
  - new sections created automatically; Arabic and English in the same file
- [x] **Menu cache:** the restaurant catalog is cached under a version that every menu change bumps (tag-style invalidation that works on the database cache). Fast-moving branch stock is read live, so it never goes stale.
- [x] **Guest menu on every branch page:**
  - sticky section chips and instant search in both languages
  - photo (AVIF/WebP) or section emoji, prices with discounts, calories, tag and allergen icons, meal savings
  - greyed "out of stock today" / "sold out" / "not served right now" states
  - [x] Adding to the bag and checkout shipped in Phase 4.
  - [ ] **Deferred to Phase 6:** routing items to kitchen stations.
  - [ ] **Deferred to Phase 10:** AI helpers (translate, write descriptions, suggest allergens).
- [x] **Demo:**
  - 50 bilingual items across breakfast (scheduled), burgers, chicken, meals, sides, drinks, coffee, desserts and kids, with 8 option groups and 6 meals
  - branch-specific stock: a West Bay daily count, an item not served at Al Wakra, Lusail out of stock today
**Done when:** the demo menu (50 products, modifiers, combos) is manageable in AR/EN, per-branch stock behaves correctly across midnight (time-travel test), and import/export round-trips.

**Kickoff prompt**
```
Read CLAUDE.md, docs/MASTER_PROMPT.md (2.6, 3.3, 6) and docs/ROADMAP.md.
Execute PHASE 3 (Menu engine). Plan → approve → TDD.
Include time-travel tests for "out of stock today" resetting at each branch's local midnight and for menu schedules.
Seed a realistic bilingual drive-thru menu (burgers, chicken, meals, fries, drinks, desserts, kids, coffee). Verify in the browser. Tick ROADMAP.md, update CHANGELOG.md.
```

---

## Phase 4: Customer ordering app (PWA)

**Goal:** the fastest, most beautiful drive-thru ordering flow in the region.

- [x] **Entry:** `/{restaurant}`, `/{restaurant}/{branch}` and `/o/{code}` short links; QR params (lane, campaign, channel, promo, source) are remembered for the visit and stored on the order; friendly not-found and paused states; the restaurant's accent colour and logo are applied.
- [x] **Landing:** greeting by time of day, "welcome back" with the guest's name, the live order first, **"Your usual?" one-tap reorder** (straight to checkout), favourites, popular and new carousels.
  - [ ] **Deferred to Phase 7:** promotions and the loyalty points chip.
- [x] **Branch picker:** distance (opt-in location), open/closed with the next opening time, prep time, **busy level** (quiet / steady / busy from live kitchen load), call and directions. The last-used branch is marked "Your branch".
- [x] **Menu:** sticky section chips, instant AR/EN search, badges, greyed unavailable items, discounts, opening hours and directions in a compact panel, a tap spinner while the sheet loads, and a **sticky bag bar**.
- [x] **Product sheet:** large image, description, ingredients, calories, allergens and tags, option groups with live validation (required / up to N / nested follow-ups), meal slots, quantity, notes, **live price** (formatted like the server), favourite. After adding: **make it a meal** (with savings) or **complete your meal** (up to 3).
- [x] **Bag:** lines with options and notes, quantity +/− and remove, problems shown per line (sold out, gone, changed), "goes well with" add-ons, subtotal, discount, service fee, VAT, total and the expected ready time.
- [x] **Phone code sign-in** (inline at checkout, on its own page and on the account page): WhatsApp first then SMS, 6 digits, 5-minute expiry, 30 s resend countdown, 5 sends per 10 min per phone and 10 per IP, 5 attempts, hashed codes, `autocomplete="one-time-code"` with auto-submit, friendly errors. Customers are **per restaurant**.
- [x] **Account:** name, birthday (optional), language, cars (plate, colour, make, type, default; up to 10), signed-in devices with "sign out", favourites, sign out.
- [x] **Checkout:** car (pick or add inline), lane, **timing** (now / in 10–60 min / "when I'm on my way" / date & time) with the **kitchen-capacity check and a one-tap "order for HH:MM instead"**, closed branches offer scheduling only, payment method (the last one is remembered), notes, server totals, **idempotent Place order** (one key per visit, button locked while sending, unique key in the database).
  - [ ] **Deferred to Phase 7:** coupon field and paying with points.
- [x] **Payments** through the `PaymentGateway` **Fake driver**: card with a 3-D Secure page (approve / decline), Apple Pay and Google Pay (instant), and pay at the window. Failed payments can be retried with any method from the order page. Return URLs never leave the site.
- [x] **Order page:** success animation, progress timeline with times, ready-around time, **pickup code and QR**, car, short code with share/copy, "I'm on the way" for held orders, cancel within the restaurant's window, order again, call the branch, printable receipt. It refreshes every 5 s (realtime arrives in Phase 5). Anyone with the link sees progress only; the pickup code and actions are for the guest who ordered.
- [x] **Orders history** with "order again", favourites, printable bilingual receipt.
- [x] **PWA:** per-restaurant manifest (name, icon, theme colour), service worker (hashed assets cached; pages always live; never caches personal pages), bilingual offline page, and a polite install prompt shown once after a real order.
- [x] Tests: full new-guest flow (menu → sheet with options → bag → code → car → order), returning guest in one tap, double tap, repricing, sold-out item, closed branch, full kitchen with the suggested time, code limits through the form, card payment and the off-site return URL, cancel window and "I'm on the way", cross-restaurant isolation (guests, bags, orders, receipts), account cars and devices, and every guest page rendering in both languages.
  - [ ] **Not yet automated:** the timed 30 s / 60 s browser test needs `pestphp/pest-plugin-browser` and Playwright (not installed). Measured by hand instead: a returning guest needs 2 taps (Order it again → Place order), a new guest 7 taps plus phone, code, name and plate; each step's server time is under 0.6 s in process.

**Done when:** a returning customer orders in ≤ 30 s and a new customer in ≤ 60 s (measured in a browser test), with no duplicate orders on double-tap, and server totals always match the UI.

**Kickoff prompt**
```
Read CLAUDE.md, docs/MASTER_PROMPT.md (2.2, 2.3, 3.4, 5, 7) and docs/ROADMAP.md.
Execute PHASE 4 (Customer ordering app). Plan → approve → TDD.
This is the highest-priority UX in the product: use taste-skill, soft-skill, impeccable, mobile-native, animate and apple-design. Mobile-first at 375 px, thumb-reach actions, sticky cart, skeletons, success animation.
Write a browser test that completes an order and asserts the 30 s / 60 s targets, plus tests for idempotency, repricing, unavailable items, closed branch, capacity full, OTP limits and cross-restaurant customer isolation.
Verify on a phone-sized viewport in Arabic and English. Tick ROADMAP.md, update CHANGELOG.md.
```

---

## Phase 5: Live operations (state machine, KDS, cashier, tracking, arrival)

**Goal:** orders flow from customer to kitchen to window in real time with zero refreshes.

- [x] **Order state machine:** pending payment → received → preparing → ready → picked up, plus cancelled and a **held (scheduled)** status for later starts and "when I'm on my way". Arrival and "on the way" are recorded facts beside the status (a guest can arrive while the food cooks), and refunds live on the payment (agreed lean set).
  - every change goes through `TransitionOrder` with a **compare-and-set** save (a second tap or a stale screen fails cleanly), status times, history, a domain event and a broadcast after commit
  - a **database guard** refuses unknown statuses (SQLite triggers, CHECK constraint on MySQL/PostgreSQL)
  - **held orders are released at their start time** by a queued job, with a per-minute `orders:release` sweep as the safety net
  - override moves (any status, **re-enable**, **reset as new**) need a reason, the override permission and the restaurant switch, and are audited
- [x] **Realtime:** Reverb, private channels `branch.{id}.orders`, `restaurant.{id}.dashboard`, `order.{code}` and `platform.dashboard` (Super Admin "Live data connected"); payloads carry ids, status and times only. Paired screens and guests are signed off by `/realtime/auth`, staff by `/broadcasting/auth`. Screens refresh from the server on each event, resync after a reconnect, and keep a slow poll as a safety net.
- [x] **Kitchen display:** New / Cooking / Ready columns (two cards per row on wide screens), timers against each order's target, urgency in colour + icon + words, one-tap Start / Ready, cancel and re-enable with a reason sheet, "coming up" strip for held orders ("starts in 12 min", "waits for the guest"), arrived / on the way / scheduled badges, done today, average prep and quoted wait, **red pulse for new orders and amber glow while cooking** (still under reduced motion), new-order chime (generated in the browser) and vibration after "Enable sound", Live / Reconnecting indicator, fullscreen, wake lock, auto-reconnect.
- [x] **Kitchen stations:** items name a station (Grill, Fryer, Cold kitchen, Drinks, Desserts, or the general kitchen); a station screen shows only its items, the first station's tap starts the order and it becomes ready when every station is done.
- [x] **Live board (cashier / manager):** KPI strip (new, cooking, ready, at the window, late, orders and sales today vs yesterday, average prep), live table (order, time, guest, car and lane, total, payment, kitchen status, waiting, arrival / distance / ready time), filters (All, New, Cooking, Ready, Late, Later & unpaid, Completed, Cancelled), list or box view, ticker, **done table** (Today, Yesterday, 7 days, This month, pick a day) with totals, and an order panel: items, payment, guest status, history, next step, **collect cash or card**, guest is here (with lane), cancel / re-enable, copy or open the **signed guest link**.
- [x] **Overrides:** change any status, reset as new and re-enable for people with "Override order status" when the restaurant's switch is on. All audited.
- [x] **Guest tracking page:** live over the order channel with catch-up, chime and vibration on each change, **full-screen Ready alert** with pickup code and replay, optional browser notification, call the branch; anyone else with the link sees progress only, and a signed staff link can follow it live.
- [x] **I'm here** (with lane or spot) and **opt-in live location**: consent and refusal are stored, pings follow the branch's interval, distance and ETA are shown, entering the approach radius tells the kitchen to start (and releases a held order), and entering the arrival radius marks the guest arrived when the branch allows it.
- [x] **Pickup window:** scan the pickup QR with the camera (built-in barcode detection) or type the pickup code or order code → **Paid ✓ Verified ✓ Ready ✓** → collect → complete. A queue of guests at the branch with car, plate, lane and waiting time, plus orders that are ready.
- [x] **Customer self-cancel** within the window (now also for held orders). **Dynamic prep estimate and busy level** from the last 3 hours of real prep times plus the live queue and busy mode; used by checkout, the home page and every screen.
- [x] **TV command center** (readable from 3 m): in the kitchen, ready, late, guests waiting, sales today vs yesterday and last week, hourly sales chart, ready numbers and alerts. **All-branches screen** for owners and general managers: Healthy / Busy / Needs attention cards (always written out), counts, sales and alerts per branch.
- [x] Screens run on **paired devices** (`/screen`, by device kind) and for **signed-in staff** (`/ops/{restaurant}/{branch}/{screen}`), linked from a new "Live operations" sidebar group shown per permission.
- [x] Tests: every from → to pair (49) and every override pair, compare-and-set race, database guard, scheduled release, broadcasts and channel access (cross-restaurant denied), desk permissions per screen kind and branch, station completion, pickup lookup and verification, collect and hand over, auto-arrival with simulated coordinates, guest live page, every screen in both languages.
  - [ ] **Deferred to Phase 6:** staff PIN sign-in on shared screens (as planned), push notifications for staff, and WhatsApp alerts to managers.

**Done when:** an order placed on a phone appears on KDS + cashier + TV in < 1 s, every transition is reflected on the customer page live, auto-arrival works with simulated coordinates, and invalid transitions are rejected (tested).

**Kickoff prompt**
```
Read CLAUDE.md, docs/MASTER_PROMPT.md (2.4, 2.5, 3.2, 3.4, 3.6, 5) and docs/ROADMAP.md.
Execute PHASE 5 (Live operations). Plan → approve → TDD.
Start with the order state machine and its exhaustive transition tests, then broadcasting, then the screens. Ops screens must be readable from 3 m, have ≥ 56 px touch targets, and never depend on hover.
Use animate + improve-animations for the order glows and the Ready alert (respect reduced motion).
Verify with two browser tabs (customer + KDS) and a third (cashier) updating live. Tick ROADMAP.md, update CHANGELOG.md.
```

---

## Phase 6: Payments, notifications & integrations

**Goal:** real money, real messages, open to POS systems.

- [x] First **real payment driver**: **SADAD (Qatar)** chosen with the user. Signed web checkout, signed callback + webhook, refunds (full; partial behind a per-account switch), status lookup, fees and provider transaction recorded, expiry sweep that asks the provider first. Per-restaurant encrypted credentials, test/live mode. *Real sandbox run waits for the user's SADAD keys.*
- [x] **WhatsApp Cloud API** driver (authentication template for OTP, utility templates for order received / ready / receipt / staff alerts; points templates come with loyalty in Phase 7), **SMSala** SMS fallback, delivery status tracking from both webhooks. Templates to create: `docs/WHATSAPP_TEMPLATES.md`.
- [x] **Notification center** (bell + live chime and toast; late orders, guests waiting, overloaded kitchen, failed refunds, webhooks switched off), **web push** for guests (order ready) and staff (alerts, per device), bilingual email for critical alerts, guest and staff **notification preferences**, restaurant **quiet hours**. Plus **staff PIN on paired screens** for refunds and overrides.
- [x] **Outgoing webhooks** (signed, retries with backoff, delivery log, replay, auto-off) + **public REST API v1** (Sanctum keys per API client, scopes, rate limits, OpenAPI JSON + `/developers/api`) + generic **POS** driver (accepted orders pushed with acknowledgement).
- [x] **Printing**: kitchen ticket + receipt (80 mm), PrintNode driver + browser print fallback, auto-print on accept (branch setting).
- [x] Provider status shown on the Platform health page, with alerts on failures.

**Done when:** a real sandbox payment completes and is refunded via webhook, WhatsApp OTP and "order ready" arrive on a real phone (sandbox), and a webhook consumer receives signed events.

**Status (2026-10-11):** everything is built and tested against the providers' documented APIs with faked HTTP (470 tests). The three real-world checks need the user's sandbox accounts: SADAD test keys, a Meta WhatsApp test number with approved templates, and an HTTPS webhook receiver.

**Kickoff prompt**
```
Read CLAUDE.md, docs/MASTER_PROMPT.md (3.4, 3.8, 5) and docs/ROADMAP.md.
Execute PHASE 6 (Payments, notifications & integrations). Before coding, ask me which payment gateway, WhatsApp account and SMS provider to use, and which sandbox credentials I will provide (never hard-code secrets; use encrypted settings + .env).
Plan → approve → TDD with Http::fake for every provider. Verify end to end in sandbox. Tick ROADMAP.md, update CHANGELOG.md.
```

---

## Phase 7: Loyalty, promotions & growth

- [x] Loyalty: earn rate per currency unit (+ per product/category/branch/tier multipliers), **earn on completion**, reverse on cancel/refund, expiry, **redeem at checkout** with cap, balance + history. *(7A; no default values: off until the owner sets them.)*
- [x] **Tiers** (Bronze/Silver/Gold/Platinum, configurable thresholds and benefits), progress bar in the customer app. *(7A)*
- [x] **Rewards catalog + wallet** (free item, % off, fixed off, bonus points), expiry, redemption history. *(7A)*
- [x] **Promotions engine** (% / fixed / BOGO / free item / combo price / first order / birthday / happy hour / branch-category-product scope / stacking rules) + **coupon codes** (limits, per-customer limits, validity). *(7A)*
- [ ] **Referral** program with fraud checks. **Favorites**, birthday reward automation.
- [ ] Optional gamification: streaks, milestones, badges (admin-configurable, off by default).
- [x] **CRM**: customer list + profile (orders, revenue, AOV, favorites, last order, points, tier, segment, campaigns), **auto segments** nightly (New, Regular, VIP, High spender, At risk, Inactive).
- [x] **Campaigns** to segments (WhatsApp/SMS/push), scheduling, consent, quiet hours, attribution (orders + revenue). Done 2026-10-27: owner composer with a live bilingual preview, audience count, test send, send/schedule/cancel, results; per-guest frequency cap; unsubscribe link and STOP replies.
- [ ] **QR campaigns**: create QR per branch/lane/campaign/channel/promo, branded poster PDF, analytics (scans → visits → orders → revenue → conversion).
- [ ] **Post-pickup rating** + **support tickets** (customer report → manager resolves → WhatsApp reply).

**Milestones:** 7A loyalty, tiers, rewards, promotions and coupons (done 2026-10-12, plan in `docs/superpowers/plans/2026-10-12-phase-7a-loyalty-promotions.md`). 7B CRM, campaigns, QR campaigns and referral. 7C ratings, support tickets, birthday automation and gamification (off by default).

**Kickoff prompt**
```
Read CLAUDE.md, docs/MASTER_PROMPT.md (3.4, 3.5) and docs/ROADMAP.md.
Execute PHASE 7 (Loyalty, promotions & growth). Plan → approve → TDD.
Write property-style tests for promotion stacking and point calculations (earn, reverse, redeem, expiry), all money in minor units.
Verify the customer wallet, a coupon at checkout and a QR campaign's analytics in the browser. Tick ROADMAP.md, update CHANGELOG.md.
```

---

## Phase 8: Reports, finance & intelligence

- [ ] Owner / GM **executive dashboard** (all branches): sales, orders, AOV, active orders, avg prep, avg pickup, customers new vs repeat, points issued/redeemed, top/worst branch, top/worst product, live sales graph, comparisons (yesterday, same weekday, last week).
- [ ] Branch manager dashboard (own branch) + **mobile-friendly** manager view.
- [ ] **Accounting dashboard + live screen**: gross, discounts, net, VAT, refunds, fees, cash/card/online, transactions, failed payments, **daily branch reconciliation** (expected vs collected, variance notes, close-day lock). PII masked.
- [ ] Reports: daily / weekly / monthly / custom, hourly heatmap, product mix, category mix, kitchen performance (prep time trends), pickup time trends, cancellation & refund rates, promotion & loyalty performance, QR performance, ratings.
- [ ] Exports: Excel, CSV, PDF (Arabic-safe via Chromium), print. Filters before export. Large exports queued + emailed.
- [ ] **Scheduled reports** (daily summary by email/WhatsApp at a chosen time, weekly/monthly PDF).
- [ ] **Live alert center** (Info/Warning/Critical, rules configurable per restaurant) + **smart insights** cards (rule-based).
- [ ] Super Admin platform analytics: MRR, churn, active clients, orders across platform, top clients.

**Kickoff prompt**
```
Read CLAUDE.md, docs/MASTER_PROMPT.md (3.6, 3.7) and docs/ROADMAP.md.
Execute PHASE 8 (Reports, finance & intelligence). Load the dataviz skill before any chart. Plan → approve → TDD.
Use aggregate tables / materialized daily rollups so dashboards stay fast with 1M orders (seed a large dataset in a performance test).
Verify every report's numbers against raw SQL in tests, and verify the PDF export renders Arabic correctly. Tick ROADMAP.md, update CHANGELOG.md.
```

---

## Phase 9: Marketing website & CMS

- [ ] Luxury public site: Home, Features, Pricing (+ FAQ, plan comparison), Contact, **Book a demo**, Industries/Use cases, Legal (Terms, Privacy, Cookies). All bilingual with hreflang.
- [ ] **Two themes** (Architectural + QR-Spring Luxury) selectable by the super admin. Fixed glass nav, hero, slides, features, roles, how it works, stats, partner strip, testimonials, CTA, footer.
- [ ] **Interactive product demo** (animated phone ordering → KDS → ready), **ROI calculator** (orders/day × time saved).
- [ ] **CMS** (Filament): theme, logo + size + show name, menus, every section's content, images (5 MB), SEO per page + OG image, **draft / preview / publish / version history / reset to default**.
- [ ] Request/demo forms → Leads (Phase 1) with spam protection (honeypot + rate limit + Turnstile/hCaptcha optional), auto-reply email/WhatsApp, and admin notification.
- [ ] Floating WhatsApp contact button, cookie consent (privacy-first), analytics hook.
- [ ] SEO: meta, JSON-LD (Organization, SoftwareApplication, FAQPage, Product/Offer for plans), sitemap, robots, Core Web Vitals green, GEO readiness (`llms.txt`).

**Kickoff prompt**
```
Read CLAUDE.md, docs/MASTER_PROMPT.md (2.1, 3.1) and docs/ROADMAP.md.
Execute PHASE 9 (Marketing website & CMS). Use frontend-design, taste-skill, soft-skill, gpt-taste, animate and awesome-design-md to define a distinctive luxury direction first; show me the direction before building.
Then the seo and geo-optimizer skills for the SEO pass. Verify Lighthouse ≥ 95 on mobile for Home in both languages. Tick ROADMAP.md, update CHANGELOG.md.
```

---

## Phase 10: AI & smart features (feature-flagged per client)

- [ ] `Ai` driver (Fake + Claude API using the latest Claude model), per-restaurant usage limits and cost tracking.
- [ ] **Recommendations** (rule-based baseline + AI re-ranking): cart, time of day, branch, history, popularity, availability. Max 3.
- [ ] **Smart search** with intent parsing ("spicy under 30", "something sweet", "without cheese", Arabic dialect).
- [ ] **AI order assistant** (chat sheet): builds a suggested cart, and the customer always confirms.
- [ ] **AI menu tools** for owners: translate AR↔EN, write descriptions, suggest tags/allergens, menu engineering hints (stars/plowhorses/puzzles/dogs).
- [ ] **AI insights**: daily natural-language summary of performance + 3 recommended actions; anomaly detection on sales/prep time.

**Kickoff prompt**
```
Read CLAUDE.md, docs/MASTER_PROMPT.md (3.3, 3.4, 3.6, 5) and docs/ROADMAP.md.
Execute PHASE 10 (AI & smart features). Load the claude-api skill first for the current model ID and SDK usage. Every AI feature must work with the Fake driver, be behind a per-client feature flag, have a non-AI fallback, never auto-submit orders, and log cost per restaurant.
Plan → approve → TDD. Tick ROADMAP.md, update CHANGELOG.md.
```

---

## Phase 11: Hardening & launch

- [ ] Security review (`security-review` skill): authz on every route/action, tenant isolation suite, rate limits, CSP/security headers, secrets, file uploads, webhooks, dependency audit.
- [ ] Full code review (`code-review` at high effort) and fixes.
- [ ] Performance: query review, indexes, caching, queue sizing, load test (e.g. 200 concurrent customers + 20 KDS screens), Reverb scaling notes.
- [ ] Accessibility audit (keyboard, screen reader, contrast, reduced motion) in both languages.
- [ ] Backups (spatie/backup, off-site, restore drill), monitoring (Pulse, Sentry, uptime), log retention, data-retention/privacy jobs (customer export/delete).
- [ ] Deployment: production server (Forge/SixPanel/Docker), zero-downtime deploys, Horizon + Reverb supervisors, scheduler, SSL, custom domains per restaurant.
- [ ] Docs: `README.md`, `docs/ARCHITECTURE.md`, `docs/DEPLOY.md`, owner user guide (AR/EN), staff quick guides (KDS, cashier, pickup), API docs.
- [ ] Final run of the **success scenario** (MASTER_PROMPT section 10) end to end, recorded.

**Kickoff prompt**
```
Read CLAUDE.md, docs/MASTER_PROMPT.md (3.8, 9, 10) and docs/ROADMAP.md.
Execute PHASE 11 (Hardening & launch). Run security-review and code-review (high) first and fix every confirmed finding with tests.
Then performance, accessibility, backups, deployment and docs. Finish by running the full success scenario end to end in the browser and report the results honestly, including anything that failed. Tick ROADMAP.md, update CHANGELOG.md.
```

---

## Backlog (after launch)
- Native apps (customer + staff) via the public API.
- License-plate recognition (ANPR) camera integration for automatic arrival.
- Delivery & curbside modes, table/dine-in QR.
- Gift cards, subscriptions (coffee pass), corporate accounts.
- Employee shifts, clock-in/out, tips.
- Ingredient-level inventory with recipes and supplier purchase orders.
- Multi-country (currencies, VAT/e-invoicing rules per country).
