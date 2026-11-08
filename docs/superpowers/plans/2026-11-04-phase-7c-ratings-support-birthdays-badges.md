# Phase 7C: Ratings, Support Tickets, Birthday Gifts and Badges — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** After every pickup the restaurant learns how it went and can put things right; guests get an automatic birthday gift; owners can switch on weekly streaks and badges.

**Architecture:** Four new domains: `Domain/Feedback` (ratings), `Domain/Support` (tickets), `Domain/Loyalty/Birthdays`, `Domain/Loyalty/Badges`.
- They hang off the existing `OrderStatusChanged` event and order page, and reuse `GrantReward`, `RefundPayment`, `Messenger`, `WebPushSender` and `StaffAlerts`.
- Settings live in `restaurants.settings.<key>` behind small value classes, following `ReferralSettings`.

**Tech Stack:** Laravel 13, Filament 5, Livewire 4, Alpine, Tailwind 4, Pest 5.

**Spec:** `docs/superpowers/specs/2026-11-04-phase-7c-ratings-support-birthdays-badges-design.md` (approved 2026-11-04). Read it with this plan; its decisions D1–D7 are binding.

## Global Constraints

- Every guest-visible string in `lang/ar` and `lang/en`; Western digits; logical CSS properties; design tokens only; phones and codes through `Bidi::ltr()`.
- Every new model uses `BelongsToRestaurant` and declares `$attributes` defaults for every column; each feature has a cross-restaurant isolation test.
- **Gating:**
  - Ratings and tickets need `Restaurant::hasService(Service::DriveThru)`.
  - Birthday gifts and badges need `hasFeature(PlanFeature::Loyalty)`.
- Money is in minor units; refunds only through `RefundPayment`; rewards only through `GrantReward`.
- New permission `RestaurantPermission::HandleSupport = 'support.handle'`, group `customers`, labels in `lang/{en,ar}/permissions.php`. Given to the `general_manager` (automatic) and `branch_manager` presets in `DefaultPositions`.
- **Audit log entries:**
  - `support.replied`, `support.refunded`, `support.rewarded`, `support.resolved`;
  - `loyalty.birthday_settings_updated`, `loyalty.badge_settings_updated`.
- Gamification is off by default: `BadgeSettings::for()` returns `enabled = false` when nothing is stored.
- Every screen has loading, empty, error and success states; no N+1 queries (eager-load in table queries).

## Review Focus

1. **A guest rates or reports on someone else's order** by guessing an order code: refused. Tasks 1 and 3 each have a test signed in as another guest.
2. **Double submit** (two taps, two tabs) on rating, ticket creation or refund: exactly one row or one refund.
   - Ratings: unique `order_id` plus a caught `QueryException`.
   - Tickets: `lockForUpdate` on the order.
   - Refunds: `RefundPayment` already locks the payment.
   - Tested in Tasks 1, 3 and 4.
3. **A partial refund larger than what is left** after an earlier refund: refused with a clear message, nothing sent. Task 4.
4. **A birthday-gift run on a day when the reward was archived**, or the guest changed their birthday after a gift: skipped, no exception, no second gift within 365 days. Task 5.
5. **A streak across 31 Dec / 1 Jan**, and a week with a cancelled order only: the year boundary keeps the streak; a cancelled order doesn't count. Task 6.

---

### Task 1: Ratings — model, guest card, alert, push

**Files:**
- Create: `database/migrations/2026_11_04_100000_create_order_ratings_table.php`.
- Create: `app/Domain/Feedback/Models/OrderRating.php`.
- Create: `app/Domain/Feedback/RatingTags.php`.
- Create: `app/Domain/Feedback/Actions/RateOrder.php`.
- Create: `app/Domain/Feedback/Exceptions/RatingRefused.php`.
- Create: `app/Domain/Feedback/Listeners/AskForRating.php`.
- Create: `app/Domain/Feedback/Jobs/SendRatingReminder.php`.
- Modify:
  - `app/Domain/Notifications/Alerts/AlertKind.php` (case `LowRating`);
  - `lang/{en,ar}/alerts.php`, or wherever `AlertKind` labels live — follow `OrderDelayed`;
  - `app/Livewire/Customer/OrderStatus.php`;
  - `resources/views/livewire/customer/order-status.blade.php`;
  - `app/Providers/AppServiceProvider.php`.
