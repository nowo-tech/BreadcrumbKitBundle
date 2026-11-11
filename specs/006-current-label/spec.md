# Feature Specification: Dynamic label of the current crumb

**Feature Branch**: `feat/dynamic-current-label`  
**Status**: Implemented (Unreleased, target 2.17.0)  
**Created**: 2026-11-11  
**Depends on**: [`001-baseline`](../001-baseline/spec.md)  
**Upstream request**: symfony-beacon — a Wiki page shows the item label "Page" instead of the page title

---

## Summary

The page being rendered can name the current (last) crumb for the current request, from Twig or from PHP. Without a
label the trail is unchanged.

## User scenarios

- **US-01 (P1)** — Given a child template calling `{% do breadcrumb_current_label(page.title) %}` outside its blocks, when the layout renders the trail before or after the content block, then the last crumb shows the page title.
- **US-02 (P1)** — Given a controller or application service calling `BreadcrumbCurrentLabelInterface::setCurrentLabel()`, then the last crumb shows that label.
- **US-03 (P1)** — Given a label containing HTML, then it is printed as escaped text.
- **US-04 (P1)** — Given one kernel serving many requests (FrankenPHP worker, `kernel.reset` never called), then the label of one request never reaches another one, nor a command or a Messenger message.
- **US-05 (P2)** — Given a translation domain (or a `TranslatableInterface`), then the label is translated in the request locale.

## Requirements

- **FR-001** `Service\BreadcrumbCurrentLabelInterface` (`setCurrentLabel(string|Stringable|TranslatableInterface|null $label, ?string $translationDomain = null, array $translationParameters = [])`, `clearCurrentLabel()`), aliased to `Service\BreadcrumbCurrentLabel`.
- **FR-002** Twig `breadcrumb_current_label(label, domain = null, parameters = {})`, outputs nothing.
- **FR-003** `BreadcrumbLoader` replaces only the label of the last crumb of a matched trail, before `BreadcrumbTrailBuiltEvent`; link, icon and parameters stay.
- **FR-004** Plain one-line text: control characters and line breaks become spaces, whitespace collapses, invalid UTF-8 is dropped, at most 255 characters; HTML is escaped on output, never stripped. Null or blank restores the item label.
- **FR-005** Cleared on main `kernel.request`, `kernel.exception`, `kernel.terminate`, `console.command`, `console.terminate` and Messenger `WorkerMessageReceived/Handled/Failed`; applied only while the main request it was set for is current; `reset()` also clears it.

## Success criteria

- **SC-001** Twig tests cover both layout orders and the documented late-block constraint.
- **SC-002** A kernel test serves many requests (PHP page, Twig page, failing page, no terminate) and a console command on one booted kernel.
- **SC-003** Coverage stays at 100 %; Igor reports no state warning.
