# Pass Board Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A second style for `/ops/{restaurant}/overview`, the "Pass" board (open orders as paper tickets on a steel rail, grouped by station, ageing live), chosen per restaurant by the platform admin; the current overview stays as "Classic".

**Architecture:** A `restaurants.ops_board` column (enum `OpsBoardStyle`) set from the admin restaurant form. `OpsController::overview()` picks `ops.executive-board` (Classic) or the new `ops.pass-board` Livewire component. All numbers come from one read-only service, `PassSnapshot`, returning plain arrays; the component only renders them and calls `OrderDesk::advance()` for "Start". Live refresh reuses the existing `x-ops.shell` channel `restaurant.{id}.dashboard` plus `wire:poll`.

**Tech Stack:** PHP 8.4, Laravel 13, Livewire 4, Alpine, Tailwind 4, Filament 5 (admin form), Pest 5.

**Spec:** `docs/prompts/pass-board-overview.md`

## Global Constraints

- Every visible string in `lang/ar` and `lang/en`; Western digits; logical CSS properties; timers and numbers LTR via `App\Support\Bidi::ltr()` or `dir="ltr"`.
- Design tokens only (`ink`, `ivory`, `brass`, `status-*`, `white/…`); no hard-coded colours; no third-party requests; fonts self-hosted.
- Status changes only through `OrderDesk` → `TransitionOrder`; the board computes nothing about money or status on the client.
- Every query scoped by `restaurant_id` and by `Operator::staff($user, $restaurantId)->covers($branch)`.
- "Late" = status `received`/`preparing` and `received_at + prep_minutes` in the past (same rule as `App\Domain\Ordering\BranchPulse::of()`).
- Items without a station belong to `StationKind::General` (same rule as `KitchenDisplay::madeAt()`).
- New model column needs an `$attributes` default; enum labels and descriptions in both languages.
- No N+1; motion only on transform/opacity and off under `prefers-reduced-motion`; one-time effects never replay on Livewire morphs.

## Review Focus

- A branch the viewer does not cover (GM limited to some branches): its tab must not appear and `?branch=` for it must fall back to the first covered branch, never show its orders. → test in Task 3.
- An order with items at two stations: it hangs once per station showing only that station's items; when one station finishes (`done_at`), only that ticket leaves. → test in Task 2.
- Restaurant switched to Pass but with no branches: the "Add a branch first" page, not an error. → test in Task 3.
- A scheduled order: not on the rail until it is due; appears in "Start next" from 15 minutes before `prepare_at`. → test in Task 2.
- "Start" pressed twice / on an order that moved meanwhile: the second press shows the desk's error toast, no duplicate transition. → test in Task 3.

---

### Task 1: Board style setting (enum, column, admin field)

**Files:**
- Create: `app/Domain/Ordering/Enums/OpsBoardStyle.php`
- Create: `database/migrations/2026_11_22_100000_add_ops_board_to_restaurants.php`
- Modify: `app/Domain/Tenancy/Models/Restaurant.php` (fillable, `$attributes` default `'classic'`, cast to enum, `@property`)
- Modify: `app/Filament/Admin/Resources/Restaurants/Schemas/RestaurantForm.php` (new field in the features section)
- Modify: `lang/{en,ar}/ops.php` (`board_style.{classic,pass}.{label,description}`), `lang/{en,ar}/clients.php` (field label + helper)
- Test: `tests/Feature/Ordering/OpsBoardStyleTest.php`

**Interfaces:**
- Produces: `enum OpsBoardStyle: string { case Classic = 'classic'; case Pass = 'pass'; }` implementing Filament `HasLabel` + `HasDescription` (`getLabel()`, `getDescription()` from `ops.board_style.*`); `Restaurant::$ops_board` cast to `OpsBoardStyle`, default `Classic`.

- [ ] **Step 1: Write the failing tests**

```php
it('defaults every restaurant to the classic overview', fn () =>
    expect(restaurant()->fresh()->ops_board)->toBe(OpsBoardStyle::Classic));

it('lets a platform admin switch a restaurant to the pass board, and logs it', function () {
    $restaurant = restaurant(slug: 'tea-time');
    $this->actingAs(User::factory()->superAdmin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(EditRestaurant::class, ['record' => $restaurant->getRouteKey()])
        ->fillForm(['ops_board' => 'pass'])->call('save')->assertHasNoFormErrors();
    expect($restaurant->fresh()->ops_board)->toBe(OpsBoardStyle::Pass);
    expect(Activity::query()->where('subject_id', $restaurant->id)->latest('id')->first()->properties['attributes']['ops_board'] ?? null)->toBe('pass');
});

it('keeps the setting away from platform staff who cannot manage clients', ...); // ViewClients-only role: edit page forbidden

it('labels both styles in both languages', fn () => /* foreach cases × ['ar','en']: label and description are not the key */);
```

