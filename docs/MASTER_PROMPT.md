# MASTER PROMPT: Drive-Thru Plus, a bilingual multi-tenant drive-thru SaaS on Laravel

> **How to use this file**
> Give this whole document to Claude (Claude Code) at the start of the project, together with `CLAUDE.md` and `docs/ROADMAP.md`.
> Then run **one phase at a time** using the kickoff prompts in `docs/ROADMAP.md`. Never ask for "everything at once".
> Product name used below: **Drive-Thru Plus**. It lives only in `config/app.php` (`APP_NAME`) and the language files, so a rename is a one-line change.

---

## 0. Your role and the bar

You are a senior Laravel architect, product designer and QA lead building a **production SaaS**, not a demo.

Every screen and every line must be:

- **Luxury**: premium, calm, editorial. It should feel like a fintech app crossed with five-star hospitality. Never a generic admin template.
- **Modern**: current Laravel ecosystem, current UI patterns, current security practice.
- **Smart**: the system should anticipate what the user needs (smart defaults, suggestions, automation), and it should fix the real problem behind a request.
- **Professional**: no placeholders, no "TODO", no lorem ipsum, no dead buttons, no console errors, no N+1 queries.
- **Easy**: as few clicks and taps as possible. Every screen answers: *What is happening now? What needs my attention? What should I do next?*

Rules:
1. **Arabic and English are both first-class.** Arabic uses a true RTL mirrored layout (logical CSS properties, mirrored icons where meaningful). It is not just translated text. Use Western digits (0-9) for prices, phones, plates, order numbers and times in both languages.
2. **Customer experience comes first.** A returning customer must finish an order in **≤ 30 seconds**, and a new customer in **≤ 60 seconds** after the menu opens.
3. **Never trust the client.** Recalculate every price, discount, tax, point and status on the server.
4. **Never ship a feature without** loading, empty, error and success states, validation messages in both languages, authorization checks, an audit entry where relevant, and Pest tests.
5. If an external provider (payment, WhatsApp, SMS, POS, AI, maps) is not connected yet, build a **driver interface + a fake driver** so the feature fully works in development, and swapping in the real provider is a config change.
6. Before each phase, use the installed skills listed in `CLAUDE.md` (design: `taste-skill`, `soft-skill`, `impeccable`, `frontend-design`, `ui-ux-pro-max`, `emil-design-eng`; motion: `animate`, `apple-design`; workflow: `brainstorming` → `writing-plans` → `test-driven-development` → `systematic-debugging` → `verification-before-completion`; complete output: `output-skill`).

---

## 1. What we are building

A SaaS platform that sells digital drive-thru ordering to restaurants.

```
PLATFORM (Super Admin + platform staff)
  └── CLIENT = Restaurant brand (owner account, subscription plan)
        ├── Branches (each with its own menu availability, hours, lanes, screens)
        ├── Employees → assigned to Positions (permission sets) → scoped to one or more Branches
        ├── Menu (categories, products, modifier groups, combos)
        ├── Customers (phone-based, per restaurant)
        └── Orders → Kitchen → Pickup → Loyalty → Reports
```

Five surfaces, one Laravel codebase:

| Surface | Users | Tech |
|---|---|---|
| **Marketing website** (`/`, `/features`, `/pricing`, `/contact`, `/demo`) | Prospects | Blade + Livewire + Alpine + Tailwind 4, SSR, SEO, CMS-driven |
| **Super Admin panel** (`/admin`) | Super admin + platform staff | Filament (latest stable), custom luxury theme |
| **Restaurant panel** (`/app/{restaurant}`) | Owner, general manager, branch manager, accountant, marketing | Filament with **tenancy by Restaurant** |
| **Operations screens** (`/ops/...`): Kitchen Display, Cashier/Live board, Pickup window, TV Command Center | Branch staff, paired devices | Full-screen Livewire pages + Reverb realtime |
| **Customer ordering app (PWA)** (`/{restaurant}`, `/{restaurant}/{branch}`, `/o/{shortCode}`) | Guests and customers | Livewire + Alpine, mobile-first, installable PWA |

---

## 2. Reference audit: everything in `alsraiyagroupcom5/drive-thru-plus`

The reference is a **TanStack Start + React + Supabase** app built in Lovable, not Laravel. Rebuild **every feature below** in Laravel, then apply the improvements in section 3. `[✓]` means built in the reference. `[◐]` means partially built. `[✗]` means promised in its README but never built (build it anyway, in the phase shown in the roadmap).

