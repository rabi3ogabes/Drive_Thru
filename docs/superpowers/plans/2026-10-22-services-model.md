# Services Model: Drive-Thru, Order-Ready Call & Rewards — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sell Drive-Thru Plus as three services. An owner takes one, two or all three. Each service can run as a free demo for a set number of days, and the admin can add any service to any owner. The public website presents the three services in a luxury, calm way, with a price calculator and a demo request.

**User decisions (2026-10-22):**
- Build this first; Phase 7B pauses after Task 1 (guest stats and consent, done and tested) and resumes afterwards.
- **Order-Ready Call** is a phone pager plus a message: the guest's phone rings and vibrates on a page, and WhatsApp/SMS is sent too. A TV shows ready numbers. No voice calls.
- **Rewards** adds stamp cards ("buy 5, the 6th is free") **next to** the points program; the owner uses either or both.
- **Price per service:** each service has a monthly price per branch, set by the admin; the invoice adds them up, with an optional bundle discount for 2 or 3 services.

**Stack:** unchanged (Laravel 13, Filament 5, Livewire 4, Alpine, Tailwind 4, Reverb, Pest 5). The React mention in the request is covered by the existing Livewire/Alpine front end; no second front-end stack.

## The three services

| Service | What the owner gets | Works on its own? |
|---|---|---|
| **Drive-Thru** | Everything built so far: online menu, ordering, payment, car and arrival, kitchen and pickup screens, TV, printing, POS. | Yes |
| **Order-Ready Call** | A pager on the guest's own phone: scan a QR at the counter (or the cashier types the number), wait anywhere, and the phone rings, vibrates and gets WhatsApp/SMS when the order is ready. A TV board shows "Preparing" and "Ready" numbers. | Yes (counter, dine-in, food court). With Drive-Thru, online orders page the guest automatically. |
| **Rewards** | Stamp cards (buy X of a product or category, the next one is free), points, tiers, rewards wallet, promotions and coupons. | Yes: the cashier adds stamps at the counter by phone number. With Drive-Thru, stamps and points are added at pickup automatically. |

## How plans and services fit together

- **Services carry the price.** `service_prices` (admin-editable): price per branch per month for each service, demo days, bundle discount for 2 and for 3 services.
- **Plans become tiers without their own price:** Starter, Growth and Enterprise keep their **limits** (branches, employees) and **add-ons** (advanced reports, API, POS, AI, custom domain, priority support). The admin can still give a tier a fee (Enterprise "custom"); it defaults to 0.
- **Feature gating:** every `PlanFeature` that belongs to a service needs that service on:
  - Loyalty, Promotions and Campaigns need Rewards.
  - Arrival tracking, Scheduled orders and Kitchen stations need Drive-Thru.

  Platform overrides still win, as today.

## Global constraints

- Same as before: tenancy trait and `$attributes` defaults, bilingual strings, Western digits, design tokens, audits, no N+1, minor units, cross-tenant tests, policies.
- Money and service state change on the server only.
- Every service change (added, demo started, converted, cancelled, expired, price override) is audited and visible to the owner in Billing.
- Existing clients keep working: the migration gives every restaurant Drive-Thru as active, and Rewards too where the plan already includes loyalty, at a price equal to what they pay today, so no invoice changes.

## Review focus

1. **A demo that ends** must switch the service off the same day, unless the owner chose "keep it". In that case it becomes paid from that day, never retroactively. Task 3.
2. **A demo is offered once per service per restaurant**; the admin can grant another one on purpose, and that is audited. Task 2.
3. **Adding a service mid-period** is charged pro rata for the days left, on one prorated invoice. Cancelling stops at the end of the paid period, with no refund. Task 3.
4. **A pager link is a capability:** an unguessable token, valid for one ticket, never listing other guests' numbers or phones. Task 5.
5. **Stamps cannot be farmed:**
   - one card per guest per program;
   - a daily cap per guest;
   - a cashier stamp needs the cashier's own permission and is audited;
   - a cancelled or refunded order takes its stamps back.

   Task 7.

---

## Milestone S1: Services and billing

### Task 1: Services catalog and restaurant services

- `Service` enum (`drive_thru`, `ready_call`, `rewards`) with ar/en label, description and icon.
- Table `service_prices`: service, price_minor (per branch per month), currency, demo_days (default 14), is_active, sort. Platform setting `billing.bundle_discount_bp` for 2 and for 3 services (defaults: 10%, 15%).
- Table `restaurant_services`:
  - columns: restaurant_id, service, status (`demo` | `active` | `cancelling` | `ended`), demo_ends_at, keep_after_demo, unit_price_minor (locked at the moment it was added; the admin may override it), started_at, ends_at, demo_used_at, added_by;
  - unique per (restaurant, service).
