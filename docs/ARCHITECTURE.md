# Architecture

How Drive-Thru Plus is put together, and the rules that keep it safe to grow.
Read this before adding a model, a permission, a panel page or a translation.

## 1. Shape of the system

```
Platform (super admin + platform staff)          → Filament panel  /admin
  └── Restaurant (tenant, a paying client)        → Filament panel  /app/{restaurant-slug}
        ├── Positions (permission sets)
        ├── Employees (user ↔ restaurant membership, position, status)
        └── … branches, menu, customers, orders (later phases)
Public website                                     → Blade + Livewire  /
Customer ordering app (Phase 4)                    → Livewire PWA  /{restaurant}
Ops screens (Phase 5)                              → Livewire + Reverb /ops/…
```

## 2. Code layout

```
app/
  Domain/                      business logic, framework-light
    Access/                    permissions, positions, platform roles
      Enums/                   PlatformPermission, RestaurantPermission (+ labels)
      Models/                  Position, PlatformRole
      DefaultPositions.php     built-in positions every restaurant starts with
    Security/                  login lockout, alerts, device sessions
    Staff/                     Employee (membership) + status
    Tenancy/                   Restaurant, TenantContext, BelongsToRestaurant, actions
  Filament/                    UI only: panels call Domain actions
    Admin/ App/                per-panel pages, resources, widgets
    Concerns/ConfiguresPanel   shared brand, theme, security, locale for both panels
  Http/Middleware/             SetLocale, NormalizeDigits, ApplyTenantContext
  Support/                     Locales, Digits, Greeting, Palette, UserAgent
```

**Rules**
- Business rules live in `app/Domain/*/Actions` (single-purpose classes). Filament pages, Livewire components and controllers stay thin and call actions.
- Enums carry their own labels through `lang/*` files. Never hard-code a user-visible string.
- Every class starts with `declare(strict_types=1);`.

## 3. Multi-tenancy (one database, row-level isolation)

- The tenant is `App\Domain\Tenancy\Models\Restaurant`.
- `TenantContext` (scoped singleton) holds the restaurant the current request or job acts for. The restaurant panel fills it from Filament's tenant (`ApplyTenantContext`). Jobs and commands use `$context->run($restaurant, fn () => …)`.
- Every model that has a `restaurant_id` column **must** use `BelongsToRestaurant`. The trait:
  - scopes every query to the current restaurant,
  - fills `restaurant_id` on create (and **throws** if there is no tenant and none was given),
  - **throws** if a record's `restaurant_id` is ever changed.
- To read across restaurants (platform code that already authorized the caller), call `Model::withoutTenancy()` explicitly.
- `tests/Feature/Tenancy/TenantIsolationTest.php` scans every model and **fails CI** if a tenant table's model forgets the trait.

## 4. Authorization

Two permission catalogues, both PHP enums:

| Enum | Who | Granted through |
|---|---|---|
| `PlatformPermission` | platform staff | `PlatformRole` (super admin implicitly has all) |
| `RestaurantPermission` | restaurant team | `Position` (owner implicitly has all) |

- Every case has a label, a plain-language description and a group in `lang/{ar,en}/permissions.php`, so the position editor in Phase 2 builds itself from the enum.
- Gates are registered for every case: `platform.clients.manage`, `restaurant.menu.manage`, … Restaurant gates resolve the restaurant from `TenantContext` when none is passed.
- **No `Gate::before`.** `User::hasPlatformPermission()` / `hasRestaurantPermission()` grant an **active** super admin everything; deactivated accounts get nothing anywhere. Every admin resource has a policy in `app/Policies` (using `Concerns\ChecksPlatformPermission`), so policies are the single, testable source of truth, and a policy can still deny a super admin (e.g. deleting a built-in role).
- Panel access: `/admin` = super admin or platform staff. `/app` = active membership of that restaurant. Invited or suspended members are refused.
- The super admin is created **only** by `php artisan platform:install`. `is_super_admin` is not mass assignable, and a test enforces that.

