# WhatsApp message templates

Meta only delivers messages we start (codes, order updates, staff alerts) when they use an approved template. Create these five templates in **WhatsApp Manager → Message templates**, each in **Arabic (`ar`)** and **English (`en`)**, with exactly these names. The number of `{{n}}` variables must match, because the app fills them in this order.

If Meta asks for different names, put yours in `.env` (`WHATSAPP_TEMPLATE_OTP`, `WHATSAPP_TEMPLATE_RECEIVED`, `WHATSAPP_TEMPLATE_READY`, `WHATSAPP_TEMPLATE_RECEIPT`, `WHATSAPP_TEMPLATE_ALERT`, `WHATSAPP_TEMPLATE_PAGER_READY`, `WHATSAPP_TEMPLATE_STAMPS`).

| Name | Category | Variables (in order) |
|---|---|---|
| `dtp_login_code` | Authentication | 1. the code (plus the copy-code button) |
| `dtp_order_received` | Utility | 1. restaurant name · 2. order number · 3. ready time (HH:MM) |
| `dtp_order_ready` | Utility | 1. order number · 2. branch name |
| `dtp_order_receipt` | Utility | 1. order number · 2. total with currency · 3. receipt link |
| `dtp_staff_alert` | Utility | 1. alert title · 2. alert details |
| `dtp_pager_ready` | Utility | 1. pager number · 2. branch name (Order-Ready Call) |
| `dtp_stamp_card` | Utility | 1. stamps added · 2. card name · 3. stamps to go (Rewards) |

## Suggested wording

### `dtp_login_code` (Authentication)
Meta writes the body for authentication templates. Choose **Copy code** as the button. The app sends the code as the body variable and as the button parameter.

### `dtp_order_received`
- **en:** `{{1}} received your order {{2}}. It will be ready around {{3}}. We'll message you when it's ready.`
- **ar:** `استلم {{1}} طلبك {{2}}، وسيكون جاهزًا حوالي {{3}}. سنرسل لك رسالة عندما يجهز.`

### `dtp_order_ready`
- **en:** `Your order {{1}} is ready at {{2}}. Drive up to the window and show your pickup code.`
- **ar:** `طلبك {{1}} جاهز في {{2}}. تفضّل إلى النافذة واعرض رمز الاستلام.`

### `dtp_order_receipt`
- **en:** `Thank you! Order {{1}}, total {{2}}. Your receipt: {{3}}`
- **ar:** `شكرًا لك! الطلب {{1}}، الإجمالي {{2}}. إيصالك: {{3}}`

### `dtp_staff_alert`
- **en:** `{{1}}: {{2}}`
- **ar:** `{{1}}: {{2}}`

### `dtp_pager_ready` (Order-Ready Call)
Sent once, when the counter taps "Ready" on a ticket that has a phone number. The guest's pager page rings at the same time.
- **en:** `Your order {{1}} is ready at {{2}}. Please come to the counter.`
- **ar:** `طلبك رقم {{1}} جاهز في {{2}}. تفضّل إلى الكاونتر.`

### `dtp_stamp_card` (Rewards)
Sent when the counter adds stamps for a guest who paid at the till.
- **en:** `+{{1}} on your {{2}} card. {{3}} to go for your free reward.`
- **ar:** `+{{1}} على بطاقة {{2}}. تبقّى {{3}} للحصول على مكافأتك المجانية.`

## Webhook (delivery and read receipts)

In **Meta App → WhatsApp → Configuration**:

- **Callback URL:** `https://<your domain>/webhooks/whatsapp`
- **Verify token:** the value of `WHATSAPP_VERIFY_TOKEN` in `.env`
- **Subscribe to:** `messages`

The app checks every callback with `X-Hub-Signature-256` using `WHATSAPP_APP_SECRET`.

## SMS fallback (SMSala)

When WhatsApp can't deliver (no WhatsApp on the number, or the template isn't approved), the same text goes by SMS through SMSala. Set `SMSALA_API_TOKEN` and `SMSALA_SENDER_ID` (a sender name approved by SMSala for Qatar). Delivery reports come back to a signed URL; nothing needs configuring on the SMSala side.
