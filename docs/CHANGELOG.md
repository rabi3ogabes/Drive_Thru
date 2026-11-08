# Changelog

All notable changes per phase. Newest first.

## 2026-11-05: Menu themes

### Built
- **Theme templates for every guest page:** six to start (Ivory & Teal, the classic look; Midnight Brass, dark; Desert Sand; Olive Grove; Rose Café; Mono Bold). A theme sets the colours, fonts, corners, cover and menu layout of the home page, menu, checkout, order page, rewards and every other guest page.
- **Owner editor** (Menu design → Theme): pick a template, change the four colours (brand, accent, background, text) with live readability checks, choose English and Arabic fonts for headings and body text from 16 self-hosted fonts or upload a brand font (WOFF2), replace the logo and cover, choose sharp, soft or rounded corners. A phone preview shows the real menu with the unsaved changes, to the owner only. Save, reset to the template, export or import a theme file. The brand colour moved here from Restaurant settings.
- **Admin library** (/admin → Menu themes, new permission "Menu themes"): create, edit, duplicate, upload a theme file (added hidden for review), set the default for new restaurants, hide or offer again, see how many restaurants use each template. Templates in use and built-ins are never deleted.
- Unreadable colours can't be saved (text needs 4.5:1, secondary text 3:1). Theme files carry only checked values: no images, fonts or code.
- Existing restaurants look exactly as before until their owner changes the theme.

### Verified
- 840 tests (full suite), Pint, Larastan 0, build.
- Browser (local, headless Chromium): the Theme editor in Arabic and English, light and dark, at 375, 768 and 1280 px; guest home and menu on Midnight Brass, Mono Bold and Ivory & Teal in both languages at 375, 768 and 1280 px. No sideways scrolling, no console errors, no requests to other hosts.

## 2026-11-05: Owner guide

### Built
- **Guide** (Restaurant → Guide, `/app/{restaurant}/guide`), in Arabic and English: a 7-step "Start here" path, then 42 articles in 8 sections (Operations, Menu, Growth, Branches, Team, Restaurant, Branch screens, What guests see). Each article says what the page is for, when to use it, what every button does (with the button's exact label), tips, and who can use it (the permission's own label). "Open this page" appears only when the reader can open it and the plan includes it; otherwise "Not on your plan" with a link to Billing.
  - Instant search that ignores Arabic diacritics and letter forms (أ/إ/ا, ة/ه, ى/ي), a "/" shortcut, a result count, an empty state, and deep links (`#support`).
- **"?" on every workspace page header:** opens that page's article in a side panel, rendered with the page so it opens without a round trip, and links to the full guide.
- `App\Domain\Guide\GuideCatalog` maps every page and resource to its article; the text lives only in `lang/{ar,en}/guide.php`. `GuideCatalogTest` fails when a workspace page has no article, or when the two languages have different keys (including the number of buttons and tips per article).

### Verified
- 755 tests (full suite), Pint, Larastan 0, build.
- Browser (local Rabii demo, headless Chromium): the guide and the Ratings "?" panel in Arabic and English, light and dark, at 375, 768 and 1280 px; Arabic search with and without hamza; deep link opening Support; the empty search state. No sideways scrolling, no console errors.

## 2026-11-04: Ratings, support, birthday gifts and badges (Phase 7C)

### Built
- **Ratings after pickup:** on the order page, 1-5 stars with tags that fit the score (praise for 4-5, problems for 1-3) and an optional comment, for 48 hours, once. One push reminder 30 minutes after pickup to guests who allowed notifications (no paid messages). 1-2 stars alert the branch managers.
  - **Ratings page** (Operations): the last 30 days' average, share of 4-5 and 1-2, scores, most-mentioned tags and a daily chart, then every rating with branch, stars, tag and comment filters. Needs "View reports".
  - A "Rating, 30 days" tile on the dashboard, and ratings on the guest profile.
- **Support tickets:** "Report a problem" on the order page up to 7 days after pickup (missing item, wrong item, quality, charged incorrectly, other; the lines that were wrong), as a conversation the guest can reply in. After a low rating naming a missing or wrong item, the report is offered straight away, pre-filled.
  - **Support inbox** (Operations, with an open-ticket badge and an "over 24 hours" marker): the order, the guest and the conversation; reply (WhatsApp utility template `dtp_ticket_reply`, then SMS, and on the order page); **refund** all or part of an online payment; **give a reward** (works for cash orders too); **resolve** with an optional note, telling the guest how it was put right. Audited.
  - New permission **Handle support**, given to managers (existing restaurants too).
