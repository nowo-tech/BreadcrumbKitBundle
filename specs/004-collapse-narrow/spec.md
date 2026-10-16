# Feature Specification: Collapse on narrow screens

**Feature Branch**: `004-collapse-narrow`  
**Status**: Implemented (v2.6.0)  
**Created**: 2026-10-16  
**Depends on**: [`001-baseline`](../001-baseline/spec.md)  
**Upstream request**: symfony-beacon — GitHub-style breadcrumbs on phones

---

## Summary

Long trails wrap onto several lines (or scroll) on phones. This opt-in mode collapses them GitHub-style: below a width breakpoint only a **"…"** disclosure, one separator and the **current crumb** stay visible; the "…" opens a dropdown with every previous crumb. Above the breakpoint the trail is unchanged. With the option off, the HTML is byte-identical to 2.5.0.

## User scenarios

- **US-01 (P1)** — Given a trail of 6 crumbs and the option enabled, when the page is viewed on a 390px phone, then the trail shows "…", a separator and the current page on one line (ellipsis if too long).
- **US-02 (P1)** — Given the collapsed trail, when the user activates "…" (pointer, Enter or Space), then a dropdown lists the previous crumbs in order with their links and icons; non-link crumbs appear as text.
- **US-03 (P1)** — Given the open dropdown, when the user presses Escape, clicks outside or chooses a link, then it closes (Escape returns focus to "…"). Without JavaScript the native disclosure still opens and closes.
- **US-04 (P2)** — Given a host with its own colours, when it sets `--breadcrumb-kit-collapse-*` custom properties, then the toggle and dropdown use them in light and dark mode.
- **US-05 (P1)** — Given an existing app that does not enable the option, when it upgrades, then the trail HTML is unchanged.
- **US-06 (P2)** — Given a global default, when a collection sets `responsiveConfig.collapse`, then that collection uses its own value.

## Requirements

- **FR-001** `presentation.collapse` (`canBeEnabled`): `enabled` (false), `min_items` (int ≥ 2, default 3), `breakpoint` (`px`/`rem`/`em` length or integer pixels, default `40rem`; anything else rejected at container build). Resolved into `BreadcrumbCollapse` (constructor validates; it is printed into CSS) and injected into `BreadcrumbLoader` (`$defaultCollapse`).
- **FR-002** Per collection `responsiveConfig.collapse`: boolean (toggle, keep global values) or object `{enabled?, min_items?, breakpoint?}` merged key by key; invalid stored values are ignored, never fatal. `BreadcrumbTrailView::$collapse` + `isCollapsible()` (`enabled` and `count(nodes) >= minItems`; Twig `trail.collapsible`).
- **FR-003** Markup (only when collapsible): wrapper `data-breadcrumb-collapse="<breakpoint>"`; `li.<classItem>.breadcrumb-collapse` inserted before the separator of the current crumb, containing `details.breadcrumb-collapse__details[data-bk-collapse]` > `summary.breadcrumb-collapse__toggle` (`aria-label`/`title` `breadcrumb.collapse.toggle`, visible "…" `aria-hidden`) + `ol.breadcrumb-collapse__menu[role=list]` (`aria-label` `breadcrumb.collapse.menu`) of `li.breadcrumb-collapse__entry` with `a.breadcrumb-collapse__link` or `span.breadcrumb-collapse__text`, icon (home icon for the first crumb) and `span.breadcrumb-collapse__label`. All normal items stay in the DOM.
- **FR-004** Styles: one `<style>` with the request `csp_nonce`; container `breadcrumb-kit / inline-size` on the wrapper, `@container breadcrumb-kit (width < bp)` with `@supports not (container-type: inline-size)` → `@media not all and (min-width: bp)` fallback; narrow = single non-wrapping row, `overflow: visible`, only collapse item + following separator + current visible, current truncated with ellipsis; wide = collapse item hidden; dropdown absolutely positioned under the toggle, wrapper lifted (`position: relative; z-index: var(--breadcrumb-kit-collapse-z, 30)`) via `:has(details[open])` because the size container is its own stacking context; `--breadcrumb-kit-collapse-*` tokens with system-colour fallbacks; open animation only under `prefers-reduced-motion: no-preference`.
- **FR-005** `js/breadcrumb-collapse.js` (external, `defer`, delegated, bound once, no inline handlers): Escape closes and focuses the toggle; outside click closes; link click closes.
- **FR-006** i18n: `breadcrumb.collapse.toggle`, `breadcrumb.collapse.menu` in en, es, de, fr, it, nl, pt.
- **FR-007** Overridable partials `_breadcrumb_collapse.html.twig` and `_breadcrumb_collapse_assets.html.twig`; hosts that override `breadcrumb.html.twig` add the three hooks (documented in USAGE).

## Success criteria

- **SC-001** `BreadcrumbCollapseTemplateTest` renders 8 trails (disabled, below `min_items`, inline edit, default classes, single crumb, empty) through the 2.5.0 template and the new one and asserts identical output.
- **SC-002** Template, DTO, loader and configuration tests cover markup order, menu entries, icons, non-link crumbs, escaping, nonce, breakpoint validation and per-collection overrides.
- **SC-003** Demo (`/demo/deep/…`, `default` collection `responsiveConfig.collapse`) verified in headless Chromium at 390px (only "…" + separator + current visible, menu lists 7 previous crumbs, Escape closes and refocuses) and 1200px (full trail).
- **SC-004** `make release-check` green.

## Out of scope

- Playwright e2e in the demo (the demo has no Playwright setup yet, REQ-DEMO-013 pending for this bundle).
- Collapsing intermediate crumbs while keeping the root visible (GitHub keeps only the current page).
