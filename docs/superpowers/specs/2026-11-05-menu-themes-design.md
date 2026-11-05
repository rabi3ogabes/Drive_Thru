# Menu themes: design

Date: 2026-11-05. Approved in chat the same day.

## Goal

Restaurants can give every guest page a look of their own, without breaking readability or the luxury feel:

- the super admin curates a library of theme templates;
- an owner picks a template, then changes its colours, logo, fonts, corners and cover;
- themes travel as a small, safe theme file (export, import, admin upload);
- owners can upload their own brand font.

## Decisions (from the brainstorm)

| Question | Decision |
|---|---|
| What "upload" covers | All four: theme file export/import, admin adds templates by file, custom font file (WOFF2), cover/background image. |
| Who controls themes | The super admin curates the library; owners pick and customise for their restaurant. |
| Where a theme applies | Every guest page: home, branch menu, checkout, order page, orders, rewards, account, sign-in, invite, pager, unsubscribe. |
| Colour control | 4 key colours: brand, accent, background, text. Every shade is generated. A contrast check blocks unreadable combinations. |
| Scope | One theme per restaurant; every branch shares it. |
| Dark | Each template is light or dark by design. Guests always see the chosen look; it doesn't follow the phone. |

Out of scope:
- per-branch themes;
- raw CSS or HTML from anyone;
- theming the workspace, the admin panel, ops screens and QR posters (posters keep using the brand colour, as today).

## How a theme reaches guest pages

The guest views draw their colours from Tailwind tokens, not fixed values. Counted in the guest views:
- `ink` (text, about 515 uses);
- `ivory` (background, about 57);
- `brass` (accent, about 47);
- `--brand` (about 128).

The guest layout (`components/layouts/customer.blade.php`) already prints `:root { --brand: … }`.

A theme overrides these tokens in that same `<style>` block. No guest view changes are needed for colours:
- `--color-ink-50…950`, built from the text colour;
- `--color-ivory-50…300`, built from the background colour;
- `--color-brass-50…900`, built from the accent colour;
- `--brand` plus `--brand-contrast`. The brand colour is used as-is; its contrast colour is white or the theme's text colour, whichever reads better.
- `--font-sans`, `--font-display` and `--font-display-ar` from the chosen fonts;
- `--radius-card`, `--radius-control` from the corner style. A small set of guest components switch from fixed `rounded-*` classes to these variables.

For dark templates, `ink` runs the other way: 50 is the darkest surface tint and 950 the lightest text. That way, `text-ink-900` stays the main text colour and `bg-ivory-50` stays the page background in both modes. The layout's `<meta name="color-scheme">` and `theme-color` follow the mode.

Shades are computed on the server in OKLCH:
- keep the hue and chroma of the chosen colour;
- step the lightness along a fixed curve;
- clamp chroma to the sRGB gamut.

This is deterministic and unit-tested. The resulting CSS block is cached per restaurant, and the cache is cleared on every theme save. CSS values come only from validated data, never from free text:
- hex colours, re-serialised by the palette builder;
- font family names from the catalogue, or a generated name for uploaded fonts.

## What a theme holds

A `ThemeSpec` value object, used by templates, restaurant overrides and theme files alike:

| Field | Values |
|---|---|
| `mode` | `light` / `dark` |
| `colors.brand` / `colors.accent` / `colors.background` / `colors.text` | `#rrggbb` |
| `fonts.heading` / `fonts.body` | a font key from the catalogue, or `brand:<id>` for an uploaded font |
| `corners` | `soft` / `rounded` / `sharp` |
| `layout` | the existing `MenuLayout` fields (card style, image style, show descriptions/calories/highlights, popular first) |
| `cover` | optional image path (templates: a bundled image; restaurants: their upload) |

