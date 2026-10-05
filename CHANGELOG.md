# Changelog

All notable changes to the Ichronoz plugin will be documented in this file.

The format is based on Keep a Changelog, and this project adheres to Semantic Versioning when possible.

## [3.1.6] - 2026-10-05

### Changed
- Prevented `to` from being earlier than or equal to `from` by deriving a valid checkout date automatically.

## [3.1.5] - 2026-10-05

### Changed
- Updated booking URL handling to use the WordPress site date when `from` is missing.
- Normalized past `from` dates against the server-provided WordPress site date.
- Added support for both `nite` and `night` URL parameters when specifying the stay duration, with a one-night default.

## [3.1.4] - 2026-10-02

### Changed
- Updated the two-stage search and booking funnel to preserve the `journey_id` returned by the availability search and send it back with single-room and multi-room booking submissions.

## [3.1.3] - 2026-09-28

### Changed
- Updated booking result tabs to prioritize accommodation types with `serviceTypeFlag="checkin-out"`, then activate the option with the lowest valid nightly price by default, including after a new search.

### Fixed
- Updated eligible room promo badges to respect the configured minimum searches, minimum time on page, and delay after search while remaining independent from popup dismissal and session-frequency rules.
- Fixed the collapsed rooms carousel launcher stretching across the viewport or using the wrong edge when configured for a left or top position.
- Fixed promo badges remaining hidden because searches initiated from the standalone search form were not included in the session search count.

## [3.1.2] - 2026-09-28

### Changed
- Updated `[ichronoz_rooms_carousel]` to use the configured primary color consistently for its button, launcher, indicators, focus states, and shadows without relying on delayed JavaScript-generated styles.
- Updated `[ichronoz_rooms_carousel]` to remain hidden on pages containing `[ichronoz_booking_page]`, `[ichronoz_booking_multi]`, or `[ichronoz_booking]`.

## [3.1.1] - 2026-09-27

### Added
- Added configurable promotion suggestions for eligible API offers, including per-room promotion badges, session-based display limits, dismissal cooldowns, delayed display, and safe promotional HTML rendering.
- Added the `[ichronoz_rooms_carousel]` shortcode with an independent React mount, availability API service, autoplay controls, dismissible presentation, configurable labels, and viewport positioning.
- Added WordPress settings for promotion suggestions and the rooms carousel, including shortcode preview support.

### Changed
- Redesigned the `[ichronoz_room_list]` carousel with availability counts, loading skeletons, empty and error states, updated room cards, clearer pricing and discount details, and improved navigation and responsive styling.
- Preserved promotion identifiers throughout room and ticket booking URLs.

## [3.1.0] - 2026-09-26

### Added
- Added multi-room booking through the `[ichronoz_booking_multi]` shortcode, including combined checkout, guest details, payment selection, tax, and deposit summaries.
- Added activity, ticket, and appointment booking through the `[ichronoz_booking]` shortcode with product-type filtering, quantities, participant details, promo codes, and payment selection.
- Added a lightweight room carousel through the `[ichronoz_room_list]` shortcode.
- Added four grouped-room presentation options: small image, regular image, compact rate table, and promo cards.
- Added an internal booking analytics dashboard for availability searches, booking funnel events, conversion rate, failed submissions, and optional booking values.
- Added Google Tag Manager and `dataLayer` integration with configurable container installation, page scope, event name, and transaction-value tracking.
- Added a payment-return status page covering paid, pending, failed, cancelled, expired, refunded, and unknown payment states, with retry support.
- Added room galleries, image carousel modals, amenities, expandable descriptions, service details, tax information, booking reassurance, and a mobile booking bar.
- Added reusable fallback images for rooms and products whose images are unavailable.
- Added returning-guest lookup by email to prefill guest details during checkout.
- Added independently configurable scripts for booking and detail pages, including sanitization and CSP nonce support.

### Changed
- Reorganized the WordPress settings page into General, UI Settings, Custom Code, Analytics, and How to Use tabs, with shortcode copy and preview controls.
- Split the React frontend into independently loaded search, booking, room-list, and ticket-booking mounts to reduce unnecessary page initialization.
- Reorganized frontend code into feature, mount, and shared modules for reusable API, pricing, analytics, checkout, form, image, and styling logic.
- Enhanced grouped-room layouts, room cards, booking summaries, currency conversion, promo banners, and mobile responsiveness.

### Fixed
- Improved booking-status response validation and payment-return error handling.
- Improved debug logging without exposing payment tokens or sensitive guest data.
- Fixed guest lookup API handling and promo-banner behavior.

## [3.0.7] - 2026-06-21

### Fixed
- adding promo banner

## [3.0.6] - 2026-06-01

### Fixed
- Fixing version comparation
- Fixing no breakfast option

## [3.0.5] - 2026-05-16

### Fixed
- Fixing calendar placement
- Enhancing calendar UI

## [3.0.4] - 2026-04-15

### Fixed
- Fixing percentage guarantee
- Fixing more then 1 night guarantee

## [3.0.3] - 2026-01-24

### Fixed
- Fixing qty more then 1 bug

## [3.0.2] - 2025-12-20

### Fixed
- Making country origin optional
- Enhance checkin-checkout UI part

## [3.0.1] - 2025-11-08

### Fixed
- Auto select payment option on checkout page if only one payment option is available
- Fixed booking summary card layout on mobile
- Fixed extras layout on mobile

## [3.0] - 2025-10-23

### Added
- New selection for multiple room list view
- Button round customable
- Gradient color customable for header

### Fixed
- Fixed extend night bug

## [3.0-beta.2] - 2025-10-16
- Added color picker for UI colors.
- Added reset all UI colors button.
- Added reset per-field UI colors button.
- Added "How to use" tab to settings page.

## [3.0-beta.1] - 2025-10-15
- Initial release.
- Significant improvements have been made to the search and booking containers, providing a cleaner layout, improved responsiveness, and a more consistent user experience across devices.
- Introduced new admin settings to control layout and color schemes, including integration with WordPress color pickers. This allows site administrators to easily tailor the interface to match their brand identity.
- The booking flow has been simplified from a four-step process to three steps plus a payment step, reducing friction and improving the overall user journey.

---

How to update this changelog:
- Add a new section under `[Unreleased]` for changes you make.
- When releasing, move items from `[Unreleased]` into a new version section: `[x.y.z] - YYYY-MM-DD`.
- Group entries with these tags when possible: Added, Changed, Fixed, Removed.