- **Birthday gifts** (Loyalty → Birthday gift): a reward from the catalog N days before the birthday (0-7, default 3) in the restaurant's time zone, once per guest per 365 days even if the birthday changes, 29 February on 28 February in other years. A message only to guests who accepted offers (the approved offer template), a push to guests who allowed notifications. The Rewards page asks for the birthday and shows a "Happy birthday" card that week.
- **Streaks and badges** (Loyalty → Badges, **off by default**): a weekly streak (Sunday to Saturday) and six badges, each counting unless switched off and optionally unlocking a reward. Achievements on the Rewards page with progress and "order by Saturday to keep it going"; a new badge is celebrated once. Guests who qualified before badges were switched on get them when they open the page. Badges on the guest profile and holders per badge in settings.
- Demo data (`DemoFeedbackSeeder`): ratings, three tickets, the birthday gift with two birthdays this week, badges on with earned ones.

### Fixed
- The workspace sidebar showed the Operations group twice and hid the live-screen links once a page joined it (enum vs string group names).
- Guest text in another language (an English comment in Arabic) now lays out in its own direction.

### Verified
- 740 tests (full suite), Pint, Larastan 0, build.
- Browser (local Masar): Ratings, Support inbox and a ticket, the Loyalty Birthday and Badges tabs and the dashboard tile in Arabic and English, light and dark, at 375, 768 and 1280 px; the guest rating card, report sheet and conversation, Rewards birthday prompt, Achievements and the badge celebration at 375 and 768 px in both languages. No sideways scrolling, no console errors.

## 2026-11-01: Phase 7B final pass (task 8)

### Built
- **Growth demo data** (`DemoCrmSeeder`, run by `db:seed` for Masar, safe to run twice): 14 guests with months of picked-up orders, points and tiers, segments computed, consent for most, a sent WhatsApp campaign with delivery receipts and 3 attributed orders, a car-window QR sticker with a month of scans and 7 orders, invites switched on with one rewarded and one waiting referral.

### Fixed
- The 5 × 5 cm sticker spilled its English line and link onto a second page; it now fits one sticker (and every poster size is tested to stay on one page).
- A guest's recent orders were listed by record number, not by order time.
- A sent campaign said its guests "will receive it"; it now says "Sent to 9 guests".
- The invite settings button said "Submit"; it says "Save".

### Verified
- Full suite, Pint, Larastan 0, `npm run build`, `composer warm`.
- Browser (local Masar, owner signed in): guests list and profile, campaign composer and results, the QR funnel, referrals with settings, in Arabic and English, light and dark, at 375, 768 and 1280 px: no sideways scrolling, no console errors except the realtime socket when Reverb isn't running. Guest pages (Rewards invite card, invite page, unsubscribe page; light only by design) in both languages at all three widths. The A5 poster and the sticker checked as rendered PDFs.

## 2026-11-01: Invite friends (Phase 7B, task 7)

### Built
- **An invite link for every guest** on their Rewards page: copy it, or share it with the phone's share sheet. The card says what each side gets and how many friends joined. The Account page links to it.
- **The invite page** `/{restaurant}/invite/{code}`: who invited the friend, their welcome gift, three steps, and sign-in right there. The invite is remembered for 30 days, so signing in later (at checkout, say) still counts. Own link, existing guest and inactive invites each get their own calm message.
- **Rewards after the first picked-up order**, through `GrantReward` (source `referral`), once. A cancelled order rewards nobody; the referral waits for the next pickup.
- **Fraud checks**, each recorded with its reason: the friend signs up on the inviter's phone (a first-party device cookie, stored only as a hash), picks up in the inviter's car (plates compared however they are typed, Arabic digits and spaces included), was already a guest, opens their own link, or the inviter passed the monthly cap.
- **Owner screen** (Growth → Referrals): totals, every referral with its status and reason, and the settings (on or off, the two rewards from the catalog, the monthly cap, 10 by default). Staff need "Manage campaigns".

### Verified
- 11 new tests: the happy path rewards both once, a cancelled first order, signing in later, each fraud rule, the cap, restaurant isolation and invites switched off, the guest card, the owner list and settings, access.
- Browser (local Masar): the invite page in Arabic and English on a phone, a real sign-in through it (referral pending, device recorded), the Rewards card; own-link and invalid-link states.

## 2026-11-01: Tracked QR codes and WhatsApp offer guide (Phase 7B, tasks 5–6)

### Built
- **Tracked QR codes** (Growth → Tracked QR codes): one code per placement (a car-window sticker, a flyer, a table).
  - Each opens a branch menu (with an optional lane and promo code) or the restaurant page.
  - The short link `/q/{code}` counts the scan, then opens the menu carrying `source=qr` and the code, so the order remembers where it came from.
  - **Counting honestly:** one scan per device per 30 minutes (the device is a salted hash of IP and browser, never the raw values); link previews and crawlers are kept but never counted; a visit is counted only when the scan reached the menu.
  - **The funnel:** scans → visits → orders → revenue → conversion, with a 30-day chart of scans and orders.
  - **Downloads:** PNG, SVG, an A5 or A4 poster and a 5 × 5 cm sticker (PDF, bilingual).
  - A code can be switched off, or given dates; outside them it opens the restaurant page without counting.
  - The Links & QR page points to tracked codes.
