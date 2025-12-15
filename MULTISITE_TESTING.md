# UtilitySign — Multisite Usage & Testing

## Network Activation
- In Network Admin → Plugins, activate “UtilitySign” network-wide.
- On activation, the plugin runs install tasks (migrations/pages) for all existing sites.
- For any new site created after network activation, the plugin auto-initializes via `wpmu_new_blog`.

## Verify Initialization
1. Create a new site in Network Admin.
2. Visit that site’s WP Admin → UtilitySign → Settings.
3. Confirm settings page loads and saves.
4. Confirm block/shortcode is usable on that site.

## Storage & Isolation
- Each site maintains its own options and data tables.
- Ensure site switching does not leak settings between sites.

## Test Checklist
- Network activate plugin
- Create new site → verify auto-init
- Save admin settings per site
- Render shortcode/block on a page
- Confirm no PHP warnings in debug.log

## E2E (Playwright) Tips
- Base URL: your local dev site (e.g., http://devora-test.local)
- Test flows:
  - Admin login
  - Open UtilitySign Settings
  - Save minimal config
  - Create a page with signing block/shortcode
  - View page; verify mount element exists