Contrast rules (WCAG 2.2, computed on the generated tokens):
- text on background must be at least 4.5:1, or the save is refused with the measured ratio and a suggestion;
- muted text (ink-500 on background) must be at least 3:1, or the theme can't be saved;
- the button text colour on brand is chosen automatically (the better of white and the theme's text colour). If neither reaches 4.5:1, the owner sees a warning, but can still save.

## Fonts

**Curated catalogue (self-hosted, `@fontsource`, no third-party requests).** Each entry has a key, a family name, the scripts it covers, a role (heading/body/both) and its WOFF2 files:

- Latin:
  - Geist (today's body);
  - Fraunces (today's display);
  - Playfair Display;
  - Cormorant Garamond;
  - DM Serif Display;
  - Manrope;
  - Plus Jakarta Sans;
  - Outfit.
- Arabic:
  - IBM Plex Sans Arabic (today's body);
  - Alexandria (today's display);
  - Tajawal;
  - Cairo;
  - Readex Pro;
  - Noto Kufi Arabic;
  - El Messiri;
  - Amiri.

The owner chooses a heading pair (Latin + Arabic) and a body pair (Latin + Arabic). Each template ships with sensible pairs.

The catalogue's files are copied at build time into `public/fonts/guest/` and declared in a generated `fonts-guest.css` with `font-display: swap`. A guest page loads only the faces the restaurant actually uses, plus a `<link rel="preload">` for the body face.

**Uploaded brand font** (tenant model `BrandFont`, `BelongsToRestaurant`):
- upload rules:
  - WOFF2 only, max 1 MB;
  - the file must start with the WOFF2 signature `wOF2`; the extension and MIME type alone are not trusted;
  - stored under `storage/app/public/brand-fonts/{restaurant}/{uuid}.woff2` with a random name;
- the owner gives it a display name and says which scripts it is for (Latin, Arabic or both);
- the other script falls back to the template's catalogue font for that role;
- the generated family name is `brand-{id}`, so nothing the owner types reaches the CSS;
- deleting a font that the theme uses is refused, with a message naming the role (heading or body) that uses it;
- limit: 4 brand fonts per restaurant.

## Template library (platform)

**Model `MenuTheme`** (platform-level, not a tenant model), table `menu_themes`:
- `key` (unique slug);
- `name` and `description` (ar/en JSON);
- `spec` (JSON `ThemeSpec`);
- `cover_path`;
- `is_built_in`, `is_active`, `is_default`;
- `sort`;
- timestamps.

Exactly one active template is the default. Setting another one moves it.

**Built-in templates.** `ThemeCatalog::install()` installs them, like `PlanCatalog::install()`. It runs from a data migration and from `platform:install`. It adds missing templates and never overwrites admin edits.

| Key | Mode | Feel |
|---|---|---|
| `ivory-teal` (default) | light | Today's look: ivory, deep teal, brass; Fraunces/Alexandria headings, Geist/IBM Plex bodies. |
| `midnight-brass` | dark | Near-black surfaces, brass accent, Cormorant/El Messiri headings. |
| `desert-sand` | light | Warm sand and terracotta, Playfair/Amiri. |
| `olive-grove` | light | Olive green, cream, Manrope/Readex Pro. |
| `rose-cafe` | light | Soft rose, cocoa text, DM Serif/Tajawal. |
| `mono-bold` | light | Black, white and one strong red; sharp corners; Outfit/Cairo. |

Every built-in template passes the contrast rules (a test checks all of them).

**Admin screen: `/admin` → Menu themes** (`MenuThemeResource`, new permission `PlatformPermission::ManageThemes = 'themes.manage'` with ar/en label and description):
- list: preview swatch, name, mode, status, default flag, number of restaurants using it;
- create: from scratch or "Duplicate"; edit: the same editor as owners (colours, fonts from the catalogue, corners, layout, cover);
- "Upload theme file" creates a new template from a `.dtheme.json` file, shown in a preview before it is saved;
- "Set as default", "Hide" / "Show";
- delete only templates no restaurant uses and that aren't built in. Others can only be hidden; restaurants on a hidden template keep it, but new picks can't choose it.

## Owner screen

Menu → Menu design gets two tabs:
- **Theme** (new, first);
- **Layout** (today's form).

Permission: `ManageMenu`, as today.

**Theme tab:**
1. **Gallery:**
   - cards for every active template, each with a mini rendering (cover, colours and the font names written in their own font);
   - the current template is marked;
   - "Use this theme" asks for confirmation when you have custom changes, because they would be replaced.
2. **Customise:**
   - **Colours:** four colour pickers, each with a hex field. Next to each pair, a live contrast badge: "Easy to read" (at least 4.5:1), "Large text only" (at least 3:1) or "Hard to read".
   - **Fonts:** heading and body pickers, each with its Latin and Arabic choice and a sample line. "Upload a font" adds a brand font.
   - **Logo:** the restaurant logo, the same one as Restaurant settings, replaceable here.
   - **Cover image:** upload, replace or remove. Re-encoded to WebP at 2 sizes (like product photos), max 5 MB, JPEG/PNG/WebP in. The template's cover is used when none is set.
   - **Corners:** Soft, Rounded or Sharp.
3. **Live preview:**
   - a phone frame showing the real branch menu with the unsaved theme;
   - it refreshes 400 ms after the last change;
   - on screens narrower than 1024 px, it moves under the form behind a "Preview" button that opens it full screen.
4. **Actions:**
   - "Save theme": validates, runs the contrast rules, stores it, clears the cache, records an audit entry;
   - "Reset to template": drops your changes, with a confirmation;
   - "Export theme file" downloads `{restaurant}-theme.dtheme.json`;
   - "Import theme file": validates, shows the preview, then "Apply" fills the form; you still save it.
   - Unsaved changes trigger a "Leave without saving?" prompt.

**Restaurant data:** the restaurant's theme lives in a new `restaurants.menu_theme` JSON column:
- `template` (key);
- `overrides` (a partial `ThemeSpec`);
- `cover_path`;
- `updated_at`.

`ThemeResolver::for(Restaurant)` merges the template's spec with the overrides. A missing or hidden template falls back to its stored spec, then to the default template. `menu_layout` keeps working: the layout fields move into the theme, `MenuLayout::for()` reads them from the resolved theme, and a data migration copies each restaurant's current `menu_layout` and `accent_color` into its overrides, so nothing changes visually for existing restaurants.

**Brand colour in Restaurant settings:** the brand colour field moves to the Theme tab. Restaurant settings shows the current colour with a "Change in Menu design → Theme" link, so there is one place to edit it.

## Live preview mechanics

- The preview iframe loads the real branch menu at `/{restaurant}/{branch}?theme_preview={token}`.
- The token is a random 40-character key. The draft `ThemeSpec` is stored in the cache for 30 minutes under that key, bound to the restaurant and the user who made it.
- The guest layout uses the draft only when the token matches the page's restaurant and the request comes from that signed-in workspace user. Anyone else gets the saved theme.
- The preview page:
  - sends `X-Robots-Tag: noindex` and `Cache-Control: no-store`;
  - shows a slim "Preview" ribbon;
  - doesn't let the preview place an order: the checkout button is disabled, with an explanation.

## Theme file (`.dtheme.json`)

```json
{
  "format": "drive-thru-theme",
  "version": 1,
  "name": { "en": "Olive Grove", "ar": "بستان الزيتون" },
  "base": "olive-grove",
  "spec": {
    "mode": "light",
    "colors": { "brand": "#4b5d2a", "accent": "#b08a3e", "background": "#f8f6ef", "text": "#1d2316" },
    "fonts": { "heading": "playfair-display+amiri", "body": "manrope+readex-pro" },
    "corners": "rounded",
    "layout": { "card_style": "grid", "image_style": "square", "show_descriptions": true, "show_calories": false, "show_highlights": true, "popular_first": true }
  }
}
```

The `ThemeFile` parser enforces:
- max 20 KB, valid UTF-8 JSON, `format` and `version` exactly as above;
- colours match `^#[0-9a-fA-F]{6}$`;
- font keys exist in the catalogue. `brand:` fonts are dropped on import into another restaurant, with a notice, and the template font is used;
- enums are checked; names are at most 60 characters, stripped of tags;
- unknown fields are ignored; nothing else is read. Files never contain images or font data.

The contrast rules apply on import too.

Errors are specific and translated, for example:
- "This isn't a theme file";
- "This theme file is from a newer version";
- "The text colour is hard to read on the background (2.1:1)".

## Security

- **No raw CSS or HTML:** no input reaches the page except through validated values (see the CSS section). The `<style>` block is built from a fixed template.
- **Uploads:**
  - fonts are checked by their signature and size;
  - images are re-encoded by the existing image pipeline, which strips metadata;
  - theme files are parsed, never executed;
  - all uploads are rate-limited, 20 per hour per user.
- **Tenancy:** `BrandFont` uses `BelongsToRestaurant`. Tests show that one restaurant can't list, use, preview or delete another's fonts, or reach another's preview token.
- **Policies:**
  - `MenuThemePolicy` (platform, `ManageThemes`);
  - owners' actions go through `ManageMenu`;
  - `BrandFontPolicy` (one policy per model).
- **Audit entries:**
  - `theme.saved`, `theme.reset`, `theme.imported`;
  - `brand_font.uploaded` / `brand_font.deleted`;
  - `menu_theme.created` / `menu_theme.updated` / `menu_theme.default_changed` / `menu_theme.hidden`.

## Performance

- The resolved theme CSS is cached per restaurant (key bumped on save, template edit or font change). There are no extra queries per guest page.
- Only the fonts the restaurant uses are loaded; the body face is preloaded.
- Cover images are served as WebP with sizes.
- The admin "used by" count is one grouped query.

## Localisation and UX

- All labels, hints, errors and template names are in `lang/{ar,en}/themes.php`.
- Arabic sample text in font pickers: «قائمة اليوم»; English: "Today's menu".
- Every list and form has loading, empty, error and success states:
  - empty gallery (no active templates): "No themes are available yet; your current look stays";
  - preview loading shimmer;
  - upload progress;
  - save toast.
- The workspace guide (`guide.php`) updates the `menu_design` article with the Theme tab's buttons. The admin library needs no guide article, because the guide covers the workspace only.

## Testing (Pest)

- **Palette:**
  - shade generation is deterministic;
  - shades stay in gamut;
  - dark mode inverts ink;
  - contrast ratios match known WCAG values.
- **Contrast rules:**
  - unreadable themes are refused;
  - the button text colour is chosen correctly;
  - every built-in template passes.
- **`ThemeResolver`:**
  - template plus overrides;
  - hidden or missing template fallback;
  - the migration keeps today's look (colour and layout).
- **`ThemeFile`:**
  - a valid round trip (export, then import, gives the same spec);
  - rejects: wrong format, newer version, oversize, bad colour, unknown font, `<script>` in a name, CSS-like payloads in colours;
  - `brand:` fonts dropped across restaurants.
- **Brand fonts:**
  - signature check (a renamed TTF/PNG is refused);
  - size limit;
  - 4-font limit;
  - deleting a font in use is refused;
  - cross-tenant isolation.
- **Guest layout:**
  - the CSS variables of the resolved theme are present on every guest page type;
  - only the chosen fonts are linked;
  - no request goes to a third party.
- **Preview:**
  - the draft is applied only for its own user and restaurant;
  - other users get the saved theme;
  - preview pages aren't cached or indexed;
  - checkout is disabled.
- **Owner page:**
  - pick a template (with the confirmation when there are custom changes);
  - customise and save;
  - reset;
  - export and import;
  - permission checks (a cashier can't open it).
- **Admin resource:**
  - create, duplicate, upload file, set default (exactly one), hide;
  - deleting is refused for templates in use or built in;
  - "used by" counts.
- **Guide:** the completeness and parity tests stay green.

Browser checks:
- the owner Theme tab, the admin library and guest pages (home, menu, checkout, order, rewards);
- in Arabic and English, with a light and a dark template, at 375, 768 and 1280 px;
- no sideways scrolling, no console errors.

## Delivery order (for the plan)

1. Theme core: `ThemeSpec`, palette and contrast, font catalogue, `ThemeResolver`, `ThemeFile`; `menu_themes` table, built-in templates and the data migration.
2. Guest layout: the CSS variables, font loading and corner variables applied to guest pages, with the preview token support.
3. Owner Theme tab: gallery, customise, live preview, save/reset/export/import, brand fonts, cover image.
4. Admin Menu themes resource and permission.
5. Guide text, docs, browser checks, deploy.
