# Dhawq brand and hero slider: design

Date: 2026-11-10. Approved in chat the same day (logo concept A, the dhal tile).

## Goal

Two changes:
- The platform is renamed from "Drive-Thru Plus" (logo "Drive-Thru+") to **ذوق / Dhawq**, with a new logo, everywhere a restaurant, guest or lead sees it.
- The home page opens on a **three-slide hero** with live motion graphics, one slide per service: Drive-Thru, Order-Ready Call and Rewards.

## Decisions (from the brainstorm)

| Question | Decision |
|---|---|
| Where the slides go | The hero becomes the slider. The services carousel under the hero (`site.partials.showcase`) is removed so nothing repeats; its scenes and JavaScript are reused. |
| How motion is made | Live SVG, CSS and JS in the page. No video. Text stays real text in both languages. |
| Logo | Concept A, the dhal tile: the letter ذ in brass inside the deep-teal rounded tile; its dot doubles as the "order ready" light. |
| Colours | Unchanged (deep teal, brass, ivory). Only the name and logo change. |

Out of scope:
- The service called "Drive-Thru" keeps its name; it is the product, not the brand.
- Restaurant names (for example "Rabii Drive-Thru") are untouched.
- The domain, the GitHub repository name and PHP namespaces don't change.

## 1. The name in one place

**`App\Support\Brand`** (final class, static):
- `name(?string $locale = null): string` gives `ذوق` for `ar` and `Dhawq` otherwise (current locale by default).
- `full(): string` gives `ذوق · Dhawq`, for places that show both languages at once: invoices, QR posters, emails.
- `legal(): string` gives `Dhawq`, the invoice legal name, used when `PlatformSettings` `billing.legal_name` is still the old default.

**Replacements:**
- Every customer, restaurant or lead-visible `config('app.name')` in views and code goes through `Brand`: page titles, the Filament `brandName`, the "powered by" footer, the QR poster foot, invoice PDF lines, JSON-LD, manifest and meta tags.
- Uses that are internal only (log channels, cache prefixes) stay as they are.
- `lang/{ar,en}/*` lines naming the platform use a `:brand` placeholder filled from `Brand::name()`. These are in access, billing, clients, developers, install, leads and site. Arabic lines read naturally with «ذوق».
- `PlatformSettings` default `billing.legal_name` becomes `Dhawq`. A data migration updates the stored value only when it still equals `Drive-Thru Plus`.
- `public/sw.js` notification fallback title is `Dhawq`.
- The webhook test payload message reads "Dhawq is connected."
- Code comments naming the product are updated.
- `.env.example` sets `APP_NAME=Dhawq`; production's `APP_NAME` is set on deploy.

**Guard:** `BrandNameTest` fails if `Drive-Thru Plus`, `Drive-Thru+`, `Drive⁠-⁠Thru Plus` or `درايف ثرو بلس` appears in `app/`, `lang/`, `resources/`, `config/` or `public/` (built assets and vendor excluded).

## 2. Logo

**Mark** (`resources/views/components/brand/mark.blade.php`, 40×40 viewBox):
- a rounded tile (rx 12) with today's deep-teal gradient (`#1E5D57` to `#082423`) and the 12% white hairline;
- the letter ذ drawn as one brass stroke (`#D5B582`, round caps and joins), shaped from a naskh dhal: a short entry stroke curving down into the bowl, then a flat base to the left;
- its dot as a brass circle that softly glows when `animated` is set: a CSS pulse on the site hero and the install success page only, and none under reduced motion;
- the gradient id is unique per render (`Str::random`), so two marks on one page don't share a definition.

**Wordmark** (`x-brand.logo`):
- the mark plus `ذوق` in Alexandria (weight 700) and `DHAWQ` in Fraunces small caps with 0.18em tracking;
- Arabic pages lead with ذوق, English pages lead with DHAWQ, and the other script sits beside it, smaller and muted;
- it keeps the `subtitle` prop (the panels use "Platform" / "Restaurant").

**Static assets**, generated from one master SVG by a script so they never drift from the component:
- `public/favicon.svg`;
- `public/favicon.ico` (16, 32, 48);
- `public/apple-touch-icon.png` (180);
- `public/icons/icon-192.png`, `icon-512.png` and `maskable-512.png`;
- `public/images/site/og.png` (1200×630): the wordmark on ivory with the three service names.

The platform's PWA and meta tags point to these. Restaurant manifests keep using the restaurant's own logo.

**PDFs:** the invoice and QR posters use an inline brass-and-teal SVG mark plus `Brand::full()`. mPDF renders inline SVG paths.

