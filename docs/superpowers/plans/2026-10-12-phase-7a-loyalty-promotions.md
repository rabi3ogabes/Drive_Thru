# Phase 7A: Loyalty, Tiers, Rewards & Promotions — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Guests earn points when they pick up, climb tiers, claim rewards into a wallet, and get promotions, coupons, rewards and points applied at checkout, all priced on the server.

**Architecture:** A new `Domain/Loyalty` (program, ledger, tiers, rewards, wallet) and `Domain/Promotions` (promotions, coupons, the engine). `CartPricer::price()` takes an optional `CheckoutBenefits` and returns adjustment lines; `PlaceOrder` re-prices inside its transaction and records the redemptions under row locks. Listeners earn on pick-up and reverse on cancel and refund. A daily `loyalty:maintain` expires points and rewards and recomputes tiers.

**Tech Stack:** Laravel 13, Filament 5, Livewire 4, Pest 5, SQLite locally / MySQL in production.

**Spec:** `docs/MASTER_PROMPT.md` §3.5, `docs/ROADMAP.md` Phase 7. User decisions (2026-10-12): three milestones (7A this plan, 7B and 7C outlined at the end); **no default point values**: loyalty is off until the owner sets them; gamification built in 7C, off by default; campaigns use explicit opt-in.

## Global Constraints

- Money in minor units (`int`), everything priced on the server; the browser sends ids and codes only.
- Every new tenant model uses `BelongsToRestaurant` and declares `$attributes` defaults for every column.
- Every guest- and staff-visible string in `lang/ar` + `lang/en`, Western digits, logical CSS properties, design tokens only.
- Plan gates: loyalty, tiers and rewards need `PlanFeature::Loyalty`; promotions and coupons need `PlanFeature::Promotions` (`$restaurant->hasFeature()`). Staff need `RestaurantPermission::ManageLoyalty` / `ManagePromotions`.
- Translation keys never contain dots (enum values map `.` → `_`).
- Audit entries (`activity()`) for: program changes, manual point adjustments, reward and promotion create/edit/archive, coupon generation.
- This folder is not a git repository: skip commit steps until the user sets one up.

## Review Focus

1. **Two checkouts racing to spend the same points or the last coupon use:** exactly one succeeds, and the other gets a calm "your points changed" or "this code has been used up" error. Covered in Task 6.
2. **A guest whose discounts exceed the subtotal** (a big fixed coupon on a small order): the total is never negative, and service fee and tax are computed on the discounted net. Covered in Task 3's property test.
3. **Cancel or refund after redeeming:** redeemed points, the wallet reward and the coupon use all come back. Earned points are never awarded for a cancelled order, and a later refund takes them away again. Covered in Task 7.
4. **Happy hour and birthday at the edges of the day:** evaluated in the restaurant's time zone, not UTC (for example 23:30 Doha = 20:30 UTC). Covered in Task 3.
5. **The owner changes point values or archives a promotion while a guest is on the checkout page:** placing the order re-prices with the current rules and shows "your total changed" instead of charging stale numbers. Covered in Task 6.

---

### Task 1: Loyalty program, ledger and balance

**Files:**
- Create migration `database/migrations/2026_10_18_100000_create_loyalty_tables.php`:
  - `loyalty_programs`: restaurant_id unique, is_enabled false, earn_points_per_unit ?int, redeem_points ?int, redeem_value_minor ?int, max_redeem_percent ?int (1–100), min_redeem_points ?int, expiry_months ?int, tier_window_months int 12.
  - `loyalty_ledger`: restaurant_id, customer_id, order_id?, type, points (signed int), remaining int (earn and bonus lots only), expires_at?, note?, created_by?, created_at. Unique (order_id, type) where type ∈ earn/reverse.
  - `customers`: add `points_balance` int 0, `tier_id` ?int.
  - `restaurants`: drop `loyalty_points_per_unit` and copy it into `earn_points_per_unit`, but only when a program already exists. No program rows are created: "no defaults".
