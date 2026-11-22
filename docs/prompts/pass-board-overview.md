# Prompt: "Pass" board for the operations overview (/ops/{restaurant}/overview)

Copy everything below the line into a new Claude Code session opened in this project.

---

You are the principal Laravel engineer, UX architect and motion designer on **Dhawq (ذوق)**, the bilingual (Arabic RTL / English LTR) multi-tenant drive-thru SaaS in this repository. Read `CLAUDE.md`, `docs/ARCHITECTURE.md` and `docs/MASTER_PROMPT.md` first and follow every rule in them (tenancy, permissions as enums, `lang/ar` + `lang/en`, logical CSS properties, Western digits, design tokens only, no third-party requests, no N+1, Pest tests, audit entries for sensitive actions, verify vendor APIs in `vendor/` before using them because Filament 5 / Livewire 4 / Pest 5 are newer than your training data).

Use the workflow in `CLAUDE.md`: `brainstorming` → `writing-plans` (show the plan and wait for approval) → `test-driven-development` → `verification-before-completion`. Design skills: `taste-skill`, `soft-skill`, `impeccable`, `emil-design-eng`; motion: `animate`, `apple-design`.

## 1. Goal

Add a second look for the owner's live operations overview at `/ops/{restaurant}/overview`: a **"Pass" board**, modelled on a professional kitchen pass, where every open order is a paper ticket clipped to a steel rail and ages in front of you. Keep the current overview (`App\Livewire\Ops\ExecutiveBoard`, "Classic") exactly as it is. **The platform admin chooses, per restaurant, which of the two the restaurant gets.**

Reference: a short screen recording of a kitchen dashboard (its first 3 seconds, the "Pass" view). Reproduce the *pattern and the feeling*, not the product: no names, logos, copy, fonts or colours from the recording. Everything uses Dhawq's own tokens, type and voice.

## 2. What the reference shows (the pattern to adapt)

A dark, calm, cinematic control room in one screen, no page scroll on desktop:

1. **Header bar**: restaurant mark + name and a small subtitle; a segmented control for the current service period; on the right, a small "covers so far" count and a large live clock.
2. **The rail (top ~45% of the screen)**: a brushed-steel rail, lit from above, running the full width. Above it, station labels in small caps with live stats (e.g. `GRILL · 3 on · avg 10:17 · 1 late`). Paper tickets hang from the rail, grouped by station, each a slightly off-white slip with a torn/zig-zag bottom edge, a soft drop shadow and a subtle tilt of ±0.6°. A ticket shows: big order number at the top, the section name in small red caps, the items with quantities and modifiers in a monospace "printed receipt" face, and a timer at the bottom. **A ticket past its target turns its timer red and gets a red left edge.** The selected ticket is outlined. At the right end of the rail, finished tickets drop onto a spike ("done") with a small count under it.
3. **The shelf (bottom panels)**, four columns:
   - **On the pass**: a huge count of open tickets with a red line for late ones; below it the **average ticket time** (big mm:ss).
   - **Fire next**: a short queue of what to start next, each row with a big reference, the item group, a red allergy note when there is one, an age, and a primary button.
   - **Stations**: one row per station with a load bar (grey, turning red past target), oldest ticket age, count on, and average time.
   - **Selected ticket**: the focused ticket in detail (big reference, status line, items with modifiers, its age in large red when late).
4. **Bottom tab bar** (Pass / Floor / Menu / Sales in the reference). For this task build **only the Pass view**; do not add the other tabs.

## 3. How it maps to Dhawq (drive-thru, not tables)

| Reference | Dhawq "Pass" board |
|---|---|
| Table number on the ticket | The order's display number (`$order->displayNumber()`), plus the car (colour + plate from `vehicle`) or "walk-up/curbside" lane, in small text |
| Service period tabs (Lunch / Dinner) | **Branch tabs**: one per branch the viewer covers (the overview is multi-branch); remember the last choice per user (session). "All branches" is not a tab: the rail is always one branch |
| Stations (Grill, Sauté, Cold, Pastry) | The branch's `stations` (`Station` model, `StationKind`); items without a station go to General. If the plan has no kitchen stations, one group named after the kitchen |
| Ticket timer | Age since `received_at` (or `placed_at` for scheduled orders once due); target = the branch's `avg_prep_minutes`; late when older than target, matching `BranchPulse`'s definition of delayed so the numbers agree everywhere |
| "Done" spike | Orders that became `ready` in the last 15 minutes, newest on top, with today's ready count |
| Fire next | Scheduled orders coming due and `received` orders not yet started, oldest first; the button moves the order forward **only through `TransitionOrder`** and only if the viewer's `RestaurantPermission` allows (`OperateKitchen` / `OperateOrders`); otherwise the row shows no button |
| Covers so far | Orders today at this branch |
| Selected ticket | The clicked ticket; also shows car arrival (`arrived_at` → "Car is here" in brass), payment status, notes and allergies |

Status flow and every money/time value come from the server. The board never computes prices or changes status on the client.