- **WhatsApp offer template guide** in `docs/WHATSAPP_TEMPLATES.md`:
  - `dtp_offer` (Marketing) wording in both languages, its opt-out button and sample values for Meta's review.
  - The sending rules, and why quality and pricing matter.
- A tap on Meta's marketing opt-out button ("Stop promotions") now stops offers too.

### Verified
- 15 new tests: scan and redirect, dedupe, bots, scan → visit → order in the funnel, switched off and out of dates, downloads and posters in every size, access and restaurant isolation.
- Browser: a code's page in Arabic at 1280 px and on a phone.

## 2026-11-01: Service showcase on the home page

### Built
- **The three services as a showcase:** one tab per service, each with a progress line, and one slide each, with its copy, price, free-demo line and a link to the service page.
- **A scene in motion for each service** (9 seconds):
  - **Drive-Thru:** tap and pay on the phone, the kitchen ticket goes from New to Preparing to Ready, and the car rolls to the window and away.
  - **Order-Ready Call:** the guest's pager rings and buzzes, the WhatsApp backup arrives, and 27 moves to Ready on the TV board.
  - **Rewards:** stamps land one by one, the Free circle lights up and the gift arrives in the wallet.
- **Usability:**
  - Plays only on screen, pauses on hover, focus or a hidden tab, and has a pause/play button.
  - Swipe on phones; arrow, Home and End keys, mirrored in Arabic.
  - Built as accessible tabs (only the visible slide can be reached).
  - With reduced motion nothing plays by itself and each scene shows its finished state.
  - Animates only transform and opacity.

### Verified
- Home page tests in English and Arabic; the full suite passes.
- Browser: each scene checked at set moments, at 1280 px and on a 375 px phone, in Arabic and English, light and dark. No sideways scroll, no console errors, and keyboard, pause and tabs work.

## 2026-10-28: Browser installer and MySQL/MariaDB readiness

### Built
- **`/install`:** a one-time installer for hosts without SSH.
  - It checks the server (PHP, extensions, writable folders).
  - It asks for a setup key that only someone with access to the server's files can read (`storage/app/install-key.txt`).
  - It tests the database, then saves the details, a new app key and production settings into `.env`. Every value is read back to prove it saved exactly.
  - It creates the tables and the super admin, then locks itself for good (`storage/app/installed.lock`) and deletes the key.
  - The page is in Arabic and English and works before the assets are built.

### Fixed (found by running everything on MariaDB, not just SQLite)
- 10 required date columns are now `dateTime`: on MariaDB/MySQL with older timestamp settings they failed to create, or silently reset to "now" on every update.
- Opening hours and menu schedules read MySQL `TIME` values (`07:00:00`) as `07:00`, so open/closed checks compare correctly.
- The product form's option-group ordering named its table, so the `sort` column is no longer ambiguous.

### Verified
- 659 tests pass on SQLite. The suite also ran on MariaDB 10.4, and every failure it found is fixed above.
- Migrations, demo seeding, the scheduled jobs and the installer itself ran end to end on MariaDB.

## 2026-10-27: Guests, campaigns and the counter stamp QR (Phase 7B, in progress)

### Built
- **Guests (CRM):** a guest list with segments, tier, visits and spend; a guest profile (orders, favourites, stamps, wallet, consent); a CSV export; "Guest rules" settings for the segment thresholds, quiet hours and the daily message limit.
- **Campaigns:**
  - Owners write an offer in Arabic and English and see a live preview of what each guest receives (with the SMS length).
  - They choose guest groups and see how many guests it reaches, add an optional gift, and send now or at a set time.
  - A test goes to the owner's own phone first.
  - Sending is gentle: only guests who agreed, never in quiet hours, at most one offer a day and three a week per guest, 30 a minute per restaurant, and within the daily limit.
  - Every message carries a stop link that works without signing in; a STOP / إيقاف reply on WhatsApp works too.
  - Results: sent, delivered, failed, skipped (and why), and the orders and revenue it brought back (its link, or within 7 days).
- **Counter stamp QR (Rewards):** a printable QR per branch. The guest scans, taps "Add my stamp", and the waiter approves it on the Stamps screen; the card fills live.
- **Stamp cards now match paper cards:** one circle per purchase, then a separate "Free" circle ("buy 9, the 10th is free").

### Verified
- 652 tests pass, Larastan level 8 clean, Pint clean.
- Browser: the campaign composer and campaign page in Arabic, the stop page and the stamp card on a phone.

## 2026-10-26: Services model, Order-Ready Call, stamp cards & the new website