### 2.1 Public marketing website
- [✓] Home page with **two selectable themes**: "Architectural" (default) and "QR-Spring Luxury" (deep teal + muted brass gold + sage, fixed glass nav that changes after scrolling, centered bilingual headline, mascot image, QR texture, refined entrance motion, trusted-partner strip).
- [✓] Hero: badge, title, subtitle, primary and secondary buttons, hero image + alt text, quick stats.
- [✓] Sliding image cards carousel (previous/next): "From scan to handover".
- [✓] Features grid (icon + title + description), "Explore all features" page.
- [✓] Roles section: Platform admin / Restaurant owner / Branch & kitchen team / Customer app, each with bullet points and a login link.
- [✓] "How it works" 3 steps: send request → we create your account and branches → upload menu and start selling.
- [✓] Pricing page: Starter **349 QAR / branch / month**, Growth **649** ("Most popular"), Enterprise (custom), plan feature lists, FAQ, "Choose this plan".
- [✓] Contact page: hours, email, Instagram, team login link.
- [✓] **Sign-up request form**: restaurant name, contact name, email, phone, number of branches, plan, message → stored as a request for the super admin, with a success state.
- [✓] Language switch (AR/EN) and light/dark theme.
- [✓] `robots.txt`, `sitemap.xml`, OG image.
- [✓] **Full website CMS for the super admin**: theme chooser; logo upload + width/height + "show name beside logo"; site menu and sign-in menu links; hero; slides; features; roles; steps; pricing plans; request form texts; contact (hours, email, Instagram, branch phones); footer (tagline, column titles, copyright); SEO title/description per page + share image; image upload (5 MB max); **Save & publish** and **Reset to default**.

### 2.2 Restaurant links and QR
- [✓] Pretty links: `/{restaurant-slug}` and `/{restaurant-slug}/{branch-code}`, plus "Branch not found" / "Restaurant not found" states.
- [✓] **Menu-link mode** per restaurant: *one link for all branches* (customer picks a branch) **or** *separate locked link per branch*.
- [✓] Client links & access dialog: restaurant link, per-branch links, copy link, open, **download QR**, copy login email.
- [✗] QR management with campaign/channel/promo parameters and scan → order → revenue analytics.

### 2.3 Customer ordering app
- [✓] Landing: greeting by time of day (good morning/afternoon/evening), "Welcome back", restaurant logo.
- [✓] Branch picker: open/closed, preparation time, **busy level (Quiet / Moderate / Busy)**, call branch, nearby branches. If the link already identifies the branch, skip the picker.
- [✓] "Your usual?" + **Order again**, Popular and New sections.
- [✓] Menu: category navigation, search (AR + EN), badges (Popular, New, Spicy, Unavailable), calories, discounted price shown against the original.
- [✓] Product page + customizer: modifier groups (single/multiple, required, default option, price delta), quantity, notes, live price.
- [✓] "Make it a meal?" upsell and "Complete your meal" suggestions (max 2-3).
- [✓] Persistent cart: lines, modifiers, subtotal, discount, tax, total. Empty cart state.
- [✓] **Passwordless phone sign-in**: WhatsApp code (SMS fallback), 6 digits, 5-minute expiry, resend countdown, max 5 sends per 10 minutes, max 5 wrong attempts, phone normalized to +974 by default.
- [✓] Name capture, **vehicles** (nickname, plate, make, model, color, type, default vehicle), multiple vehicles.
- [✓] Payment choice: Card, Apple Pay, Google Pay, Pay at pickup (through a payment adapter).
- [✓] **Preparation timing**: *start right away*, *start after a chosen delay (5-240 min)*, or *"I'm on the way"*, with "Kitchen starts around HH:MM".
- [✓] Loyalty preview at checkout: "Added to your balance after confirmation".
- [✓] Confirmation: order number, pickup code, **short tracking code (4-6 chars, no ambiguous characters)** and link.
- [✓] **Live tracking page**: timeline (Received → Preparing → Ready → Picked up), realtime updates, chime + vibration on every status change, a full-screen **Ready alert** with sound, browser notification and "Replay alert", restaurant logo and branch details (address, phone, hours), call branch.
- [✓] **"I'm here 🚗"** button notifies the branch.
- [✓] **Live location arrival tracking** (opt-in): position pushed every 15 s, distance + ETA shown to the customer and to staff, **automatic arrival** inside the branch arrival radius, "location denied" flag shown to staff.
- [✓] Staff/admin can open any customer tracking link without the customer's phone login ("Staff view").
- [✓] Order history with points earned and Order again.
- [✓] PWA-ready, one-tap demo sign-in (demo mode only).
- [✗] Favorites, reward wallet, referral, birthday, support tickets, rating, scheduled date/time orders, smart search ("under 30", "something sweet"), AI order assistant.