- Create `app/Domain/Loyalty/Models/{LoyaltyProgram,LedgerEntry}.php`, `app/Domain/Loyalty/Enums/LedgerType.php` (Earn, Bonus, Redeem, Reverse, Returned, Expire, Adjust).
- Create `app/Domain/Loyalty/Points.php`.
- Modify `app/Domain/Customers/Models/Customer.php` (new columns, `tier()`, `ledger()`), `Restaurant.php` (remove the column, add `loyaltyProgram()`), `RestaurantSettings.php` and `tests/Feature/Workspace/OwnerJourneyTest.php` (remove the loyalty-rate field and its assertion).
- Test: `tests/Feature/Loyalty/PointsTest.php`.

**Interfaces (produces):**
- `LoyaltyProgram::isActive(): bool`: enabled and all five values set and positive.
- `LoyaltyProgram::pointsFor(int $netMinor, string $currency, int $multiplierBp = 10000): int`: floor(net major units × rate × multiplier).
- `LoyaltyProgram::valueOf(int $points): int`: minor units. `pointsForValue(int $minor): int` (ceil).
- `Points::credit(Customer $c, LedgerType $type, int $points, ?Order $order, ?string $note, ?User $by): LedgerEntry`: creates a lot with `remaining`, sets `expires_at` from `expiry_months`, increments the balance.
- `Points::debit(Customer $c, LedgerType $type, int $points, ?Order $order, ?string $note, ?User $by): LedgerEntry`: consumes lots first-in-first-out (earliest `expires_at` first), throws `NotEnoughPoints`, decrements the balance.
- Both run inside `DB::transaction` with `Customer::lockForUpdate()`.

- [ ] Write the failing tests:
  - `it credits a lot and debits FIFO by earliest expiry`: credit 100 expiring in 1 month and 100 in 12 months, debit 150 → the first lot `remaining` = 0, the second 50, balance 50.
  - `it refuses to debit more than the balance` → `NotEnoughPoints`, balance unchanged.
  - `it keeps balance equal to the sum of the ledger` (property): 300 random credit/debit operations with seeded `mt_srand(7)`; after each, `points_balance === sum(points)` and `sum(remaining) === points_balance`.
  - `it is inactive until every value is set` (no defaults).
- [ ] Run `vendor/bin/pest tests/Feature/Loyalty/PointsTest.php` → FAIL.
- [ ] Implement the migration, models and `Points`. Run `php artisan migrate`.
- [ ] Run the tests → PASS; run `tests/Feature/Workspace` → PASS after the settings-field removal.

### Task 2: Tiers and earn rules

**Files:**
- Migration (same file as Task 1):
  - `loyalty_tiers`: restaurant_id, name json, threshold_points int, earn_multiplier_bp int 10000, benefits json?, sort.
  - `loyalty_earn_rules`: restaurant_id, scope (product|category|branch), scope_id, multiplier_bp, starts_at?, ends_at?.
- Create `app/Domain/Loyalty/Models/{Tier,EarnRule}.php`, `app/Domain/Loyalty/Tiers.php`.
- Test: `tests/Feature/Loyalty/TiersTest.php`.

**Interfaces (produces):**
- `Tiers::qualifyingPoints(Customer $c): int`: Earn + Bonus − Reverse within `tier_window_months`.
- `Tiers::recompute(Customer $c): ?Tier`: the highest tier whose threshold ≤ qualifying points; saves `tier_id`.
- `Tiers::progress(Customer $c): array{tier: ?Tier, next: ?Tier, points: int, needed: int, percent: int}`.
- `Tiers::standardPreset(LoyaltyProgram $p): void`: creates Bronze 0, Silver, Gold, Platinum with thresholds left at 0 and names bilingual, so the owner fills the numbers in.
- `EarnRule::multiplierFor(OrderItem $item, Order $order): int` (bp): the highest matching active rule, product over category over branch on ties.

