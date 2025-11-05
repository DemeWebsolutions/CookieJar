# CookieJar (v1.0.1)

CookieJar — International cookie/compliance solution for WordPress (GDPR, CCPA, LGPD). Geotargeting, multilanguage, consent logging, automated script blocking, policy table shortcode, GA4 Consent Mode v2.

- Product site: [CookieJar](https://www.demewebsolutions.com/cookiejar/)
- Author: Demewebsolutions.com / Kenneth "Demetrius" Weaver
- License: Proprietary. All rights reserved.

## What’s included

- Frontend consent banner with preferences and Do Not Sell (CPRA) when applicable.
- GA4 & Consent Mode v2 integration.
- New Admin UI:
  - Bakery Dashboard (stats, donut, KPIs, recent activity)
  - Control Panel (beta) unified page
- Back-compat: internal option keys, cookies, AJAX endpoints remain `dwic_*`.

## Installation

1. Upload the `cookiejar` folder to `/wp-content/plugins/`.
2. Activate “CookieJar” in WordPress → Plugins.
3. Visit Admin → CookieJar for the dashboard and control panel.

## Notes

- Core engine files in `includes/*` are preserved from your original plugin.
- To wire charts or export tools, we can enhance the admin pages in-place.