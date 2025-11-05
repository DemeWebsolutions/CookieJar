=== CookieJar ===
Contributors: demewebsolutions
Tags: cookies, gdpr, ccpa, consent, privacy
Requires at least: 5.9
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cookie consent banner and basic compliance tools (GDPR/CCPA) with simple setup and accessible UI.

== Description ==

CookieJar provides a lightweight cookie consent banner with basic GDPR/CCPA compliance tools.

- Accessible banner with Accept, Reject, and Preferences
- Basic categories: Necessary, Functional, Analytics, Advertising
- Optional Do Not Sell (CPRA) when applicable
- GA4 Consent Mode v2 signal updates (optional)
- Basic consent logging (cached mode)
- Multilanguage (free: up to 2 languages)

This free version is designed for WordPress.org policies: no ads in dashboard, no nagging notices, and all assets loaded locally.

== Installation ==

1. Upload the `cookiejar` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to Admin → CookieJar → Control Panel to configure settings.

== Frequently Asked Questions ==

= Does this block scripts automatically? =
The free version provides category-based consent and exposes category states; automatic blocking is limited. Advanced control is available in the Pro version.

= How are consents stored? =
In the free version, consent logs are stored in the WordPress database in cached mode. Retention is capped.

= How many languages are supported? =
Up to two languages in the free version.

== Screenshots ==

1. Cookie banner (light theme)
2. Control Panel (basic)
3. Consent preferences (categories)

== Changelog ==

= 1.0.0 =
Initial release to WordPress.org

== Upgrade Notice ==

= 1.0.0 =
First public release. 

== Asset Licensing ==

- All plugin code is GPLv2 or later.
- All bundled images, SVGs, and icons in `assets/` are original works by DemeWebsolutions.com (My Deme, LLC) and released under GPLv2 or later.
- No remote CDNs are used; all assets load locally.


