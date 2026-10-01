=== Local Consent ===
Contributors: tbuck
Tags: consent, privacy, google maps, local
Requires at least: 6.7
Tested up to: 6.8.1
Requires PHP: 7.4
Stable tag: 0.1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Local consent choices and server-side blocking for supported external content. No account, CDN, license server or traffic limits.

== Description ==

Local Consent makes supported scripts, iframes, images and resource links inert in the HTML before the browser receives them. Visitors can allow individual services, reject all, save a selection and withdraw consent later. Everything is served by your WordPress installation.

Version 0.1.0 includes Google Maps, Google Analytics / Tag Manager, YouTube including youtube-nocookie.com, and Vimeo. Direct Google Fonts links and known Consentmanager.net/.de loaders can be disabled. Local fonts are preserved. German and English are included, with Polylang language detection.

Preferences are stored in the visitor's localStorage, with an expiry and a configuration revision. No consent records are transmitted to a server. Changing service rules invalidates previous choices. Withdrawal reloads the page to terminate previously loaded scripts. Known readable first-party Google Analytics cookie names are expired on rejection or withdrawal; third-party, HttpOnly and unknown cookies cannot be cleared by this script.

== Installation ==

1. Upload the local-consent ZIP through Plugins > Add New > Upload Plugin.
2. Activate it, then configure Plugins > Local Consent.
3. Select the services actually used and set a privacy-policy URL.
4. Deactivate your previous consent plugin and remove its integration code. The built-in replacement option also suppresses known Consentmanager.net/.de loaders.
5. Clear page, hosting, CDN and Divi caches.
6. Test a new browser visit, rejection, individual consent, all consent, and withdrawal with the browser's Network panel.

Use [local_consent_settings] to add another settings button in content. A persistent settings button is already included.

== Design ==

Plugins > Local Consent > Design can adopt existing site fonts, colors and button radii. Selecting Custom design opens editable fields and copies automatic values into empty fields. Light, Soft and Dark presets, synchronized color pickers and hex inputs, and a live banner preview are included. Automatic design can be copied again at any time. The persistent settings control uses a fingerprint icon with an accessible name.

Use the Import / Export tab to choose a file or paste JSON, then select Import. General settings and help have their own tabs. Imports replace design values only; invalid imports leave settings unchanged. Design changes preserve visitor consent. Fonts must already be available on the site; the plugin does not install fonts or add external font providers. Automatic adoption checks text contrast; review custom colors yourself. Clear page caches after changes.

== Scope ==

This is a first release with explicit integrations, not a universal network firewall or a legal certification. It does not automatically recognize every WordPress plugin. Resources injected dynamically by unrelated JavaScript require an integration. It does not scan external CSS files, control server-side tracking or PHP cookies, change HTTP Link headers, or rewrite pages that bypass WordPress via a page cache. Cached pages must be regenerated after installation and settings changes.

Do not flush a partially rendered HTML response before the plugin's output buffer runs. Streaming templates and integrations that flush or replace output buffers need separate testing. REST API output is left unchanged; the_content-generated HTML fragments are protected where the content filter runs outside REST.

Direct Google Fonts links and inline style imports are blocked, not downloaded. Host fonts locally. Resources loaded inside an allowed third-party iframe are controlled by that provider and covered by that service's description.

Google Tag Manager containers may include more than statistics. Review your container and adjust the description/category through the service filter if needed. Local Consent does not provide Google Consent Mode or IAB TCF certification.

WordPress admin, previews, feeds and Divi visual-builder editing are excluded. The published page remains protected.

== Developer integration ==

Use the local_consent_services PHP filter to register a service with a label, description, category and host/path rules. Enable the service in the settings. Add data-local-consent="service_id" to script or iframe elements that use a first-party URL and need an explicit integration. JavaScript can query LocalConsent.allows('service_id'), open LocalConsent.open(), and subscribe to local-consent:ready and local-consent:change. These hooks coordinate consent; they do not intercept requests made elsewhere.

See README.md in the source repository for examples and tests.

== Changelog ==

= 0.1.4 =
* Privacy-policy link sits beneath the introduction, before service choices.

= 0.1.3 =
* Desktop dialog opens from and shrinks toward the FAB.
* Mobile sheet slides from the bottom; FAB is hidden while open.
* Downward dismissal continues from the dragged position.

= 0.1.2 =
* FAB remains visible and clickable while the dialog is open, with an active state.
* Fingerprint feedback after saving or rejecting, including after withdrawal reload.
* Reduced-motion preferences are respected.

= 0.1.1 =
* Desktop dialog opens above the fingerprint button.
* Mobile bottom sheet with fixed actions, safe-area padding and downward swipe to reject.
* Responsive map placeholders and embedded frames.

= 0.1.0 =
* First local release with server-side HTML blocking, service-specific choices and local settings.