### 2.4 Kitchen Display (KDS)
- [✓] Status columns with **two order cards per row**, per-order timer, target prep time.
- [✓] Urgency: normal / approaching / overdue, shown with **color + icon + text**.
- [✓] One-tap advance, cancel order, **re-enable** a cancelled order, "That status change is not allowed" guard.
- [✓] **New-order chime** (bell arpeggio) + vibration; "Enable sound" unlock button; Live/Offline indicator.
- [✓] Badges: customer **arrived**, **on the way (prepare now)**, **prepare in X min** (scheduled).
- [✓] Completed today / done today / average prep.
- [✓] **Red pulsing glow for brand-new orders, orange glow once in progress** (applied everywhere: latest orders, branch grids, ticker, kitchen, cards).

### 2.5 Branch live orders (cashier / manager)
- [✓] KPI strip + live order list (order, time, customer, vehicle, value, payment, kitchen status, elapsed), arrival alerts.
- [✓] Orders ticker (marquee) with status, branch, customer, minutes waiting.
- [✓] **Completed/picked-up orders move to a "done" table** with filters: Today, Yesterday, Last 7 days, This month, specific date.
- [✓] List view and Box view, all-branches or single-branch filter.
- [✓] Order detail: items + modifiers, payment, placed at, since placed, prep time, ready in, customer ETA, distance, arrived / away / location off, points earned, customer request notes.
- [✓] Actions: next stage, cancel, re-enable, **reset as a new order** (admin), copy/open customer page link.
- [✓] **Order permission toggles**: "Admin can change any order's status" (platform setting) and "Owner can override order status" (per restaurant).

### 2.6 Restaurant owner console
- [✓] Overview with branch boxes and item/availability summaries.
- [✓] **Branches** (create/edit in a modal): bilingual name, code, city, address, phone(s), Google Maps link, latitude/longitude, opening/closing time, open toggle, average prep minutes, branch logo.
- [✓] **Menu** (branch boxes → item grid, search): bilingual name/description, price, calories, prep minutes, image, Popular/New/Spicy flags, **discount %**, **"Out of stock today"** (auto-returns tomorrow), available toggle, **branches serving this item**.
- [✓] **Menu sections/categories**: name, order, visibility.
- [✓] **Menu design/layout**: image style, card style, ordering and highlights, "Preview as guest".
- [✓] **Team**: create account (name, email, password, role: branch manager / cashier / kitchen, branch), change access, reset password, remove (with confirm).
- [✓] **Tracking settings** per branch: enable tracking, arrival radius (m), approach radius (m), ETA speed (km/h), location refresh (s), automatic arrival, set coordinates from "Use my current location".
- [✓] **Loyalty rate**: points per riyal.
- [✓] **Branding**: restaurant logo, per-branch logos, "apply to all branches", 3 MB limit.

### 2.7 Super admin
- [✓] Operations center: 24h revenue, 24h orders, in progress, ready, completed, cancelled, new sign-up requests, "Live data connected".
- [✓] **Restaurants as client cards**: logo/initials, name, plan, branches, team accounts, orders today, open/closed → **Client workspace** (overview, menu, categories, menu layout, branches, staff, contact, branding, orders, settings), so the admin can do anything the owner can.
- [✓] Create restaurant + group (bilingual names, slug, currency).
- [✓] Accounts: create (role incl. general manager/owner, existing or new branch), change role, reset password, remove.
- [✓] Sign-up requests inbox with status.
- [✓] **Security**: 5 failed sign-ins in 15 minutes (per email hash **and** per IP hash) → 30-minute lock, security alerts inbox (mark read / mark all read).
- [✓] Order status state machine enforced in the database + full status history + audit log.
- [✓] Website editor (section 2.1).

### 2.8 Promised in the reference README but **never built** (we build them)
Super admin for roles/permissions/providers/integrations; general manager executive dashboard; accounting/finance dashboard and live screen; multi-branch live map; live alert center; daily/weekly/monthly reports with exports (Excel/CSV/PDF/print); promotion engine and coupons; reward wallet, tiers (Bronze/Silver/Gold/Platinum), gamification; referrals; CRM segmentation and marketing automation; notification center and preferences; QR campaign analytics; pickup-QR express scan; scheduled orders by date/time; smart combo builder; AI recommendations, AI assistant, smart insights; POS integration layer and webhooks; support tickets; inventory transactions.

