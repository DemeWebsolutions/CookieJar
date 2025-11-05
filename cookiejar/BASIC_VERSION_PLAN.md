# CookieJar Basic/Free Version - WordPress.org Submission Plan

## Overview
Convert CookieJar Pro to a Basic/Free version suitable for WordPress.org submission while maintaining CSS consistency and core functionality with tier restrictions properly enforced.

---

## Phase 1: Code Structure & Tier Enforcement

### 1.1 Update Main Plugin File
**File:** `cookiejar.php`
- [ ] Remove/update `COOKIEJAR_PRO` constant definition to default to `false`
- [ ] Ensure tier detection always returns `COOKIEJAR_TIER_BASIC`
- [ ] Update plugin header for WordPress.org (remove proprietary license note, update author)
- [ ] Verify minimum requirements (PHP 7.2+)
- [ ] Update version number (e.g., 1.0.0 → 1.0.1-basic or reset to 1.0.0)

### 1.2 Tier Enforcement Audit
**Files:** All files using tier checks
- [ ] Verify `cookiejar_get_tier()` always returns `'basic'`
- [ ] Verify `cookiejar_is_pro()` always returns `false`
- [ ] Review all tier-based feature restrictions:
  - Language limit: Max 2 languages
  - Trend data: Fixed at 7 days
  - Logging mode: Forced to 'cached'
  - Log retention: Max 365 days
  - Categories: Limited set (necessary, functional, analytics, advertising)
  - No CCPA/LGPD advanced modes
  - No API/webhooks
  - No GTM/Facebook Pixel integrations
  - No custom scripts
  - No advanced performance features (CDN, lazy load, minify)

### 1.3 Remove Premium Upsells
**Files:** `admin/class-cookiejar-admin.php`, JS files
- [ ] Remove all "Upgrade to Pro" links/buttons
- [ ] Remove premium feature highlights/promotions
- [ ] Remove links to demewebsolutions.com/account/
- [ ] Simplify tier badge display (show "Free" or "Basic" only)
- [ ] Remove upgrade prompts from settings pages

### 1.4 Add Compliant Upgrade Touchpoints (non-intrusive)
**Goal:** Include upgrade affordances only where permitted by WordPress.org
- [ ] Plugin row meta: add a small `Pro` text link next to `Settings` (Plugins list)
- [ ] Settings page footer: add a single "Learn about Pro" text link (no buttons, no images)
- [ ] Optional: one-time, dismissible post-activation notice mentioning Pro (persist dismissal)
- [ ] Do not render any upsell on non-plugin admin screens or on the frontend

---

## Phase 2: WordPress.org Compliance

### 2.1 Licensing & Legal
**Files:** `cookiejar.php`, `README.md`, all PHP files
- [ ] Update license to GPL v2+ (required for WordPress.org)
- [ ] Add GPL-compatible license headers to all PHP files
- [ ] Update README.md with proper WordPress.org format
- [ ] Remove proprietary/enterprise references
- [ ] Update copyright notices to GPL-compatible format
 - [ ] Ensure any "Pro" mentions are informational only and not promotional

### 2.2 Code Standards
**Files:** All PHP files
- [ ] Run WordPress Coding Standards check
- [ ] Fix PHP compatibility issues (PHP 7.2+)
- [ ] Ensure proper escaping (esc_html, esc_attr, esc_url, wp_kses_post)
- [ ] Verify nonce usage on all forms/AJAX
- [ ] Check capability checks (manage_options)
- [ ] Remove eval(), exec(), system() if any exist
- [ ] Verify SQL prepared statements

### 2.3 Security Review
**Files:** All files
- [ ] Audit all user input sanitization
- [ ] Verify file path validation (no directory traversal)
- [ ] Check AJAX endpoint security
- [ ] Verify rate limiting on public endpoints
- [ ] Review cookie handling security
- [ ] Check for XSS vulnerabilities
- [ ] Verify CSRF protection

### 2.4 Data Privacy
**Files:** `includes/class-cookiejar-db.php`, frontend files
- [ ] Ensure GDPR compliance in consent logging
- [ ] Verify IP anonymization for Basic tier
- [ ] Check data retention settings
- [ ] Review consent data handling
- [ ] Ensure proper data deletion on uninstall

---

## Phase 3: Branding & Attribution

### 3.1 Branding Footer
**Files:** `frontend/class-dwic-frontend.php`, `assets/js/banner.js`
- [ ] Update default branding to WordPress.org appropriate text
- [ ] Make branding optional but default to enabled
- [ ] Ensure branding is subtle and non-intrusive
- [ ] Remove proprietary/copyright claims from default text
- [ ] Allow customization via settings
 - [ ] No sales links in frontend branding; if present, default to disabled

