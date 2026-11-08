# Phase 7C: ratings, support tickets, birthday gifts and badges — design

**Status:** draft for review (2026-11-04). Nothing is built until this is approved, then a task-by-task plan follows.

**Goal:** after every pickup the restaurant learns how it went and can put things right quickly, guests get a gift on their birthday without anyone lifting a finger, and owners who want it can add a light layer of play (streaks and badges).

**Choices made for you (change any of them):**

| # | Choice | Why |
|---|--------|-----|
| D1 | Gamification = **weekly streaks + milestone badges**, each badge optionally unlocking a catalog reward. **Off by default.** | Easy for guests to understand, little to configure, fits the existing rewards wallet. Challenges can come later. |
| D2 | Ratings are asked **on the order page after pickup**, plus **one push notification** 30 minutes later if the guest allowed notifications. No WhatsApp or SMS. | Asking costs nothing and doesn't use paid message credits. |
| D3 | A rating of **1 or 2 stars** raises a staff alert. | Matches the master spec ("low ratings create an alert"). |
| D4 | Ticket replies go by **WhatsApp utility template** (`dtp_ticket_reply`), then SMS, and always show on the guest's order page. | Meta needs an approved template for messages the business starts; a utility template is cheaper than marketing. |
| D5 | Putting things right = **refund** (online payments only, needs "Refund payments") or a **reward from the catalog** (any order, also for cash). | Cash orders can't be refunded online; a reward still closes the loop. |
| D6 | Birthday gifts go **3 days before** the birthday (owner can set 0–7), **once per guest per 365 days** even if the birthday is changed. WhatsApp/SMS only to guests who accepted offers; push to anyone who allowed notifications; the gift always appears in the wallet. | Stops "change my birthday every month" abuse; respects the opt-in rule from 7B. |
| D7 | Favorites are already built (menu heart, home page). The roadmap line is ticked, no new work. | — |

---

## 1. Ratings

**Guest:**
- On the order page, once the order is picked up: "How was your order?" with five stars, then quick tags that depend on the score.
  - 4–5 stars: Fast · Hot and fresh · Friendly · Accurate.
  - 1–3 stars: Slow · Cold · Missing item · Wrong order · Unfriendly.
- An optional comment (280 characters).
- One rating per order, open for 48 hours after pickup; not editable once sent.
- After a 1–3 star rating with "Missing item" or "Wrong order", the thank-you card offers **Report a problem** (section 2), pre-filled.
- One push notification ("How was your order?") 30 minutes after pickup, only if the guest allowed notifications and hasn't rated yet.

**Staff and owner:**
- 1–2 stars → `AlertKind::LowRating` through `StaffAlerts` to the branch's managers (in-panel, sound, optional WhatsApp, like the other alerts).
- **Ratings page** (Operations group):
  - average and count for the period, a 1–5 distribution, the most common tags, a 30-day trend;
  - the list with filters (branch, stars, tag, has comment) and a link to the order and the guest.
- The restaurant dashboard gets a "Rating, last 30 days" tile; the guest profile lists the guest's ratings.
- Who sees them: "View reports".

**Data:** `order_ratings` (restaurant, branch, order — unique, customer, stars 1–5, tags json, comment, created_at). A rating is written by the server only for the signed-in guest who owns a picked-up order inside the window.

## 2. Support tickets

**Guest:**
- **Report a problem** on the order page for picked-up orders, up to 7 days after pickup. One open ticket per order.
- Choose what happened: Missing item · Wrong item · Quality · Charged incorrectly · Other.
  - For missing or wrong items, tick which lines (from the order).
- Describe it (500 characters).
- The ticket then shows on the order page as a short conversation: the guest's report, staff replies, and a reply box while the ticket is open.
- The outcome is shown plainly: "Refunded 12.00 QAR" or "A free Karak was added to your wallet".

**Staff:**
- **Support inbox** (Operations group): open tickets first, with the number of open tickets as a badge and an "over 24 h" marker.
- Ticket page:
  - the order (items, total, payment, branch, car);
  - the guest (masked phone unless "View contacts");
  - the conversation, and a reply box.
- Actions:
  - **Reply:** saved to the conversation and sent to the guest (D4).
  - **Refund:** full or partial, through `RefundPayment`; online payments only; needs "Refund payments"; audited.
  - **Give a reward** from the catalog, through `GrantReward` (new source `support`).
  - **Resolve**, with an optional closing note. Resolving sends the outcome to the guest.
- A new ticket raises `AlertKind::TicketOpened` (not critical) for the branch's managers.
- Who handles them: a new permission **"Handle support" (`support.handle`)**, Arabic and English label and description, given to the manager preset.

**Data:**
- `support_tickets`:
  - restaurant, branch, order, customer;
  - number (per restaurant, e.g. T-1042);
  - category; items json; status (open | answered | resolved);
  - outcome (refund | reward | none); refund_id; wallet_reward_id;
  - first_reply_at, resolved_at, resolved_by.
