# Open to Work — Design System Rules

## Overview

**Stack:** Laravel 12 (backend API) + Vue 3 SPA (frontend) + TypeScript + Tailwind CSS 3.4 + Vite 5  
**Architecture:** Monorepo — `backend/` (Laravel), `frontend/` (Vue 3 SPA), `infra/` (Docker)  
**Auth:** Laravel Sanctum (cookie-based SPA auth)

---

## 1. Token Definitions

### Colors

Tokens are defined in `frontend/tailwind.config.js` under `theme.extend.colors`. Two semantic palettes:

| Token    | Role                | Base hex    |
|----------|---------------------|-------------|
| `brand`  | Primary / accent    | `#10b981` (emerald/green) |
| `ink`    | Neutral / text / bg | `#64748b` (slate)  |

Each has shades 50–950. Additionally, semantic Tailwind defaults are used for status:
- **Success:** `emerald-*`
- **Danger:** `red-*`
- **Warning:** `amber-*`
- **Info:** `blue-*`, `sky-*`

```js
// frontend/tailwind.config.js
colors: {
  brand: { 50: '#ecfdf5', /* ... */ 600: '#059669', 950: '#022c22' },
  ink:   { 50: '#f8fafc', /* ... */ 900: '#0f172a', 950: '#020617' },
}
```

### Typography

- **Font:** Inter (400, 500, 600, 700, 800) from Google Fonts CDN
- **Feature settings:** `'cv02', 'cv03', 'cv04', 'cv11'` (stylistic alternates)
- **Headings:** `letter-spacing: -0.02em`
- **Font smoothing:** antialiased + grayscale + optimizeLegibility
- **Families:** `font-sans` and `font-display` both map to Inter

### Shadows

```js
boxShadow: {
  soft: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.06)',
  card: '0 1px 3px 0 rgb(15 23 42 / 0.06), 0 4px 12px -2px rgb(15 23 42 / 0.08)',
  glow: '0 10px 40px -12px rgb(79 70 229 / 0.45)',
}
```

### Border Radius

```js
borderRadius: { xl: '0.875rem', '2xl': '1.25rem' }
```

### Background Patterns

- `bg-grid-slate` — subtle SVG grid pattern (32×32px)
- `bg-gradient-hero` — radial + linear gradient for hero/dark sections

---

## 2. Component Library

### CSS Component Classes (frontend/src/style.css)

No separate component library — reusable patterns defined as Tailwind `@layer components`:

| Class          | Purpose                           |
|----------------|-----------------------------------|
| `.btn`         | Base button (flex, rounded-lg, gap-2, focus ring) |
| `.btn-primary` | Brand-600 bg, white text          |
| `.btn-secondary` | White bg, ink-200 border        |
| `.btn-ghost`   | Transparent, ink-700 text         |
| `.btn-danger`  | Red-600 bg, white text            |
| `.card`        | Rounded-xl, border ink-200, white bg, soft shadow |
| `.input`       | Full-width text input, focus ring brand-500 |
| `.label`       | Block text-sm font-medium ink-700 |
| `.chip`        | Pill badge, ink-100 bg            |
| `.chip-brand`  | Pill badge, brand-50 bg           |

### Key Patterns

```vue
<!-- Button usage -->
<button class="btn-primary">Save</button>
<button class="btn-secondary">Cancel</button>

<!-- Card usage -->
<div class="card p-6">Content here</div>

<!-- Form fields -->
<label class="label">Email</label>
<input class="input" type="email" />

<!-- Chips/badges -->
<span class="chip">Status</span>
<span class="chip-brand">Featured</span>
```

### Component Architecture

- **No shared component files** — UI built inline with Tailwind utilities + CSS component classes
- **Vue 3 Composition API** (`<script setup lang="ts">`) everywhere
- **Single-File Components** (.vue) with `<script setup>`, `<template>`, no `<style>` blocks
- Styling is 100% Tailwind utility classes or the component classes above

---

## 3. Frameworks & Libraries

| Layer            | Technology                  | Version |
|------------------|-----------------------------|---------|
| Framework        | Vue 3                       | ^3.5.0  |
| Language         | TypeScript                  | ^5.5.3  |
| Routing          | vue-router                  | ^4.4.0  |
| State (client)   | Pinia                       | ^2.2.2  |
| State (server)   | TanStack Vue Query          | ^5.56.0 |
| State machines   | XState                      | ^5.18.0 |
| HTTP             | Axios                       | ^1.7.7  |
| Validation       | Zod                         | ^3.23.0 |
| i18n             | vue-i18n                    | ^10.0.0 |
| CSS              | Tailwind CSS                | ^3.4.10 |
| Build            | Vite                        | ^5.4.0  |
| PDF              | jspdf + pdf-lib + html2canvas | —     |

---

## 4. Asset Management

- **No local image assets** — company logos loaded from API (`job.company?.logo_url`)
- **Fonts:** Google Fonts CDN (Inter)
- **No CDN for static assets** — Vite bundles everything to `dist/assets/`
- **No image optimization pipeline**
- **Favicon:** only in `backend/public/favicon.ico`

---

## 5. Icon System

**Approach:** Inline SVG path strings — no icon library installed.

Icons are defined as SVG `d` attribute strings and rendered inline:

```vue
<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="item.icon" />
</svg>
```

**Conventions:**
- 24×24 viewBox
- Stroke-based (not filled)
- `stroke="currentColor"` for color inheritance
- `stroke-width="1.75"` for nav icons, `"2"` for UI icons
- Sizes: `h-4 w-4` (small), `h-5 w-5` (default), `h-6 w-6` (large)
- Style is Heroicons Outline compatible

