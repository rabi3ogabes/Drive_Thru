# Test mode: a simulated day on a real restaurant

Status: design for review (approved direction: option 1, 2026-11-24).

## 1. Purpose

The platform team turns on **test mode** for one restaurant (e.g. Tea Time). For the chosen time, fake guests use the restaurant as real guests would, across all three services, through the system's own actions. The owner, staff and the platform team see everything live (kitchen, window, pager, stamps and Pass boards, guest pages), can work the orders themselves, and nothing reaches real people, reports or invoices. When test mode ends, every trace is removed.

Success: switch it on for Tea Time → within a minute orders hang on the Pass board and kitchen screen, cars "arrive", pager tickets ring, stamps are asked for; switch it off (or let it expire) → Tea Time is exactly as before.

## 2. Decisions already made

| Question | Decision |
|---|---|
| Where it runs | On the real restaurant, its real menu, branches and screens |
| Who moves orders | The simulation, unless staff act first on a screen |
| Pace | Calm (≈ 1 order / 6 min), Normal (≈ 1 / 3 min), Rush (≈ 1 / min), shaped by opening hours with breakfast, lunch and evening peaks |
| Duration | 1, 4 or 8 hours, or until closing, whichever comes first; then auto-off and wipe |
| Who switches it | Platform team only, on the client page in `/admin` |
| Visibility | A "Test mode" ribbon on every screen and the workspace; a TEST tag on every fake order, ticket and guest |
| Approach | Test flag + simulator using the real actions (not raw inserts, not a hidden branch) |

## 3. What the simulator does (one tick per minute)

For each restaurant with test mode on and not expired, for each open branch:

1. **New guests (Poisson arrivals).** Expected arrivals this minute = pace rate × hour weight (06–09 ×1.6, 12–14 ×1.8, 18–21 ×1.5, otherwise ×0.7; 0 when the branch is closed). Each arrival picks a service by what the restaurant has on: Drive-Thru order 70 %, Ready Call ticket 20 %, Rewards stamp request 10 % (weights renormalised over the services that are on).
2. **Drive-Thru order.** A test guest (reused from a pool of 30 per restaurant, Arabic and English names, phones in a reserved fake range `+974 5550 0xxx`, a car colour/make/plate) orders 1–4 available products with valid modifier choices through `PlaceOrder`; payment: pay at pickup 60 %, card through the **Fake gateway** 40 %; 15 % are scheduled 20–45 min ahead.
3. **Arrival.** Received/preparing/ready orders get the car "arriving" `prep_minutes ± 3` after `received_at` via `OrderDesk::markArrived` (system operator).
4. **Auto-advance unless staff act.** If an order has not changed status for its stage delay (received → preparing after 1–2 min; preparing → ready after `prep_minutes ± 2`; ready + arrived → picked up after 1–3 min), the simulator moves it through `OrderDesk::advance()` as the **Simulation** actor. Any change by a person resets the clock for that order; staff can always act first.
5. **Ready Call.** `Pager::create` a ticket (label "TEST · n"); `ready` after 3–8 min; `collected` 1–4 min later.
6. **Rewards.** For a guest with a stamp card, `StampRequests::ask`; approved after 1–2 min by the Simulation actor; a full card is redeemed now and then.
7. **Ratings** on 30 % of picked-up orders, 3–5 stars.

Every action goes through the same code a real guest or staff member triggers (events, broadcasts, loyalty, history), so the whole system is exercised.

## 4. Data and isolation

- **Flag:** `is_test` (boolean, default false) on `customers`, `orders`, `pager_tickets`, `stamp_requests`, `stamp_cards`, `wallet_rewards`, `ratings`. Anything created for a test customer is test, set at creation by a model observer that reads the customer (or, for pager tickets, the simulator).
- **Restaurant state:** `restaurant_test_modes` (restaurant_id unique, pace, started_at, ends_at, started_by, ended_at, stats JSON). One row = one run; history kept for audit, data wiped.
- **Kept out of:** reports and exports, the dashboard and executive board numbers, invoices and billing usage, CRM lists and segments, campaigns and audiences, loyalty leaderboards, customer search, webhooks and the public API. One reusable scope `withoutTestData()` plus an **architecture test** that fails if a reporting/billing/CRM/export query reads these tables without it.
- **Shown on:** live operations screens (kitchen, window, live board, TV, Pass, pager, stamps) and the guest pages, each test item tagged TEST.
- **Nothing leaves the system:** `Messenger` (WhatsApp, SMS), mail and web push skip test customers and test pager tickets at one choke point; webhooks skip test orders; the Fake gateway is forced for test orders whatever the restaurant's real provider is.
- **Wipe on end:** a queued job deletes every `is_test` row of the restaurant (children first), then stamps `ended_at`; idempotent and safe to re-run. Order numbers used by test orders are not reused (numbers keep counting).

## 5. Admin experience

On the client page in `/admin`, a **Test mode** card:
- Off: pace (Calm / Normal / Rush, each with its rate), duration (1 / 4 / 8 hours), a warning line ("Fake guests will order at Tea Time's branches; staff will see them, tagged TEST"), **Start test mode**.
- On: started by, ends at (countdown), live counters (orders, pager tickets, stamps), **Stop and clean up**.
- Audit entries for start and stop. Starting is refused when the restaurant has no branch or no available product (clear message).

Workspace and screens: a slim amber ribbon "Test mode · ends 14:30 · fake orders are tagged TEST" in both languages.

## 6. Safety rails

- Only one active run per restaurant; global cap of 3 active runs on the platform.
- The tick never creates more than 5 arrivals per branch per minute, whatever the maths says.
- A test order can never be refunded to a real card, invoiced, or counted in billing usage.
- Turning a restaurant's test mode off is always possible, even if the simulator errored.
- Scheduler-down tolerance: an expired run found by any tick is ended and wiped.

## 7. Components

- `App\Domain\Testing\TestMode` (start / stop / active / expire) + `RestaurantTestMode` model + migration.
- `App\Domain\Testing\Simulator` (tick for one restaurant) with small, separately testable parts: `Arrivals` (rate × hour weight, seeded randomness), `GuestPool`, `OrderMaker` (valid lines and modifiers), `Stagehand` (auto-advance with stage delays), `PagerPlayer`, `StampPlayer`.
- Command `test-mode:tick` scheduled every minute (`withoutOverlapping`), and `WipeTestData` job.
- `is_test` columns, the `withoutTestData()` scope and the guard test; message/webhook/payment choke-point guards.
- Admin card (Filament), ribbon component, TEST tags on tickets and orders, lang ar/en.

## 8. Testing

- Unit: arrival rates by pace and hour (seeded), stage delays, guest pool, valid order lines.
- Feature: start/stop/expiry with audit; a tick creates orders, pager tickets and stamp requests through the real actions; auto-advance only when nobody acted; staff action resets the clock; nothing sent (Messenger, mail, push, webhooks faked and asserted untouched); test data absent from reports, CRM, billing usage, exports, API; wipe removes every test row and nothing else; cross-tenant isolation; ar/en.
- Browser: Tea Time-like local restaurant in test mode, Pass board and kitchen screen filling live, ribbon in both languages, stop wipes everything.

## 9. Out of scope

Simulating payments failures and refunds, multi-restaurant runs started together, owner-started test mode, and a "replay a recorded day" mode.

## 10. Depends on

- The scheduler cron fix on production (the simulator runs from the scheduler).
- Realtime on production is optional: screens already refresh every 10–20 s.