- `ticket_messages`: ticket, author (guest | staff + user), body, message_delivery_id.

**WhatsApp:** add `dtp_ticket_reply` (Utility) to `docs/WHATSAPP_TEMPLATES.md`. Its parameters are restaurant, ticket number, the reply (shortened) and a link to the order page.

## 3. Birthday gifts

**Owner (Loyalty settings, new "Birthday gift" section):**
- Switched on or off.
- The reward, from the catalog.
- Days before the birthday (0–7, default 3).
- Send a message (on or off).
- The section shows how many guests have a birthday in the next 7 days, and how many gifts went out this month.

**How it runs:**
- `loyalty:birthdays` runs daily at 06:00 Doha.
- For each restaurant with the gift switched on, it finds guests whose birthday falls on today + N days in the restaurant's time zone. A 29 February birthday counts as 28 February in non-leap years.
- It skips guests who already got a birthday gift in the last 365 days, then grants the reward through `GrantReward` (source `birthday`).
- Safe to run twice on the same day: a unique record per guest and year.

**Guest:**
- The gift appears in the wallet, and a "Happy birthday" card shows on the Rewards page that week.
- Messages follow D6.
- When the gift is on and the guest hasn't added a birthday, the Rewards page asks for it ("Add your birthday to get a gift"); it is saved through the existing account field.

**Data:** `birthday_gifts` (restaurant, customer, year — unique, wallet_reward_id, sent_at).

## 4. Streaks and badges (off by default)

**Owner (Loyalty settings, new "Badges" section):**
- A master switch, off by default.
- Six badges, each with an on/off switch and an optional reward:
  - First order;
  - 10 orders;
  - 25 orders;
  - Explorer (5 different items);
  - Early bird (5 orders picked up before 9:00);
  - Streak (orders in N weeks in a row; N = 3 by default, 2–8).

**How it runs:**
- On each picked-up order, `AwardBadges` updates the guest's progress.
- Weeks run Sunday to Saturday in the restaurant's time zone (the Qatar working week).
- A badge earned the first time is recorded once (unique per guest and badge) and its reward is granted.
- Cancelled or refunded orders don't count. A badge already earned is never taken back.

**Guest (Rewards page, "Achievements"):**
- Earned badges with their date; locked badges with progress ("7 of 10 orders").
- The current streak, with "Order by Saturday to keep your 3-week streak".
- A new badge is celebrated once: it is drawn on the server the first time and marked seen, so a page refresh doesn't replay it.

**Owner view:** badges on the guest profile, and how many guests hold each badge.

**Data:** `customer_badges` (restaurant, customer, badge, earned_at, wallet_reward_id, seen_at; unique customer and badge). Streak and progress are worked out from orders, not stored.

## 5. Rules carried over

- **Gating:**
  - Ratings and tickets need the Drive-Thru service (they are about orders).
  - Birthday gifts and badges need the Loyalty feature (Rewards service).
- **Bilingual:** every string in `lang/ar` and `lang/en`; Western digits; RTL with logical properties; design tokens only.
- **Tenancy:** every new model uses `BelongsToRestaurant`, with cross-restaurant isolation tests for each feature.
- **Audit:** refunds, rewards given, resolving, and settings changes go to the audit log.
- **States:** loading, empty, error and success states on every new screen; no N+1 queries.

## 6. Order of work (becomes the plan)

1. Ratings (guest card, push, alert, Ratings page, dashboard tile).
2. Support tickets (guest report and conversation, inbox, reply, refund, reward, resolve, WhatsApp template guide).
3. Birthday gifts (settings, daily job, guest card and birthday prompt).
4. Streaks and badges (settings, listener, Achievements on the Rewards page).
5. Quality gate:
   - demo data;
   - full suite, Pint, Larastan, build;
   - browser checks in Arabic and English, light and dark, at 375, 768 and 1280 px;
   - docs (ROADMAP, CHANGELOG, ARCHITECTURE §4.9).

## 7. Tests (main cases)

- **Ratings:**
  - only the owner of a picked-up order inside 48 h can rate, and only once;
  - 1–2 stars raise one alert;
  - the push is sent once and not after a rating.
- **Tickets:**
  - one open ticket per order, 7-day window;
  - a reply is stored and sent;
  - a refund is limited to online payments, to the amount paid, and to "Refund payments";
  - a reward goes to the wallet;
  - another restaurant's ticket can't be seen or acted on.
- **Birthdays:**
  - the right day in the restaurant's time zone, 29 February;
  - once per 365 days after a birthday change;
  - a second run on the same day does nothing;
  - messages respect consent.
- **Badges:**
  - each badge's rule;
  - the streak across a year end;
  - a cancelled order doesn't count;
  - a reward is granted once;
  - off by default means nothing is recorded.