- [ ] **Step 2: Run** `vendor/bin/pest tests/Feature/Ordering/OpsBoardStyleTest.php` → FAIL (class `OpsBoardStyle` not found).
- [ ] **Step 3: Implement** enum, migration (`string('ops_board', 16)->default('classic')->after('menu_layout')`), model changes, and a `Radio::make('ops_board')->options(OpsBoardStyle::class)->descriptions(...)->inline()` in the features section (check Filament 5 `Radio` API in `vendor/filament/forms/src/Components/Radio.php` first). Run `php artisan migrate` locally.
- [ ] **Step 4: Run** the test file → PASS.
- [ ] **Step 5: Commit** `feat(ops): per-restaurant overview style (classic or pass), set by the platform admin`

### Task 2: `PassSnapshot` (all the board's numbers)

**Files:**
- Create: `app/Domain/Ordering/PassSnapshot.php`
- Test: `tests/Feature/Ordering/PassSnapshotTest.php`

**Interfaces:**
- Consumes: `Order`, `OrderItem` (`station`, `done_at`, `name`, `quantity`, `options`, `notes`), `Station`, `Branch` (`avg_prep_minutes`).
- Produces: `PassSnapshot::of(Branch $branch): array` returning exactly:

```php
[
  'stations' => list<array{kind: string, name: string, on: int, late: int, oldest_seconds: ?int, avg_seconds: ?int, load: float /* 0..1+, on ÷ capacity */,
                          tickets: list<array{order_id: int, number: string, status: string, received_at: string /* ISO */, target_at: string, late: bool,
                                               car: ?string, lane: ?string, arrived: bool, notes: ?string,
                                               items: list<array{quantity: int, name: string, options: list<string>, notes: ?string}>}>}>,
  'next'     => list<array{order_id: int, number: string, status: string, due_at: ?string, items_count: int, notes: ?string}>, // received (oldest first) then scheduled due within 15 min
  'done'     => list<array{order_id: int, number: string, ready_at: string}>, // ready in the last 15 min, newest first, max 12
  'totals'   => array{on_pass: int, late: int, ready_today: int, orders_today: int, avg_ticket_seconds: ?int},
]
```

Station order: the branch's `Station` rows by `sort`; a `General` group is added (named after `__('ops.pass.kitchen')`) only when some item has no matching station. `load` = `on / max(1, branch capacity_per_slot)`. Station `avg_seconds` = mean of `done_at − order.received_at` for that station's items done today. `avg_ticket_seconds` = mean of `ready_at − received_at` for orders ready today (restaurant timezone day). Names are resolved in the current locale.

- [ ] **Step 1: Write the failing tests**

```php
it('hangs an order once per station, with only that station\'s items', ...);      // drinks + general items → 2 tickets; marking drinks items done removes only the drinks ticket
it('marks tickets late exactly like the branch pulse', ...);                     // prep_minutes=6, received 7 min ago → late; 5 min ago → not; matches BranchPulse::of()['delayed']
it('puts items without a station in the kitchen group', ...);
it('lists what to start next: received oldest first, then scheduled due within 15 minutes', ...); // scheduled prepare_at +10 min in, +30 min out
it('shows orders that became ready in the last 15 minutes on the spike, newest first', ...);
it('computes the average ticket time and station averages for today', ...);     // two orders 4:00 and 6:00 → 300 seconds
it('never includes another restaurant\'s or another branch\'s orders', ...);
it('runs a fixed number of queries however many orders there are', ...);        // DB::enableQueryLog(); 1 order vs 30 orders → same count
```

- [ ] **Step 2: Run** `vendor/bin/pest tests/Feature/Ordering/PassSnapshotTest.php` → FAIL (class not found).
- [ ] **Step 3: Implement `PassSnapshot::of()`** with at most 4 queries (open orders with `items`, `lane`; done-today orders; items done today; stations) and group in PHP.
- [ ] **Step 4: Run** → PASS.
- [ ] **Step 5: Commit** `feat(ops): pass snapshot — tickets per station, late, next, done and averages`

### Task 3: `PassBoard` component, controller switch and the Start action

**Files:**
- Create: `app/Livewire/Ops/PassBoard.php`, `resources/views/livewire/ops/pass-board.blade.php` (functional markup first; visual polish in Task 4)
- Modify: `app/Http/Controllers/OpsController.php` (`overview()` picks the component by `$restaurant->ops_board`; restaurant with no branch → existing `noBranch()` page)
- Modify: `lang/{en,ar}/ops.php` (`pass.*` strings)
- Test: `tests/Feature/Ordering/PassBoardTest.php`

**Interfaces:**
- Consumes: `PassSnapshot::of()` (Task 2), `OpsBoardStyle` (Task 1), `OrderDesk::advance(Operator, Order)`, `Operator::staff()`, `ChannelAccess::restaurantDashboard()`.
- Produces: Livewire `ops.pass-board` with `#[Locked] public int $restaurantId`, `#[Url(as: 'branch')] public ?int $branchId`, `public ?int $selected`; methods `selectBranch(int $id)`, `select(int $orderId)`, `start(int $orderId)`.