### 2.9 Defects found in the reference (do **not** repeat them)
1. **Fake multi-tenancy**: `RESTAURANT_ID` is hardcoded in 5 server files. Customers, orders and new branches are always written to the demo restaurant.
2. **Tax always 0** even though restaurants have `tax_rate`.
3. **Loyalty points are added when the order is placed**, not when it is completed, and never reversed on cancel/refund. Points can never be redeemed.
4. **The mock payment always returns PAID**, the charge happens after the order is inserted, and there is no gateway webhook confirmation.
5. **Order creation is not transactional**: an order can exist without its items, and there is no idempotency key, so a double-tap creates duplicate orders.
6. **OTP codes are stored in plain text and returned in the API response** ("demo mode") with no environment guard.
7. **Scheduled orders are only labeled.** Nothing holds them until `prepare_at`.
8. "Make me general admin" is a **self-promotion button** in the UI (a bootstrap hack).
9. Customer session is a hand-rolled 90-day HMAC token in localStorage, with no revocation or device list.
10. Busy level and prep time are static numbers, not computed from the kitchen load.
11. Sound alerts depend on the user tapping "Enable sound" each time. There is no wake lock, no reconnect strategy and no offline queue.

---

## 3. Improvements: what makes ours more professional and smarter

### 3.1 SaaS and Super Admin
- **Platform staff sub-accounts** with their own permission sets (Support, Sales, Finance, Content editor, Read-only auditor). The super admin manages all of them.
- **Plans and subscriptions**: plan limits (branches, employees, orders/month, features), trial, grace period, invoices (PDF, bilingual), payment status, **suspend/reactivate client** (customer app shows a polite "temporarily unavailable" page), upgrade/downgrade with proration notes.
- **Lead pipeline** from the website: New → Contacted → Demo booked → Won → Lost, with notes, assignee and follow-up date. **"Convert to client"** creates the restaurant, owner account, first branch and a welcome email/WhatsApp in one action.
- **Impersonate client** ("View as owner") for support, with a persistent banner, a reason prompt and an audit entry.
- **Feature flags per client** (e.g. AI assistant, loyalty, geolocation, POS).
- **Platform health**: queue/Horizon status, failed jobs, Reverb connections, provider status (payment/WhatsApp/SMS), last backup, error rate.
- **Global announcements** shown in every restaurant panel (maintenance, new features).
- Bootstrap the first super admin with an **artisan command** (`php artisan platform:install`), never a UI button.

### 3.2 Restaurant owner and staff
- **Onboarding wizard + setup checklist** (logo → first branch → menu import → staff → print QR → test order → go live) with a progress ring.
- **Positions**: built-in positions (General Manager, Branch Manager, Cashier, Kitchen, Expeditor/Runner, Accountant, Marketing) **plus custom positions** the owner builds from permission checkboxes grouped by module, with a plain-language description of each permission.
- **Employee ↔ branches**: one employee can cover several branches. Status: invited / active / suspended. **Invite by email or WhatsApp link** (the employee sets their own password); the owner never needs to know passwords.
- **PIN login for shared devices** (4-6 digits) and **device pairing**: a kitchen tablet or TV is paired to a branch screen with a one-time code, stays signed in, keeps the screen awake (Wake Lock), auto-reconnects and shows a connection banner.
- **Opening hours per weekday**, split shifts, holidays/special days, **"Pause ordering" / "Busy mode"** (adds +X min or stops new orders for N minutes).
- **Capacity throttling**: maximum orders per 15-minute slot per branch. When full, offer the next slot.
- **Drive-thru lanes / pickup points** (Lane 1, Lane 2, curbside spot number) chosen at arrival.
- **Kitchen stations** (Grill, Fryer, Drinks, Desserts): items route to station screens, and the order is ready when all stations finish.
- **Printing**: kitchen ticket and customer receipt (80 mm ESC/POS through a print-agent driver or PrintNode-style driver, plus browser print fallback).
- Optional **shift clock-in/out** for employees (later phase).