- [ ] Write the failing tests:
  - `it promotes and demotes by points in the window`: earn 500 now (Silver at 400), travel 13 months → Bronze.
  - `it uses the highest matching earn rule per line`.
  - `it reports progress to the next tier` (percent 0–100, `needed` ≥ 0).
- [ ] Run → FAIL. Implement. Run → PASS.

### Task 3: Promotions engine (pure)

**Files:**
- Migration `database/migrations/2026_10_18_110000_create_promotions_tables.php`:
  - `promotions`: restaurant_id, name json, type, value_bp ?int, value_minor ?int, buy_qty ?int, get_qty ?int, free_product_id ?int, combo_product_ids json?, combo_price_minor ?int, scope (all|branches|categories|products), scope_ids json?, branch_ids json?, first_order bool, birthday bool, days json?, time_from ?string(5), time_to ?string(5), starts_at?, ends_at?, min_subtotal_minor int 0, requires_coupon bool, stackable bool, priority int 0, usage_limit ?int, per_customer_limit ?int, used_count int 0, archived_at?.
  - `coupons`: restaurant_id, promotion_id, code (unique per restaurant, stored upper-case), usage_limit ?int, per_customer_limit ?int, used_count int 0, expires_at?.
  - `promotion_redemptions`: restaurant_id, promotion_id, coupon_id?, customer_id, order_id, discount_minor, released_at?.
- Create `app/Domain/Promotions/Enums/PromotionType.php` (Percent, Fixed, Bogo, FreeItem, ComboPrice, BonusPoints), `app/Domain/Promotions/Models/{Promotion,Coupon,Redemption}.php`.
- Create `app/Domain/Promotions/PromotionEngine.php`, `app/Domain/Ordering/Data/Adjustment.php`, `app/Domain/Ordering/Data/CheckoutBenefits.php`.
- Test: `tests/Feature/Promotions/PromotionEngineTest.php`.

**Interfaces (produces):**
- `final readonly class CheckoutBenefits { __construct(public ?Customer $customer, public ?string $couponCode = null, public ?int $walletRewardId = null, public int $pointsToRedeem = 0) }`.
- `final readonly class Adjustment { __construct(public string $kind /* promotion|coupon|reward|points */, public int $sourceId, public array $label /* ['en'=>…,'ar'=>…] */, public int $amountMinor /* positive = discount */, public int $bonusPoints = 0) }`.
- `PromotionEngine::apply(Branch $b, list<PricedLine> $lines, ?Customer $c, ?Coupon $coupon, CarbonInterface $at): array{adjustments: list<Adjustment>, couponError: ?string}`.

**Rules the engine decides (write them in the class docblock):**
- **Eligibility:** active, not archived, inside starts/ends, day and time in the restaurant's time zone (a range may cross midnight), branch in `branch_ids` (null = all), `min_subtotal_minor`, `first_order` (customer `orders_count` = 0 and no open orders), `birthday` (the customer's birthday ± 3 days, ignoring the year), usage limits (`used_count < usage_limit`, per-customer redemptions not released < `per_customer_limit`), `requires_coupon` only through its coupon.
- **Amounts:**
  - Percent: `round(scoped subtotal × bp / 10000)`.
  - Fixed: `min(value, scoped subtotal)`.
  - Bogo: for every `buy+get` scoped units, the `get` cheapest units are free.
  - FreeItem: the free product's unit price when it is in the cart, else not applicable.
  - ComboPrice: when every listed product is present, discount = their single-unit sum − combo price (if > 0).
  - BonusPoints: amount 0, `bonusPoints` = value_minor as points.
- **Stacking:** non-stackable candidates exclude everything else. Result = the larger of (best single exclusive candidate) and (sum of all stackable candidates); ties go to the higher `priority`, then the lower id. An entered coupon's promotion is always in the result: if it is exclusive the result is only the coupon, and if it is stackable it joins the stackables.
- **Total cap:** the summed discount never exceeds the subtotal. When capped, reduce the last adjustment.

