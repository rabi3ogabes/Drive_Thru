# Phase 7B: CRM, Campaigns, QR Campaigns & Referral — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Owners see who their guests are, group them automatically, send offers only to guests who said yes, measure what QR codes and campaigns bring in, and grow through referrals.

**Architecture:** A new `Domain/Crm` holds guest stats, nightly segments and consent. `Domain/Campaigns` holds campaigns, the throttled send queue, unsubscribe handling and attribution. `Domain/QrCampaigns` holds tracked short links, scans and the funnel. `Domain/Referrals` holds codes, the pending-to-rewarded flow and fraud checks. Messages reuse `Messaging\Messenger` (WhatsApp → SMS) and `WebPushSender`; rewards reuse `Loyalty\Actions\GrantReward`.

**Tech Stack:** Laravel 13, Filament 5, Livewire 4, Pest 5; mPDF and the existing `QrCodes` for posters.

**Spec:** `docs/MASTER_PROMPT.md` §3.5 (CRM segments, campaigns, QR campaigns, referral), `docs/ROADMAP.md` Phase 7. User decisions (2026-10-12): campaigns only with **explicit opt-in**; Phase 7 split into milestones.

## Global Constraints

- Same as 7A: tenancy trait and `$attributes` defaults on every new model; bilingual strings; Western digits; design tokens; audits; no N+1; minor units.
- Plan gates: campaigns and QR campaigns need `PlanFeature::Campaigns`; referral needs `PlanFeature::Loyalty`. Staff need `ViewCustomers` (list and profile), `ViewCustomerContacts` (to see phone numbers) and `ManageCampaigns` (campaigns, QR campaigns, referral settings).
- Phone numbers stay masked unless the viewer has `ViewCustomerContacts`.
- Marketing goes **only** to guests with `offers` consent. Consent records the moment, the source (checkout, account or QR page) and the wording version; withdrawing it is one tap and takes effect immediately.
- Every marketing message carries an unsubscribe link (signed, no sign-in needed). A WhatsApp reply of STOP or إيقاف also withdraws consent.
- Frequency cap per guest: at most 1 campaign message per 24 hours and 3 per 7 days. Quiet hours hold messages back until they end.

## Review Focus

