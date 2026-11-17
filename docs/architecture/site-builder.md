# The academy's page builder (Site module)

Status: spec, ready to build. Branch `feature/site-page-builder`, from `84aca260`.
Readers: the server engineer (apps/api, packages/openapi, `apps/web/src/lib/api/schema.ts`) and the portal engineer (the rest of apps/web). Each part says who owns it. The two build in parallel. The contract between them is section 3 (the JSON) and section 8.3 (the TypeScript types the portal writes by hand).

This document adds to `docs/architecture/02`, section 24 («صفحة الأكاديمية»). When the work lands, the server engineer updates section 24 (in Arabic) and the Site line in `CLAUDE.md`.

---

## 0. Why, and what changes

Today the academy's public page (`apps/web/src/components/site/academy-page.tsx`, data from `GET v1/site`, settings at `/admin/settings/site`) has one fixed design. It opens with phone mock-ups of a children's app («متابعة يوسف», «حفظ ممتاز», an island). The sections come in a fixed order with fixed headings, and the academy can only edit a few texts and switch eight sections on or off. Many academies on the platform sell online courses and adult training. For them that page is wrong.

After this work:

- The page is an **ordered list of blocks** that the academy builds in `/admin`. It can add, duplicate, hide, delete and reorder blocks. Any block can go, the hero included.
- Each block has a **type**, a **variant** (its layout), **style options** (tone, alignment, spacing, width) and **content** in Arabic and English.
- The academy uploads its own **images and videos** to a media library, and blocks point at them.
- The academy edits a **draft**. A live preview iframe shows the draft at desktop, tablet and phone widths. **Publish** makes the draft the page. **Discard** brings the draft back to what is published.
- **Templates** give a starting page: online courses, training and development, school for children and families, language school, and blank.
- **Page settings** cover fonts (from BrandFonts), accent and mood (always the academy's branding colours), corners, motion, audience, header, footer, WhatsApp and search words.
- **Existing academies see no change after deploy.** A data migration turns their saved content into blocks that render exactly as today, and the code falls back the same way while no page row exists.
- Nothing in the builder is Quran-specific. The Quran module is leaving the platform in a parallel stream.

What does not change: trial settings (who books, response hours, notice hours, the help-choose subject), the teachers' own cards (People's `teacher_public_cards`, written by each teacher), join requests, `form_token`, `regions`, the trial flows (`site/trial-slots`, `site/trials…`), the platform's own page (`GET v1/site/platform`, Platform's `Site\SiteContent`), and the mobile app's use of `GET v1/site` (section 3.4).

---

## 1. Data model (server)

### 1.1 Tables

Both tables are tenant tables: `$table->tenant()`, `TenantTable::enableRls()`, models `use BelongsToTenant`, cross-table references through `$table->tenantForeign()`. Migrations go in `modules/Site/database/migrations/2027_06_…`.

**`site_pages`**: one row per academy. The draft and the published page.

| column | type | notes |
|---|---|---|
| `academy_id` | uuid PK (`tenant()`) | |
| `draft` | jsonb not null | a `PageDoc` (1.2). `check (jsonb_typeof(draft) = 'object')` |
| `published` | jsonb null | a `PageDoc`. Null means nothing has been published yet; the page then falls back (5.2). |
| `draft_revision` | integer not null default 1 | +1 on every draft save |
| `published_revision` | integer null | the `draft_revision` that was published |
| `template` | varchar(32) null | the template the draft started from (for the "reset" menu only) |
| `draft_saved_at` / `draft_saved_by` | timestamptz / uuid null | `tenantForeign('draft_saved_by','users')` |
| `published_at` / `published_by` | timestamptz null / uuid null | `tenantForeign('published_by','users')` |
| `created_at`, `updated_at` | timestampsTz | |

"Unpublished changes" means `published is null or draft_revision <> published_revision`.

**`site_page_publishes`**: the publishing history. A row is never updated (the model refuses; no trigger needed). It lets the academy roll back.

| column | type | notes |
|---|---|---|
| `id` | uuid PK | uuid7 |
| `academy_id` | tenant | |
| `revision` | integer | the published revision |
| `page` | jsonb | the `PageDoc` as published |
| `published_by` | uuid null | `tenantForeign` users, `on delete set null` |
| `published_at` | timestamptz | |

Index `(academy_id, published_at desc)`. `site:sweep` keeps the **last 20** per academy and deletes older ones.

**`site_media`**: the academy's uploaded files for the page.

| column | type | notes |
|---|---|---|
| `id` | uuid PK | uuid7 |
| `academy_id` | tenant | |
| `kind` | varchar(8) | `image` \| `video`. Check constraint. |
| `mime` | varchar(32) | from the bytes: `image/jpeg`, `image/png`, `image/webp`, `video/mp4`, `video/webm` (check constraint) |
| `path` | varchar(255) | on the `public` disk: `site/{academy}/media/{id}.{ext}` |
| `bytes` | bigint | |
| `width`, `height` | integer null | images: from `getimagesize()`. Videos: null (the poster holds the size). |
| `name` | varchar(120) | the original file name, cleaned. Shown in the library only. |
| `alt` | jsonb not null default `{}` | `{ar, en}`, each ≤ 200. The default alt for images; a block may override it. |
| `uploaded_by` | uuid null | `tenantForeign` users |
| `created_at`, `updated_at` | timestampsTz | |
| `unused_since` | timestamptz null | set by `site:sweep` when nothing references the file; cleared when something does (1.6) |

Storage meter: in `SiteServiceProvider`, `UsageMeters::add(Limit::StorageMb, fn () => (int) DB::table('site_media')->sum('bytes'))`.

**`site_settings`** stays as it is. It keeps `enabled` and the trial columns. Its `content` column is **frozen** after the migration: the new code never writes it. It is kept for one release so the migration can be rolled back, then dropped in a later migration (not part of this work).

### 1.2 `PageDoc`: the JSON kept in `draft` and `published`

The pure `Modules\Site\Domain\Page\PageDoc` normalizes and checks it. Like `SiteContent`, it uses plain loops and no collection closures (Scramble).

```jsonc
{
  "v": 1,                                   // schema version; PageDoc::upgrade() reads older ones
  "settings": { /* PageSettings, 1.4 */ },
  "blocks": [ /* Block, 1.3; at most 40 */ ]
}
```

**Shared value types** (the same names are used throughout this document):

- `Text<N>`: `{ "ar": string, "en": string }`. Each side is trimmed, has control characters removed (the `SiteContent::text()` rules), and is at most N characters. An empty side means "use the other language". Both empty means "not set"; for fields marked **(d)**, the portal then shows its own default wording for the audience (section 6.6). Required fields need at least one side.
- `Lines<N,M>`: a list of at most M `Text<N>`.
- `Rich`: limited rich text (1.5). At most 5,000 characters of text in total per language.
- `MediaRef`: `{ "media": uuid, "alt": Text<200> | null }`. `media` must be a `site_media` row of this academy with the right `kind`. `alt` null means use the media's own alt.
- `Link`: `{ "label": Text<40>, "action": Action }`, where `Action` is one of:
  - `{ "kind": "trial" }`: to the form block (`#join`), opened on the trial tab. Not offered when the page has no visible form block (the button is dropped).
  - `{ "kind": "teach" }`: the form block on the teach tab. Needs a form block with `teach: true`.
  - `{ "kind": "sign_in" }`: to `/{locale}/sign-in`.
  - `{ "kind": "whatsapp" }`: to the page's WhatsApp number. Dropped when there is none.
  - `{ "kind": "block", "block": "<block id>" }`: scrolls to that block. Must be an id on the page.
  - `{ "kind": "url", "url": string }`: `https://…` (≤ 500), `mailto:`, `tel:+…`, or a path that starts with `/ar/` or `/en/`. Nothing else: no `javascript:`, no `data:`, no `http:`. Opens in a new tab when it is another host.
- `Style`: common to every block (1.3).

### 1.3 Blocks

Every block has the same envelope:

```jsonc
{
  "id": "b7k2m9x4",            // 8 chars [a-z0-9], unique on the page, made by the server; kept across saves, publishes and duplicates get a new one
  "type": "hero",              // one of the types below
  "variant": "split",          // one of the type's variants; unknown → the type's first
  "hidden": false,             // kept in the draft, never rendered, never sent by GET v1/site
  "nav": { "ar": "", "en": "" },  // Text<24>: when set, the block appears in the header's menu under this label
  "style": {
    "tone": "paper",           // paper | white | tint | brand | night   (background, 6.2)
    "align": "start",          // start | center                         (headings and short text)
    "space": "normal",         // compact | normal | roomy               (padding above and below)
    "width": "normal",         // narrow | normal | wide | full          (content measure; full = edge to edge where the variant allows)
    "eyebrow": false           // show the small label above the heading (the label text is the block's `eyebrow` field)
  },
  "data": { /* per type, below */ }
}
```

Defaults when missing: `tone` comes from the type (table below), `align: start`, `space: normal`, `width: normal`, `eyebrow: false`. Anchor ids on the page are `b-{id}`. The migrated legacy blocks also get their old anchor names (`why`, `subjects`, `teachers`, `start`, `app`, `pricing`, `testimonials`, `faq`, `join`), in the field `"anchor"` (`[a-z][a-z0-9-]{0,31}`, unique). Old links such as `/ar#pricing` keep working.

Most blocks share a **heading group**: `eyebrow: Text<40>`, `title: Text<120>` **(d)**, `lead: Text<300>` **(d)**. It is listed below as "heading".

Limits: at most 40 blocks. At most one of each of `hero`, `form`, `pricing`, `teachers`, `subjects` and `app`. Any number of the others. The **draft** may break these rules while it is being edited: a draft save only checks shape and lengths. **Publish** checks everything (`PublishRules`), and publish is refused with the reasons (1.7).