> Decision: we use our own `Position`/`PlatformRole` models instead of spatie/laravel-permission teams. Positions are per-restaurant, custom and described in plain language, and one employee belongs to many restaurants. A small enum-backed model keeps this explicit, fast and fully tested.

### 4.1 SaaS core (Phase 1)

- **Billing** (`app/Domain/Billing`): money is integer minor units (`Money`; KWD/BHD/OMR/JOD use 3 decimals). `Sequence` hands out gap-free invoice numbers `DTP-YYYY-00001` inside the issuing transaction. `RunBillingCycle` (scheduled `billing:run`, daily 05:30 Asia/Qatar) moves subscriptions forward: trial end → active + invoice; period end → renewal + invoice (catching up missed periods); overdue → past due; grace passed → suspended with reason `billing:unpaid`. `MarkInvoicePaid` reactivates **only** billing suspensions, never a manual one. `InvoicePdf` renders bilingual PDFs with mPDF (Arabic shaping via xbriyaz).
- **Invitations** (`UserInvitation`): only a SHA-256 hash of the token is stored; links last 7 days. Expired links show a friendly page (HTTP 410), and unknown links return 404. The same flow serves owners and platform staff.
- **Impersonation** (`Access\Impersonation`): the admin's id and reason are kept in the session. The session password hash is re-pinned so `AuthenticateSession` doesn't log anyone out. `GuardImpersonation` blocks profile, sessions and 2FA pages. Start and end are both audited, and a floating pill offers "Return to platform".
- **Growth**: `SubmitLead` (rate limiter `leads`: 3/min, 20/day; honeypot; 24h dedupe by email) → `NewLeadReceived` to staff with `leads.manage` + `LeadAcknowledgement` to the visitor. `ConvertLead` runs `OnboardClient`, the same action as the wizard.
- **Platform settings** (`PlatformSettings`): a cached key/value store with typed defaults. **Announcements** are scheduled, targeted (`everyone` / `owners`) and dismissible per user, and the workspace renders them through the `workspace-notices` Livewire component.

### 4.2 Restaurant workspace (Phase 2)

- **Branch scope.** An `Employee` covers every branch when `is_owner` or `all_branches`; otherwise it covers its `branch_employee` rows. `Employee::branchIds()` returns `null` for "all" and a list otherwise. `App\Filament\App\Workspace::scopeToBranches()` applies that to any query: the Branches, Screens, Team and Links pages all use it, so branch managers only ever see their branches.
- **Escalation guard** (`Domain\Staff\StaffGuard`): only owners and platform admins are unrestricted. Everyone else can only grant permissions they hold, only assign branches they cover (never "all"), never change the owner or themselves, and never manage someone more powerful. Enforced in the actions (`InviteEmployee`, `UpdateEmployee`, `ChangeEmployeeStatus`, `SetEmployeePin`, `SavePosition`) and mirrored in `EmployeePolicy` and `PositionPolicy` for the UI.
- **Workspace policies** (`Policies\Concerns\ChecksRestaurantPermission`): the current restaurant comes from `TenantContext`. In the platform console (no tenant) staff with `clients.view` may only list, never change. `BranchPolicy::operate` (hours, closures, pause, busy) is separate from `update` (branch details), so branch managers get exactly the live controls.
- **Branch schedule** (`Domain\Branches\BranchSchedule`): precedence is switched off → paused → closure → weekly hours; no hours at all = open around the clock. Shifts may be split and may run past midnight (`closes_at ≤ opens_at`). Everything runs in the restaurant's timezone, and times are stored as wall-clock `HH:MM` (Filament time pickers are pinned to UTC so they never shift them). `reopensAt()` answers "taking orders again at", including a pause that outlasts the shift.
- **Screens** (`Device`): `CreateDevice` / `IssuePairingCode` give a 6-digit code (SHA-256 stored, 15 min, unique among live codes). `PairDevice` burns it under a row lock and returns a 64-char token (hash stored) that lives in the encrypted, HTTP-only `dtp_screen` cookie for about 5 years. `AuthenticateDevice` resolves it, sets the tenant and throttles `last_seen_at` writes to every 30 s. Revoking or re-pairing invalidates the old token immediately. Online means a heartbeat within 90 s.
- **PINs** are an HMAC-SHA256 of `restaurant_id|pin` keyed with `APP_KEY`, so they're unique per restaurant and checkable without storing the PIN.
- **Plan limits** (`Billing\PlanLimits`): enforced inside the creating transaction with a row lock on the restaurant, so parallel requests can't exceed the limit. `PlanLimitReached` carries a translated, plural-aware message.
- **Public links:** `/{restaurant}` and `/{restaurant}/{branch}` are registered last, and their slug pattern excludes every reserved platform path (`CreateRestaurant::publicSlugPattern()`), so `/admin/login`, `/pair`, `/screen` etc. can never resolve as a restaurant. Unknown slugs get a branded "restaurant not found" page and paused clients a 503 "temporarily unavailable". QR codes (chillerlan, ECC level M) and the A5 poster (mPDF) are generated on our servers.
- **Setup checklist** (`Tenancy\SetupChecklist`) is computed from data, never stored, except two markers in `restaurants.settings.onboarding` (QR downloaded, checklist dismissed).