- [ ] Write the failing tests (one per type, plus):
  - `it applies happy hour in restaurant time`: 18:00–20:00 Doha, at 16:30 UTC applies and at 17:30 UTC does not.
  - `a range crossing midnight` 22:00–02:00.
  - `birthday ±3 days across New Year`: birthday 30 Dec, order 2 Jan → applies.
  - `first order only`.
  - `exclusive beats smaller stack and loses to bigger stack`.
  - `an exclusive coupon removes automatic promotions`.
  - `per-customer limit counts unreleased redemptions only`.
  - **property** `discounts never exceed the subtotal and are deterministic`: 500 random carts and promotion sets with `mt_srand(11)` → `0 ≤ sum ≤ subtotal`, and the same input twice gives identical adjustments.
- [ ] Run → FAIL. Implement. Run → PASS.

### Task 4: Rewards catalog and wallet

**Files:**
- Migration (Task 1 file):
  - `rewards`: restaurant_id, name json, description json?, type (free_item|percent_off|fixed_off|bonus_points), points_cost ?int, product_id ?int, value ?int, min_order_minor int 0, valid_days int 30, stock ?int, is_active bool, archived_at?.
  - `customer_rewards`: restaurant_id, customer_id, reward_id, source (claimed|tier|birthday|referral|campaign|manual), status (available|used|expired|cancelled), expires_at, used_order_id?, created_at.
- Create `app/Domain/Loyalty/Models/{Reward,WalletReward}.php`, `app/Domain/Loyalty/Actions/{ClaimReward,GrantReward}.php`.
- Modify `PromotionEngine`, or add `app/Domain/Loyalty/RewardPricer.php`.
- Test: `tests/Feature/Loyalty/RewardsTest.php`.

**Interfaces (produces):**
- `ClaimReward::handle(Customer $c, Reward $r): WalletReward`: debits `points_cost` (`LedgerType::Redeem`), decrements stock, `expires_at = now + valid_days`. Throws `RewardUnavailable` (inactive, out of stock, not enough points, `bonus_points` type not claimable).
- `GrantReward::handle(Customer $c, Reward $r, string $source, ?User $by): WalletReward`: `bonus_points` credits points immediately and records the reward as used.
- `RewardPricer::adjustment(WalletReward $w, list<PricedLine> $lines, int $remainingMinor): ?Adjustment`:
  - free_item: that product's unit price if it's in the cart;
  - percent_off: on the remaining amount;
  - fixed_off: `min(value, remaining)`;
  - `min_order_minor` respected.

- [ ] Write the failing tests:
  - claim deducts points and creates an available wallet item;
  - claiming with too few points fails and changes nothing;
  - out-of-stock fails;
  - a free item not in the cart → null adjustment;
  - grant bonus_points credits the balance.
- [ ] Run → FAIL. Implement. Run → PASS.

### Task 5: Pricing with benefits

**Files:**
- Modify `app/Domain/Ordering/CartPricer.php` (signature below), `app/Domain/Ordering/Data/PricedCart.php` (add `public array $adjustments = []`, `public int $pointsRedeemed = 0`, `public int $pointsToEarn = 0`, `public ?string $benefitError = null`).
- Create `app/Domain/Loyalty/PointsRedemption.php`.
- Test: `tests/Feature/Ordering/CheckoutBenefitsPricingTest.php`.

**Interfaces:**
- Consumes: `PromotionEngine::apply`, `RewardPricer::adjustment`, `LoyaltyProgram`.
- Produces: `CartPricer::price(Branch $branch, array $lines, ?CarbonInterface $at = null, ?CheckoutBenefits $benefits = null): PricedCart`. Existing callers keep working without benefits.
- `PointsRedemption::clamp(LoyaltyProgram $p, Customer $c, int $requested, int $remainingMinor): array{points: int, discountMinor: int}`:
  - 0 when the program is inactive, or the request is below `min_redeem_points`;
  - otherwise capped by the balance and by `max_redeem_percent` of the remaining amount;
  - points are rounded down to whole redeem steps (`redeem_points`).