### 3.3 Menu engine
- **Reusable modifier groups** (min/max selections, nested options, per-option availability and calories), attached to many products.
- **Combos/meals** with automatic savings display, and a **smart combo detector** ("Make it a meal: save 4 QAR").
- **Menu schedules** (breakfast 6-11, late night), per-branch price overrides, per-branch availability.
- **Stock**: daily count with auto-hide at 0, "out of stock today" auto-reset at the branch's local midnight, low-stock alert.
- Allergens (icons), nutrition, ingredients, "remove ingredient" options, tags (vegetarian, high protein, kids).
- **Bulk import/export** (Excel template), duplicate product, drag-and-drop ordering, bulk price update (% or fixed) with preview.
- Images auto-converted to WebP/AVIF in responsive sizes.
- **AI helpers** (feature flag): translate AR↔EN, write appetizing descriptions, suggest tags/allergens from the description.

### 3.4 Customer experience
- **Branch auto-detect** by location (opt-in), otherwise the last branch used.
- **Smart search** in AR/EN with typo tolerance and intent filters ("under 30", "spicy", "without cheese", "kids").
- **Favorites** (products and full orders), **"Order my usual"** in one tap from the home screen.
- **Scheduled orders by date and time** (within branch hours and capacity), as well as the delay-based timing from the reference.
- **Express pickup QR**: staff scan it at the window → Paid ✓ Verified ✓ Ready ✓ → Complete.
- **Customer self-cancel** within a configurable window (before preparation starts), with an automatic refund through the gateway.
- **Post-pickup rating** (1-5 stars + quick tags) feeding the manager dashboard, and low ratings create an alert.
- **Support**: "Report a problem" (missing item, wrong order, payment issue) → a ticket the branch manager resolves, with a reply sent by WhatsApp.
- **Receipt** page (bilingual, VAT-ready, printable PDF).
- Gentle **web push** notifications (PWA) as well as WhatsApp. The customer controls preferences.
- **Offline-tolerant** tracking page (reconnect + catch-up on status).
- The **branded customer app** uses the restaurant's logo, accent color and cover image, and an optional custom domain/subdomain.