### 4.3 Menu engine (Phase 3)

- **One question, one place:** `Domain\Menu\BranchMenu::offer(product, branch, setting, at)` returns a `ProductOffer`: base and final price (branch override, then the item's discount), stock left today, and, when it can't be ordered, an `Unavailability` reason. The reasons, in precedence order, are switched off → section hidden → not served here → out of stock today → sold out → outside schedule. Guests still see sold-out and "not now" items (greyed); the others are hidden.
- **Local time everywhere:** "out of stock today" stores the branch's next local midnight in UTC. Daily stock refills lazily: a `stock_date` different from the local date means a fresh day, so no cron is needed. Schedules use `MenuSchedule::isActiveAt(local)`, and a window ending at or before it starts runs past midnight and belongs to the day it started.
- **Caching:** `MenuCache` keeps a per-restaurant version number; `BustsMenuCache` bumps it on any save or delete of a catalog model (and the editor bumps it after pivot syncs). The catalog (sections → items → option groups → meal slots) is cached as models under that version. Branch settings (`branch_products`) are never cached and are read in one query per page. Laravel 13 only unserializes allow-listed classes; `config/cache.php` lists exactly the menu models, enums and collections.
- **Money:** prices are minor units in the database; `Filament\Support\MoneyInput` shows major units and converts on save. Bulk price changes are computed by `BulkPriceUpdate::preview()` and applied exactly as previewed in one transaction (one cache bump, one audit entry).
- **Excel** (`MenuSpreadsheet`, OpenSpout): rows match by id, then SKU; unknown sections are created. `parse()` validates everything without writing; `apply()` runs in one transaction, so imports are all-or-nothing.
- **Images:** `ProcessProductImage` runs after the response (GD) and stores 400/800 px WebP and AVIF variants; `Product::imageUrl($width, $format)` picks the closest one and falls back to the original.
- **Permissions:** `menu.manage` covers the catalog screens (`MenuPolicy`, shared by every catalog model); `menu.availability` covers the Branch menu screen, limited to branches the person covers, and only `menu.manage` can set branch prices.

### 4.4 Guest ordering (Phase 4)

- **Guests are not users.** A customer belongs to one restaurant (`unique(restaurant_id, phone)`). `Domain\Customers\CustomerAuth` signs them in per restaurant with a random token kept in the session (only its SHA-256 is stored in `customer_sessions`, so devices can be listed and signed out). Signing in regenerates the session id. Signed in at restaurant A means nothing at restaurant B.
- **Codes:** `SendOtp` / `VerifyOtp`: HMAC-hashed 6-digit codes, 5-minute expiry, 30 s between sends, 5 sends per phone and 10 per IP per 10 minutes (rate limiter), 5 attempts. Messages go through `Messenger` (channel order from platform settings: WhatsApp, then SMS; the local `LogChannel` never logs the text). The code is shown on screen only in `local` and `testing`.
- **The bag holds ids, never prices.** `Ordering\Cart` keeps lines (product, quantity, option ids, meal choices, notes) in the session per restaurant. `CartPricer` prices them for the branch at the given time: availability, option rules, nested groups, meal parts, service fee then VAT. The bag, checkout and `PlaceOrder` all use it, so the UI always shows the server's numbers.
- **Placing an order** (`PlaceOrder`) runs in one transaction with the branch row locked: capacity per 15-minute slot (`Capacity`), re-pricing, stock (`BranchStock::consume`), snapshot of items, options and the car, then the first history entry through `TransitionOrder::start`. The checkout keeps one idempotency key per visit, and `unique(restaurant_id, idempotency_key)` turns a racing second tap into the first order.
- **Timing:** now, a delay (5–240 min), "when I'm on my way" (`prepare_at` stays empty until `MarkOnTheWay`) or a scheduled time (15 min to 7 days ahead). A capacity rejection carries the next free slot; the checkout moves it to the first time a scheduled order accepts.
- **Payments:** `Payments\Contracts\PaymentGateway`, bound to `FakeGateway`. Online methods start as `pending_payment`; `CompletePayment` (idempotent) marks the payment and moves the order to `received`. Return URLs must stay on this site.
- **Order page access:** the short code works as a share link and shows progress only. The pickup code, QR (`{short_code}-{pickup_code}`, scanned at the window in Phase 5), receipt and actions need the guest who placed it.
- **Livewire pages** (`App\Livewire\Customer\*`) share `Concerns\GuestPage` (locked restaurant, guest, bag, layout) and `Concerns\SignsInWithPhone`. Everything the browser sends is re-checked on the server.

### 4.5 Live operations (Phase 5)

- **State machine:** `OrderStatus::next()` is the normal map; `canOverrideTo()` covers override moves. `TransitionOrder` is the only writer: it reads the row, checks the move, then saves with `UPDATE … WHERE id = ? AND status = <read status>` (compare-and-set). Zero rows updated means someone else moved it first (`InvalidTransition::stale`). Status times (`received_at`, `preparing_at`, `ready_at`, `picked_up_at`, `cancelled_at`) are stamped there. The database refuses unknown statuses (SQLite triggers, CHECK constraint elsewhere).
- **Held orders:** `PlaceOrder::startingStatus()` puts later starts and "when I'm on my way" orders in `scheduled`. `QueueScheduledRelease` queues `ReleaseScheduledOrder` for `prepare_at`; `orders:release` runs every minute as the safety net. `MarkOnTheWay`, `MarkArrived` and an approach ping release "on my way" orders.
- **Operations desk:** screens and staff never call the actions directly. `Operations\Operator` describes who acts (staff: position permissions + covered branches; paired device: permissions by kind, its own branch only, and its station), and `Operations\OrderDesk` checks it before every action (advance, complete, cancel, re-enable, reset, set status, collect payment, arrival, station done, pickup lookup). Refusals are `OperationRefused` with a message for the screen.
- **Realtime:** `OrderStatusChanged` and `OrderUpdated` (facts such as arrival, location, station done, payment) are turned into `OrderBroadcast` by `BroadcastOrderChange`, after commit, on `branch.{id}.orders`, `restaurant.{id}.dashboard`, `order.{code}` and `platform.dashboard`. Payloads carry ids, status and times only. A broadcast failure is reported and never blocks the order. Staff channels are authorised by `Realtime\ChannelAccess` through `routes/channels.php`; paired screens and guests by `RealtimeAuthController` (`/realtime/auth`). `resources/js/live.js` subscribes any element with `data-live-channel` and emits `live-update` / `live-status` DOM events; pages re-render from the server on each event and resync after a reconnect.
- **Screens:** Livewire components in `App\Livewire\Ops` share `Concerns\OpsScreen` (device re-checked from its cookie on every request, or the signed-in user). The same components run at `/screen` (paired device, by kind) and `/ops/{restaurant}/{branch}/{screen}` (staff). Timers tick from one Alpine clock store; the chime is generated with WebAudio (no files).
- **Load and pulse:** `KitchenLoad` (scoped per request) gives the estimate and busy level from real prep times, the queue and busy mode. `BranchPulse` gives the screens' numbers (counts, delays, guests waiting, sales vs yesterday and last week at the same time of day, hourly series, alerts, health).
- **Guest location:** opt-in only. `TrackGuestLocation` keeps the last position and distance on the order for that trip, ignores pings faster than the branch interval, raises the approach alert once and auto-arrives inside the arrival radius when the branch allows it. A refusal is recorded (`location_denied_at`) so staff see "location off".

### 4.6 Payments, notifications & integrations (Phase 6)

- **Providers:** every outside call goes through `Integrations\ProviderClient` (timeout, one retry on network errors, a `provider_calls` row with status and duration, never the body or secrets). A restaurant's own accounts live in `integrations` (one row per kind: payments, printing, POS) with `encrypted:array` credentials that are write-only in the UI; `SaveIntegration` audits key names only and resets `verified_at` when keys change. Platform accounts (WhatsApp, SMSala, VAPID) come from `.env`.
- **Payments:** `GatewayResolver` picks the restaurant's SADAD integration (QAR only), else the Fake gateway outside production, else none (pay at the window). `StartPayment` → redirect to SADAD's signed checkout → `SadadNotification` verifies the checksum with the key for the payment's mode and the amount, then `CompletePayment`. The webhook always answers 200 and never trusts a callback without a valid checksum. `RefundPayment` reserves the amount under a row lock before calling the provider, then records success or failure (failure gives the amount back and raises a critical alert). Counter payments (`Payment::COUNTER`) never reach a provider. `payments:expire` settles unpaid orders by asking the provider before cancelling. A payment that lands after the order was cancelled is refunded automatically.
- **Messaging:** `Messenger` tries WhatsApp Cloud (templates, see `docs/WHATSAPP_TEMPLATES.md`) then SMSala, logging each attempt in `message_deliveries` (masked and hashed number, provider id, status that only moves forward). Guest messages (received, ready, receipt) go once per order and respect the guest's preferences.
- **Alerts:** `Notifications\Alerts\OpsAlert` (kinds in `AlertKind`) goes to the people who run the branch (`StaffAlerts`: active staff with "Run a branch" covering it, and the owner). Channels: the Filament bell (database) and a broadcast on `App.Models.User.{id}` named `database-notifications.sent`, so the bell refreshes itself and `live.js` emits `live-alert` (chime and toast); web push per subscribed browser; WhatsApp for those who opted in; email for critical alerts. Quiet hours (restaurant time, may cross midnight) hold back push and WhatsApp for routine alerts. `ops:alerts` (every minute) finds late orders, guests waiting and overloaded kitchens, claiming `delay_alerted_at` / `wait_alerted_at` with compare-and-set so an order alerts once.
- **Web push:** VAPID keys from `webpush:keys`; `PushTransport` (`VapidTransport` in production) behind `WebPushSender`, which drops expired subscriptions. `resources/js/push.js` exposes `window.dtpPush` (state, enable, disable) for the staff Alerts page and the guest order page; `public/sw.js` shows the notification and focuses or opens its link.
- **Screens: PIN step-up:** on a paired screen, refunds and status overrides ask for a staff PIN (`ScreenPin`: HMAC lookup, 5 wrong PINs lock the screen for 5 minutes, audit entry). The action then runs as that person (`Operator::staffOnDevice`: their permissions, the screen's branch only), so the history names them.
- **Outgoing webhooks:** `DispatchWebhook` freezes the payload (`OrderPayload`, also used by the API) into one `webhook_deliveries` row per subscribed endpoint and queues `DeliverWebhook`. `SendWebhookDelivery` claims the attempt, re-checks the URL (`SafeUrl`: HTTPS, public addresses only, no redirects), signs with `X-DTP-Signature: t=…,v1=HMAC-SHA256("t.body")`, and schedules retries (1 min … 12 h) picked up by `webhooks:retry`. An endpoint that only fails for 72 hours is switched off and the owner gets a critical alert. The event id is stable across retries and replays.
- **Public API v1:** `routes/api.php`, Sanctum tokens owned by `ApiClient` (never a person), scopes `orders:read|write`, `menu:read|write` checked on both the client and the token, the client's restaurant set as tenant by `AuthenticateApiClient`, per-key rate limit. Status changes go through `OrderDesk` with `Operator::integration` (moves and cancels, never overrides or refunds; history actor `integration`). `OpenApi` is the single description behind `/api/v1/openapi.json` and `/developers/api`.
- **POS:** `PosDriver` with `GenericPosDriver` (HTTPS POST of `OrderPayload`, bearer key + signature, keeps the POS reference). `SendAcceptedOrdersToPos` queues `PushOrderToPos` when an order is received; outcome on the order (`pos_reference`, `pos_synced_at`, `pos_error`), shown on the live board.
- **Printing:** `PrintOrder` uses the branch's PrintNode printers when set up, else the browser print dialog with an 80 mm ticket (`TicketRenderer`).
- **Health:** `ProviderHealth` summarises `provider_calls` per provider for the platform Health page; `providers:watch` (every 5 minutes) alerts super admins when one fails most of its calls.

### 4.7 Loyalty and promotions (Phase 7A)

- **Points ledger:** `Loyalty\Points` is the only writer. Credits are lots (`loyalty_ledger.remaining`, `expires_at` from the program). Debits spend the soonest-expiring lots first under a lock on the customer row, so `customers.points_balance` always equals the sum of the ledger and of the unspent lots (tested with random operations). Types: earn, bonus, redeem, reverse, returned, expire, adjust.
- **Program:** `LoyaltyProgram` has no defaults; `isActive()` needs every value set. Tiers (`Tiers`) come from earn + bonus − reverse points within `tier_window_months`. Earn boosts (`EarnRules`) take the strongest live match on product, category or branch.
- **Pricing:** `CartPricer::price($branch, $lines, $at, ?CheckoutBenefits)` applies, in order:
  1. `PromotionEngine::apply()` (pure: eligibility in restaurant time, amounts, stacking, cap at the subtotal; the entered coupon always applies);
  2. a wallet reward (`RewardPricer`);
  3. points (`PointsRedemption::clamp`, on what is left).

  Fee and tax are on the net. Each discount is an `Adjustment` (kind, source, bilingual label, amount, bonus points). Plan features gate promotions and loyalty.
- **Placing:** `PlaceOrder` re-prices inside its transaction and calls `RecordBenefits`. It rejects with `benefit_changed` when what the guest chose is no longer what the cart gives. It locks promotion, coupon, wallet and customer rows, re-checks the limits, writes `promotion_redemptions`, marks the reward used, debits points and stores `order_adjustments`.
- **Lifecycle listeners:**
  - `AwardPointsOnPickup` (once per order; the line share of the paid amount × boost × tier, plus bonus points).
  - `ReturnBenefitsOnCancel` (points as a fresh lot, wallet reward, redemptions released and use counts decremented).
  - `ReversePointsOnRefund` (proportional, never more than earned or than the balance).
  - `loyalty:maintain` daily at 03:30 Doha (expire lots and rewards, recompute tiers; idempotent).
- **Owner UI:** "Loyalty & offers" group: `LoyaltySettings` page (repeaters synced on save), `RewardResource`, `PromotionResource` with `CouponsRelationManager` (`GenerateCoupons`). Policies: `RewardPolicy` (ManageLoyalty) and `PromotionPolicy` (ManagePromotions) over `GrowthPolicy`.
- **Guest UI:** checkout savings card (`Checkout::applyCoupon/chooseReward/togglePoints`), `/{restaurant}/rewards` (`Customer\Rewards`; `rewards` is a reserved branch code).

## 5. Security

- **Sign-in lockout** (`LoginThrottle`): failures counted per email (limit 5) and per IP (limit 20) in a 15-minute window → 30-minute lock. Keys are HMAC hashes of the email/IP, never raw values. The custom `Filament\Auth\Login` refuses a locked principal before checking the password. Recording happens through Laravel's `Failed`/`Login` auth events, so every sign-in path is covered.
- A lock raises a `SecurityAlert` (masked email) and a bell notification for every super admin, each in their own language.
- 2FA: authenticator app with recovery codes (Filament MFA), secrets encrypted at rest. `SECURITY_REQUIRE_PLATFORM_MFA=true` forces it for platform staff (on in production).
- Devices & sessions page: list every signed-in browser, sign one out, or sign out all others (password confirmed).
- Production: HTTPS forced, stronger password rules (mixed case, numbers, breach check).
- Strict Eloquent outside production: lazy loading, missing attributes and silently discarded attributes throw.
- No third-party calls from the UI: fonts are self-hosted and avatars are generated locally (`InitialsAvatarProvider`).

## 6. Localization

- Arabic (default, RTL) and English (LTR). `config/locales.php` lists them.
- `SetLocale` resolves: `?lang=` → session → user preference → cookie → `Accept-Language` → default. It runs globally (so 404 pages are translated) and again in the web stack once the session exists.
- `/locale/{code}` switches, remembers on the user + cookie and returns to the same page.
- **Western digits everywhere.** `NormalizeDigits` converts Arabic-Indic and Persian digits in form posts. Filament text inputs (except passwords) normalize on dehydration. Display with `Digits::number()`.
- Arabic plurals use explicit ranges (`{1}`, `{2}`, `[3,10]`, `[11,*]`).
- `LocalizationTest` fails if any key exists in one language and not the other.

## 7. Design system

- Tokens: `resources/css/tokens.css` (color, type, radius, elevation, motion), shared by `resources/css/app.css` (website/customer) and `resources/css/filament/theme.css` (both panels).
- Moods: **Midnight & Ice** (dashboards: floating midnight rail, brass active state) and **Deep Teal & Brass on Ivory** (website/customer: editorial Fraunces headlines, double-bezel cards, pill buttons with nested icons).
- Fonts (self-hosted via fontsource): Geist (Latin UI), IBM Plex Sans Arabic (Arabic UI), Fraunces (Latin display), Alexandria (Arabic display).
- Motion: spring/expo easings, transform + opacity only, `prefers-reduced-motion` respected.
- Filament palettes are mirrored in `App\Support\Palette` (teal = primary, ink = gray, brass = warning).

## 8. Quality gate (CI)

`.github/workflows/ci.yml` runs on every push and PR: Pint → Larastan level 8 → Pest. Local equivalents:

```
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=2G
vendor/bin/pest
```

## 9. Environments

| | Local | Production |
|---|---|---|
| Database | SQLite (`database/database.sqlite`) | MySQL 8 |
| Cache / queue / session | database | Redis (+ Horizon) |
| Realtime | Reverb (installed, used from Phase 5) | Reverb behind a supervisor |
| Mail | log | real mailer |
| 2FA for platform staff | optional | required |