- **Order of application:** promotions and coupon → wallet reward → points. Then `net = subtotal − discount`, fee and tax on `net` exactly as today. `pointsToEarn` = program `pointsFor(net)` × the customer's tier multiplier (an estimate shown at checkout; the real award happens at pick-up).
- Plan gates: no promotions without `PlanFeature::Promotions`, no rewards or points without `PlanFeature::Loyalty`.

- [ ] Write the failing tests:
  - coupon + reward + points reduce the total in that order, and tax is on net;
  - points capped at 50% when the program says 50;
  - requests below the minimum → 0;
  - an invalid coupon → `benefitError` set and the cart otherwise priced;
  - a plan without Promotions → coupon ignored with an error;
  - **property** `the total is never negative and discount ≤ subtotal` (300 seeded random cases).
- [ ] Run → FAIL. Implement. Run → PASS. Run `tests/Feature/Ordering` → all PASS (no regressions).

### Task 6: Placing an order with benefits

**Files:**
- Modify `app/Domain/Ordering/Data/OrderRequest.php` (add `public ?CheckoutBenefits $benefits = null`), `app/Domain/Ordering/Actions/PlaceOrder.php`.
- Migration `2026_10_18_120000_create_order_adjustments_table.php`: `order_adjustments` (restaurant_id, order_id, kind, source_id, label json, amount_minor, bonus_points); `orders` add `points_redeemed` int 0, `points_earned` int 0.
- Create `app/Domain/Ordering/Models/OrderAdjustment.php`.
- Modify `app/Domain/Integrations/Api/OrderPayload.php` (add `adjustments`).
- Test: `tests/Feature/Ordering/PlaceOrderBenefitsTest.php`.

**Interfaces:** Inside the existing transaction, after pricing:
- lock the customer (`lockForUpdate`), then the coupon row and the promotion rows that have limits;
- re-check the limits;
- create the `promotion_redemptions` rows and increment `used_count`;
- mark the wallet reward used (`used_order_id`);
- debit the points with `Points::debit(…, LedgerType::Redeem, …, $order)`;
- write `order_adjustments`.

If the priced benefits differ from what the guest saw (`OrderRequest` carries no prices), nothing special is needed: the server price is the truth and the Checkout compares totals (Task 8). Any benefit that fails at lock time → `OrderRejected::because('benefit_changed')` (new key in `lang/*/ordering.php`).

- [ ] Write the failing tests:
  - places an order with a coupon, a reward and points, and stores the adjustments, redemptions, the wallet item used and the ledger Redeem;
  - **race** `two orders cannot spend the same points`: with a balance of 100, two requests each redeeming 100; simulate with the first committed, then the second → the second throws `OrderRejected` `benefit_changed`, balance 0, one Redeem entry;
  - the coupon's last use is taken by one order only;
  - an archived promotion at placement is not applied, and the total equals the re-priced total;
  - cross-tenant: a coupon code from restaurant B is unknown in restaurant A.
- [ ] Run → FAIL. Implement. Run → PASS.

### Task 7: Earn, reverse, return, expire

**Files:**
- Create:
  - `app/Domain/Loyalty/Listeners/AwardPointsOnPickup.php` (`OrderStatusChanged` to PickedUp);
  - `app/Domain/Loyalty/Listeners/ReturnBenefitsOnCancel.php` (to Cancelled);
  - `app/Domain/Loyalty/Listeners/ReversePointsOnRefund.php` (`PaymentRefunded`, succeeded);
  - `app/Domain/Loyalty/Actions/MaintainLoyalty.php`;
  - `app/Console/Commands/MaintainLoyaltyCommand.php` (`loyalty:maintain`, scheduled daily 03:30 Asia/Qatar).
- Modify `AppServiceProvider` (listeners) and `routes/console.php`.
- Test: `tests/Feature/Loyalty/LifecycleTest.php`.