- [ ] **Step 1: Write the failing tests**

```php
it('shows the pass board when the restaurant uses it, the classic board otherwise', ...); // assertSeeLivewire(PassBoard::class) / ExecutiveBoard::class
it('keeps the same access rules as the classic overview', ...);                           // owner ok; kitchen staff 403; other restaurant's GM 403
it('offers only the branches the viewer covers, and ignores others in the address', ...); // GM on branch A only: no tab for B; ?branch={B} shows A
it('shows the add-a-branch page when the restaurant has no branch', ...);                 // status 409, ops.no_branch.title
it('starts an order through the order desk', ...);                                         // received → preparing, OrderStatusHistory row exists
it('hides Start without the kitchen permission and refuses the call', ...);               // marketing GM-like role: no button; start() → no change
it('refuses a second start of the same order with a message', ...);                        // second call: status unchanged, error dispatched
it('renders in Arabic and English', ...);                                                  // keys ops.pass.* present in both, page OK in both
```

- [ ] **Step 2: Run** `vendor/bin/pest tests/Feature/Ordering/PassBoardTest.php` → FAIL.
- [ ] **Step 3: Implement** the component (authorise in `mount()` and `render()`; branch = requested one if covered, else first covered by `sort`; `start()` checks `$operator->can(P::OperateKitchen, P::OperateOrders)`, loads the order scoped to the restaurant and covered branch, wraps `OrderDesk::advance()` in try/catch for the desk's domain exception and dispatches the same toast events the kitchen display uses) and the controller switch. View wraps content in `<x-ops.shell :restaurant :title :channel="'restaurant.'.$id.'.dashboard'" screen="overview">` with `wire:poll.15s`.
- [ ] **Step 4: Run** the file → PASS; run `vendor/bin/pest tests/Feature/Ordering` → PASS.
- [ ] **Step 5: Commit** `feat(ops): the pass board — tickets on the rail, start next, done spike`

### Task 4: The look, motion, states, guide and docs

**Files:**
- Modify: `resources/views/livewire/ops/pass-board.blade.php`
- Create: `resources/views/livewire/ops/partials/pass-ticket.blade.php`
- Create: `resources/css/ops-pass.css` (imported from the ops CSS entry; rail steel gradient, paper, zig-zag edge via `mask`, ticket tilt, keyframes) and, if no monospace face is bundled yet, a self-hosted one under `resources/fonts` registered as `--font-receipt`
- Modify: `resources/js/` ops entry: Alpine `passClock` (ticks every second from server ISO times; adds `is-late` when crossing `target_at`) and keyboard navigation along the rail
- Modify: `lang/{en,ar}/guide.php` (one more tip on the `all_branches` article about the Pass style, same count in both)
- Modify: `docs/ARCHITECTURE.md`, `docs/CHANGELOG.md`, `docs/ROADMAP.md`
- Test: extend `tests/Feature/Ordering/PassBoardTest.php`

Layout and states per spec §5: header (branch segmented tabs, orders today, live clock); rail (station headers `NAME · n on · avg m:ss · k late`, tickets, done spike with today's count; horizontal scroll inside the rail with snap and edge fade; RTL flows from the right); shelf of four panels (On the pass + average ticket time; Start next; Stations with load bars; Selected ticket with car, lane, arrival, notes); empty / closed / connection-lost states. Tickets are `<button>`s with an accessible name built from `ops.pass.ticket_label` (`Order :number, :items items, :age, late`). Breakpoints: shelf 4 columns ≥1280, 2×2 at 768, stacked at 375; rail swipes at 375.

- [ ] **Step 1: Write the failing tests**

```php
it('names every ticket for screen readers', ...);                  // aria-label contains number, item count and "late" for a late one
it('shows a calm empty rail when nothing is open', ...);           // ops.pass.empty
it('shows when the branch opens again when it is closed', ...);    // ops.pass.closed with the next opening time
it('keeps the guide in step in both languages', ...);              // GuideCatalogTest stays green
```

- [ ] **Step 2: Run** → FAIL; **Step 3: Implement** the view, partial, CSS, Alpine; `npm run build`; **Step 4: Run** the full suite, Pint, Larastan → PASS.
- [ ] **Step 5: Browser verification** (local, `drive-thru` + `drive-thru-proxy`, headless Chromium, a temporary restaurant switched to Pass with orders at two stations, one late, one scheduled, one ready; removed afterwards): Arabic and English, light and dark, 375 / 768 / 1280 px and 1920×1080; tickets age and turn late, Start moves an order, ready orders reach the spike; no console errors, no horizontal page scroll, no third-party requests. Screenshots.
- [ ] **Step 6: Commit** `feat(ops): pass board look — steel rail, paper tickets, live timers` and update docs in the same commit.