## 4. The admin's choice

- New column on `restaurants`: `ops_board` string, default `'classic'`, values from a new enum `App\Domain\Ordering\Enums\OpsBoardStyle` (`Classic`, `Pass`) with ar/en labels and a one-line description each (`lang/{ar,en}/ops.php`). Declare the `$attributes` default on the model.
- In the **platform admin panel** (`/admin`), on the restaurant (client) edit page, add a field "Live overview style" (radio cards with a small preview image of each style and the description). Gate it with the existing platform permission that manages clients; record an audit entry when it changes.
- `OpsController::overview()` renders `ops.executive-board` for Classic and the new `ops.pass-board` for Pass. Same route, same access check (`ChannelAccess::restaurantDashboard`), same `ops.page` shell.
- Changing the style takes effect on the next page load; an open Classic board keeps working.

## 5. Build it

- **Livewire component** `App\Livewire\Ops\PassBoard` (`#[Locked] public int $restaurantId`, `public ?int $branchId`, `public ?int $selected`), view `resources/views/livewire/ops/pass-board.blade.php`. Authorise in `mount()` and `render()` like `ExecutiveBoard`. Scope every query by restaurant and by `Operator::staff()->covers($branch)`.
- **Data** in one query service (e.g. `App\Domain\Ordering\PassSnapshot`) returning plain arrays: tickets grouped by station, station stats, fire-next list, done list, totals, average ticket time (today, picked-up/ready orders). Eager-load items, product names and modifiers: no N+1 (add a query-count test).
- **Realtime**: listen on the branch's existing private orders channel (Reverb, same events the kitchen display uses) and refresh the snapshot; fall back to `wire:poll.10s` when the socket is down. Timers tick on the client with Alpine from server timestamps (no request per second). New tickets slide in from the start of the rail; tickets that become ready travel to the spike.
- **Actions** (fire/advance) call the same domain desk the kitchen display uses, through `TransitionOrder`, with toasts in both languages and proper error states.
- **Layout**: fills the viewport at 1280 px and up (TV-friendly at 1920×1080, readable from 3 m); the rail scrolls horizontally inside itself when tickets overflow (scroll-snap, edge fade), never the page. At 768 px the shelf becomes 2×2; at 375 px the rail becomes a horizontal swipe of tickets and the shelf stacks. RTL: the rail flows from the right, the spike is at the left end, timers and numbers stay LTR (`App\Support\Bidi::ltr()`).
- **Look** (Dhawq tokens only, add any missing tokens to the theme, never hard-coded colours): deep ink background with a faint vignette; the rail as a steel gradient with one soft top highlight; tickets in warm paper (`ivory`), receipt text in a monospace face (self-hosted, add it under `resources/fonts` if none exists), section labels in brass, late state in the theme's danger colour; panels as soft "double-bezel" cards. Large numbers in the display face with tabular digits. Contrast meets WCAG AA on the TV.
- **Motion**: tickets hang with a tiny settle (spring, 400 ms) when they arrive; a late ticket's timer pulses once when it crosses the target, not forever; ready tickets slide to the spike (500 ms, ease-out). Everything off under `prefers-reduced-motion`. Animate only transform and opacity. No animation is replayed on Livewire morphs (follow the `CLAUDE.md` note on one-time effects).
- **States**: loading skeleton of the rail; empty branch ("No orders on the pass. New orders hang here the moment they come in"); branch closed (show its next opening time); no branches (reuse the "Add a branch first" page); connection lost banner with auto-retry; every state in ar/en.
- **Accessibility**: tickets are buttons with an accessible name ("Order 12, 2 items, 7 minutes, late"); arrow keys move along the rail; Enter selects; the selected panel is an `aria-live="polite"` region; visible focus rings.

## 6. Tests (Pest, write them first)

- Admin can switch a restaurant between Classic and Pass; staff without the permission cannot; an audit entry is written.
- `/ops/{restaurant}/overview` renders the right board for each style; access rules unchanged (owner/GM yes, kitchen staff and outsiders 403).
- Snapshot: grouping by station (and General fallback), late detection identical to `BranchPulse`, average ticket time, fire-next ordering, done list window, branch scoping, **cross-tenant isolation** (another restaurant's orders never appear).
- Fire button moves the order through `TransitionOrder` and is hidden without permission.
- No N+1 (query count stays flat with 1 vs 30 orders).
- Arabic and English render; enum labels exist in both languages; `GuideCatalog` entry for the new board with matching ar/en actions and tips.

## 7. Done when

- Full suite, Pint, Larastan level 8 pass; `npm run build` clean.
- Browser check (Arabic and English, light and dark, 375 / 768 / 1280 px and 1920×1080 TV): live orders appear, age, turn late, move to the spike; fire works; no console errors, no horizontal page scroll, no third-party requests. Report exactly what was verified with screenshots.
- `docs/ROADMAP.md` and `docs/CHANGELOG.md` updated; `docs/ARCHITECTURE.md` notes the board styles and where the switch lives.