### 3.2 Admin Branding
**Files:** `admin/class-cookiejar-admin.php`, CSS files
- [ ] Replace external image URLs with local assets
- [ ] Download and localize all demewebsolutions.com images
- [ ] Update admin logo references to local files
- [ ] Remove promotional content
- [ ] Keep plugin branding minimal
 - [ ] If a "Pro features" section exists, keep it text-only, informational, and non-blocking

### 3.3 External Dependencies
**Files:** All files
- [ ] Audit all external URLs
- [ ] Replace external assets with local copies
- [ ] Remove tracking/analytics references
- [ ] Ensure no external API calls without user consent

---

## Phase 4: CSS Refinement & Consistency

### 4.1 Banner CSS
**File:** `assets/css/banner.css`
- [ ] Ensure consistent styling with Pro version
- [ ] Review color schemes (light/dark)
- [ ] Verify responsive design
- [ ] Check accessibility (contrast ratios, focus states)
- [ ] Optimize CSS (remove unused rules)
- [ ] Ensure cross-browser compatibility

### 4.2 Admin CSS
**File:** `assets/css/cookiejar-admin.css`
- [ ] Match Pro version styling
- [ ] Ensure consistent typography
- [ ] Review layout consistency
- [ ] Check responsive admin design
- [ ] Verify color scheme consistency
- [ ] Remove Pro-only visual elements

### 4.3 Wizard CSS
**File:** `assets/css/wizard.css`
- [ ] Match Pro version appearance
- [ ] Ensure consistent step indicators
- [ ] Review form styling
- [ ] Check button consistency

### 4.4 CSS Optimization
**Files:** All CSS files
- [ ] Minimize CSS (production build)
- [ ] Remove commented-out code
- [ ] Consolidate duplicate rules
- [ ] Ensure CSS follows WordPress admin standards

---

## Phase 5: Feature Limitations Implementation

### 5.1 Language Restrictions
**Files:** `includes/class-cookiejar-ajax.php`, `admin/class-cookiejar-admin.php`
- [ ] Enforce 2-language maximum in all handlers
- [ ] Update UI to disable additional language selection
- [ ] Add clear messaging about Basic tier limitation
- [ ] Ensure validation on save

### 5.2 Category Restrictions
**Files:** Frontend, admin, AJAX
- [ ] Limit to: necessary, functional, analytics, advertising
- [ ] Remove chatbot, donotsell from Basic tier
- [ ] Update category manager UI
- [ ] Disable advanced categories in wizard

### 5.3 Logging Restrictions
**Files:** `includes/class-cookiejar-db.php`, admin
- [ ] Force 'cached' logging mode
- [ ] Disable 'live' logging option
- [ ] Limit log retention to 365 days max
- [ ] Update UI to reflect limitations

### 5.4 Analytics Restrictions
**Files:** `includes/class-cookiejar-ajax.php`, admin
- [ ] Fix trend summary to 7 days only
- [ ] Remove 30/90 day options from UI
- [ ] Disable advanced analytics features
- [ ] Simplify dashboard for Basic tier

---

## Phase 6: Documentation & Readme

### 6.1 README.md
**File:** `README.md`
- [ ] Rewrite for WordPress.org format
- [ ] Include installation instructions
- [ ] Add screenshots section
- [ ] List features (Basic tier)
- [ ] Add FAQ section
- [ ] Include changelog structure
- [ ] Add upgrade path (optional, non-promotional)
 - [ ] Add a "Pro features" section with a single link to a neutral landing page

### 6.2 Code Documentation
**Files:** All PHP files
- [ ] Add/update PHPDoc blocks
- [ ] Document function parameters
- [ ] Add return type hints where possible
- [ ] Include inline comments for complex logic
- [ ] Document filter/action hooks

### 6.3 User Documentation
**Files:** Create new docs if needed
- [ ] Basic setup guide
- [ ] Configuration instructions
- [ ] Feature limitations explanation
- [ ] Troubleshooting guide

---

## Phase 7: Testing & Quality Assurance

### 7.1 Functional Testing
- [ ] Test consent banner display
- [ ] Test consent logging
- [ ] Test admin dashboard
- [ ] Test setup wizard
- [ ] Test settings save/load
- [ ] Test uninstall/cleanup
- [ ] Test activation/deactivation

### 7.2 Tier Restriction Testing
- [ ] Verify all Pro features are disabled/removed
- [ ] Test language limit enforcement
- [ ] Test category restrictions
- [ ] Test logging mode restrictions
- [ ] Test analytics limitations
- [ ] Verify no Pro upgrade prompts appear

### 7.3 Compatibility Testing
- [ ] Test on WordPress 5.9+ (minimum)
- [ ] Test on PHP 7.2, 7.4, 8.0, 8.1, 8.2
- [ ] Test with various themes
- [ ] Test with popular plugins (WooCommerce, Contact Form 7, etc.)
- [ ] Test responsive design
- [ ] Test accessibility (WCAG 2.1 AA)