### 3.5 Loyalty, promotions and growth
- Points are earned **on completion only**, reversed on cancel/refund, expire after N months (configurable), and can be **redeemed at checkout** (points → discount) with a server-side cap.
- **Tiers** with configurable thresholds and per-tier earn multipliers.
- **Reward catalog + wallet** (free item, % off, fixed off, bonus points) with expiry and history.
- **Promotions engine**: % off, fixed off, BOGO, free item, combo price, first order, birthday, happy hour (days/times), branch/category/product scope, **coupon codes** with usage limits and per-customer limits, stacking rules.
- **Referral** (code/link, reward both sides after the friend's first completed order, with fraud checks: same device/phone pattern).
- **CRM segments** (auto-computed nightly): New, Regular, VIP, High spender, At risk, Inactive, Frequent buyer of X.
- **Campaigns** (WhatsApp template / SMS / push) to a segment, scheduled, with consent and quiet hours, measured by orders and revenue attributed.
- **QR campaigns**: QR per branch/lane/campaign/channel with UTM-style parameters, branded printable posters (A4/A5/sticker PDF), and analytics for scans → visits → orders → revenue → conversion rate.

### 3.6 Operations intelligence
- **Dynamic prep-time and busy-level estimates** from a rolling average of real prep times and the current queue, instead of a static number.
- **Live alert center** (Info / Warning / Critical): order delayed past target, kitchen overloaded, arrived customer waiting > N min, payment failed, low stock, provider down, unusual sales drop/spike, low rating. Delivered in-panel, by sound and optionally by WhatsApp to the manager.
- **TV Command Center** (fullscreen, readable from 3 m): live orders, sales today vs yesterday and vs the same weekday, preparing/ready/delayed counts, average prep, alerts, live sales graph.
- **Executive multi-branch screen** for the owner/GM: branch health cards (🟢 Healthy / 🟡 Busy / 🔴 Attention), with text labels and not color alone.
- **Smart insights** (rule-based first, AI-written summaries when enabled), for example "Sales are 14% higher than last Tuesday", "Lusail prep time rose 2 min since 7 PM", "12 customers are at risk".

### 3.7 Reports and finance
- Owner/GM/branch reports: daily, weekly, monthly, custom range, with comparisons.
- Accounting: gross, discounts, net, VAT, refunds, payment fees, cash vs card vs online, transactions, failed payments, **daily branch reconciliation** (expected vs collected cash for pay-at-pickup, with variance notes).
- Exports: Excel, CSV, PDF (proper Arabic shaping, so render PDFs through headless Chromium), and a print view. Filters are applied before export.
- **Scheduled reports**: daily summary to the owner by email/WhatsApp at a chosen time, and weekly/monthly PDFs.

### 3.8 Security and privacy
- Staff: email + password with **optional 2FA (TOTP) required for owners and super admins**, device/session list with "sign out other devices", lockout rules from the reference (5 fails / 15 min → 30 min lock by email and IP), security alerts.
- Customers: hashed OTP (never logged or returned outside `local` env), Sanctum-backed customer sessions with a device list and revoke option, rate limits per phone + per IP + per device.
- Strict **tenant isolation**: global scopes + policies + tests proving that restaurant A can never read or write restaurant B (see section 5).
- Encrypted provider secrets (per restaurant or per platform), signed webhook verification, idempotency keys, CSP and security headers, Spatie activity log on every important model, PII masking for accounting roles, customer **data export / delete** requests, and location consent stored with a timestamp.

---

## 4. Tech stack (use the latest stable versions, verify with `composer`/`npm` at project start)

- **PHP 8.4+, Laravel 13**, MySQL 8 in production (or PostgreSQL 16). SQLite locally and in tests (XAMPP MariaDB 10.4 is too old for Laravel 13).
- **Filament** (latest stable) for the Super Admin and Restaurant panels, with a **custom theme** (no stock look) and full RTL.
- **Livewire** (latest stable) + **Alpine.js** + **Tailwind CSS 4** + Vite for the website, customer PWA and ops screens.
- **Laravel Reverb** + Laravel Echo for realtime. **Redis** for cache, queues and sessions. **Laravel Horizon** for queues.
- **Laravel Sanctum** (customer sessions/API), enum-backed **Positions / Platform roles** (our own, see docs/ARCHITECTURE.md §4; chosen over spatie/laravel-permission teams in Phase 0), **spatie/laravel-activitylog**, **spatie/laravel-medialibrary** (images, conversions), **spatie/laravel-translatable** (bilingual JSON columns), **spatie/laravel-backup**, **spatie/laravel-pdf** (Chromium) for Arabic-safe PDFs, **maatwebsite/excel** or **openspout** for exports, **simplesoftwareio/simple-qrcode** or **endroid/qr-code** for QR.
- Testing: **Pest** (feature + unit + browser tests for the critical flows), **Larastan** level 8+, **Laravel Pint**, GitHub Actions CI.
- Monitoring: **Laravel Pulse**, Sentry (or similar) driver, health checks.
- Fonts (self-hosted): English **Geist** (UI) + **Fraunces** (display); Arabic **IBM Plex Sans Arabic** (UI) + **Alexandria** (display). Icons: **Lucide** or **Phosphor** (one set only).
- Timezone `Asia/Qatar` by default, per-restaurant currency (default QAR) and timezone.

---

## 5. Architecture rules

- **Domain modules** under `app/Domain/{Platform, Tenancy, Staff, Branches, Menu, Customers, Ordering, Kitchen, Payments, Loyalty, Promotions, Marketing, Notifications, Reporting, Support, Integrations, Website}` with Actions (single-purpose classes), DTOs, Enums, Events, Policies. Controllers, Livewire components and Filament resources stay thin and call Actions.
- **Tenancy**: single database. Every tenant-owned table has `restaurant_id` (and `branch_id` where it applies). A `BelongsToRestaurant` trait adds a global scope from the resolved current tenant. Filament tenancy uses `Restaurant`. There must be a **Pest architecture test** that fails if a tenant model lacks the trait, and **cross-tenant tests** for every resource.
- **Order state machine** (enum + transition map, enforced in one `TransitionOrder` action and guarded at the DB level):
  `PENDING_PAYMENT → PAID|PAYMENT_FAILED → RECEIVED → (SCHEDULED) → ACCEPTED → PREPARING → (QUALITY_CHECK) → READY → (ARRIVED) → PICKED_UP → COMPLETED`, plus `CANCELLED` and `REFUNDED` from the allowed states only. Every transition writes `order_status_history` (who, when, from, to, reason), fires a domain event, broadcasts, and triggers notifications/loyalty side effects through queued listeners. Admin "reset as new" and "re-enable" are explicit, permission-gated, audited transitions.
- **Money**: integer minor units (dirhams) or `decimal(12,3)` with a Money value object. VAT/tax and service fee are computed server-side per restaurant settings. Rounding rules are documented.
- **Order creation**: one DB transaction, an idempotency key from the client, server-side repricing, an availability + stock + capacity check, then payment intent → order confirmed only on gateway success/webhook. Pay-at-pickup stays `PENDING` until the cashier collects.
- **Scheduled orders** are held in `SCHEDULED` and released to the kitchen by a queued job at `prepare_at` (computed from pickup time − dynamic prep estimate).
- **Realtime channels**: `private-branch.{id}.orders`, `private-branch.{id}.kitchen.{station}`, `private-restaurant.{id}.dashboard`, `private-platform.dashboard`, and `order.{shortCode}` (authorized by the customer session or a signed staff link). All screens update with **no manual refresh**.
- **Provider drivers** (Laravel Manager pattern) configured per restaurant with encrypted credentials: `PaymentGateway` (Fake, SkipCash, MyFatoorah, Tap, ...), `WhatsApp` (Fake, Meta Cloud API), `Sms` (Fake, Twilio/local), `Pos` (Fake, generic webhook), `Printer`, `Maps`, `Ai` (Fake, Claude API using the latest Claude model), `Mail`.
- **Outgoing webhooks** for clients (order.created, order.status_changed, payment.*, refund.*) with signing secret, retries and delivery log. A **public REST API** (Sanctum tokens, per-restaurant scopes) for POS integrations, versioned `/api/v1`.
- **Localization**: `lang/ar` and `lang/en` JSON + PHP files, no hard-coded strings, `dir` switches on `<html>`, a locale switcher that remembers the user's choice, bilingual content columns for every customer-visible text, and Carbon locale for dates.
- **Performance budget**: customer menu LCP < 2.0 s on 4G, JS < 120 KB gzip on the customer app, all lists paginated, eager loading enforced (`Model::preventLazyLoading()` outside production), caching of menu per branch with tag-based invalidation.

---

## 6. Data model (minimum; add what the features require)

`users`, `platform_roles/permissions` (spatie), `plans`, `plan_features`, `subscriptions`, `invoices`, `leads`, `lead_notes`, `restaurants` (slug, names, logo, accent color, currency, timezone, tax_rate, service_fee, menu_link_mode, loyalty settings, status), `restaurant_settings`, `feature_flags`, `branches` (code, names, address, phones, lat/lng, maps_url, logo, tracking settings: tracking_enabled, arrival_radius_m, approach_radius_m, avg_speed_kmh, location_ping_seconds, auto_arrival; capacity_per_slot, pause_until, busy_extra_minutes), `branch_hours`, `branch_closures`, `lanes`, `stations`, `devices` (paired screens), `positions`, `position_permissions`, `employees` (user + restaurant + position + status + pin_hash), `branch_employee`, `categories`, `products`, `product_branch` (availability, price override, out_of_stock_on, daily_stock), `modifier_groups`, `modifier_options`, `product_modifier_group`, `combos`, `combo_items`, `menu_schedules`, `customers` (restaurant_id + phone unique, name, birthday, language, totals, tier), `customer_devices`, `customer_vehicles`, `favorites`, `otp_requests` (hashed code, channel, attempts, ip, device), `orders` (number, short_code, pickup_code, status, payment status/method, subtotal, discount, tax, service_fee, total, points_earned, points_redeemed, prep preference/delay, scheduled_for, prepare_at, target_prep_minutes, location fields, arrival fields, lane, idempotency_key, source/qr_code_id), `order_items`, `order_item_modifiers`, `order_status_history`, `payments`, `payment_transactions`, `refunds`, `loyalty_accounts`, `loyalty_transactions`, `tiers`, `rewards`, `reward_redemptions`, `promotions`, `coupons`, `coupon_redemptions`, `referrals`, `segments`, `campaigns`, `campaign_messages`, `notifications`, `notification_preferences`, `qr_codes`, `qr_scans`, `ratings`, `support_tickets`, `ticket_messages`, `alerts`, `reports` (scheduled), `report_runs`, `webhook_endpoints`, `webhook_deliveries`, `api_tokens`, `integrations`, `site_content` (versioned, draft/published), `site_media`, `security_alerts`, `login_attempts`, `activity_log`, `settings`.

---

## 7. Design system

- Build the **tokens first** (CSS variables in Tailwind 4 `@theme`, mirrored into the Filament theme): color, type scale, spacing (4/8 grid), radius, elevation, motion durations/easings, z-index. **No hard-coded colors in components.**
- **Palette direction** (inherited from the reference's best work): *Midnight & Ice* for dashboards (ink #0B1220, slate surfaces, ice highlights) and *Deep Teal & Brass* for luxury customer/website surfaces (teal #0F3B3A, brass #B08D57, sage, ivory #F7F5F0). Semantic status colors: new, in-progress, ready, delayed, done, cancelled, always paired with an icon + label. Both light and dark modes pass **WCAG AA**.
- Customer UI: large food photography, generous whitespace, sticky cart bar, thumb-reach primary actions, bottom navigation (Home, Menu, Orders, Rewards, Profile), skeletons, success micro-animations, haptics where supported. Respect `prefers-reduced-motion`.
- Management UI: information-dense but calm. Left rail navigation (collapsible), top bar with branch selector + date range + global search (⌘K) + notification bell + profile, KPI cards with trend arrows, clean tables with saved filters, slide-over detail panels, confirm dialogs for destructive actions, and toasts.
- Ops screens: huge touch targets (≥ 56 px), readable at distance, high contrast, no hover-dependence, sound + visual + text for every alert.
- Use the motion rules of the `animate` / `apple-design` / `emil-design-eng` skills: fast (150-250 ms), purposeful, interruptible. Avoid cheap gradients, clutter, tiny text and generic templates.

---

## 8. Roles and permissions matrix (enforced by policies + panel navigation)

| Capability | Super admin | Platform staff | Owner | General mgr | Branch mgr | Cashier | Kitchen | Expeditor | Accountant | Marketing |
|---|---|---|---|---|---|---|---|---|---|---|
| Platform settings, plans, clients, CMS | ✓ | per permission | | | | | | | | |
| Restaurant settings, billing | ✓ (impersonate) | support: view | ✓ | | | | | | | |
| Branches | ✓ | | ✓ | ✓ | own: edit hours/pause | | | | | |
| Employees & positions | ✓ | | ✓ | ✓ | own branch | | | | | |
| Menu | ✓ | | ✓ | ✓ | own: availability/stock | | | | | |
| Live orders / status change | override | | override (toggle) | ✓ | ✓ | ✓ | kitchen stages | ready → picked up | view | |
| Kitchen display | | | view | view | ✓ | | ✓ | ✓ | | |
| Payments collect / refund | | | ✓ | ✓ | refund ≤ limit | collect | | | view | |
| Customers / CRM | | | ✓ | ✓ | own branch (masked) | name+vehicle only | | | masked | ✓ |
| Loyalty, promotions, campaigns | | | ✓ | ✓ | | | | | | ✓ |
| Reports & exports | all | per permission | ✓ | ✓ | own branch | | | | ✓ finance | marketing |
| Audit log | ✓ | per permission | own restaurant | | | | | | | |

Custom positions are built from the same permission list.

---

## 9. Definition of Done (every phase, every feature)

1. Works in **Arabic RTL and English LTR**, light and dark mode, at **375 px, 768 px, 1280 px and 1920 px** (ops screens also at TV size).
2. **Pest tests pass**: happy path, validation, authorization (wrong role → 403), and **cross-tenant isolation**. Larastan and Pint are clean.
3. No N+1 queries, no console errors, no failed jobs, no unhandled exceptions shown to users (friendly bilingual error states).
4. Loading / empty / error / success states designed, with keyboard navigation and screen-reader labels.
5. Audit entries are recorded for sensitive actions. Seeders give realistic bilingual demo data (3 branches, 50 products, 100 customers, 200 orders across all statuses, loyalty transactions, promotions).
6. Demo the result in the browser before calling it done (use the `verification-before-completion` and `webapp-testing` skills). Report exactly what was verified and anything that was not.
7. Update `docs/ROADMAP.md` checkboxes and append a short entry to `docs/CHANGELOG.md`.

---

## 10. Success scenario (the whole system must pass this end to end)

1. A prospect submits the website request form → the lead appears in Super Admin → converted to a client in one action.
2. The owner logs in → completes the onboarding wizard → creates 2 branches, imports a menu, creates a Cashier and a Kitchen employee, assigns positions and branches, prints the branch QR.
3. A customer scans the QR → menu opens on the right branch → customizes a burger, accepts "make it a meal", sees 2 recommendations → enters phone → WhatsApp OTP → adds vehicle → pays (fake gateway) → gets a confirmation with order number, pickup QR and tracking link.
4. Cashier board and KDS receive the order **instantly** with a chime and red glow → kitchen advances → customer tracking updates live → Ready alert fires.
5. Customer shares location → staff see distance/ETA → auto-arrival → staff scan the pickup QR at Lane 1 → completed.
6. Points are added **on completion**, the wallet updates, and a rating request arrives.
7. Branch manager, owner (all branches), accountant (finance) and the TV Command Center all reflect the order live. The daily report includes it. Every step is in the audit log.
8. Restaurant B's users cannot see any of restaurant A's data (automated test).