**When adding new icons:** use the same inline SVG pattern with Heroicons Outline paths.

---

## 6. Styling Approach

### Methodology

**Utility-first Tailwind CSS** with a thin component class layer.

- All styling via Tailwind utility classes in templates
- No `<style>` blocks in Vue SFCs
- No CSS Modules, no Styled Components, no scoped styles
- Component-level classes (`.btn`, `.card`, etc.) defined in `frontend/src/style.css`

### Global Styles

File: `frontend/src/style.css`
- `@layer base` — font smoothing, body defaults, heading letter-spacing
- `@layer components` — reusable component classes (buttons, cards, inputs, chips)

### Responsive Design

- **Mobile-first** approach using Tailwind breakpoint prefixes
- Breakpoints: `sm:` (640px), `md:` (768px), `lg:` (1024px)
- Current layout: sidebar hidden on mobile, shown on `lg:`
- Container: `max-w-7xl` with responsive padding (`px-4 sm:px-6 lg:px-10`)

### Accessibility Patterns

```
focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2
disabled:opacity-60 disabled:cursor-not-allowed
```

---

## 7. Project Structure

```
frontend/src/
├── App.vue                          # Root: <RouterView /> only
├── main.ts                          # Entry: Vue + Pinia + Router + i18n + Query
├── style.css                        # Tailwind base + component classes
├── router/index.ts                  # Routes with guards
├── shared/
│   ├── api/
│   │   ├── client.ts                # Axios instance + CSRF
│   │   └── schemas.ts               # Zod schemas for API types
│   └── layouts/
│       └── AppLayout.vue            # Main app layout (sidebar + content)
├── locales/                         # i18n: pt-BR.json, en.json, es.json
└── modules/                         # Feature modules
    ├── auth/       (stores, views)  # Login, Register, OAuth
    ├── landing/    (views)          # Marketing page
    ├── metrics/    (composables, views) # Dashboard
    ├── jobs/       (composables, views) # Job listing
    ├── applications/ (composables, machines, views) # Kanban + detail
    ├── resumes/    (composables, templates, views)  # Builder + export
    ├── profile/    (composables, views) # User profile
    └── account/    (composables, views) # Settings
```

### Module Pattern

Each module under `modules/` contains:
- `views/` — page-level Vue components (lazy-loaded by router)
- `composables/` — `use*` hooks for data fetching (TanStack Query) and mutations
- `stores/` — Pinia stores (only auth module)
- `machines/` — XState machines (only applications module)
- `templates/` — Reusable sub-components (only resumes module)

### Path Alias

`@` resolves to `frontend/src/` (configured in `vite.config.ts` and `tsconfig.json`).

---

## 8. Figma-to-Code Translation Rules

When converting Figma designs for this project:

1. **Use Vue 3 `<script setup lang="ts">`** — never Options API
2. **Use Tailwind utility classes only** — no inline styles, no `<style>` blocks
3. **Map colors to tokens:** Figma indigo → `brand-*`, Figma slate/gray → `ink-*`
4. **Use existing component classes** (`.btn-primary`, `.card`, `.input`, `.label`, `.chip`) before creating new ones
5. **Icons:** inline SVG with `stroke="currentColor"`, 24×24 viewBox, Heroicons Outline style
6. **Responsive:** mobile-first, use `sm:`, `md:`, `lg:` prefixes
7. **Spacing:** use Tailwind scale (`gap-2`, `p-4`, `space-y-1`, etc.)
8. **Text:** use `text-sm`, `text-xs`, `font-medium`, `font-semibold` etc.
9. **Shadows:** prefer `shadow-soft` or `shadow-card` over custom shadows
10. **Border radius:** prefer `rounded-lg` (buttons/inputs) or `rounded-xl` (cards)
11. **Internationalization:** all user-facing strings must use `t('key')` from vue-i18n
12. **Data fetching:** use TanStack Vue Query composables (`useQuery`, `useMutation`)
13. **Validation:** use Zod schemas for API response parsing
14. **No raw hex colors** — always use token-based Tailwind classes

### Color Mapping Quick Reference

| Design Intent     | Tailwind Class           |
|-------------------|--------------------------|
| Primary button    | `bg-brand-600 text-white` |
| Primary hover     | `hover:bg-brand-700`     |
| Page background   | `bg-ink-50`              |
| Card background   | `bg-white`               |
| Body text         | `text-ink-900`           |
| Secondary text    | `text-ink-500`           |
| Muted text        | `text-ink-400`           |
| Borders           | `border-ink-200`         |
| Focus ring        | `ring-brand-500`         |
| Success           | `text-emerald-600`       |
| Danger            | `text-red-600`           |
| Warning           | `text-amber-600`         |

---

## 9. Current Layout Architecture

The current layout (`shared/layouts/AppLayout.vue`) uses a **top navbar** pattern:
- Sticky top bar (h-16, bg-white/80 with backdrop-blur) with brand + horizontal nav + user menu
- Mobile: hamburger menu opens a dropdown panel with nav links
- Main content area: `max-w-7xl` centered container on `bg-ink-100` background

### Navigation Structure (6 items)
- Dashboard, Jobs, Applications, Resumes, Profile, Account
- Active state: `bg-brand-50 text-brand-700`
- User dropdown menu (desktop) with avatar initials + logout

---

## 10. Development Environment

- **Docker-only** — never suggest local config; missing extensions go in Dockerfile
- Dev server: `npm run dev` (port 5173, proxies API to :8000)
- Type check: `npm run type-check`
- Lint: `npm run lint:fix`
- Test: `npm run test`
- Build: `npm run build`