### Built
- **Three services, sold separately:** Drive-Thru, Order-Ready Call and Rewards.
  - Each has a monthly price per branch and a free demo.
  - Taking two or three services applies a bundle discount.
  - Plans became tiers: branch and staff limits plus add-ons.
  - The admin adds services to clients: demo, activate, extend, change the price, end.
  - Owners try, keep, add and cancel from Billing.
  - Billing charges added services pro rata, settles demos daily and records every change in the audit log.
- **Order-Ready Call:**
  - A counter pager screen.
  - A guest pager page that rings, vibrates and keeps the screen awake.
  - WhatsApp/SMS when the order is ready.
  - A ready board for the waiting-area TV.
- **Stamp cards (Rewards):**
  - "Buy 5, the 6th is free", with a daily cap and carry-over.
  - Stamps come back off after a cancellation or refund.
  - Stamps can be added at the counter, by the guest's phone number.
  - Guests see their cards, and checkout shows "1 more to go".
- **Website:**
  - A new home page, a page for each service and a pricing page.
  - A live price calculator fed by the admin's price list.
  - Plan tiers, FAQs and the demo form, which now records the services asked for.
  - Light, dark or "match my device", with no flash on load.
  - A sitemap with Arabic and English links, and structured data for search engines.
- **Karak House demo menu:** 17 photographed dishes, with photos stored and served by the app itself. The branch menu page was redesigned (photo hero, "Most loved" carousel, category chips that follow the scroll).
- **CRM foundations (7B task 1):** guest stats from picked-up orders, and an offers opt-in with proof of consent at checkout and on the account page.

### Fixed during verification
- **Floating ticket in the hero:** it covered the pager number, so it moved on desktop and is hidden on phones.
- **Brand name in Arabic:** "Drive-Thru Plus" broke at its hyphen, so it now stays on one line.
- **Pager counter:** a view variable clashed with a Livewire property of the same name and broke the screen.

## 2026-10-12: Phase 7A, Loyalty, tiers, rewards & promotions

### Built
- **Points program per restaurant**, with no default values: it stays off until the owner sets every one.
  - Points are earned at pick-up only, on what was actually paid, with earn boosts (product, category, branch) and the guest's tier multiplier.
  - Points come back off after a refund, in proportion, and expire after the chosen months, soonest-expiring first.
  - Guests can spend points at checkout up to the owner's cap, in whole steps.
- **Tiers:** Bronze to Platinum, or the owner's own levels, from points earned in a rolling window, with benefits shown to the guest. The nightly `loyalty:maintain` job moves guests between tiers and expires old points and rewards.
- **Rewards catalog and wallet:** free item, % off, amount off and bonus points, with expiry, stock and minimum order. Guests claim rewards with points; bonus points are only given.
- **Promotions engine:** % off, amount off, buy X get Y, free item, combo price and bonus points.
  - Limited to branches, categories or products, dates, days and hours in the restaurant's time zone (windows may cross midnight), first order, birthday (±3 days), minimum order, and total and per-guest limits.
  - Clear stacking rules: an offer that doesn't combine stands alone when it saves more.
- **Coupon codes:** your own or generated in batches of up to 500 (no look-alike characters), with limits, expiry and CSV download.
- **Guest experience:**
  - A savings card at checkout (code, wallet reward, a points switch showing exactly what it takes off).
  - Itemised discounts in the total and "you'll earn about N points".
  - A new Rewards page with balance, tier progress, wallet, catalog and points history.
  - Discounts and points on the order page and receipt.