- `Restaurant::hasService(Service)`: true for an active service, a running demo, or a service that is cancelling until its paid period ends. `hasFeature()` also checks the service a feature belongs to.
- Gates:
  - **Drive-Thru:** online ordering pages, ops kitchen and pickup screens, arrival.
  - **Order-Ready Call:** pager screens and guest pager pages.
  - **Rewards:** loyalty, stamps and promotions.

  Without the service, owners see a calm "Try it free" card instead of the page; guests see the restaurant page without ordering.
- Migration of existing clients (see Global constraints).
- **Tests:**
  - every gate, in both directions;
  - an override still wins;
  - the migration keeps today's invoice amount;
  - cross-tenant isolation.

### Task 2: Admin adds services; owner starts demos

- **Admin client page:** a "Services" card listing the three services with status, demo days left, price, and these actions:
  - "Add as demo (N days)";
  - "Activate now";
  - "Extend demo";
  - "Grant another demo";
  - "Override price";
  - "End now".

  Each action asks for a reason where it changes money, and is audited.
- **Admin Plans page:** a "Service prices" section to edit prices, demo days and bundle discounts.
- **Owner Billing page:** "Your services".
  - Each service shows a card with its status and price per branch.
  - "Try free for 14 days", once per service.
  - "Keep it after the demo": the owner sees the monthly amount before confirming.
  - "Add now": shows the pro rata amount for today.
  - "Cancel at period end".
  - A live monthly total: branches × services − bundle discount.
- Notifications to the owner: demo started, 3 days left, demo ended or converted, service added by the platform (mail and the in-app bell, bilingual).
- **Tests:**
  - a demo is offered only once;
  - the admin can grant another, and it is audited;
  - an owner without `ManageBilling` is refused;
  - totals are correct with 1, 2 and 3 services and with discounts.

### Task 3: Billing and the demo lifecycle

- The `Subscription` amount becomes the sum of the tier fee and each paid service (price × billable branches), minus the bundle discount.
- Invoices get one line per service plus a discount line, in both languages.
- `RunBillingCycle` gets a new first step:
  - demos that ended either convert to active (if the owner chose to keep them) and are invoiced pro rata to the period end, or end and switch the service off;
  - cancelling services end at the period end.
- Adding a service mid-period issues one prorated invoice. The amount is rounded to the minor unit, and the days are counted in the restaurant's time zone.
- **Tests:**
  - a demo ends exactly once, even when the cycle runs twice;
  - a kept demo becomes paid that day;
  - proration on the 1st, 15th and last day;
  - cancel at period end;
  - a suspended client keeps no services working;
  - a property test: an invoice total is never negative.

## Milestone S2: Order-Ready Call

### Task 4: Pager tickets and the cashier screen

- `PagerTicket`:
  - columns: restaurant_id, branch_id, number (per branch per day, shown big), label (optional, e.g. "2 lattes"), status (`waiting` | `ready` | `collected` | `expired`), phone (optional), token (32 random bytes), order_id (optional), ready_at, collected_at, expires_at;
  - expires 6 hours after creation.
- A new ops screen kind, **"Pager"**, for the counter (paired device or staff PIN):
  - "New ticket" (big number keypad or auto number, optional phone);
  - a QR shown on screen and printable on the receipt;
  - waiting and ready lists;
  - one tap "Ready" calls the guest, one tap "Collected", and "Call again".
- Drive-Thru orders: when an order becomes Ready, the existing order page rings the same way. No ticket is needed; the pager is the order page.
- Permission `OperatePager` (new `RestaurantPermission` case, ar/en label and description), given to Cashier and Manager positions by default.
- **Tests:**
  - numbers per branch per day;
  - tokens are unguessable and scoped to one ticket;
  - a cashier from another branch is refused;
  - expiry;
  - realtime events go only to that branch's channel.

### Task 5: The guest's pager page and the ready board