### 7.4 Security Testing
- [ ] Penetration testing for XSS
- [ ] SQL injection testing
- [ ] CSRF protection verification
- [ ] Input validation testing
- [ ] Rate limiting verification

---

## Phase 8: WordPress.org Submission Prep

### 8.1 File Structure
- [ ] Ensure clean file structure
- [ ] Remove development files
- [ ] Remove test files
- [ ] Remove `.git` if present
- [ ] Clean up `.svn` directories

### 8.2 Assets Preparation
- [ ] Prepare banner image (772x250px)
- [ ] Prepare icon (256x256px)
- [ ] Prepare screenshots (1200x900px)
- [ ] Optimize all images

### 8.3 Version Management
- [ ] Set version to 1.0.0 (initial release)
- [ ] Tag release
- [ ] Create zip file for submission
- [ ] Verify plugin slug uniqueness

### 8.4 Submission Checklist
- [ ] All code follows WordPress standards
- [ ] No proprietary license issues
- [ ] Security review complete
- [ ] Documentation complete
- [ ] Assets prepared
- [ ] Tested on multiple environments
- [ ] No external dependencies blocking approval
 - [ ] All upgrade touchpoints verified to be compliant: plugin row meta, settings footer link, optional dismissible notice

---

## Upgrade-to-Pro Placements (WordPress.org Compliant)

Where we will place upgrade affordances, and how they must behave to pass review:

- Plugin Row Meta (Plugins → Installed Plugins)
  - Add a small `Pro` text link next to `Settings`.
  - No button styling, no emojis, no images.

- Settings Page (inside our own admin page only)
  - Footer-only small text link: "Learn about Pro".
  - No banners, no modals, no cards advertising Pro.
  - If a "Pro features" section exists, it must be purely informational and not block free features.

- Post-Activation Notice (optional)
  - One-time, truly dismissible notice.
  - No reappearing nags; store persistent dismissal.
  - Keep copy short and neutral.

- Prohibited
  - No upsells outside our pages (e.g., Dashboard, Posts, Settings screens not ours).
  - No frontend promotional links/branding by default.
  - No disabled UI with "Upgrade" overlays; hide Pro-only controls entirely in the free build.

---

## Phase 9: Post-Submission Maintenance

### 9.1 Monitoring Plan
- [ ] Set up support forum monitoring
- [ ] Plan for bug fixes
- [ ] Prepare update roadmap

### 9.2 Update Strategy
- [ ] Version numbering scheme
- [ ] Update compatibility matrix
- [ ] Changelog maintenance

---

## Key Files to Modify

### Critical Files
1. `cookiejar.php` - Main plugin file, tier enforcement
2. `admin/class-cookiejar-admin.php` - Admin UI, remove upsells
3. `frontend/class-dwic-frontend.php` - Frontend banner, branding
4. `includes/class-cookiejar-ajax.php` - AJAX handlers, tier enforcement
5. `includes/class-cookiejar-config.php` - Configuration defaults
6. `README.md` - Documentation

### CSS Files
1. `assets/css/banner.css` - Banner styling
2. `assets/css/cookiejar-admin.css` - Admin styling
3. `assets/css/wizard.css` - Wizard styling

### JavaScript Files
1. `assets/js/admin.js` / `cookiejar-admin.js` - Admin functionality
2. `assets/js/banner.js` - Banner functionality
3. `assets/js/wizard.js` - Wizard functionality

---

## Estimated Timeline

- **Phase 1-2:** 2-3 days (Code structure & compliance)
- **Phase 3:** 1 day (Branding)
- **Phase 4:** 1-2 days (CSS refinement)
- **Phase 5:** 1 day (Feature limitations)
- **Phase 6:** 1 day (Documentation)
- **Phase 7:** 2-3 days (Testing)
- **Phase 8:** 1 day (Submission prep)

**Total:** ~10-12 days

---

## Notes

- Keep Pro version as separate branch/repository
- Basic version should be maintainable independently
- Consider using feature flags for easier maintenance
- Document all tier restrictions clearly
- Ensure easy upgrade path to Pro (non-intrusive)

---

## Questions for Review

1. **Version Numbering:** Start at 1.0.0 or continue from current?
2. **Branding:** How much branding is acceptable for WordPress.org?
3. **Upgrade Path:** Should there be any mention of Pro version?
4. **Support:** Will support be provided via WordPress.org forums only?
5. **Updates:** How frequently should updates be released?

---

## Next Steps

After review:
1. Prioritize phases
2. Adjust scope as needed
3. Begin implementation
4. Set milestone checkpoints