- **Server-side safety:** checkout re-prices everything when the order is placed. Points, the last use of a code and wallet rewards are locked, so two orders can't spend the same thing; the loser sees "your total changed". Cancelling gives back the points, the reward and the code use.
- **Owner workspace:** a new "Loyalty & offers" group with Loyalty (program, tiers, earn boosts, live example, adjusting a guest's points with a reason), Rewards and Promotions (with a Codes tab). Everything is audited, gated by plan features and the loyalty/promotions permissions.
- **Integrations:** orders in the API and webhooks now include their discount lines and points.
- **Quality:** 536 Pest tests (up from 470), including property-style tests on stacking (500 random carts), the points ledger (300 random operations), pricing (300 random carts) and earn/full-refund round trips. Larastan level 8 with 0 errors, Pint.

### Fixed during verification
- **Points were capped on the amount before a wallet reward**, letting guests use more points than the owner's cap allowed. The cap now applies to what is left after the code and the reward.
- **The rewards catalog loaded each free item's product separately** (N+1, refused by the app). It's now loaded up front.
- **An order could be charged a total the guest never saw** when an automatic offer ended (or a price changed) while they were on the checkout page. Checkout now sends the total it showed, and a different server total is refused with "your total changed"; the next tap places it at the new total.

## 2026-10-11: Phase 6, Payments, notifications & integrations

### Built
- **SADAD payments (Qatar):** each restaurant connects its own merchant account (encrypted, write-only keys, test/live). Signed web checkout; payments confirmed by a checksum-verified callback and webhook; refunds; status lookup; fees and provider transaction recorded. Unpaid orders are checked with SADAD before they're cancelled. A payment that arrives after an order was cancelled is refunded automatically. The Fake gateway stays for demos outside production.
- **WhatsApp + SMS:** login codes, "order received", "order ready" and receipts on WhatsApp Cloud API templates, with SMSala as the SMS fallback. Every attempt is logged with a masked number and its delivery status. Guests can turn order updates and receipts off.
- **Alerts for the team:** late orders, guests waiting at the window, an overloaded kitchen, failed refunds and webhooks switched off. They go to the people who run that branch, in each person's language: the bell with a live chime and toast, web push per device, WhatsApp (opt-in) and email for critical ones. Restaurants set **quiet hours**; each person picks channels under **Account → Alerts**.
- **Web push:** guests can get "your order is ready" with the page closed; staff get alerts on every browser where they turned them on.
- **Refunds at the live board**, and a **staff PIN on shared screens** for refunds and status overrides. The action is recorded under the person, and wrong PINs lock the screen for 5 minutes.
- **Open to other systems:** signed **webhooks** (retries up to 12 h, delivery log with the exact body and answer, replay, test ping, automatic switch-off), a **public REST API v1** (API keys per system with scopes, rate limits, OpenAPI JSON and a bilingual `/developers/api` page), and a generic **POS connection** that pushes accepted orders and keeps the POS reference on the order.
- **Printing:** 80 mm kitchen tickets and receipts through PrintNode or the browser, with auto-print on accept per branch.
- **Platform Health** for the super admin: success rate, speed and last error per provider, platform setup checks, and an alert when a provider keeps failing.
- **New permission:** "Integrations & API" (owners have it; general managers don't by default).
- **Quality:** 470 Pest tests (up from 401), Larastan level 8 with 0 errors, Pint.

### Fixed during verification
- Webhook event names contain dots, so their labels showed raw translation keys. Labels now use dot-free keys, and a test covers every event and scope in both languages.
- A webhook address that can't be reached only showed a passing toast. It's now an inline error on the field.
- The developer page header wrapped the logo on phones. The optional `openapi.json` link is hidden on small screens.
- Counter payments could have been sent to the restaurant's online gateway for a refund. They now never reach a provider.
- The overloaded-kitchen check never fired on SQLite (a bound number was compared as text), and it read a stale cached queue in long-running workers. Both fixed and tested.

## 2026-10-11: Phase 5, Live operations

### Built
- **Order state machine:** a held **Scheduled** status for later starts and "when I'm on my way"; arrival and "on the way" as recorded facts; status times; **compare-and-set** saves so a double tap or a stale screen fails cleanly; a **database guard** on status values; override, re-enable and reset as new with a reason and an audit entry. Held orders are released at their start time by a queued job, with an `orders:release` sweep every minute.
- **Realtime with Reverb:** branch, restaurant, order and platform channels with id-only payloads. Screens and guests are signed off at `/realtime/auth`, staff at `/broadcasting/auth`. Every screen resyncs after a reconnect and keeps a slow poll as a safety net.
- **Kitchen display**, **kitchen stations**, **live board** (cashier/manager), **pickup window** (QR scan or code → Paid ✓ Verified ✓ Ready ✓ → complete), **TV command center** and **all-branches screen**. They run on paired devices (`/screen`) and for signed-in staff (`/ops/…`), linked from a new **Live operations** sidebar group shown per permission.
- **Guest live page:** realtime updates, chime and vibration, a **full-screen Ready alert** with the pickup code, optional notification, **I'm here** with lane, **opt-in live location** with distance and ETA, an approach alert that starts the kitchen, and **auto-arrival** inside the branch radius. Signed staff links can follow an order live.
- **Dynamic prep estimate and busy level** from the last 3 hours of real prep times, the live queue and busy mode, used everywhere a wait is shown.
- **Super Admin:** "Live data connected" with live order activity across restaurants.
- **Quality:** 401 Pest tests (up from 263), including every status pair and override pair, the compare-and-set race, the database guard, channel access across restaurants, station completion, pickup verification and auto-arrival with simulated coordinates. Larastan level 8 with 0 errors, Pint.

### Measured in the browser (dev server)
- Guest taps "Place order" → the kitchen and cashier tabs receive the event in **968 ms**, including placing the order on the single-threaded PHP dev server; the cashier board shows it about 0.6 s later.
- Kitchen taps Start / Ready → the guest's page receives the event in **509 / 517 ms** and shows the new status at **882 / 885 ms**; the Ready alert opens.

### Fixed during verification
- **The live board overflowed on phones and tablets.** A visually hidden table label is absolutely positioned and escaped its scroll container; the scroll wrappers are now positioned.
- **The guest page threw a script error on live refreshes.** The one-time "Order placed" celebration was rendered again on every refresh; it now plays once.
- **The overview screen was too open.** Anyone with "view orders" across all branches could see every branch's sales; it now needs reports access and coverage of every branch.
- **Unpaid counter orders read "Pay at the window · Pay at pickup".** Staff now see "To collect".
- **Guests reaching pages outside the panels** (ops screens, invoice PDFs) are sent to the restaurant sign-in instead of an error.

## 2026-10-11: Phase 4, Customer ordering app (PWA)

### Built
- **Guest app** on the restaurant's own links (`/{restaurant}`, `/{restaurant}/{branch}`, `/o/{code}`), mobile-first with a bottom tab bar (Home, Menu, Orders, Account) and the restaurant's colour.
- **Home:** greeting, the live order first, **"Your usual?"** in one tap, favourites, popular and new, and every branch with open state, wait, busy level, distance (opt-in), call and directions.
- **Menu and product sheet:** search, section chips, hours panel, live-priced options with validation, nested follow-ups, meal slots, notes, favourites, then **make it a meal** (with savings) or **complete your meal**.
- **Checkout on one page:** bag with per-line problems, "goes well with", inline **phone code sign-in** (only the first time), car, lane, **now / later / when I'm on my way / scheduled**, payment, notes, server totals. Place order is **idempotent**. A full kitchen suggests the next free time in one tap; a closed branch offers scheduling only.
- **Payments** through the Fake gateway: card with a 3-D Secure page, Apple Pay, Google Pay, pay at the window; retry after a failure.
- **Order page:** success animation, timeline, ready time, **pickup code + QR**, share link, "I'm on the way", cancel within the window, order again, receipt. Shared links show progress only.
- **Orders history, account** (name, birthday, language, cars, devices, favourites) and a printable bilingual **receipt**.
- **PWA:** per-restaurant manifest, service worker that never caches pages, bilingual offline page, polite install prompt after the first order.
- **Quality:** 263 Pest tests (up from 224), including the full new-guest and returning-guest flows, double tap, repricing, sold out, closed branch, full kitchen, code limits, payments, cancel window and cross-restaurant isolation. Larastan level 8 with 0 errors, Pint.

### Fixed during verification
- **Capacity suggestions could be refused.** The next free slot could be less than 15 minutes away, which scheduled orders don't accept. The suggestion now moves to the first free slot the server accepts, and the message shows that time (test added).
- **Guest sign-in failed inside Livewire tests.** `CustomerAuth` read the session from the injected request; it now uses the session store directly.
- **Short links typed in lowercase** returned 404; `/o/{code}` now accepts both cases.
- **The branch page lost its opening hours and directions** when the menu replaced it; they are back in a compact panel.
- **"Add to bag" wrapped onto two lines** at 375 px; sheets now skip their slide-in for reduced-motion users.
- **The payment line repeated itself** for pay-at-pickup orders ("Pay at the window · Pay at pickup").
- **Local environment:** the dev database was missing the ordering tables (tests use their own database), and the dev server ran without OPcache (about 1 s per request) and stalled on idle browser sockets. Both are fixed and documented in CLAUDE.md, with a small proxy for browser checks.

## 2026-10-11: Phase 3, Menu engine

### Built
- **Catalog:** sections (emoji, image, schedule, drag to reorder), items (bilingual text, price with discount and a live "Guests pay…" line, photo, calories, prep time, allergens with icons, tags, highlights, SKU), reusable **option groups** (choices or "remove ingredients", required/single/multiple rules, defaults, price and calorie changes, nested follow-ups), **meals** with slots, upcharges and per-branch savings, "make it a meal" links and "goes well with" suggestions.
- **Schedules:** breakfast, late night and any weekly window, including past midnight.
- **Branch menu:** a branch-first screen for the shift: out of stock today (back at local midnight by itself), daily stock that refills every morning with a once-a-day low-stock alert, branch price, serve/don't serve, and bulk actions. Branch managers stay within their branches.
- **Bulk tools:** price update by % or amount with friendly rounding and a full preview; duplicate; move; switch on or off.
- **Excel:** export, template and all-or-nothing import that lists every problem by row.
- **Photos** become 400/800 px WebP and AVIF automatically.
- **Menu design** settings and **Preview as guest**.
- **Guest menu** on every branch page: sticky section chips, instant bilingual search, discounts, meal savings, allergen and tag icons, and greyed sold-out or "not now" items. Sections that can't be ordered right now move to the end.
- **Caching:** the catalog is cached per restaurant under a version that any menu change bumps; branch stock is always read live.
- **Demo menu:** 50 bilingual items with option groups, meals, a breakfast schedule and branch-specific stock.
- **Quality:** 224 Pest tests (up from 196), including time-travel tests across each branch's local midnight, schedules past midnight and an Excel round trip. Larastan level 8 with 0 errors, Pint.

### Fixed during verification
- **The cached menu came back broken in development.** Laravel 13 refuses to unserialize cached objects unless their classes are allow-listed, and tests had used the non-serializing array cache.
  - Fix: `cache.serializable_classes` now lists exactly the menu classes.
  - A regression test on the file store fails without the fix.
- **The guest page failed to render.** An inline `@php(...)` followed later by a `@php … @endphp` block made Blade swallow everything between them. The inline form is now a block.
- **The item editor crashed.** Filament injects closure arguments by name: a `Builder $q` parameter received an empty container-built query. Renamed to `$query`.
- **Polish:** hid the grouping controls and the empty photo column, and widened item names on the busy tables.

## 2026-10-11: Phase 2, Restaurant workspace

### Built
- **Branches:**
  - live cards showing open / busy / paused / closed / holiday, prep time, lanes, screens and "closes at"
  - a four-tab slide-over editor: details, hours (split and past-midnight shifts, closures), pickup & kitchen (lanes, curbside spots, stations, capacity), arrival tracking
  - "use my current location"
  - **live controls:** pause, busy mode, resume, switch off. Each is one tap and audited.
- **`BranchSchedule`:** one source of truth for a branch's state, closing time, next opening and "taking orders again at", always in the restaurant's timezone.
- **Team:**
  - invite by email or WhatsApp link into a position and one or many branches; suspend, reactivate or remove (signs out everywhere); resend invite or password link; screen PINs (4–6 digits, unique, hashed, weak PINs refused)
  - **escalation guard:** nobody grants or edits more power than they hold, and branch managers stay inside their branches
- **Positions:** tune or restore the 7 built-ins; build custom ones from grouped, described permissions. Permissions you don't hold are shown but locked.
- **Screens:**
  - one-time 6-digit pairing at `/pair`, then a long-lived device sign-in
  - the standby view keeps the screen awake, sends a heartbeat and reconnects on its own
  - revoke or pair a replacement; online / offline / waiting status
- **Restaurant settings:** brand (logo, cover, accent), region (language, timezone, currency), ordering rules (tax, service fee, menu-link mode, cancellation window, loyalty rate, override toggle).
- **Links & QR:** copy/open every link; QR codes generated locally as PNG, SVG or a bilingual A5 print poster.
- **Public pages:** restaurant and branch pages behind every link (live status, hours, closures, directions, tap-to-call), plus friendly not-found and temporarily-unavailable pages. Platform paths can never be shadowed by a restaurant slug.
- **Dashboard:** live stats and a go-live checklist with a progress ring, computed from real data.
- **Plan limits:** branches and team members are enforced in the domain, with usage in page headers and a friendly upgrade prompt.
- **Demo data:**
  - Masar has 3 branches (one with split shifts), a team spread across branches, PINs and a paired kitchen tablet
  - Sahra and Karak have their own branches
- **Quality:** 196 Pest tests (up from 148), Larastan level 8 with 0 errors, Pint.

### Fixed during verification
- Owners saw an empty branch list: "all branches" (`null`) was being turned into "no branches" (`[]`). Caught by the isolation test and fixed in `Workspace::branchIds()`.
- Opening hours displayed 3 hours late: the time picker converted wall-clock times between timezones. Fixed by pinning the picker to UTC; a regression test fails without the fix.
- The "use my current location" button broke Alpine with a double-quoted message inside an HTML attribute. Fixed by switching to `Js::from()`.
- A paused branch showed its next shift instead of the end of the pause. Fixed with the new `reopensAt()`, which handles a pause that outlasts the shift.
- Letter-spacing broke the joined Arabic letters on the QR poster. Spacing now applies to Latin text only.
- The setup checklist and the restaurant hero were cramped or bled colour. Fixed with container queries and `overflow-hidden`.
- Asking for a fresh pairing code on an unpaired screen showed an unnecessary confirmation. It is now instant.

## 2026-10-10: Phase 1, Super Admin & SaaS core

### Built
- **Clients:**
  - card grid with status tabs and a three-step onboarding wizard (restaurant → owner → plan)
  - client page with subscription, owner, billing details, features and notes, plus Team, Invoices and Activity tabs
  - change plan, issue invoice, suspend/reactivate, resend owner invitation (email + WhatsApp link)
- **View as owner:** reason required, floating "Return to platform" pill, sensitive account pages blocked, both ends audited.
- **Billing:**
  - plans (Starter / Growth / Enterprise) with limits, trials and feature flags
  - subscriptions with a daily billing cycle: trial → active → past due → grace → suspended; paying reactivates
  - gap-free invoice numbers and bilingual PDF invoices by email
  - owner Billing page in the workspace
- **Growth:** lead pipeline with notes, assignee, follow-ups, WhatsApp, and one-click **Convert to client**. Landing-page "Request access" form with honeypot, rate limits and dedupe; notifies sales and acknowledges the visitor in their language.
- **Platform team:** staff invitations, custom roles with a permission matrix, suspend/reactivate, unlock sign-in, reset 2FA.
- **Platform settings:** billing rules, invoice details, bank details, order override, OTP channels, maintenance banner.
- **Announcements:** scheduled and targeted; owners can dismiss them in their workspace.
- **Security center:** alerts inbox with unlock, platform-wide active sessions, and an audit log viewer with filters and readable diffs.
- **Authorization:** a policy for every admin resource. `Gate::before` was removed, so the policies are the single source of truth.
- **Quality:** 148 Pest tests (up from 70), Larastan level 8 with 0 errors, Pint. A translation-key test scans the code for missing ar/en keys.

### Fixed during verification
- Admin navigation groups showed Arabic on English pages, because Filament reads enum labels once at boot. Fixed with lazily resolved group labels and a regression test.
- Workspace notices sat flush under the top bar, because the panel theme didn't scan Livewire views for Tailwind classes. Fixed.
- Phone numbers were mirrored on Arabic pages ("6330 5492 974+"). They are now wrapped in Unicode LTR isolates via `App\Support\Bidi`; copying still gets the raw number.
- The "convert lead" uniqueness check targeted the wrong table inside the record modal. Fixed.
- The impersonation banner covered page content. Fixed by turning it into a floating pill.
- Billing tiles were cramped, money values wrapped mid-word, and the announcements title column was too narrow. All re-laid out.
- The Windows dev server timed out while compiling views on first load. Fixed with `composer warm` and a project-root `server.php` for PHP's built-in server.

## 2026-10-10: Phase 0, Foundation

### Built
- **Laravel 13 + Filament 5 + Livewire 4** project with two panels: `/admin` (platform) and `/app/{restaurant}` (tenant workspace).
- **Multi-tenancy:** `Restaurant` tenant, `TenantContext`, `BelongsToRestaurant` (query scope, auto-fill, guard against cross-tenant moves), plus an architecture test that fails CI if any tenant model skips isolation.
- **Access model:** `PlatformPermission` (11) and `RestaurantPermission` (23) enums with Arabic/English labels and descriptions; `PlatformRole` for platform staff; `Position` with 7 built-in positions created for every restaurant; owner flag; gates for every permission.
- **Security:**
  - brute-force lockout per email and per IP, with hashed keys
  - security alerts with a bell notification to super admins
  - optional authenticator-app 2FA with recovery codes, encrypted at rest
  - Devices & sessions page (sign out one device or all others)
  - `platform:install` command, the only route to super admin
  - strict Eloquent in development, HTTPS and strong passwords in production
- **Localization:** Arabic (default, RTL) and English; remembered switcher; Western digits everywhere (Arabic-Indic input converted); translated validation messages and error pages; ar/en key-parity test.
- **Design system:** shared tokens (midnight ink, ice, ivory, deep teal, brass, sage, order-status semantics, radii, layered shadows, spring easings); self-hosted Geist / IBM Plex Sans Arabic / Fraunces / Alexandria; luxury Filament theme (floating midnight rail, brass active state, glass topbar, sign-in canvas with grain); bilingual landing page; branded error pages; brand mark and favicon; local initials avatars.
- **Dashboards with live data:**
  - Platform: live restaurants, team accounts, security alerts, locked sign-ins
  - Restaurant: your role, active team, positions, account status
  - Both greet the user by time of day in the restaurant's timezone
- **Quality:** 70 Pest tests, Larastan level 8 with 0 errors, Pint, GitHub Actions CI.
- **Docs:** README, ARCHITECTURE.

### Fixed during verification
- Filament JS assets were never published (an installer step timed out), so buttons and fields were inert. Fixed by publishing them and adding `filament:upgrade` to the Composer hook, plus a regression test.
- Arabic headline lines collided. Fixed with Arabic-specific line-height and sizes.
- Permission keys containing dots broke translation lookups. Fixed by reading the scope array directly.
- Freshly created users failed under strict mode because default attributes weren't loaded. Fixed with complete model defaults.
- 404 pages ignored the visitor's language. Fixed by running locale detection globally.
- Avatars called the third-party ui-avatars.com, leaking staff initials. Replaced with local SVG initials.
- Stats widgets flashed an empty card. Fixed by rendering them eagerly. Stats use 2 columns on phones.

## 2026-10-10: Planning
- Audited the reference repository `alsraiyagroupcom5/drive-thru-plus` (TanStack Start + React + Supabase, built in Lovable) and listed every built, partial and missing feature, plus 11 defects to avoid.
- Wrote `docs/MASTER_PROMPT.md` (full Laravel spec with improvements), `docs/ROADMAP.md` (12 phases with kickoff prompts) and `CLAUDE.md` (project rules).