**Rules:**
- **Award:** once per order (the unique index). Points = Σ per line `pointsFor(line net share)` × the line's earn-rule multiplier × the tier multiplier, plus bonus points from adjustments. The line net share is the line total minus its proportional share of the discount. Store `orders.points_earned`, then `Tiers::recompute`.
- **Cancel:** redeemed points come back (`LedgerType::Returned`, new lot with a fresh expiry). The wallet reward goes back to available if not expired, else stays expired. Redemptions get `released_at` and `used_count` is decremented.
- **Refund** (after pick-up): `Reverse` = round(points_earned × refunded / amount), never below the remaining balance. When the balance is short, take what exists and record the shortfall in the note. Then `Tiers::recompute`.
- **Maintain:** expire lots past `expires_at` with remaining > 0 (an Expire entry for the sum per customer), expire wallet rewards, recompute tiers for customers with ledger activity in the window or a tier set. Idempotent.

- [ ] Write the failing tests:
  - pick-up awards points once even when the event fires twice;
  - a cancelled order never earns;
  - cancel returns the points, the reward and the coupon use;
  - a full refund reverses all earned points, and a half refund half;
  - maintenance expires an old lot and leaves a fresh one;
  - **property** `earn then full reverse returns the balance to its start` (200 seeded orders).
- [ ] Run → FAIL. Implement. Run → PASS.

### Task 8: Guest UI: checkout benefits and wallet

**Files:**
- Modify `app/Livewire/Customer/Checkout.php` and its view:
  - coupon field with apply/remove;
  - "Use a reward" chooser listing available wallet rewards that apply;
  - "Use points" toggle with the maximum shown ("Use 1,200 points · −12.00 QAR");
  - adjustment lines in the summary;
  - "You'll earn ~N points";
  - when `place()` gets `benefit_changed`, refresh and show "Your total changed".
- Create `app/Livewire/Customer/Rewards.php` and `resources/views/livewire/customer/rewards.blade.php`, route `/{restaurant}/rewards` (`customer.rewards`); add `rewards` to `CreateBranch::RESERVED_CODES` and a tab or link in the account area:
  - balance;
  - tier card with progress bar to the next tier (`Tiers::progress`);
  - wallet (available, then used and expired);
  - catalog with Claim (needs sign-in);
  - points history (last 50, with expiry dates).
- Modify the order page and receipt to show adjustments and "+N points earned".
- Lang: `lang/{en,ar}/loyalty.php`, `promotions.php`, additions to `guest.php` and `ordering.php`.
- Test: `tests/Feature/Customer/RewardsPageTest.php`, `tests/Feature/Customer/CheckoutBenefitsTest.php` (Livewire).

- [ ] Write the failing Livewire tests:
  - apply a coupon → summary shows it and the total drops;
  - a wrong code shows the error;
  - toggling points applies the clamped value;
  - claiming a reward moves it into the wallet;
  - the page is hidden (404) when the plan lacks Loyalty;
  - a guest who isn't signed in sees "Sign in to see your points".
- [ ] Run → FAIL. Implement with the design skills (soft-skill, emil-design-eng): ivory/teal/brass tokens, double-bezel cards, tier progress with a spring transition, empty/loading/error states. Run → PASS.

### Task 9: Owner UI: Loyalty, Rewards, Promotions

**Files:**
- Add `WorkspaceNavigationGroup::Growth` (bilingual label).
- Create `app/Filament/App/Pages/LoyaltySettings.php` (slug `loyalty`) with three tabs:
  - **Program:** all values required to enable, a live example "A 50 QAR order earns 50 points; 1,000 points = 10 QAR off".
  - **Tiers:** a repeater plus a "Start with 4 standard tiers" action.
  - **Earn boosts:** a rules repeater.