- Create: `lang/{en,ar}/feedback.php`.
- Test: `tests/Feature/Feedback/RatingsTest.php`.

**Interfaces:**
- Produces:
  - `OrderRating`: `restaurant_id`, `branch_id`, `order_id` (unique), `customer_id`, `stars` (int 1–5), `tags` (array cast), `comment` (?string, max 280), `created_at` (immutable). Relations: `order()`, `customer()`, `branch()`.
  - `RatingTags::POSITIVE = ['fast','hot_fresh','friendly','accurate']` and `RatingTags::NEGATIVE = ['slow','cold','missing_item','wrong_order','unfriendly']`.
  - `RatingTags::for(int $stars): list<string>` returns POSITIVE for 4–5 stars, NEGATIVE otherwise.
  - `RateOrder::WINDOW_HOURS = 48`.
  - `RateOrder::handle(Order $order, Customer $customer, int $stars, array $tags, ?string $comment): OrderRating` throws `RatingRefused` with a reason (`not_yours`, `not_picked_up`, `too_late`, `already_rated`, `invalid`).
  - `RateOrder::canRate(Order $order, ?Customer $customer): bool`.
  - Tags outside `RatingTags::for($stars)` are dropped, not refused.
- Consumes: `StaffAlerts::send(AlertKind, int $restaurantId, ?Branch, array $params, string $url)` and `WebPushSender::send(Model $subscriber, array $payload, ?int $restaurantId)`.

- [ ] **Step 1: Write the failing tests** in `RatingsTest.php`:
  - `it('lets the guest rate a picked-up order once, keeping only tags that fit the stars')`:
    - rate 2 stars with tags `['slow','fast']`;
    - expect one `OrderRating` with `stars` 2 and `tags` `['slow']`;
    - a second call throws `RatingRefused` with reason `already_rated`.
  - `it('refuses another guest, an order not picked up, and after 48 hours')`: the three reasons `not_yours`, `not_picked_up`, `too_late` (travel 49 h).
  - `it('raises one low-rating alert for 1 or 2 stars only')`:
    - `Notification::fake()`; rate 1 star, then on another order 3 stars;
    - assert managers got exactly one `LowRating` alert.
  - `it('sends one rating reminder 30 minutes after pickup, and none once rated')`:
    - `Queue::fake()` asserts `SendRatingReminder` is pushed with a 30-minute delay;
    - running the job after a rating sends no push (fake `PushTransport`).
  - `it('shows the rating card on the order page and saves from it')`:
    - Livewire `OrderStatus`: `->call('rate', 5, ['fast'], 'Great')`;
    - `assertSee(__('feedback.thanks'))`.
  - `it('keeps ratings to their restaurant')`: a guest of restaurant B can't rate restaurant A's order through `OrderStatus` (404 from mount).