- `/{restaurant}/p/{token}` is a full-screen page in the restaurant's brand. It shows a large number and a calm "We'll call you here" waiting state, with a breathing animation, and keeps the phone awake (Wake Lock API).
  - **On ready:** a ringtone loops (unlocked by the guest's first tap: "Tap to turn on sound"), the phone vibrates in a pattern, the screen turns to "Your order is ready" with the pickup point, and a push notification is sent if allowed.
  - Updates arrive in realtime (Reverb), with polling every 10 s as a fallback.
- If a phone was entered, WhatsApp/SMS is sent through `Messenger` with a new purpose, `pager_ready`, and its template.
- A TV ready board: new ops kind **"Ready board"** with "Preparing" and "Ready" columns, the latest ready number highlighted, and an optional chime. It is reduced-motion aware and readable at 3 m.
- Docs: add the `pager_ready` template to `docs/WHATSAPP_TEMPLATES.md`.
- **Tests:**
  - the page shows only its own ticket;
  - ready triggers the message once;
  - collected and expired states;
  - the board lists numbers only (no phones, no names);
  - AR/EN copy.

## Milestone S3: Stamp cards (Rewards)

### Task 6: Stamp programs

- `StampProgram`:
  - columns: restaurant_id, name (ar/en), what counts (a product or a category, with optional extra products), stamps_needed (e.g. 5), the reward (free item: the cheapest counted item, or a chosen product), card_expiry_days (optional), daily_cap per guest (default 3), is_active, archived_at;
  - several programs can run at once, each with its own card.
- `StampCard`: customer, program, stamps, completed_count, last_stamped_at. `StampEvent` is a ledger: order or cashier, +/−, actor, reason.
- **Owner UI:** "Stamp cards" under Loyalty & offers. It has a live card preview (the guest's card in the restaurant's brand, with N circles) and an example line: "Buy 5 Ice creams, the 6th is free".
- **Tests:**
  - validation (needs at least 2 stamps, one target);
  - archiving keeps existing cards usable until they expire;
  - cross-tenant isolation.

### Task 7: Earning and using stamps

- **At pickup:** each counted item adds 1 stamp, up to the daily cap. A full card turns into a wallet reward (reusing `GrantReward`, source `stamps`) and the card starts again with any extra stamps carried over.
- **At the counter** (new ops action "Add stamps", permission `OperatePager` or `CollectPayments`): enter the guest's phone, choose the items, and the guest gets a WhatsApp/SMS "+2 stamps, 3 to go". The step is audited and counts toward the daily cap.
- **Cancel or refund:** the order's stamps are taken back. If that undoes a completed card, the unused reward is withdrawn; if it was already used, the card goes negative and is recorded, never below the previous completed state.
- **Guest UI:**
  - the Rewards page shows each card with stamp circles that fill with a soft animation;
  - checkout shows "1 more for a free Ice cream";
  - the free item is used from the wallet as today.
- **Tests:**
  - the happy path;
  - the daily cap;
  - carry-over;
  - cancel takes stamps back;
  - a completed-then-refunded card;
  - a counter stamp needs permission and is audited;
  - stamps and points both earned on the same order;
  - no N+1 on the rewards page.

## Milestone S4: The luxury website

### Task 8: Home, services and pricing

- The home page is redesigned around the three services (design skills: taste-skill, soft-skill, impeccable, emil-design-eng, apple-design):
  - a hero;
  - three service sections, each with a living product illustration (phone pager ringing, stamp card filling, drive-thru order flow), built in HTML/SVG and tokens, no stock photos;
  - "how it works in 3 steps";
  - trust and security notes;
  - FAQ;
  - final call to action.
- `/services/{drive-thru|ready-call|rewards}`: one page per service with features, the screens it includes, and "Works with" notes.
- `/pricing` has a calculator. The visitor picks services and the number of branches and sees the monthly total with the bundle discount live; prices come from `service_prices`, never hard-coded. Every service shows "Free for 14 days".
- "Book a demo" becomes a lead form with the chosen services and branches, saved on the `Lead`. The admin can convert the lead into a client with those services already on demo.
- SEO and quality:
  - meta and Open Graph per page in AR and EN;
  - `hreflang`;
  - JSON-LD (Organization, Product/Offer);
  - a sitemap;
  - self-hosted fonts;
  - no third-party requests;
  - Lighthouse ≥ 95 for performance and accessibility on mobile.
- **Tests:**
  - every page renders in AR and EN;
  - calculator totals match `ServicePricing` (one shared class);
  - the lead stores its services;
  - converting the lead starts demos;
  - no external URLs in the HTML.

## Task 9: Quality gate, browser verification, docs

- [ ] Full suite, Pint, Larastan 0, `npm run build`, `composer warm`.
- [ ] Demo data:
  - Masar has all three services;
  - Sahra has Drive-Thru plus a Rewards demo with 5 days left;
  - Karak House has Ready Call only, with pager tickets and two stamp programs on another client.
- [ ] Browser checks in AR/EN, light/dark, at 375/768/1280 (and TV size for the ready board):
  - the website home, a service page and pricing;
  - the admin Services card;
  - owner Billing "Your services";
  - the pager screen;
  - the guest pager ringing;
  - the ready board;
  - stamp program editing;
  - the guest stamp card;
  - "Add stamps" at the counter.
- [ ] Update ROADMAP (new "Services" phase between 7A and 7B; Phase 9 partly done), CHANGELOG, ARCHITECTURE §4.9 (services and gating), CLAUDE.md gotchas.

## After this plan

Resume Phase 7B from Task 2 (segments), with campaigns and referral gated by the Rewards service, then 7C.