- Create `app/Filament/App/Resources/Rewards/RewardResource.php` (manage page, archive instead of delete when used).
- Create `app/Filament/App/Resources/Promotions/PromotionResource.php`:
  - wizard-like form sections by type;
  - a `CouponsRelationManager` with "Generate codes" (count up to 500, prefix, length 8, unambiguous alphabet without 0/O/1/I);
  - CSV download of codes;
  - a usage column (`used_count` / limit).
- Manual points adjustment: an action on a new minimal `CustomerLoyaltyAction` reachable from the Rewards page header ("Adjust a guest's points" by phone, with a reason, audited). The full CRM comes in 7B.
- Policies: `LoyaltyPolicy`, `PromotionPolicy`, registered in `AppServiceProvider`.
- Lang: `lang/{en,ar}/loyalty.php`, `promotions.php`.
- Test: `tests/Feature/Workspace/GrowthAdminTest.php`.

- [ ] Write the failing tests:
  - enabling the program without values fails validation;
  - the standard preset creates 4 tiers;
  - creating each promotion type saves it;
  - generating 50 codes creates 50 unique upper-case codes;
  - a cashier gets 403 on all three pages;
  - restaurant B can't see restaurant A's promotions;
  - pages are hidden without the plan feature;
  - a manual adjustment writes a ledger Adjust entry and an audit entry.
- [ ] Run → FAIL. Implement. Run → PASS.

### Task 10: Quality gate, browser verification, docs

- [ ] Run `vendor/bin/pest --parallel` → all pass.
- [ ] Run `vendor/bin/pint` and `vendor/bin/phpstan analyse --memory-limit=2G` → 0 errors.
- [ ] Run `npm run build` and `composer warm`.
- [ ] Add a demo seeder for Masar: program enabled with owner-chosen-looking values, 4 tiers, 3 rewards, a happy hour, a 10% coupon `WELCOME10`. Demo data only; the "no defaults" rule applies to new restaurants.
- [ ] Browser (via the :8010 proxy), AR/EN, light/dark, 375/768/1280:
  - checkout with a coupon, a reward and points;
  - the wallet and claiming a reward;
  - the tier progress;
  - the owner pages;
  - order and receipt showing discounts and points.
- [ ] Docs: tick 7A items in `docs/ROADMAP.md`, add a `docs/CHANGELOG.md` entry, add `docs/ARCHITECTURE.md` §4.7, add CLAUDE.md gotchas if any.

---

## Milestone 7B (separate plan after 7A is verified)

- **CRM:** a customer resource (profile: orders, revenue, AOV, favorites, last order, points, tier, segment, campaigns).
  - Nightly `crm:segment` assigns New, Regular, VIP, High spender, At risk, Inactive and Frequent buyer of X, with thresholds per restaurant.
- **Campaigns:** WhatsApp marketing template, SMS or push to a segment.
  - Only to guests with explicit `offers` consent: an opt-in on the account page and a checkbox at checkout, recorded with a timestamp.
  - Scheduled, respecting quiet hours, with a one-click unsubscribe link, a throttled send queue, and attribution of orders and revenue within 7 days.
- **QR campaigns:** QR per branch, lane, campaign, channel or promotion, with UTM-style source parameters (into `orders.source`), A4/A5/sticker PDF posters, and analytics for scans → visits → orders → revenue → conversion.
- **Referral:** a code or link; both sides rewarded (`GrantReward`, source referral) after the friend's first completed order. Fraud checks: same phone pattern, same device fingerprint cookie, self-referral, and velocity limits.

## Milestone 7C (separate plan)

- **Post-pickup rating:** 1–5 stars and quick tags; a low rating raises an `OpsAlert`; shown on the manager dashboard.
- **Support tickets:** "Report a problem" (missing item, wrong order, payment issue) → a ticket the branch manager resolves, with the reply sent by WhatsApp; an optional refund through `RefundPayment`.
- **Birthday automation:** a daily job grants the restaurant's chosen birthday reward.
- **Gamification:** streaks, milestones and badges, configurable by the Super Admin and off by default per restaurant.