- [ ] **Step 2: Run** `vendor/bin/pest tests/Feature/Feedback/RatingsTest.php`. Expected: FAIL (classes missing).
- [ ] **Step 3: Implement** the migration, model, `RatingTags`, `RateOrder` (transaction; unique-violation → `already_rated`), the `AlertKind::LowRating` case (icon `heroicon-o-star`, warning color, not critical), and the listeners:
  - `AskForRating` on `OrderStatusChanged` to `PickedUp` dispatches `SendRatingReminder($orderId)->delay(now()->addMinutes(30))`.
  - The job sends `['title' => feedback.push.title, 'body' => feedback.push.body, 'url' => route('customer.order', …)]` only when there is no rating yet and `WebPushSender::hasSubscribers($customer)`.
  - `RateOrder` raises the alert after commit when stars ≤ 2, with url `ListRatings::getUrl(...)` (Task 2's page; until then use the order URL and switch it in Task 2).
- [ ] **Step 4: Implement the guest card** in `OrderStatus`:
  - `public function rate(int $stars, array $tags = [], ?string $comment = null): void`;
  - view data `rating` (existing `OrderRating` or null) and `canRate`.
  - Card: 5 stars (44 px targets, `aria-label` per star), tag chips that change with the stars, comment textarea, send button with loading state; thank-you state after.
  - After 1–3 stars with `missing_item` or `wrong_order`, the thank-you card links to "Report a problem" (Task 3 adds the target; until then hide the link behind `Route::has('…')`, not a dead button).
- [ ] **Step 5: Run** the test file and `vendor/bin/pest tests/Feature/Ordering`. Expected: all PASS.
- [ ] **Step 6: Commit** `feat(feedback): post-pickup ratings with low-rating alert and reminder`.

### Task 2: Ratings — owner page, dashboard tile, guest profile

**Files:**
- Create: `app/Filament/App/Resources/Ratings/RatingResource.php` (Operations group, list only, slug `ratings`).
- Create: `app/Filament/App/Resources/Ratings/Pages/ListRatings.php`.
- Create: `resources/views/filament/app/resources/ratings/summary.blade.php`.
- Create: `app/Domain/Feedback/RatingStats.php`.
- Create: `app/Policies/RatingPolicy.php` (ViewReports, read-only).
- Modify: `app/Filament/App/Widgets/RestaurantOverview.php` (tile), `app/Domain/Crm/GuestProfile.php` and `resources/views/filament/app/resources/customers/view-guest.blade.php` (ratings list), `AppServiceProvider` (policy).
- Test: `tests/Feature/Feedback/RatingsAdminTest.php`.

**Interfaces:**
- Produces: `RatingStats::for(int $restaurantId, ?array $branchIds, CarbonImmutable $from, CarbonImmutable $to): array{count:int, average:?float, distribution:array<int,int>, tags:array<string,int>, days:list<array{date:string,average:?float,count:int}>}`.
  - `average` is rounded to 1 decimal; `distribution` keys 1–5 always present; `tags` sorted by count desc.
- Consumes: `OrderRating` (Task 1); `Workspace::scopeToBranches()`.

- [ ] **Step 1: Write failing tests:**
  - `it('summarises ratings: average, distribution, top tags and 30 days')` with fixed ratings → exact numbers.
  - `it('lists ratings with branch, stars and tag filters, scoped to the employee branches')`.
  - `it('shows the 30-day rating on the dashboard and the guest profile')`.
  - `it('forbids staff without View reports, and hides other restaurants')`.
- [ ] **Step 2: Run them**, expect FAIL.
- [ ] **Step 3: Implement** `RatingStats` (one grouped query for distribution, one for tags in PHP over the period's rows, one for days), the resource with filters `branch_id`, `stars`, tag (`whereJsonContains`) and `has_comment`; the summary view (tiles + distribution bars + top tags + 30-day SVG chart, `dir="ltr"` like the QR funnel); the dashboard Stat ("4.6 ★ · 128 ratings", description "last 30 days"); the guest profile list (last 5). Point the low-rating alert URL at `ListRatings`.
- [ ] **Step 4: Run** the Feedback tests. Expected: PASS.
- [ ] **Step 5: Commit** `feat(feedback): ratings page, dashboard tile and guest profile`.

### Task 3: Support tickets — guest report and conversation

**Files:**
- Create: `database/migrations/2026_11_04_110000_create_support_tables.php` (`support_tickets`, `ticket_messages`).
- Create:
  - `app/Domain/Support/Models/SupportTicket.php`;
  - `app/Domain/Support/Models/TicketMessage.php`;
  - `app/Domain/Support/Enums/TicketCategory.php` (`missing_item`, `wrong_item`, `quality`, `charged_incorrectly`, `other`);
  - `app/Domain/Support/Actions/OpenTicket.php`;
  - `app/Domain/Support/Actions/GuestReply.php`;
  - `app/Domain/Support/Exceptions/TicketRefused.php`.
- Modify: `AlertKind` (case `TicketOpened`), `OrderStatus` (+ view), `RestaurantPermission` (+ `HandleSupport`, group `customers`), `lang/{en,ar}/permissions.php`, `DefaultPositions` (`branch_manager`).
- Create: `lang/{en,ar}/support.php`.
- Test: `tests/Feature/Support/GuestTicketsTest.php`.

**Interfaces:**
- Produces:
  - `SupportTicket`:
    - `number` (int per restaurant), `category` (`TicketCategory`), `items` (array of order_item ids);
    - `status` constants `OPEN`, `ANSWERED`, `RESOLVED`;
    - `outcome` (`refund` | `reward` | null), `refund_id`, `wallet_reward_id`, `first_reply_at`, `resolved_at`, `resolved_by`;
    - `displayNumber(): string` returns `"T-{number}"`;
    - relations `order()`, `customer()`, `branch()`, `messages()` (oldest first).
  - `TicketMessage`: `author` (`guest` | `staff`), `user_id` (?int), `body`, `message_delivery_id` (?int).
  - `OpenTicket::WINDOW_DAYS = 7`.
  - `OpenTicket::handle(Order $order, Customer $customer, TicketCategory $category, array $itemIds, string $body): SupportTicket`. Throws `TicketRefused` (`not_yours`, `not_picked_up`, `too_late`, `already_open`, `invalid`). Item ids not on the order are dropped.
  - `GuestReply::handle(SupportTicket $ticket, Customer $customer, string $body): TicketMessage` (only on open or answered tickets; sets status back to `OPEN`).
- Consumes: `StaffAlerts::send`.

- [ ] **Step 1: Write failing tests:**
  - `it('opens one ticket per order within 7 days, numbered per restaurant')`:
    - first ticket `T-1001`, second restaurant also starts at `T-1001`;
    - a second open call → `already_open`;
    - after 8 days → `too_late`.
  - `it('keeps only items from the order and needs a description')`.
  - `it('refuses another guest and orders not picked up')`.
  - `it('alerts the branch managers once when a ticket opens')`.
  - `it('lets the guest follow and reply in the conversation on the order page')`: Livewire `OrderStatus` `->call('reportProblem', 'missing_item', [$itemId], 'No fries')`, then `->call('replyToTicket', 'Still waiting')`, assert both messages visible.
  - `it('opens the report form pre-filled after a low rating with a missing item')`.
  - `it('blocks a double tap: two concurrent opens make one ticket')`: call `OpenTicket` twice; the second throws `already_open`.
- [ ] **Step 2: Run them**, expect FAIL.
- [ ] **Step 3: Implement:**
  - the migration (unique `restaurant_id, number`; index `restaurant_id, status`);
  - the models;
  - `OpenTicket` (transaction, `lockForUpdate` on the order row, number = max + 1 starting at 1001 under the lock);
  - `GuestReply`;
  - `AlertKind::TicketOpened`;
  - the permission (label "Handle support", description "See guest problem reports, reply, refund or give a reward." and the Arabic equivalent).
- [ ] **Step 4: Implement the guest UI** in `OrderStatus` (`reportProblem`, `replyToTicket`, view data `ticket` with messages):
  - a "Report a problem" button;
  - a bottom sheet with category chips, an item checklist for missing or wrong items, a textarea and a submit button with loading state;
  - then the conversation card with the outcome line.
- [ ] **Step 5: Run** Support and Ordering tests, expect PASS.
- [ ] **Step 6: Commit** `feat(support): guests report a problem and follow the conversation`.

### Task 4: Support tickets — staff inbox, reply, refund, reward, resolve

**Files:**
- Create:
  - `app/Domain/Support/Actions/StaffReply.php`;
  - `app/Domain/Support/Actions/RefundTicket.php`;
  - `app/Domain/Support/Actions/RewardTicket.php`;
  - `app/Domain/Support/Actions/ResolveTicket.php`;
  - `app/Policies/SupportTicketPolicy.php`.
- Create:
  - `app/Filament/App/Resources/SupportTickets/SupportTicketResource.php` (Operations group, slug `support`, navigation badge = open count for the user's branches);
  - `Pages/ListSupportTickets.php`;
  - `Pages/ViewSupportTicket.php`;
  - `resources/views/filament/app/resources/support-tickets/view-support-ticket.blade.php`.
- Modify: `app/Domain/Messaging/Data/Message.php` (const `SUPPORT = 'support'`), `GrantReward::SOURCES` (+ `support`), `docs/WHATSAPP_TEMPLATES.md` (`dtp_ticket_reply`, Utility).
- Test: `tests/Feature/Support/StaffTicketsTest.php`.

**Interfaces:**
- Produces:
  - `StaffReply::handle(SupportTicket $t, User $by, string $body): TicketMessage`. Sends `Message(purpose: SUPPORT, parameters: [restaurant, displayNumber, Str::limit(body, 900), orderUrl])` through `Messenger`, stores `message_delivery_id`, sets `ANSWERED` and `first_reply_at` once. Audit `support.replied`.
  - `RefundTicket::handle(SupportTicket $t, User $by, ?int $amountMinor): Refund`. `null` means everything still refundable. Throws `TicketRefused` (`not_online`, `nothing_to_refund`, `too_much`, `refund_failed`); needs `RefundPayments`; on success sets `outcome = refund`, `refund_id`. Audit `support.refunded`.
  - `RewardTicket::handle(SupportTicket $t, User $by, Reward $reward): WalletReward` via `GrantReward` (source `support`); sets `outcome = reward`. Audit `support.rewarded`.
  - `ResolveTicket::handle(SupportTicket $t, User $by, ?string $note): void`. Sets `RESOLVED`, `resolved_at`, `resolved_by`; sends the outcome line to the guest (same template). Audit `support.resolved`.
- Consumes: `RefundPayment::handle(Payment, ?int, string $reason, string $requestedBy)`, `GrantReward::handle(Customer, Reward, string, ?User)`, `Messenger::send(Message)`.

- [ ] **Step 1: Write failing tests:**
  - `it('lists open tickets first with an over-24-hours marker, scoped to the employee branches')`.
  - `it('sends a reply by WhatsApp with the utility template, then shows it to the guest')`: fake messaging driver; assert purpose `support` and 4 parameters.
  - `it('refunds an online payment in part, then refuses more than what is left')`: pay 40.00, refund 15.00, then 30.00 → `too_much`, then `null` → 25.00.
  - `it('refuses a refund for a cash order and offers a reward instead')`: `not_online`; `RewardTicket` puts the reward in the wallet with source `support`.
  - `it('needs Handle support to see tickets and Refund payments to refund')`: a cashier is forbidden; a branch manager can.
  - `it('resolves with a closing note and tells the guest the outcome')`.
  - `it('hides other restaurants tickets')`.
- [ ] **Step 2: Run them**, expect FAIL.
- [ ] **Step 3: Implement** the actions and the policy (`viewAny`/`view`/`update` need `HandleSupport` in the workspace; no `create`/`delete`).
- [ ] **Step 4: Implement the inbox and ticket page.**
  - Table: number, guest, branch, category badge, status badge, age.
  - Ticket page:
    - order card (items with the reported ones highlighted, total, payment, car);
    - conversation (guest on the start side, staff on the end side);
    - reply form;
    - header actions Refund (amount input defaulting to the refundable total, hidden unless online and permitted), Give a reward (catalog select), Resolve (note).
  - Point the `TicketOpened` alert URL at the ticket page.
- [ ] **Step 5: Add `dtp_ticket_reply`** to `docs/WHATSAPP_TEMPLATES.md`: category Utility, Arabic and English bodies with `{{1}}`–`{{4}}`, sample values, and a note that SMS is the fallback.
- [ ] **Step 6: Run** Support and Payments tests. Expected: PASS.
- [ ] **Step 7: Commit** `feat(support): staff inbox with reply, refund, reward and resolve`.

### Task 5: Birthday gifts

**Files:**
- Create: `database/migrations/2026_11_04_120000_create_birthday_gifts_table.php`.
- Create:
  - `app/Domain/Loyalty/Birthdays/BirthdaySettings.php`;
  - `app/Domain/Loyalty/Birthdays/BirthdayGift.php` (model);
  - `app/Domain/Loyalty/Birthdays/SendBirthdayGifts.php`;
  - `app/Console/Commands/SendBirthdayGiftsCommand.php` (`loyalty:birthdays`).
- Modify:
  - `routes/console.php` (daily 06:00 Asia/Qatar, `withoutOverlapping()->onOneServer()`);
  - `app/Filament/App/Pages/LoyaltySettings.php` (new tab "Birthday gift");
  - `app/Livewire/Customer/Rewards.php` (+ view: birthday card and "add your birthday" prompt);
  - `lang/{en,ar}/loyalty.php`.
- Test: `tests/Feature/Loyalty/BirthdayGiftsTest.php`.

**Interfaces:**
- Produces:
  - `BirthdaySettings` (`restaurants.settings.birthday`): `enabled` (default false), `rewardId` (?int), `daysBefore` (0–7, default 3), `sendMessage` (default true).
    - `for(Restaurant): self`, `save(Restaurant, array): self`, `reward(): ?Reward` (active, not archived).
  - `BirthdayGift`: `restaurant_id`, `customer_id`, `year`, unique (`customer_id`, `year`), `wallet_reward_id`, `sent_at`.
  - `SendBirthdayGifts::run(Restaurant $r, CarbonImmutable $now): int` returns the number of gifts.
    - Target date = local today + `daysBefore`; 29 Feb matches 28 Feb in non-leap years.
    - Skips when a gift exists with `sent_at` within 365 days.
    - Grants via `GrantReward` (source `birthday`).
    - Message: `Messenger` only if `acceptsOffers()`; push if subscribed.
- Consumes: `GrantReward`, `Messenger`, `WebPushSender`.

- [ ] **Step 1: Write failing tests:**
  - `it('gives the gift 3 days before the birthday in the restaurant time zone')`: travel to 2026-11-04 23:30 UTC (= 5 Nov Doha); a birthday on 8 Nov gets it.
  - `it('treats 29 February as 28 February in other years')`.
  - `it('gives one gift per 365 days even if the guest changes the birthday')`.
  - `it('does nothing on a second run the same day')`.
  - `it('messages only guests who accepted offers, and pushes to subscribers')`.
  - `it('skips quietly when the reward was archived or the gift is off')`.
  - `it('asks for the birthday on the Rewards page when the gift is on and shows a birthday card that week')`.
  - `it('saves the settings from the Loyalty page and audits it')`.
  - `it('keeps gifts to their restaurant')`.
- [ ] **Step 2: Run them**, expect FAIL.
- [ ] **Step 3: Implement** the settings, model, service, command, schedule, Loyalty tab and Rewards page pieces.
  - The Loyalty tab shows how many guests have a birthday in the next 7 days and how many gifts went out this month (test: `it('shows upcoming birthdays and gifts sent this month in the settings tab')`).
  - Message wording: `loyalty.birthday.message`, sent as a WhatsApp campaign-category template only when consent exists; reuse the `dtp_offer` parameters (restaurant, offer text, order URL, stop URL) so no new template is needed.
- [ ] **Step 4: Run** Loyalty tests, expect PASS.
- [ ] **Step 5: Commit** `feat(loyalty): automatic birthday gifts`.

### Task 6: Streaks and badges (off by default)

**Files:**
- Create: `database/migrations/2026_11_04_130000_create_customer_badges_table.php`.
- Create:
  - `app/Domain/Loyalty/Badges/Badge.php` (enum);
  - `app/Domain/Loyalty/Badges/BadgeSettings.php`;
  - `app/Domain/Loyalty/Badges/CustomerBadge.php` (model);
  - `app/Domain/Loyalty/Badges/BadgeProgress.php`;
  - `app/Domain/Loyalty/Badges/AwardBadges.php` (listener).
- Modify: `LoyaltySettings` (tab "Badges"), `Rewards` (+ view "Achievements"), `GuestProfile` and the guest view, `AppServiceProvider`, `lang/{en,ar}/loyalty.php`.
- Test: `tests/Feature/Loyalty/BadgesTest.php`.

**Interfaces:**
- Produces:
  - `Badge` cases with target counts:
    - `FirstOrder` (1);
    - `TenOrders` (10);
    - `TwentyFiveOrders` (25);
    - `Explorer` (5 distinct products);
    - `EarlyBird` (5 orders picked up before 09:00 local);
    - `Streak` (target from settings).
  - `BadgeSettings` (`restaurants.settings.badges`): `enabled` (default **false**); per badge `on` (default true once enabled) and `rewardId` (?int); `streakWeeks` (2–8, default 3).
  - `CustomerBadge`: unique (`customer_id`, `badge`); `earned_at`, `wallet_reward_id`, `seen_at`.
  - `BadgeProgress::for(Customer $c, BadgeSettings $s, CarbonImmutable $now): array<string, array{current:int, target:int, earned:?CarbonImmutable}>` and `BadgeProgress::streak(Customer $c, string $tz, CarbonImmutable $now): array{weeks:int, keepBy:CarbonImmutable}`.
    - Weeks run Sunday–Saturday local; only `picked_up` orders count.
    - The current week counts if it has an order; otherwise the streak runs to last week.
  - `AwardBadges` on `OrderStatusChanged` to `PickedUp`: inserts newly reached badges (insert-or-ignore) and grants each badge's reward once.
- Consumes: `GrantReward` (source `badge`; add to `SOURCES`).

- [ ] **Step 1: Write failing tests:**
  - `it('records nothing while badges are off (the default)')`.
  - `it('earns first order, 10 and 25 orders, explorer and early bird by their rules')`.
  - `it('counts a weekly streak across the new year and ignores cancelled orders')`: orders 21 Dec, 29 Dec, 3 Jan → 3 weeks.
  - `it('grants a badge reward once, even if the event fires twice')`.
  - `it('shows achievements with progress and celebrates a new badge once')`: render twice; the celebration markup only on the first render (`seen_at` set).
  - `it('saves badge settings and keeps badges to their restaurant')`.
- [ ] **Step 2: Run them**, expect FAIL.
- [ ] **Step 3: Implement** the enum, settings, model, progress (grouped queries: picked-up count, distinct products, early-bird count, weeks with orders over the last 60 weeks), listener, Loyalty tab (master switch with `->helperText` "Off by default", per-badge toggle and reward select, streak weeks), Achievements grid (earned with date, locked with progress bar, streak line), one-time celebration (server-rendered, then `seen_at = now()`), badges on the guest profile, and how many guests hold each badge in the Badges tab (one grouped count query).
- [ ] **Step 4: Run** Loyalty tests, expect PASS.
- [ ] **Step 5: Commit** `feat(loyalty): weekly streaks and badges, off by default`.

### Task 7: Quality gate, demo data, docs

**Files:** `database/seeders/DemoCrmSeeder.php` (or a new `DemoFeedbackSeeder` called after it), `docs/ROADMAP.md`, `docs/CHANGELOG.md`, `docs/ARCHITECTURE.md` (§4.9), `CLAUDE.md` (new gotchas only), `tests/Feature/Seeders/DemoCrmSeederTest.php`.

- [ ] **Step 1: Demo data for Masar:**
  - 40 ratings over 30 days (mostly 4–5 stars, two low ones with comments);
  - three tickets (open, answered, resolved with a reward);
  - birthday gift on with the "Free drink" reward and two guests with birthdays this week;
  - badges switched on, with a few earned.
  - Extend the seeder test with these counts and a second run changing nothing.
- [ ] **Step 2: Run** `vendor/bin/pest --parallel`, `vendor/bin/pint`, `vendor/bin/phpstan analyse --memory-limit=2G`, `npm run build`, `composer warm`. Expected: all pass, Larastan 0 errors.
- [ ] **Step 3: Browser checks** (`drive-thru` + `drive-thru-proxy`, http://localhost:8010):
  - Owner screens — Ratings, Support inbox, a ticket, the Loyalty Birthday and Badges tabs, the dashboard tile — in AR/EN, light/dark, at 375/768/1280.
  - Guest screens — order page rating card, report sheet and conversation, Rewards birthday card and Achievements — in AR/EN at 375/768/1280.
  - No sideways overflow (`scrollWidth - clientWidth === 0`), no console errors.
- [ ] **Step 4: Docs:**
  - tick the ROADMAP lines (Favorites, birthday automation, gamification, post-pickup rating, support tickets) and the 7C milestone;
  - CHANGELOG entry;
  - ARCHITECTURE §4.9;
  - CLAUDE.md gotchas learned.
- [ ] **Step 5: Commit** `chore: Phase 7C quality gate, demo data and docs`.