**Legibility:** the mark must stay readable at 16 px (favicon), on dark surfaces (the admin sidebar, ops screens) and in one colour (black on white in PDFs). A `monochrome` prop draws it in `currentColor`.

## 3. Hero slider

**Structure:** `site.partials.hero` replaces the static hero and the showcase in `home.blade.php`. It has:
- three `<article>` slides in a `role="region"` carousel, with `aria-roledescription="carousel"` and `"slide"`, and labelled tabs;
- per slide:
  - an eyebrow (the service's icon and name);
  - a display headline;
  - a one-line pitch;
  - a primary button ("Start free for N days", going to the request form with the service preselected) and a secondary button (the service page);
  - three proof points;
  - the scene.
- New copy lives in `lang/{ar,en}/site.php` under `hero.slides.{drive_thru,ready_call,rewards}`. The old `site.hero` and `site.showcase` keys that are no longer used are removed.

**Scenes:** the existing `site.partials.scenes.*`, enlarged to hero size and choreographed in three beats, each about 0.6–0.9 s with spring easing:

| Slide | Beat 1 | Beat 2 | Beat 3 |
|---|---|---|---|
| Drive-Thru | A car glides along a curved road toward the window | The order ticket drops in and the items tick on | A "Ready" pulse and the window light |
| Order-Ready Call | The phone rises into frame | Pulse rings radiate and the number flips to 27 | "Your order is ready" fades up and the phone buzzes |
| Rewards | The stamp card slides in | Stamps land one by one with a press bounce | The free reward pops with a brass shimmer |

Motion rules:
- animate only `transform` and `opacity`, plus `stroke-dashoffset` for the road;
- `will-change` only while a slide is active;
- the headline and pitch enter with a short staggered fade-up, timed to the scene.

**Behaviour** (`resources/js/hero.js`, Alpine `heroSlider(count)`, built from `showcase.js`):
- **Autoplay:** 7 s per slide, with the active tab's progress line filling.
- **Pauses** on hover, on focus inside, when the tab is hidden (`visibilitychange`), and via the pause button. The user's pause sticks.
- **Navigation:** tabs, Left and Right keys (mirrored in RTL), swipe (pointer events, pan-y kept), and Home/End.
- **Reduced motion:** no autoplay and no scene motion. Scenes show their final frame and change only on tap.
- **Server render:** slide 1 is rendered visible on the server, so there's no layout shift or empty hero before JavaScript. Other slides are `inert` and hidden from assistive tech until active.
- **Layout:**
  - desktop: text on the left (right in Arabic), scene on the right, `min-h-[calc(100dvh-6rem)]`;
  - phone: the scene goes above the text, the tabs become three compact pills, and the 16 px side gutter is kept.

## 4. Error, empty and edge states
- If JavaScript is unavailable, slide 1 shows fully and the tabs are links to `#hero-{key}` anchors that show each slide (`:target`).
- A service that isn't for sale (from `Service::forSale()`) still gets a slide, but its primary button goes to the contact form instead of the trial.
- Arabic headlines are written for Arabic, not translated word for word, with line breaks kept natural.

## 5. Testing

**Pest:**
- `Brand::name()` per locale and `Brand::full()`.
- The guard test finds no old name.
- The home page:
  - in English shows `Dhawq` in the title and logo and three hero slides with their headlines;
  - in Arabic shows `ذوق` and the Arabic headlines;
  - no longer renders the old showcase section;
  - JSON-LD `name` is `Dhawq`.
- The admin and workspace panels show the new brand name.
- The invoice PDF and QR poster contain `Dhawq`.
- The invitation and lead emails and WhatsApp texts use the brand placeholder.
- `PlatformSettings` legal name migration: the old default is replaced, a custom value is kept.

**Browser (headless):**
- The home hero in Arabic and English, light and dark, at 375, 768 and 1280 px:
  - autoplay advances;
  - pause works;
  - keyboard arrows (mirrored in RTL) and swipe work;
  - with reduced motion there is no autoplay;
  - no sideways scrolling and no console errors.
- The favicon at 16 px, the admin and workspace headers, and the invoice PDF looked at as an image.

## Delivery order (for the plan)
1. `Brand` helper, the guard test, and replacing names in code, language files, settings and the service worker.
2. Logo: the mark and wordmark components, generated static assets (favicon, icons, OG image), and PDFs.
3. Hero slider: copy, partial, scenes, `hero.js`, and removing the old showcase.
4. Browser checks, docs (CHANGELOG, ARCHITECTURE §7 design system), deploy with `APP_NAME=Dhawq`.
