# Changelog

All notable changes to `capell-app/structured-content-library` will be documented in this file.

## Unreleased

## 2026-06-03

- Security: sanitise structured content summaries through the portable HTML guard during create and update actions, rejecting scripts, inline event handlers, and designed markup before storage.
- Implemented real `StructuredContentLibraryHealthCheck` diagnostics for storage, model registration, protected table registration, and admin resource registration.
- Rewrote marketplace summary and package descriptions (`capell.json` and `composer.json`) to describe the typed, theme-safe content library.