1. **A guest who opted out after a campaign was scheduled** must not receive it. Consent is checked at send time, not at scheduling. Task 4.
2. **Two campaigns aimed at the same guest on the same day:** the frequency cap is enforced across campaigns, under a lock. Task 4.
3. **A QR code scanned by a bot or the same phone 50 times:** scans are deduplicated per device per 30 minutes, and known bots are ignored. Task 6.
4. **Self-referral** (same device, same car plate, the referrer's own number): no reward, and the reason is recorded. Task 7.
5. **Segments for a restaurant with no orders yet:** nothing crashes, and every segment is empty. Task 2.

---

### Task 1: Guest stats and consent

- Migration:
  - `customers` gets `first_order_at`, `last_order_at`, `orders_90d`, `spent_90d_minor`, `avg_order_minor`, `offers_consent_at`, `offers_consent_source`, `offers_withdrawn_at`.
  - New table `customer_segments` (restaurant_id, customer_id, segment, product_id?, computed_at), unique per (customer, segment, product).
- `Crm\GuestStats::refresh(Customer)`: called after pick-up and cancellation (listener) and from the nightly job; counts only picked-up orders.
- `Crm\Consent::give(Customer, string $source)` and `withdraw(Customer, string $source)` keep `notification_prefs.offers` in sync and write an audit entry.
- Checkout gets an "Send me offers" checkbox (unticked) and the account page a switch. Both record the source.
- **Tests:**
  - stats ignore cancelled orders;
  - consent writes the timestamp, the source and the preference;
  - withdrawing clears it;
  - the checkout checkbox defaults off.

### Task 2: Nightly segments

- `Crm\Segments::compute(Restaurant)`. Default rules; thresholds editable per restaurant under Settings → Guests:
  - **New:** first order in the last 30 days.
  - **Regular:** 3 or more orders in 90 days.
  - **VIP:** the top loyalty tier, or the top 5% by 90-day spend.
  - **High spender:** average order at least twice the restaurant's average.
  - **At risk:** 2 or more orders before, none in the last 30–60 days.
  - **Inactive:** no order in more than 60 days.
  - **Frequent buyer of X:** 3 or more orders containing product X in 90 days. Computed for the restaurant's top 20 products only.
- `crm:segments` runs nightly at 04:00 Doha. It recomputes in one pass per restaurant (chunked queries, no per-guest queries) and replaces the rows atomically.
- **Tests:**
  - each rule with fixture guests;
  - an empty restaurant gives empty segments;
  - a guest leaves a segment when they stop qualifying;
  - a property test: a guest is never both New and Inactive.

### Task 3: CRM screens

- `Filament/App/Resources/Customers/CustomerResource` (group "Loyalty & offers", or a new "Guests" group):
  - the list has search, segment and tier filters, and columns for orders, spend, last order, points and consent;
  - the profile page shows orders, revenue, AOV, favorites, last order, points, tier, segments, campaigns received and attributed orders;
  - actions: adjust points (reuses 7A), grant a reward, withdraw consent on the guest's request.
- CSV export for `ExportReports` (consent-respecting: phone numbers only with `ViewCustomerContacts`).
- **Tests:**
  - a cashier is refused;
  - phones are masked without the contacts permission;
  - cross-tenant isolation;
  - the profile has no N+1 (query count assertion).

### Task 4: Campaigns

- Models:
  - `Campaign`: name, segment(s) or "all opted-in", channel (whatsapp | sms | push), WhatsApp template name and parameters, SMS/push text in both languages, an optional reward or coupon, scheduled_at, status, counts.
  - `CampaignMessage`: per guest, with status, delivery id and attribution.
- `Campaigns\SendCampaign` (queued, scheduled): each guest is resolved **at send time**; consent, the frequency cap (locked per guest), quiet hours and the restaurant's daily limit are checked; sending is throttled (30 per minute per restaurant).
  - It goes through `Messenger` with a new purpose `campaign`; WhatsApp needs an approved template, otherwise SMS.
  - Every message includes the signed unsubscribe link `/{restaurant}/unsubscribe/{token}`.
- The WhatsApp webhook treats STOP and إيقاف replies as unsubscribe.
- Attribution: an order picked up within 7 days of receiving the message, or carrying `source.campaign`, is attributed (last touch).
- Owner UI: a resource with a composer, an audience preview (count, with a sample of masked guests), test send to the owner's own phone, schedule and cancel, and a results card (sent, delivered, failed, orders, revenue, conversion).
- **Tests:**
  - an opted-out guest is skipped even if they opted out after scheduling;
  - the cap holds across two campaigns;
  - quiet hours delay sending;
  - the unsubscribe link works without sign-in and is audited;
  - STOP via the webhook;
  - attribution by time and by link;
  - Arabic and English messages per guest locale;
  - HTTP is faked for the providers.

### Task 5: Docs for WhatsApp marketing

- Add the campaign template guidance (Marketing category, opt-out button) to `docs/WHATSAPP_TEMPLATES.md`.

### Task 6: QR campaigns

- Models:
  - `QrCampaign`: name, branch?, lane?, channel label, coupon?, slug code, active dates.
  - `QrScan`: campaign, scanned_at, device hash (salted IP plus user agent), is_bot.
- Route `/q/{code}` records the scan, starts the visit (session `source`), and redirects to the branch menu (or the restaurant page) with the source parameters. It is rate-limited.
- Funnel: scans (unique devices) → visits (sessions that reached the menu) → orders (`source.campaign`) → revenue → conversion rate; per day, with a chart (dataviz skill).
- Posters: A4, A5 and sticker (5×5 cm) PDFs, bilingual, with the restaurant's brand, extending `QrCodes::poster`. The existing Links & QR center gets a "Tracked QR codes" tab.
- **Tests:**
  - a scan is recorded and the redirect carries the source;
  - repeat scans from the same device within 30 minutes count once;
  - bots are ignored;
  - an order attributed through `/q` shows in the funnel;
  - poster PDFs render (`%PDF`) in AR and EN;
  - inactive or expired codes go to the restaurant page without recording a scan.

### Task 7: Referral

- Settings: on or off, the reward for the referrer, the reward for the friend (both from the 7A catalog), and a monthly cap per referrer (default 10).
- Each guest gets a code; links are `/{restaurant}/invite/{code}`. A friend who signs up through it is recorded as `Referral(pending)`.
- After the friend's first **picked-up** order: fraud checks, then `GrantReward` to both (source `referral`) and `Referral(rewarded)`.
- Fraud checks:
  - same device cookie;
  - same car plate;
  - a friend who already had an account;
  - the referrer referring themselves;
  - the referrer over the monthly cap.

  A failed check sets `Referral(rejected, reason)`, visible to the owner.
- Guest UI: a "Invite friends" card on the Rewards page with share (Web Share API, copy) and the count of friends who joined.
- **Tests:**
  - the happy path rewards both once;
  - a cancelled first order rewards nobody;
  - each fraud rule;
  - the cap;
  - cross-tenant: codes are restaurant-scoped.

### Task 8: Quality gate, browser verification, docs

- [ ] Full suite, Pint, Larastan 0, `npm run build`, `composer warm`.
- [ ] Demo data for Masar: segments computed, one sent campaign with attribution, one QR campaign with scans and orders, two referrals.
- [ ] Browser checks in AR/EN, light/dark, at 375/768/1280:
  - the CRM list and profile;
  - the campaign composer and its results;
  - the QR funnel;
  - a poster;
  - the invite card;
  - the unsubscribe page.
- [ ] Tick ROADMAP, update CHANGELOG, add ARCHITECTURE §4.8, update CLAUDE.md gotchas.

## Milestone 7C (after 7B)

- Post-pickup rating with tags (a low rating raises an alert).
- Support tickets with WhatsApp replies and an optional refund.
- Birthday reward automation.
- Gamification, off by default.