Which blocks pull **live data**: `subjects` (Catalog + Curriculum), `teachers` (People's public cards), `pricing` (Billing plans and regions), `app` (the platform's store links and the academy's code), `form` (subjects, regions' countries, trial settings, form token). Their editable parts are the heading, the layout and filters. The data itself is edited where it lives (Subjects, Plans, teachers' own cards), and the block editor links there.

#### `hero`: the opening

Variants:
- `centered`: text centred, optional media below the buttons (an image or a video in a frame).
- `split`: text on one side and the academy's image or video on the other. `data.media_side: start | end` (default `end`). On a phone the media goes under the text.
- `video`: the academy's video fills the hero behind the text (muted, loops, `playsinline`, autoplay only when the visitor allows motion and is not on Save-Data). A dark scrim keeps the text readable (`data.scrim: light | medium | strong`, default medium). The poster is the fallback and the first paint.
- `phones`: today's phone mock-up (`phones.tsx`, starfield, aurora). It is kept for academies that teach children. The migration uses it.
- `minimal`: title and lead only, smaller, no media. For a page that opens straight into its courses.

Data:
| field | type | notes |
|---|---|---|
| `kicker` | Text<60> **(d)** | the small line above the title (today `site.kicker`) |
| `title` | Text<120> required | the h1 |
| `lead` | Text<300> | |
| `primary` | Link \| null | default `{label: (d), action: trial}` |
| `secondary` | Link \| null | default `{label: (d), action: sign_in}` |
| `trust` | Lines<60,4> **(d)** | the short ticked lines under the buttons |
| `media` | MediaRef(image) \| null | `split`, `centered` |
| `video` | MediaRef(video) \| null | `video`; also allowed in `split`/`centered` instead of `media` |
| `poster` | MediaRef(image) \| null | required when `video` is set (publish rule) |
| `media_side` | `start` \| `end` | `split` |
| `media_shape` | `rounded` \| `arch` \| `circle` \| `bleed` | `split`. `bleed` runs the image to the page edge. |
| `scrim` | `light` \| `medium` \| `strong` | `video` |
| `show` | `{kicker, lead, buttons, trust, media}` all bool, default true | each element can be switched off without deleting its text |

Hero default tone: `night` for `phones` and `video`, `paper` for the others.

The h1 rule: the page has exactly one `<h1>`. It is the hero's title when there is a hero, otherwise the first block's title, otherwise a visually hidden h1 with the academy's name (6.5).

#### `features`: why us (grid of reasons)

Variants: `cards` (today's look), `icons` (an icon above each, no card), `list` (two columns of ticked lines with a sentence each), `numbers` (large ordinal per item; use it only when the items really are an order).
Data: heading (title **(d)** from `why.title` for the audience, lead **(d)**); `items`: list of 1–9 `{ icon: IconName, title: Text<60> required, text: Text<200> }`; `columns: 2 | 3` (default 3).
`IconName` is one of the portal's icon set (`icons.tsx`): `users, report, calendar, map, shield, heart, star, check, play, book, clock, globe, chat, award, laptop, phone`. The portal adds the missing ones (portal owns `icons.tsx`). The server only checks the name against the constant `Blocks::ICONS`.

#### `subjects` (live)

Variants: `cards` (today's: levels ladder, programs), `list` (compact rows: name, levels as a line), `chips` (names only, for many subjects).
Data: heading (**(d)**); `only`: list of subject ids (≤ 50) or empty for all, kept in this order when set; `show_levels: bool` (true), `show_programs: bool` (true), `show_categories: bool` (true), `ask_label: Text<40>` **(d)** (the "ask for a trial in this subject" link; hidden when the page has no form block).
Ids no longer in Catalog are dropped silently when the page is read.

#### `teachers` (live)

Variants: `cards` (today's), `portraits` (large photo first, name and subjects under it; for academies with real photos), `compact` (small avatar, name, subjects).
Data: heading (**(d)**); `only`: list of teacher card ids (≤ 50), in this order, empty for all shown cards; `max: 1–24` (12); `show_bio`, `show_languages`, `show_ages`, `show_badges` (trials / groups): bool, default true.
The academy still cannot write a teacher's card. It can only hide one (`HideTeacherCard`, unchanged).

#### `pricing` (live)

Variants: `cards` (today's, region switcher), `table` (plans as columns, facts as rows; good for 2–4 plans).
Data: heading (**(d)**); `only`: plan ids (≤ 12) in order, empty for all; `highlight`: a plan id or null (that plan gets the "most chosen" mark: `highlight_label: Text<30>` **(d)**); `per_lesson: bool` (true: show the price per lesson); `note: Text<300>` (small print under the plans).

#### `steps`: how it starts

Variants: `line` (today's connected line), `cards`.
Data: heading (**(d)**); `items`: 0–6 `{ title: Text<60>, text: Text<200> }`. Empty `items` means the portal's default steps for the audience **(d)**. This is a real sequence, so the numbers are drawn.

#### `app` (live)

Variants: `phones` (today's AppShowcase), `image` (the academy's own screenshot or picture: `media` required), `plain` (text and store buttons only).
Data: heading (**(d)**); `points`: 0–4 `{ title: Text<60>, text: Text<200> }` **(d)** (today's `app.steps`); `show_code: bool` (true: the academy's code to open the app); `show_stores: bool` (true); `media: MediaRef(image) | null`.
Store links come from the platform's config, as today. The block is hidden on the page when `show_stores` is on, the platform has no store link and `show_code` is off.

#### `testimonials`

Variants: `marquee` (today's moving strip; it stops under reduced motion), `grid`, `single` (one large quote at a time, with buttons to move between them; no autoplay).
Data: heading (**(d)**); `items`: 1–12 `{ name: string ≤ 60 required (no language), role: Text<60>, quote: Text<400> required, photo: MediaRef(image) | null, rating: 0 | 1–5 }`. 0 means no stars shown.
The builder's hint says: real words only, with the owner's consent (the same rule as today).

#### `faq`

Variants: `accordion` (today's), `two_columns` (the heading sticks on one side and the questions run on the other).
Data: heading (**(d)**); `items`: 1–20 `{ q: Text<160> required, a: Text<800> required }`.
The portal also writes FAQPage JSON-LD (6.7).

#### `stats`: numbers

Variants: `band` (one row on a tinted band), `cards`.
Data: heading (optional; none means no heading); `items`: 2–6 `{ value: Text<12> required ("+1,200", "98%", "١٥ عامًا"), label: Text<60> required }`.
The numbers are typed by the academy. The page never counts anything itself, and it never shows real student numbers from the database.

#### `rich_text`

Variants: `article` (a narrow measure, for prose), `two_columns` (the heading on one side and the text on the other).
Data: heading (title optional); `body: { ar: Rich, en: Rich }` (1.5).

#### `image_text`: image and text side by side

Variants: `side` (image start or end), `overlap` (the text card overlaps the image), `stacked`.
Data: heading; `body: Rich` pair; `media: MediaRef(image)` required, **or** `video` + `poster` (as in the hero); `media_side: start | end`; `bullets: Lines<120,6>`; `link: Link | null`.

#### `gallery`

Variants: `grid` (equal tiles), `masonry` (sizes held: each image keeps its own ratio), `carousel` (scroll-snap strip, arrows, no autoplay).
Data: heading (optional); `items`: 2–24 `{ media: MediaRef(image), caption: Text<120> }`; `ratio: auto | 1:1 | 4:3 | 3:2 | 16:9` (the grid only). Clicking an image opens it larger in a lightbox `<dialog>`.

#### `video`

Variants: `framed` (a video at the content width with a caption), `wide` (full width), `side` (the video beside a heading and text).
Data: heading (optional); `source`: either `{ kind: "upload", video: MediaRef(video), poster: MediaRef(image) }` or `{ kind: "youtube", id: [A-Za-z0-9_-]{11} }` or `{ kind: "vimeo", id: [0-9]{6,12} }`; `poster` is optional for youtube or vimeo (without one, the facade shows a neutral frame; provider thumbnails are not fetched, see 6.4); `caption: Text<200>`; `ratio: 16:9 | 4:3 | 1:1 | 9:16` (default 16:9); `autoplay_muted: bool` (false; upload only, and only when the visitor allows motion).
YouTube and Vimeo play only after a click (a "facade"). Nothing from the provider loads before the click. The embed then uses `youtube-nocookie.com` or `player.vimeo.com` with `dnt=1`.

#### `logos`: partners, accreditations, "as seen in"

Variants: `row` (a single line, wraps), `marquee` (moves, stops under reduced motion), `grid`.
Data: heading (optional, e.g. «شركاؤنا»); `items`: 2–24 `{ media: MediaRef(image) required, name: string ≤ 60 required (the alt text), url: https URL ≤ 500 | null }`; `mono: bool` (true: shown in one colour, full colour on hover or focus).
PNG or WebP with transparency is recommended. The library flags JPEGs used here.

#### `cta`: call-to-action banner

Variants: `banner` (a full-width band), `card` (a raised card inside the content width), `split` (text with an image on one side).
Data: `title: Text<120>` required; `lead: Text<300>`; `primary: Link` required; `secondary: Link | null`; `media: MediaRef(image) | null` (`split`, or a background for `banner` with a scrim).
Default tone `brand`.

#### `form`: the free trial and teach-with-us form (live)

Variants: `split` (today's: text and points beside the form), `centered` (the form alone in a card under the heading).
Data: heading (**(d)**: today's `join.title` and `join.lead`/`join.leadBook`); `points: Lines<120,5>` **(d)**; `teach: bool` (false; replaces the old `teach` section: shows the "teach with us" tab and its footer link); `whatsapp_button: bool` (true: "chat on WhatsApp" beside the text when the page has a number).
The form itself (`JoinAcademyForm`, `lib/site/actions`, the trial flows) is unchanged. Its anchor is always `join` (and `teach` for the teach tab), whatever the block id.
A page with no visible form block takes no requests from the page. Buttons with `trial` or `teach` actions are dropped, and the builder warns before publishing (1.7, a warning, not a blocker). `POST v1/site/requests` keeps working, because the app sends trials too.

#### `contact`

Variants: `cards` (one card each for WhatsApp, email, phone and address), `band`.
Data: heading (**(d)**); `email`: string email ≤ 120 | null; `phone`: E.164 | null; `address: Text<300>`; `hours: Text<120>`; `whatsapp: bool` (true: show the page's WhatsApp number as a card).
This is the only place the page shows an email or phone, and the academy typed it here on purpose. The API never adds staff or user contact details.

#### `spacer`

Variants: `space`, `divider`.
Data: `size: sm | md | lg`. A small tool for rhythm. Its style has `tone` only.

### 1.4 `PageSettings`

```jsonc
{
  "audience": "families",          // families | adults: the default words the portal uses for (d) fields, and the form's "your child" vs "you"
  "fonts": { "heading": null, "body": null },   // a BrandFonts family name or null = the academy's branding fonts
  "accent": "primary",             // primary | attention | info: which branding colour carries buttons and highlights
  "mood": "light",                 // light | warm | night: the paper colour and how dark "night" tones are (6.2); all derived from branding
  "corners": "brand",              // brand | sharp | soft | round: brand = the branding radius
  "motion": "subtle",              // full | subtle | none (full = today's: tilt, starfield, marquee; subtle = one hero entrance and quiet reveals)
  "header": {
    "style": "overlay",            // overlay (over the hero, solid on scroll: today's) | solid | minimal (logo and one button)
    "cta": { /* Link */ } | null,  // default {label:(d) "احجز حصة مجانية", action: trial}
    "sign_in": true,
    "language_switch": true
  },
  "footer": {
    "text": { "ar": "", "en": "" },      // Text<300>: a line about the academy
    "links": [ { "label": Text<40>, "url": "https://…" } ],   // ≤ 6, the same URL rules as Link.url
    "social": [ { "network": "instagram|x|facebook|youtube|tiktok|linkedin|snapchat|telegram", "url": "https://…" } ]  // ≤ 8, the host must match the network
  },
  "whatsapp": "+9665…" | "",       // E.164 or empty (the floating button, Link whatsapp, contact block, form)
  "floating_whatsapp": true,
  "seo": { "title": Text<70>, "description": Text<170>, "image": MediaRef(image) | null }   // the share image (Open Graph), ≥ 1200×630 recommended
}
```

Fonts: the server checks names against the Platform's list through a **new contract method**, `Platform\Contracts\BrandingApi::fontFamilies(): list<string>` (returns `array_keys(BrandFonts::ALL)`), and builds the stylesheet with `BrandingApi::fontsUrl(string ...$families): string` (wraps `BrandFonts::stylesheetUrl`). Site does not import `Platform\Branding\*` (arch rules). The legal privacy, terms and legal-name lines in the footer are always added by the portal and cannot be removed.

### 1.5 `Rich`: limited rich text, never HTML

Stored as a small tree. The portal renders it as React elements; it never uses `dangerouslySetInnerHTML`.

```jsonc
[
  { "t": "p",  "c": [ Inline, … ] },
  { "t": "h3", "c": [ Inline, … ] },            // only h3 and h4: the block owns h2
  { "t": "h4", "c": [ Inline, … ] },
  { "t": "ul", "items": [ [Inline, …], … ] },   // ≤ 20 items
  { "t": "ol", "items": [ [Inline, …], … ] },
  { "t": "quote", "c": [ Inline, … ] }
]
Inline = { "text": string, "b"?: true, "i"?: true, "href"?: string }   // href: the Link.url rules
```

The editor is Filament's `RichEditor` with the toolbar limited to bold, italic, link, h3, h4, bullet list, ordered list and blockquote. It saves TipTap JSON (`->json()`). The pure `Domain\Page\RichText::fromEditor(array $tiptap): array` keeps only the nodes above and drops everything else (marks, attributes, images, tables). `RichText::toEditor(array)` turns it back for the form. At most 60 nodes and 5,000 characters per language.

### 1.6 Media references and unused files

`Domain\Page\MediaRefs::of(PageDoc): list<uuid>` lists every media id a document uses, posters and the SEO image included.
A file is **in use** when its id appears in the draft, the published page, or any of the 20 kept `site_page_publishes` rows.
- The library's delete (`DeleteSiteMedia`) refuses a file in use and says which block uses it ("مستخدم في: الواجهة، معرض الصور").
- `site:sweep` (already daily) sets `unused_since` on files not in use and clears it on files in use again. Files unused for **7 days** are deleted: the row now, the file after commit (`DB::afterCommit` → `Storage::disk('public')->delete`).
- When a page is read, a missing media id is dropped (the block renders without it, or with the variant's fallback). It never causes an error.

### 1.7 Publish rules (`Domain\Page\PublishRules`, pure)

`PublishRules::check(PageDoc, Facts): Verdict`, where `Facts` holds the media rows (kind, dims) and which subject, plan and card ids exist. The verdict has `blockers` (publish refused) and `warnings` (shown in the confirmation). The builder's status bar and `PublishSitePage` both read it, so they never disagree (the same idea as Curriculum's `PublishReadiness`).

Blockers:
- More than 40 blocks, or a second `hero`, `form`, `pricing`, `teachers`, `subjects` or `app`.
- A required field empty in **both** languages.
- A `video` without a poster (uploads); a media id that is not this academy's, or of the wrong kind.
- A `Link` to a missing block, or a URL breaking the rules.
- No visible block at all.

Warnings:
- No visible `form` block (no trial requests from the page).
- A text set in only one language (named by block and field; the other language will show it).
- A hero image smaller than 1200 px wide, a gallery image smaller than 600 px, an SEO image smaller than 1200×630.
- A `logos` item that is a JPEG.
- `testimonials` with no items while visible (the block hides itself).
- The hero not first.

---

## 2. Media (server)

### 2.1 What is accepted

| kind | types (from the bytes) | size | other |
|---|---|---|---|
| image | `image/jpeg`, `image/png`, `image/webp` | ≤ 5 MB after the browser resize | the browser resizes to fit 2560×2560 (`imageResizeMode('contain')`, `imageResizeUpscale(false)`) before uploading, as the favicon box does. The server reads width and height with `getimagesize()` (core PHP; the image has no GD). At least 200×200. |
| video | `video/mp4`, `video/webm` | ≤ 50 MB | needs a poster image before it can be published. No transcoding (no ffmpeg in the image). The hint says: "MP4 (H.264), under a minute for a background video, 1080p or less." |

- **No SVG, no GIF, no HTML** (CLAUDE.md: never SVG or HTML; GIF is heavy and can be animated). AVIF is not accepted in v1: `getimagesize` support depends on the PHP build.
- The type is checked by content: `mimetypes:` with the list above, plus `File::types()` on the real bytes (`finfo`), never the browser's word or the extension. The extension is chosen from the detected type (`jpg|png|webp|mp4|webm`).
- `new CleanFile` on every upload (ClamAV when it is set up).
- Per academy: at most **300 files** and **1 GB** in `site_media`. Over that, the upload is refused with "Delete unused files from the library first". The plan's storage limit is watched through the meter (1.1), as other modules do.
- Path: `site/{academy_id}/media/{media_id}.{ext}` on the `public` disk (the teachers' photos already live under `site/{academy}/teachers/`). Names are uuid7, so URLs never collide and can be cached forever.

### 2.2 Actions

- `UploadSiteMedia::handle(UploadedFile $file, array $alt = []): SiteMedia`: `site.manage`, validation as above, counts under `lockForUpdate` on the academy's `site_pages` row (made if missing), `storeAs` on public, row created. If the database write fails, the file is deleted.
- `UpdateSiteMedia::handle(string $id, array $alt): SiteMedia`: alt texts only.
- `DeleteSiteMedia::handle(string $id): void`: refused while in use (1.6). File removed after commit.

None of these is audited (media are public by nature). Publishing is audited.

### 2.3 Server and proxy limits (server engineer)

- **`apps/api/config/livewire.php`** (new, published from vendor, only the changed keys kept and the rest left at their defaults): `temporary_file_upload.rules => ['required', 'file', 'max:51200']` (50 MB; the default is 12 MB) and `max_upload_time => 10` (minutes). `temporary_file_upload.directory` stays the default. The builder's own `FileUpload` sets `maxSize(51200)` for videos and `5120` for images. The server rules in `UploadSiteMedia` are the real check.
- `infra/deploy/php.ini`: `upload_max_filesize = 50M` and `post_max_size = 55M` are already enough. **No change.** Also check that `max_execution_time` is not below 120 for the upload request (FrankenPHP's default is fine).
- `infra/deploy/nginx/academy-lms.conf`: `client_max_body_size 55m` is enough. Add `proxy_request_buffering off;` **only** if large uploads time out in staging. This is a "watch", not a change.
- `infra/deploy/Caddyfile`: add an immutable cache header for the page's media (uuid names never change):
  ```caddy
  @sitemedia path /storage/site/*/media/*
  header @sitemedia Cache-Control "public, max-age=31536000, immutable"
  ```
  Caddy's file server answers `Range` requests, so videos seek. Videos are served with their real `Content-Type` (Caddy's mime table has mp4 and webm).
- The portal never proxies media. URLs in the API are **paths on the academy's host** (`/storage/site/…`), as `BrandingSettings::uploadedOnThisHost` does. In production the page and `/storage` share a host through Caddy. Locally the portal (`localhost:3101`) cannot reach `/storage` on its own origin, so the API returns **absolute** URLs built from the request's host when `config('site.media_absolute')` is true (env `SITE_MEDIA_ABSOLUTE=true`, false by default; set to true by `bin/api-up`). Add the setting to `deploy.sh`'s `settings()` with `false`, and to the README "Settings" list.

---

## 3. API contract (server owns; portal reads)

### 3.1 `GET v1/site` (published page)

Unchanged: the route, `throttle:120,1`, 404 while off, `Cache-Control: no-store`, and every existing top-level key. Added: `page`. `content` stays, derived from the published page (3.4).

```jsonc
{
  "data": {
    "academy": { "name": "أكاديمية النور", "code": "demo" },
    "page": {
      "revision": 7,                         // the published revision; the portal may key caches on it
      "settings": {
        "audience": "adults",
        "fonts": { "heading": "Readex Pro", "body": "IBM Plex Sans Arabic", "url": "https://fonts.googleapis.com/css2?family=…&display=swap" },
        // fonts.heading/body: the resolved family (page setting, else branding); url: null when both are the branding's (the layout loads those already)
        "accent": "primary",
        "mood": "light",
        "corners": "brand",
        "motion": "subtle",
        "header": { "style": "overlay", "cta": { "label": "احجز حصة مجانية", "action": { "kind": "trial" } }, "sign_in": true, "language_switch": true },
        "footer": { "text": "…", "links": [ { "label": "…", "url": "https://…" } ], "social": [ { "network": "instagram", "url": "https://instagram.com/…" } ] },
        "whatsapp": "+966500000000",         // or null
        "floating_whatsapp": true,
        "seo": { "title": "…", "description": "…", "image": Media | null }
      },
      "blocks": [
        {
          "id": "b7k2m9x4",
          "type": "hero",
          "variant": "split",
          "anchor": "b-b7k2m9x4",             // the element id to use: the legacy anchor when set, else "b-{id}"
          "nav": "الدورات",                     // or null
          "style": { "tone": "paper", "align": "start", "space": "roomy", "width": "wide", "eyebrow": false },
          "data": {
            "kicker": null,                    // (d) not set → the portal's default for the audience
            "title": "تعلّم البرمجة من الصفر حتى أول وظيفة",
            "lead": "…",
            "primary": { "label": "ابدأ بحصة مجانية", "action": { "kind": "trial" } },
            "secondary": null,
            "trust": null,                     // (d) list fields: null = default, [] = none
            "media": { "url": "/storage/site/…/media/0190….webp", "width": 2400, "height": 1600, "alt": "…", "mime": "image/webp" },
            "video": null,
            "poster": null,
            "media_side": "end",
            "media_shape": "rounded",
            "scrim": "medium",
            "show": { "kicker": true, "lead": true, "buttons": true, "trust": true, "media": true }
          }
        }
        // … more blocks, only visible ones, in order
      ]
    },
    "content": { /* the legacy shape, as today, derived (3.4). Deprecated. */ },
    "subjects": [ … ],                       // as today; always every subject (the app and the form need them)
    "teachers": [ … ],                       // as today; filled only when a visible teachers block exists (its `only`/`max` applied, in order)
    "plans": [ … ],                          // as today; filled only when a visible pricing block exists (its `only` applied, in order)
    "regions": [ … ], "app": { … }, "whatsapp": "+966…" | null, "trials": { … }, "form_token": "…"
  }
}
```

Localization rules for `page` (the server's `Domain\Page\Localize`, pure):
- Every `Text` becomes a **string in the request's language**, falling back to the other language. Not set in both → `null` (for **(d)** fields the portal then uses its default; for others the element is not rendered).
- `Lines` → `list<string>` or `null` when the field was never set (**(d)**: default) versus `[]` when the academy cleared it on purpose. The document keeps that difference: an absent key means "not set", an empty array means "none".
- `Rich` → the node tree of that language (the other language's when this one is empty).
- `MediaRef` → `Media = { url, width, height, alt, mime }` (video: `width`/`height` null; `alt` resolved by the same fallback, `""` when none). A missing file → `null`.
- `Link` → `{ label: string, action: Action }`. Labels marked **(d)** may come back `""`, and the portal fills them. A link whose action cannot work is dropped (→ `null` or removed from lists): `trial` or `teach` with no visible form block, `teach` without `teach: true`, `whatsapp` with no number, `block` pointing at a hidden or missing block.
- `testimonials.items[].name`, `logos.items[].name` and `contact` fields pass as plain strings.
- Live blocks' `data.only` is **not** sent. The server applies it to the top-level lists, which keep their current item shapes. The block data carries only display options.
- Hidden blocks are never sent.
- `subjects` block: `data.subject_ids: list<string>` is sent: the ids to show, in order (all subjects when `only` was empty). The portal picks them from top-level `subjects`.
- `teachers` block: the top-level `teachers` already holds exactly the cards to show, in order.
- `pricing` block: top-level `plans` already filtered and ordered; `data.highlight` is a plan id or null.

OpenAPI: describe `page` with the PHPDoc and array-comment style the controller already uses. Scramble cannot infer a union of block data shapes well, so the server adds a hand-written schema: a `SiteBlock` component with a `type` enum and `data` as `object` (additionalProperties). The **portal types blocks by hand** (8.3) and does not depend on generated per-block types. `content` gets `deprecated: true`.

### 3.2 `GET v1/site/preview` (the draft)

- Route: `Route::get('site/preview', [SiteController::class, 'preview'])->middleware('throttle:60,1')->name('site.preview')`. Same middleware group as `site` (api, tenant, locale, timezone).
- Query: `token` (required). A `LinkToken` with purpose `site-preview`, the academy id as its record, and an expiry 2 hours ahead (`Support\PreviewLinks::issue(): string`, `::valid(?string): bool`). A token of another academy or purpose → **404**. An expired token → **410** `link-expired` (as other `LinkToken` pages do: `LinkToken::expired`).
- Answers the **same shape** as `GET v1/site`, built from `site_pages.draft`, **even while the page is off** (so an academy can build before switching on). Differences:
  - `data.preview = { "revision": <draft_revision>, "published_revision": <int|null>, "enabled": <bool> }` (absent in `GET v1/site`).
  - `form_token` is `""`: the preview's form does not send.
  - Hidden blocks are still left out. The builder's list shows them dimmed.
  - Live lists are computed as for the published page, from the draft's blocks.
- Headers: `Cache-Control: no-store, private`, `X-Robots-Tag: noindex, nofollow`, `Referrer-Policy: no-referrer`.
- Why a token and not the panel session: the portal's server calls the API without the admin's cookie (the portal holds only its own `lms_session`, and an admin is usually not signed in there). The token is the proof. It is short-lived, and it only shows a draft of a page that is meant to be public anyway.

### 3.3 Portal side of the preview

- New public route `apps/web/src/app/[locale]/site-preview/page.tsx`, reading `?t=<token>&r=<revision>`. `r` only breaks caches; the page ignores its value.
- `proxy.ts`: add `rest === '/site-preview'` to the open (public) paths. For that path only, send a different policy: `frame-ancestors 'self' ${SITE_PREVIEW_FRAME_ANCESTORS}` (an env of the portal, space-separated origins, empty in production; locally `http://demo.localhost:8101`). `lib/csp.ts` gets `previewPolicy(nonce, ancestors, development)`.
- `next.config.ts`: exclude `/:locale/site-preview` from the `X-Frame-Options: SAMEORIGIN` and the static `frame-ancestors 'self'` header set (the same way `/bff/games/` is excluded). The proxy's header is then the only one. Locally the admin (`demo.localhost:8101`) and the portal (`localhost:3101`) are different origins. In production both are the academy's host (`/admin` on Laravel, `/ar` on the portal through Caddy), so `'self'` is enough.
- The preview page: `export const dynamic = 'force-dynamic'`; `metadata.robots = { index: false, follow: false }`; fetches `GET /v1/site/preview` through `call()` with `query: { token }` (the path is typed from the schema, so the server must ship it in OpenAPI first, or the portal stubs it until then: see 8.4). It renders the same `SitePage` component with `preview` set. That means: a thin bar "معاينة — لم تُنشر بعد" fixed at the top, form submit disabled with a note, links to other pages open in a new tab, WhatsApp links work.
- Expired or invalid token → a calm page: "انتهت صلاحية المعاينة. أعد فتحها من لوحة التحكم."
- Scroll keeping: a tiny nonce'd inline script on the preview page saves `scrollY` to `sessionStorage` (key `lms-preview-scroll`) on `pagehide` and restores it on load. The iframe is same-origin with itself, so this works locally and in production. When the URL has `#b-{id}`, it scrolls to that block instead and outlines it for 1.2 s (the "show this block" button in the editor).
- `postMessage`: the preview page posts `{ type: 'lms-site-preview', revision, height }` to `window.parent` on load (target origin `*` is acceptable: it carries no secret). The builder ignores messages whose `revision` is older than the one it asked for.

### 3.4 Backwards compatibility

- **Same image, same deploy.** API and portal ship together (`deploy.yml`), so the window where an old portal reads a new API is a rolling restart. Even so:
  - `content` keeps its exact old shape, made by `Domain\Page\LegacyContent::from(PageDoc, locale)`: hero title and lead from the first hero; features from the first `features` block; testimonials and faqs from the first blocks of those types; `sections` = the old section names whose block types are visible (`teach` = the form's `teach`); `whatsapp` and `seo` from settings; `audience` from settings; `why` from the features heading. Keep it for one release, then remove it in a later change (`oasdiff breaking` will ask for the major note).
  - The new portal checks `site.page`. If it is missing (an old API during rollback), it renders the old `AcademyPage` unchanged. Keep `academy-page.tsx` until `content` is removed.
- **The mobile app** reads only `academy`, `subjects`, `trials`, `form_token` and `whatsapp` from `GET v1/site` (`apps/mobile/lib/features/trial/domain/trial.dart`). They are unchanged, and `subjects` stays **always** complete and independent of blocks. The top-level `whatsapp` = `page.settings.whatsapp`.
- `SiteApi::trialSettings()`, the CRM and `SubmitJoinRequest` do not read page content. No change. `SubmitJoinRequest`'s "teach" kind must still be accepted when the form block has `teach: false`: the app may send it. Today it is accepted regardless of the section, and that does not change.

---

## 4. Admin UX: the builder (server owns: Filament)

### 4.1 Where

- New page **`Modules\Site\Filament\Pages\SiteBuilder`**: slug `site/builder`, nav label «تصميم الصفحة» / "Page design", `NavGroup::Settings`, sort 907 (just before «صفحة الأكاديمية», 908). `canAccess`: `Gate::allows(Ability::ManageSite->value)` (the plan's `Feature::Site` is already behind the ability). Full width (`getMaxContentWidth(): Width::Full`).
- The existing **`SiteSettings`** page («صفحة الأكاديمية») keeps: on/off, the audience (moved into the builder's Page tab; here it shows read-only with a link), Free trial, Teachers' cards. These tabs **move to the builder** and are removed from it: Page (hero), Why us, Testimonials, Questions, Sections, Contact and search. Its header gets a primary action, "Design the page" → the builder. `SaveSiteSettings` keeps `enabled` and the trial fields only. The content keys are dropped from its input and docblock. `SiteContent::normalize` is no longer called by it.
- "Start here" (`SiteStep`) keeps its meaning (the page is on). Its URL may point to the builder when no page has been published.

### 4.2 Layout of the screen

```
┌ top bar ─────────────────────────────────────────────────────────────────────────────┐
│ تصميم الصفحة   ● مسودة محفوظة 12:04 · تغييرات لم تُنشر     [السجل] [تجاهل المسودة] [نشر] │
├──────────────────────────────┬───────────────────────────────────────────────────────┤
│ [الأقسام] [الصفحة] [الوسائط]   │  [🖥 حاسوب] [▭ جهاز لوحي] [▯ هاتف]   [ع | EN]  [↗ فتح]   │
│                              │ ┌───────────────────────────────────────────────────┐ │
│ ⋮⋮ الواجهة · منقسمة       👁 ⋯ │ │                                                   │ │
│ ⋮⋮ لماذا نحن · بطاقات      👁 ⋯ │ │            iframe: /ar/site-preview?t=…&r=42      │ │
│ ⋮⋮ الدورات (مباشر)         👁 ⋯ │ │            (scaled to fit the column)             │ │
│ ⋮⋮ آراء المتعلمين (مخفي)   ◌ ⋯ │ │                                                   │ │
│ …                            │ └───────────────────────────────────────────────────┘ │
│ [+ أضف قسمًا]                 │   ⚠ تنبيهان قبل النشر  ·  ✓ لا عوائق                   │
└──────────────────────────────┴───────────────────────────────────────────────────────┘
```

- Two columns at ≥ 1280 px: the editor (start side, 440 px) and the preview (the rest). Below 1280 px the preview becomes a button that opens it in a slide-over, and below 768 px it opens in a new tab.
- The **Sections tab** is a Filament `Builder` (`statePath('data.blocks')`): one `Block` per type with its own icon, `blockPickerColumns(3)`, `blockPickerWidth('3xl')`, `->searchable()`; items `collapsible()->collapsed()`, `reorderableWithDragAndDrop()`, `cloneable()` (the clone gets a new id in `SaveSiteDraft`), `deletable()` with a confirmation. A block's label is `«{type}» · {variant}` plus the first 40 characters of its title. Extra item actions:
  - **hide/show** (eye icon, toggles `hidden`; hidden items are dimmed through `extraItemActions` and a CSS class);
  - **show in preview** (posts the iframe to `#b-{id}`).
  Each block's form has three sections: **Content** (Arabic and English side by side, as today's `$pair`: Arabic first, the English input `dir="ltr"`), **Layout** (`variant` as `ToggleButtons` with a small drawn thumbnail per variant, plus type-specific options), and **Style** (tone swatches drawn from the academy's colours, align, space, width, eyebrow, the menu label). Live blocks show a note with a link to where their data lives ("المواد تُدار من «المواد»"). Pickers for `only` are `Select::multiple()->reorderable()` fed by `CatalogApi::subjectNames`, `PeopleApi::teacherCards` (shown cards) and `BillingApi::planOptions`.
- **Media fields** are a custom form component `Site\Filament\Forms\MediaPicker` (a `ViewField` over `site_media`): it shows the chosen thumbnail with its size, a "choose" button (a modal grid of the library, filtered by kind) and an "upload" button (a `FileUpload` in a modal that calls `UploadSiteMedia` and then selects the new row). The block state keeps only `{media, alt}`. The image upload box resizes in the browser (2.1).
- The **Page tab**: audience; fonts (two `Select`s from `BrandingApi::fontFamilies()`, each with a "the academy's font" option, a live sample line in each font); accent (three swatches from branding); mood; corners; motion; header (style, button, sign-in, language); footer (text pair, links repeater ≤ 6, social repeater ≤ 8); WhatsApp (+ floating toggle); search (title, description pairs, share image). Under it, a line says the colours come from Settings › Branding, with a link.
- The **Media tab**: the library grid (thumbnail, name, size, dims, "in use" badge with where), an upload drop zone, alt editing, delete (refused in use). Totals: "23 ملفًا · 184 ميغابايت من 1 غيغابايت".
- The **History** (top bar) opens a slide-over listing `site_page_publishes` (when, who, block count). "Restore to the draft" copies that version into the draft. It does not publish: the person reviews it and publishes.
- **Templates**: the top bar's ⋯ menu has "Start from a template". A modal shows five cards (thumbnail, name, one line): «أكاديمية دورات أونلاين», «تدريب وتطوير مهني», «مدرسة للأطفال والعائلات», «معهد لغات», «صفحة فارغة». Choosing one replaces the **draft** after a confirmation that names what will be lost. Nothing goes live until publish. A brand-new academy with no row gets a draft made from the template that fits its audience (`families` → children's school, `adults` → online courses).

### 4.3 Saving, preview refresh, publishing

- **Autosave**: the form is `->live(debounce: 800)` at the Builder level (not on every keystroke of every field). On each change Livewire calls `saveDraft()` → `SaveSiteDraft::handle(array $doc, int $baseRevision)`: shape and lengths only (`PageDoc::normalize`, validation messages under `data.blocks.{i}.data.*` so fields show their errors), new ids for new or cloned blocks, `draft_revision` +1, and a conflict check: if `$baseRevision` ≠ the stored `draft_revision` (another admin saved), the save is refused with a notification "حفظ شخص آخر المسودة الآن. حدّث الصفحة لترى آخر نسخة." Not audited (drafts are not sensitive; publish is).
- After a save, the page dispatches the browser event `site-draft-saved` with the revision. The preview pane (Alpine, in the page's Blade, with the nonce'd script) sets the iframe `src` to `{previewBase}/{locale}/site-preview?t={token}&r={revision}` (+ `#b-{id}` when "show in preview" was used). It loads into a hidden second iframe and swaps the two when the new one loads, so the preview never flashes white.
- `previewBase`: `config('site.preview_url')` (env `SITE_PREVIEW_URL`) when set (local: `http://localhost:3101`), else `SiteSettings::pageUrl()`'s host (the academy's own host, production). Add `SITE_PREVIEW_URL` (empty) to `deploy.sh`'s `settings()` and to the README. `bin/api-up cms` should export `SITE_PREVIEW_URL=http://localhost:3101`.
- The token is issued at mount and renewed by the page every 90 minutes (`wire:poll` is not used: an Alpine timer calls `$wire.renewPreview()`, which is only a token, not data).
- Device widths: desktop 1280, tablet 820, phone 390 CSS px. The iframe is given that width and scaled down with `transform: scale()` to fit the pane (height = the pane's height ÷ scale). The language switch changes `/ar/` ↔ `/en/` in the URL.
- **Publish** (`PublishSitePage::handle(int $revision)`): `site.manage`; under `lockForUpdate`, the revision must equal `draft_revision`, else "the draft changed; review it again". It runs `PublishRules` and refuses on blockers (as a `ValidationException` keyed by no field, so `ShowRefusals` shows it). It copies the draft to `published`, sets `published_revision`, `published_at`/`published_by`, inserts `site_page_publishes`, and audits `site.page_published` with `{revision, blocks: count, types: [distinct types]}` (never the words). The confirmation modal lists warnings. If the page is off, the success notice says so with a button to switch it on («الصفحة مطفأة: شغّلها من صفحة الأكاديمية»).
- **Discard draft** (`DiscardSiteDraft`): sets draft = published (refused when nothing is published), `draft_revision` +1, audit `site.page_discarded`.
- **Apply template** (`ApplySiteTemplate::handle(string $key)`): draft = `Templates::make($key, audience, facts)`, audit `site.page_template_applied` with the key.
- **Restore** (`RestoreSitePage::handle(string $publishId)`): draft = that version, audit `site.page_restored` with the revision.
- Wording for the audit lives in `shared::audit.actions.site.*` (ar and en).

### 4.4 Templates (`Domain\Page\Templates`, pure, wording in `site::templates`)

All texts are MSA and name no academy (the page adds the name). Each template is a list of blocks with variants, tones and (d) fields left unset where the portal's default wording fits. Media fields are empty (the builder shows "add your image" where a variant needs one; the variant degrades gracefully until then: `split` without media renders like `centered`).

| key | audience | blocks in order |
|---|---|---|
| `online_courses` | adults | hero `split` (paper) · stats `band` · subjects `cards` (titled as courses) · features `icons` · teachers `portraits` · pricing `cards` · testimonials `grid` · faq `two_columns` · cta `banner` · form `centered` · contact `band` |
| `training` | adults | hero `video` (night) · logos `row` · features `list` · image_text `side` · steps `line` · stats `cards` · testimonials `single` · cta `split` · form `split` |
| `kids_families` | families | today's page exactly: hero `phones` · features `cards` · subjects · teachers · steps · app `phones` · pricing · testimonials `marquee` · faq · form `split` (`teach: true`) |
| `language_school` | adults | hero `centered` · subjects `chips` · features `cards` · teachers `cards` · gallery `masonry` · pricing `table` · faq `accordion` · form `split` |
| `blank` | current | hero `minimal` · form `centered` |

Settings per template: `online_courses` → mood `light`, motion `subtle`, header `solid`; `training` → mood `night` hero then `light`, motion `subtle`; `kids_families` → mood `light`, motion `full`, header `overlay`; `language_school` → mood `warm`, motion `subtle`.

---

## 5. Migration of existing content

### 5.1 Data migration (server)

Migration `2027_06_…_site_pages_from_settings.php`, run as `lms_owner`. It follows `2027_04_04_100000_site_wording_in_msa.php`: `alter table … no force row level security` around the loop, then `force` again. Do this for both `site_settings` (read) and `site_pages` (write).

For each `site_settings` row: `doc = Domain\Page\LegacyImport::fromContent($row->content merged over SiteContent::defaults())`. Insert `site_pages(draft = doc, published = doc, draft_revision = 1, published_revision = 1, published_at = now(), template = 'kids_families')`. Insert no `site_page_publishes` row (the history starts at the first real publish).

`LegacyImport` (pure, unit-tested) produces exactly today's page:
1. `hero` `phones`: title and lead from `content.hero`; kicker, trust, primary and secondary **not set** (d); tone `night`; `show` all true.
2. `features` `cards`, anchor `why`: title and lead from `content.why` (empty → not set (d)); items from `content.features` with icons in today's order (`users, report, calendar, map, shield, heart`, then `star`); tone `white`. Hidden when it has no items (today: the section shows only when features exist).
3. `subjects` `cards`, anchor `subjects`, tone `paper`; `hidden = !in_array('subjects', sections)`.
4. `teachers` `cards`, anchor `teachers`, tone `white`; hidden as above.
5. `steps` `line`, anchor `start`, items not set (d); hidden as above.
6. `app` `phones`, anchor `app`, tone `night`; hidden as above.
7. `pricing` `cards`, anchor `pricing`, tone `white`; hidden as above.
8. `testimonials` `marquee`, anchor `testimonials`; items from content; hidden as above (and the portal hides it when empty).
9. `faq` `accordion`, anchor `faq`, tone `white`; hidden as above.
10. `form` `split`, anchor `join`, tone `night`; `teach = in_array('teach', sections)`; heading and points not set (d).

Settings: audience from content; fonts null; accent `primary`; mood `light`; corners `brand`; motion `full`; header `overlay` with the default trial button; footer empty; whatsapp from content (`floating_whatsapp: true`); seo title and description from content, image null.

`down()`: drop the inserted rows (the tables' own migration drops the tables).

### 5.2 Fallback in code

`Models\SitePage::current()` returns the row, or an unsaved model whose `draft` and `published` are `LegacyImport::fromContent(SiteSettings::current()->said())`. So an academy made after the migration (no row; `NewAcademy` does not create one), or a test that only writes `site_settings`, renders today's page. The first `SaveSiteDraft` creates the row from that document. `DemoSiteSeeder` writes a `site_pages` row too (the `online_courses` template with demo media is a follow-up; for now the legacy import of the demo's content). `DemoCatchUpSeeder` needs no step: the migration covers servers that have a demo.

Old keys: `SiteContent` stays (the migration and `LegacyImport` use its `defaults()` and `localized()` helpers; `LegacyContent` too). `SiteContent::SECTIONS` stays as the list of legacy section names.

### 5.3 What happens to the old settings screen

Covered in 4.1. Language strings for the removed tabs stay until `content` is removed, so a rollback still has its words. The panel `site-settings.blade.php` stays, with one less save hint ("Shows on the page at once" now applies to on/off and trials only; the word changes to «يُطبَّق فورًا»).

---

## 6. Portal rendering (portal owns)

### 6.1 Structure

```
apps/web/src/components/site/page/
  site-page.tsx         // <SitePage site preview?>: header, blocks in order, footer, floating WhatsApp, JSON-LD
  block.tsx             // <Block> switch on type → component; wraps each in <section id={anchor} data-tone data-align data-space data-width>
  header.tsx  footer.tsx
  heading.tsx           // eyebrow / h2 / lead, with (d) defaults for the audience
  media.tsx             // <Picture>, <PageVideo>, <Facade> (YouTube/Vimeo), sizes, lazy rules
  rich.tsx              // Rich → React (p, h3, h4, ul, ol, blockquote, strong, em, a)
  link.tsx              // Link action → href (trial → #join, sign_in → /{locale}/sign-in, …) and rel/target
  blocks/hero.tsx features.tsx subjects.tsx teachers.tsx pricing.tsx steps.tsx app.tsx testimonials.tsx
         faq.tsx stats.tsx rich-text.tsx image-text.tsx gallery.tsx video.tsx logos.tsx cta.tsx form.tsx
         contact.tsx spacer.tsx
  page.css              // the builder's layer; imports nothing from academy-page; site.css stays for shared parts
apps/web/src/lib/site/page.ts        // the hand-written types (8.3) and pure helpers (defaults, link resolution)
apps/web/src/lib/site/page.test.ts
apps/web/src/app/[locale]/site-preview/page.tsx
```

`app/[locale]/page.tsx`: `if (academy?.page) return <SitePage site={academy} look={…} utm={…} chosenSubject={…} />`, else today's `AcademyPage`. `generateMetadata` reads `page.settings.seo` (title and description, the image as `openGraph.images` with width and height), falling back as today.

Reuse, don't fork: `JoinAcademyForm`, `AcademyPlans`, `AppShowcase`, `phones.tsx`, `Starfield`, `motion.tsx` (`Reveal`, `SolidOnScroll`, `Tilt`) and `icons.tsx` are used by the new blocks as they are. The `phones` hero and the legacy blocks must look **pixel-equal** to today's page (section 7 compares screenshots).

### 6.2 Design direction

The page has two characters. Each academy picks one through templates, variants and mood:

1. **Playful** (children and families): today's look. Night hero, aurora, starfield, phones, tilt, marquee. Kept as it is, through the `phones` hero and `motion: full`.
2. **Editorial** (online courses and training): the new default for adults. The idea is a well-made course prospectus, not a SaaS landing page. The academy's real photography and video carry the page. Type carries the hierarchy, and colour is used sparingly.

Editorial principles (what makes it look serious, not templated):
- **Type does the work.** Headings are large, set tight in Latin (`letter-spacing: -0.02em`) and never letter-spaced in Arabic. One family can serve both roles; two families must be clearly different (for example Readex Pro for headings and IBM Plex Sans Arabic for body). No all-caps labels. Eyebrows are **off by default** and, when on, they are sentence case in the accent colour, with no pill.
- **Scale** (modular, ratio 1.25, fluid with `clamp`), from `--fs-0` (body):
  `--fs--1: 0.875rem; --fs-0: clamp(1rem, 0.96rem + 0.2vw, 1.0625rem); --fs-1: 1.25rem; --fs-2: clamp(1.4rem, 1.2rem + 0.8vw, 1.6rem); --fs-3: clamp(1.75rem, 1.4rem + 1.4vw, 2.25rem); --fs-4: clamp(2.2rem, 1.6rem + 2.6vw, 3.25rem); --fs-5: clamp(2.6rem, 1.8rem + 4vw, 4.5rem)` (hero h1 = `--fs-5`, minimal hero `--fs-4`, h2 `--fs-4`, h3 `--fs-1`).
  Line height: Arabic body 1.8, Latin body 1.6, headings 1.2 (Arabic 1.3). Measure: prose `max-inline-size: 68ch` (`article`), leads `56ch`.
- **Spacing**: an 8-px base. Block padding `compact 48/40 px`, `normal 96/64`, `roomy 144/88` (desktop/phone, block-start and block-end). Widths: `narrow 720`, `normal 1180` (today's `ls-wrap`), `wide 1360`, `full 100%` with 16 px gutters on phones.
- **Tones** come from branding (`--brand`, `--neutral`, `--attention`, `--info` from the layout), never hard-coded hex values:
  - `paper`: `color-mix(--neutral 3%, white)`; with mood `warm`, `color-mix(--attention 6%, white)`.
  - `white`: white.
  - `tint`: `color-mix(--accent 7%, white)`.
  - `brand`: the accent at 100%, text white (or the darkest ink when the accent is light; the portal computes contrast at render with a small OKLCH lightness check in `lib/site/page.ts`, `onAccent(hex)`).
  - `night`: `color-mix(--accent 22%, #0a0f1a)`.
  `--accent` = the branding colour named by `settings.accent`. Mood `night` makes `paper` blocks render as `night`.
- **Images**: the academy's photos with no filters or duotone. Corners follow `corners` (the hero's `arch` shape is the one distinctive move: a tall arch mask on the split image, which fits Arabic architecture without clichés). Fixed aspect boxes from width and height, so nothing jumps (`aspect-ratio` from the API's sizes). `object-fit: cover`, with the focus in the middle.
- **Motion**: `subtle` = one orchestrated entrance in the hero (the title's lines rise 12 px and fade in over 600 ms, staggered 80 ms, then the media scales from 1.02 to 1) and quiet reveals for the rest (opacity only, 300 ms, no slide). `full` = today's. `none` = nothing. Everything sits inside `@media (prefers-reduced-motion: no-preference)`, uses transform and opacity only, and is whole without script (as `site.css` does now).
- **Buttons**: the primary is a solid accent with large, comfortable padding (min 48 px tall); the secondary is a text button with an underline that grows. No arrows appended to labels; Arabic direction would flip them anyway.

### 6.3 RTL/LTR and language

- Logical properties only (`margin-inline-start`, `inset-inline-end`, `text-align: start`). `media_side: start` is the reading start (right in Arabic). Carousels and marquees reverse with `dir`.
- Numbers typed by the academy are shown as typed. Live prices keep today's formatting (`perLesson`).
- A string that fell back to the other language carries no `lang` attribute in v1 (the API sends plain strings). This is a known gap, listed in 8.5.

### 6.4 Media rendering and performance

- Images: `<img src width height alt loading="lazy" decoding="async">`. The hero image gets `loading="eager" fetchpriority="high"`. No `next/image` optimisation (the files are on another server path, and the remote patterns would vary per academy); sizes are already capped at 2560 px by the upload. `sizes` hints are not needed without srcset.
- Uploaded video: `<video playsinline muted loop preload="none" poster=…>`. The hero `video` variant autoplays only when `prefers-reduced-motion: no-preference` and `navigator.connection?.saveData !== true` (a small client component sets `autoplay` after mount, so the server HTML has none). Other videos show the poster with a play button; `controls` appear on play. `<source>` carries the mime. A video never loads before it is near the viewport (`preload="none"` + IntersectionObserver to call `load()` for the hero's background).
- YouTube and Vimeo: a facade (poster + play button). On click, the iframe is inserted with `allow="autoplay; fullscreen; picture-in-picture"` and `title`. With no poster uploaded, the portal shows a neutral frame with the play button; it does not fetch provider thumbnails in v1 (simpler, private).
- Fonts: when `page.settings.fonts.url` is not null, `SitePage` adds `<link rel="stylesheet" href>` (the layout already loads the branding fonts) and sets `--font-head` / `--font-body` on `.ls`. Add `<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>`.
- Server rendered. Only these are client components: the header's solid-on-scroll, the video player, the carousel/single testimonials, the gallery lightbox, the preview scroll script, and the existing form and plans switcher.
- Target on a mid phone (Moto G-class, 4G throttled): LCP < 2.5 s on the demo page, CLS < 0.05, no long task over 200 ms from the page's own code.

### 6.5 Accessibility

- One h1 (1.3, hero). Each block's title is an h2. Item titles are h3.
- Landmarks: `header`, `main`, `footer`, and the header `nav` with an `aria-label`. A "skip to content" link first.
- Contrast: text on `brand` and `night` tones is checked with `onAccent()`. The video hero's scrim guarantees 4.5:1 for body text over the poster (use `strong` when unsure).
- The FAQ uses `details/summary` (as today). The gallery lightbox is a `<dialog>` with focus kept inside, Escape to close and the image's alt. Carousels have previous/next buttons with labels, are keyboard reachable, and have no autoplay. The marquee pauses on hover or focus, and under reduced motion it becomes a static grid.
- Videos: labelled play buttons. A background video is `aria-hidden` and has no sound. Uploaded videos may have no captions in v1 (no track upload); the hint asks academies to put the key words on screen.
- Focus visible on everything (today's `:focus-visible` style).

### 6.6 The default wording (the (d) fields)

The portal keeps its messages (`messages/{ar,en}.json`, `site.*`, `site.adults.*`) as the defaults, chosen by `settings.audience`. New keys for new blocks go under `site.blocks.{type}.*` (MSA, `messages.test.ts` passes). `lib/site/page.ts` exports `say(field, value, audience)`: the API string when not null, else the message key. Mapping:

| field | default key |
|---|---|
| hero.kicker | `site.kicker` |
| hero.trust | `site.trust` / `site.adults.trust` |
| hero.primary.label | `site.cta.trial` |
| hero.secondary.label | `site.cta.signIn` |
| features.title / lead | `site.why.title` / `site.why.lead` (adults variants) |
| subjects.title / lead | `site.subjects.title` / `site.subjects.lead` |
| subjects.ask_label | `site.subjects.ask` |
| teachers.title / lead | `site.teachers.title` / `.lead` |
| steps.title, items | `site.steps.title`, `site.steps.items` |
| app.title / lead / points | `site.app.title` / `.lead` / `.steps` |
| pricing.title / lead | `site.pricing.title` / `.lead`; `highlight_label` → `site.blocks.pricing.highlight` |
| testimonials.title | `site.testimonials.title` |
| faq.title | `site.faq.title` |
| form.title / lead / points | `site.join.title` / `.lead` or `.leadBook` / `.points` |
| contact.title | `site.blocks.contact.title` |
| header.cta.label | `site.cta.trialShort` |

The old eyebrows (`site.*.eyebrow`) are used only when `style.eyebrow` is true and the block's eyebrow field is not set.

### 6.7 SEO

- `generateMetadata`: title, description, `alternates` (as today), `openGraph` with `images: [{url, width, height}]` from `seo.image` (an absolute URL on the academy's host), `twitter.card = summary_large_image` when there is an image.
- JSON-LD in the page (a `<script type="application/ld+json" nonce>` built from data, `JSON.stringify` with `<` escaped as `<`): `EducationalOrganization` (name, url, logo, sameAs from footer.social) and `FAQPage` from a visible faq block.
- The preview is `noindex` (3.2, 3.3).

---

## 7. Tests and acceptance

### 7.1 Server (Pest, `tests/Feature/Site/*`, `tests/Unit/Site/*`)

Unit (pure):
- `PageDocTest`: normalize keeps known fields and drops unknown ones; lengths per field; a required pair needs one side; ids are kept, new ones are made and duplicates re-made; unknown types are dropped; unknown variants → the first; `Link` URL rules (javascript:, data:, http: refused; https, mailto, tel, /ar/… accepted); `Lines` absent vs `[]` kept apart; the 256 KB size cap.
- `RichTextTest`: TipTap in → only the allowed nodes and marks; links with bad schemes lose their href; the 60-node and 5,000-character limits.
- `PublishRulesTest`: each blocker and warning listed in 1.7.
- `LocalizeTest`: fallback to the other language; (d) → null; dropped links (no form, no WhatsApp, hidden target); media → `{url,width,height,alt,mime}` and missing → null.
- `LegacyImportTest`: today's default content and the demo content → the expected blocks (snapshot); hidden sections → hidden blocks; the features block hidden when empty; `teach` → `form.teach`.
- `LegacyContentTest`: `LegacyContent::from(LegacyImport::fromContent(x))` equals `SiteContent::localized(x)` for the defaults, the demo and a filled-in academy (round trip). This is the backwards-compatibility proof.
- `TemplatesTest`: every template passes `PublishRules` with no blockers; its words exist in both languages; no word "قرآن", "حفظ" or "تلاوة" appears in any template (a guard for the Quran removal).

Feature:
- `SitePageApiTest`: `GET v1/site` has `page` with only visible blocks, in order, localized for `Accept-Language: en`; `teachers` and `plans` empty unless a visible block asks; `only` ordering and filtering applied; `subjects` always full; `content` legacy shape unchanged for a migrated academy (compare to a fixture captured from base); 404 while off.
- `SitePreviewTest`: a valid token returns the draft while the page is off; another academy's token → 404; an expired one → 410; `X-Robots-Tag` and `no-store` present; `form_token` empty; `preview.revision` present.
- `SiteDraftTest`: `SaveSiteDraft` needs `site.manage`; a stale `baseRevision` is refused; no audit row. `PublishSitePage` refuses blockers, audits `site.page_published` without words, writes history, and needs the revision current. Discard, template and restore each audited.
- `SiteMediaTest`: PNG, JPEG, WebP, MP4 and WebM accepted by bytes; an SVG renamed `.png`, an HTML file renamed `.mp4`, a GIF and a PDF refused; over 5 MB or 50 MB refused; the 300-file cap; `CleanFile` called (fake scanner infected → refused); the stored path under `site/{academy}/media/`; delete refused while used by the draft, published page or kept history; `site:sweep` marks and then deletes after 7 days (time travel); the storage meter counts the bytes.
- `SiteMigrationTest`: run the data migration on rows written in the old shape (two academies, RLS forced) → a `site_pages` row each, published = draft = the legacy import.
- `SiteBuilderPageTest` (Livewire): the page renders for owner and admin, 403 for others; adding a block, cloning, hiding and reordering change the saved draft; publish from the page; the preview iframe URL uses `SITE_PREVIEW_URL` when set.
- Existing: `PublicSiteTest`, `SiteAdminTest` (adapt to settings without content tabs: the test changes because the screen changed, not to hide a regression), `SiteDemoTest`, `TenantIsolationSweepTest` (the new routes: another academy's token → 404), `HttpHardeningTest`, `NavGroupTest` (the new page's sort), `ArabicWordingTest` (new lang files), `ServerEnvTest` (`SITE_PREVIEW_URL`, `SITE_MEDIA_ABSOLUTE` in `deploy.sh`), the arch tests (Site → Platform only through `Contracts`).
- OpenAPI regenerated and committed; `oasdiff` shows additions only.

### 7.2 Portal (Vitest + Playwright)

Vitest (`lib/site/page.test.ts`, `components/site/page/*.test.tsx` where pure):
- Link resolution: trial → `#join`; sign_in → `/{locale}/sign-in`; whatsapp → `https://wa.me/…`; url external → `target=_blank rel=noopener`; bad schemes never rendered (defence in depth).
- `say()` defaults per audience; `null` vs `[]` for lists.
- Rich → elements: no `dangerouslySetInnerHTML` (grep test over `components/site/page`), links filtered.
- `onAccent()` contrast picks white or ink correctly for light and dark brand colours.
- `messages.test.ts` passes with the new keys.

Playwright (`apps/web/e2e/site-page.spec.ts`; the server's `E2eResetSeeder` gets a page with every block type and demo media (server owns the seeder; the portal owns the spec):
- The legacy demo page renders and matches the base screenshot at 1280 and 390 px (visual diff threshold 0.2%), in `ar` and `en`.
- A page made from `online_courses` with uploaded media: hero split image visible, no layout shift (CLS check via PerformanceObserver), the header menu lists blocks with `nav`, the form submits a trial.
- Hidden blocks are absent; reordered blocks render in the new order.
- Video hero: poster first; no autoplay under `reducedMotion: 'reduce'`.
- Preview: `/ar/site-preview?t=…` shows the draft bar, has `noindex`, the form does not send, and an expired token shows the calm page.
- Keyboard: tab through the header, the FAQ, the gallery lightbox (Escape closes), the carousel.
- axe-core on the demo page and the `online_courses` page: no serious or critical issues.

### 7.3 Acceptance with screenshots (both engineers, at the end)

With `bin/api-demo cms`, `bin/api-up cms`, `bin/web-up cms`, a Playwright script through `bin/shot` takes and the reviewer **looks at**:
1. `/ar` and `/en` of the migrated demo page at 1280 and 390, next to the same from `base` (port 3000/8000): identical.
2. The builder at `demo.localhost:8101/admin/site/builder`: the block list, an open block editor, the picker, the Page tab, the Media tab, the preview at the three widths, the publish confirmation with warnings.
3. Each template applied, published, and screenshot at 1280 and 390 in both languages (5 × 2 × 2).
4. Each block type and variant at 390 px in Arabic (one long screenshot per type).
5. Dark tones and `mood: night` with three brand colours: a light yellow, a mid blue and a near-black brand. The contrast holds.
6. A failure case: a page with every block hidden except the form, and a page with no form (the warning shown in the builder; no trial buttons on the page).

Done means: all tests green (apart from the failures that already exist on base, listed in the workspace notes), screenshots reviewed, `npm run lint`, `typecheck`, `test`, Pint, and an OpenAPI with no drift.

---

## 8. Split of work

### 8.1 Server engineer owns

- `apps/api/modules/Site/**`: migrations (`site_pages`, `site_page_publishes`, `site_media`, the data migration), `Domain/Page/*` (`PageDoc`, `Blocks` (types, variants, icons, limits), `RichText`, `Localize`, `PublishRules`, `MediaRefs`, `LegacyImport`, `LegacyContent`, `Templates`), `Models/{SitePage, SitePagePublish, SiteMedia}`, `Actions/{SaveSiteDraft, PublishSitePage, DiscardSiteDraft, ApplySiteTemplate, RestoreSitePage, UploadSiteMedia, UpdateSiteMedia, DeleteSiteMedia}`, `Support/PreviewLinks`, `Http/V1/Controllers/SiteController` (`show` + `preview`), `routes/api.php`, `Filament/Pages/SiteBuilder` + its Blade and the `MediaPicker` component, the slimmed `SiteSettings` page and `SaveSiteSettings`, `Commands/SweepJoinRequests` (or a new `SweepSiteMedia` called from `site:sweep`), `lang/{ar,en}/{site,templates,builder}.php`, `database/seeders/DemoSiteSeeder.php`, the provider (meter, setup step URL), `config` keys `site.preview_url` and `site.media_absolute`.
- `apps/api/modules/Platform/src/Contracts/BrandingApi.php` + its binding: `fontFamilies()` and `fontsUrl()` only.
- `apps/api/modules/SharedKernel/lang/*/audit.php`: the `site.*` actions.
- `apps/api/config/livewire.php` (new).
- `apps/api/database/seeders/E2eResetSeeder.php` (the e2e page with every block; small demo media files committed under `modules/Site/database/seeders/media/`, under 2 MB in total: two WebP photos, one logo PNG, one 3-second MP4).
- `apps/api/tests/**` for the above.
- `infra/deploy/Caddyfile` (the cache header), `infra/deploy/deploy.sh` (`SITE_PREVIEW_URL`, `SITE_MEDIA_ABSOLUTE` in `settings()`), the README's settings, `docs/architecture/02` section 24, `CLAUDE.md` (the Site line).
- `packages/openapi/openapi.json` and `apps/web/src/lib/api/schema.ts` (regenerated: `scramble:export`, move, `npm run api:types`).
- `/Users/ahmedmahdy/Desktop/progma/academy-lms-wt/bin/api-up`: export `SITE_PREVIEW_URL=http://localhost:3101` and `SITE_MEDIA_ABSOLUTE=true` for `cms` (tooling, not committed in the repo).

### 8.2 Portal engineer owns

Everything in `apps/web` **except** `src/lib/api/schema.ts`:
- `src/components/site/page/**` (new), `src/lib/site/page.ts` + tests, `src/lib/site/server.ts` (the `AcademySite` type gains `page?: SitePageData` and `preview?`; a new `academySitePreview(token)`), `src/app/[locale]/page.tsx` (switch), `src/app/[locale]/site-preview/page.tsx` (new), `src/proxy.ts` and `src/lib/csp.ts` (the preview's public path and frame-ancestors), `next.config.ts` (exclude the preview from the static frame headers), `src/components/site/icons.tsx` (new icon names), `messages/{ar,en}.json` (`site.blocks.*`, `site.preview.*`), `e2e/site-page.spec.ts`, `.env.example` (`SITE_PREVIEW_FRAME_ANCESTORS`).
- Leave `academy-page.tsx` and `site.css` working as they are (the fallback). Shared visual parts may be imported from them, but they must not change their rendering.

### 8.3 Hand-written TS types (portal, `src/lib/site/page.ts`)

The portal declares these and does not wait for generated types. The server must emit exactly this.

```ts
export type Tone = 'paper' | 'white' | 'tint' | 'brand' | 'night';
export type BlockStyle = { tone: Tone; align: 'start' | 'center'; space: 'compact' | 'normal' | 'roomy'; width: 'narrow' | 'normal' | 'wide' | 'full'; eyebrow: boolean };

export type Media = { url: string; width: number | null; height: number | null; alt: string; mime: string };

export type Action =
  | { kind: 'trial' } | { kind: 'teach' } | { kind: 'sign_in' } | { kind: 'whatsapp' }
  | { kind: 'block'; block: string /* the target's anchor */ }
  | { kind: 'url'; url: string };
export type PageLink = { label: string /* '' = default wording */; action: Action };

export type Inline = { text: string; b?: true; i?: true; href?: string };
export type RichNode =
  | { t: 'p' | 'h3' | 'h4' | 'quote'; c: Inline[] }
  | { t: 'ul' | 'ol'; items: Inline[][] };

type Heading = { eyebrow: string | null; title: string | null; lead: string | null };
type Base<T extends string, V extends string, D> = { id: string; type: T; variant: V; anchor: string; nav: string | null; style: BlockStyle; data: D };

export type HeroBlock = Base<'hero', 'centered' | 'split' | 'video' | 'phones' | 'minimal', {
  kicker: string | null; title: string; lead: string | null;
  primary: PageLink | null; secondary: PageLink | null; trust: string[] | null;
  media: Media | null; video: Media | null; poster: Media | null;
  media_side: 'start' | 'end'; media_shape: 'rounded' | 'arch' | 'circle' | 'bleed'; scrim: 'light' | 'medium' | 'strong';
  show: { kicker: boolean; lead: boolean; buttons: boolean; trust: boolean; media: boolean };
}>;
export type FeaturesBlock = Base<'features', 'cards' | 'icons' | 'list' | 'numbers', Heading & {
  items: { icon: string; title: string; text: string }[]; columns: 2 | 3;
}>;
export type SubjectsBlock = Base<'subjects', 'cards' | 'list' | 'chips', Heading & {
  subject_ids: string[]; show_levels: boolean; show_programs: boolean; show_categories: boolean; ask_label: string | null;
}>;
export type TeachersBlock = Base<'teachers', 'cards' | 'portraits' | 'compact', Heading & {
  show_bio: boolean; show_languages: boolean; show_ages: boolean; show_badges: boolean;
}>;
export type PricingBlock = Base<'pricing', 'cards' | 'table', Heading & {
  highlight: string | null; highlight_label: string | null; per_lesson: boolean; note: string | null;
}>;
export type StepsBlock = Base<'steps', 'line' | 'cards', Heading & { items: { title: string; text: string }[] | null }>;
export type AppBlock = Base<'app', 'phones' | 'image' | 'plain', Heading & {
  points: { title: string; text: string }[] | null; show_code: boolean; show_stores: boolean; media: Media | null;
}>;
export type TestimonialsBlock = Base<'testimonials', 'marquee' | 'grid' | 'single', Heading & {
  items: { name: string; role: string | null; quote: string; photo: Media | null; rating: number }[];
}>;
export type FaqBlock = Base<'faq', 'accordion' | 'two_columns', Heading & { items: { q: string; a: string }[] }>;
export type StatsBlock = Base<'stats', 'band' | 'cards', Heading & { items: { value: string; label: string }[] }>;
export type RichTextBlock = Base<'rich_text', 'article' | 'two_columns', Heading & { body: RichNode[] }>;
export type ImageTextBlock = Base<'image_text', 'side' | 'overlap' | 'stacked', Heading & {
  body: RichNode[]; media: Media | null; video: Media | null; poster: Media | null;
  media_side: 'start' | 'end'; bullets: string[]; link: PageLink | null;
}>;
export type GalleryBlock = Base<'gallery', 'grid' | 'masonry' | 'carousel', Heading & {
  items: { media: Media; caption: string | null }[]; ratio: 'auto' | '1:1' | '4:3' | '3:2' | '16:9';
}>;
export type VideoSource =
  | { kind: 'upload'; video: Media; poster: Media | null }
  | { kind: 'youtube'; id: string; poster: Media | null }
  | { kind: 'vimeo'; id: string; poster: Media | null };
export type VideoBlock = Base<'video', 'framed' | 'wide' | 'side', Heading & {
  source: VideoSource; caption: string | null; ratio: '16:9' | '4:3' | '1:1' | '9:16'; autoplay_muted: boolean;
}>;
export type LogosBlock = Base<'logos', 'row' | 'marquee' | 'grid', Heading & {
  items: { media: Media; name: string; url: string | null }[]; mono: boolean;
}>;
export type CtaBlock = Base<'cta', 'banner' | 'card' | 'split', {
  title: string; lead: string | null; primary: PageLink | null; secondary: PageLink | null; media: Media | null;
}>;
export type FormBlock = Base<'form', 'split' | 'centered', Heading & { points: string[] | null; teach: boolean; whatsapp_button: boolean }>;
export type ContactBlock = Base<'contact', 'cards' | 'band', Heading & {
  email: string | null; phone: string | null; address: string | null; hours: string | null; whatsapp: boolean;
}>;
export type SpacerBlock = Base<'spacer', 'space' | 'divider', { size: 'sm' | 'md' | 'lg' }>;

export type PageBlock =
  | HeroBlock | FeaturesBlock | SubjectsBlock | TeachersBlock | PricingBlock | StepsBlock | AppBlock
  | TestimonialsBlock | FaqBlock | StatsBlock | RichTextBlock | ImageTextBlock | GalleryBlock | VideoBlock
  | LogosBlock | CtaBlock | FormBlock | ContactBlock | SpacerBlock;

export type PageSettings = {
  audience: 'families' | 'adults';
  fonts: { heading: string; body: string; url: string | null };
  accent: 'primary' | 'attention' | 'info';
  mood: 'light' | 'warm' | 'night';
  corners: 'brand' | 'sharp' | 'soft' | 'round';
  motion: 'full' | 'subtle' | 'none';
  header: { style: 'overlay' | 'solid' | 'minimal'; cta: PageLink | null; sign_in: boolean; language_switch: boolean };
  footer: { text: string | null; links: { label: string; url: string }[]; social: { network: string; url: string }[] };
  whatsapp: string | null;
  floating_whatsapp: boolean;
  seo: { title: string | null; description: string | null; image: Media | null };
};

export type SitePageData = { revision: number; settings: PageSettings; blocks: PageBlock[] };
export type SitePreviewInfo = { revision: number; published_revision: number | null; enabled: boolean };
```

Rules both sides keep: the JSON fields named above are always present (null when empty), never missing. Unknown block types (a newer server) are skipped by the portal's `<Block>` switch, never an error. `Heading.title` null → the default for blocks that have one, else no h2. Ids and anchors are safe for `id=` (`[a-z0-9-]`).

### 8.4 Order and independence

- **Day 1, both:** the portal builds against a fixture, `apps/web/src/lib/site/fixtures/page.json` (every block type, written by the portal engineer from 8.3), rendered by a dev-only route behind `NODE_ENV === 'development'`, or by Vitest with screenshots through `bin/shot`. The server builds the domain, the API and the builder.
- **Hand-off point:** once the server commits `GET v1/site/preview` in `packages/openapi` and `schema.ts`, the portal switches its preview from the fixture to `call('get', '/v1/site/preview', …)`. Until then the preview page reads the fixture when `token === 'fixture'` (dev only). That code is removed before merge.
- Neither engineer edits the other's files. A contract question goes into this document (a commit to `docs/architecture/site-builder.md` with a "contract change:" subject), and the other engineer reads it before merging.

### 8.5 Not in this work

Per-string `lang` attributes for fallback text; caption tracks for videos; server-side image resizing or srcset (no GD in the image); A/B tests; scheduled publishing; multiple pages per academy (only the home page; the block model allows `/{locale}/p/{slug}` later); custom CSS or HTML (never: security); the platform's own page (Platform's `SiteContent`, unchanged).

---

## 9. Decisions during build

### 9.1 Portal (contract changes for the server marked **contract**)

- **contract: the migrated page keeps its menu and its small labels.** Today's header lists «لماذا نحن», «المواد», «المعلّمون», «الأسعار», «أسئلة», and every section shows its small label (eyebrow) above the heading. For the migrated page to render the same, `LegacyImport` sets on those blocks:
  - `nav` = the old menu words: features (anchor `why`) «لماذا نحن» / "Why us", subjects «المواد» / "Subjects", teachers «المعلّمون» / "Teachers", pricing «الأسعار» / "Pricing", faq «أسئلة» / "FAQ" (the portal's `site.nav.*` today). Steps, app, testimonials and form get no `nav` (they were not in the menu).
  - `style.eyebrow = true` on every migrated block except the hero, with the `eyebrow` text left unset (the portal then shows the old label, `site.{section}.eyebrow`, 6.6).
  The `kids_families` template does the same.
- The portal draws a block in one of two looks. The legacy variants (`hero.phones`, `features.cards`, `subjects.cards`, `teachers.cards`, `steps.line`, `app.phones`, `pricing.cards`, `testimonials.marquee`, `faq.accordion`, `form.split`) reuse today's markup and `site.css` classes, so a migrated page is pixel-equal. Every other variant is drawn by `components/site/page/page.css` (`sp-*`). A page is **playful** when `motion` is `full` or it has a `phones` hero; otherwise **editorial** (the root carries `data-character`), and the shared legacy parts are toned down under editorial (no gradient icons, no glowing title).
- The header: `overlay` over a dark first block (night, brand with light ink, or a video hero) is today's header. `overlay` over a light first block keeps dark ink and turns white on scroll. `solid` is a sticky bar in the page's paper. `minimal` is the logo and one button. When the first block is not a hero, `overlay` acts as `solid`.
- The h1: the hero's title; with no hero, the first block's title; else a visually hidden h1 with the academy's name.
- The preview's form: the inputs work, sending is stopped in the browser (a capture handler) with a note «المعاينة لا ترسل الطلبات».
- The JSON-LD script and the preview's scroll script live outside `components/site/page` (`components/site/page-scripts.tsx`), so the "no `dangerouslySetInnerHTML` under `components/site/page`" test stays strict.
- Pricing per lesson and the plans' wording moved from `academy-page.tsx` into `components/site/plans.ts` (shared by both pages; no change in rendering).
