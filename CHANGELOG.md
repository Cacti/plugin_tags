# ChangeLog

--- develop ---
* feature: Add option to hide tags from graphs
* feature: Turn the graph drilldown button into an 'Enable Tags'/'Disable Tags' toggle that reflects the current session state and shows or hides this plugin's tag legend lines on graphs, rather than a static link, so it reads cleanly as a label in Cacti's graph action menu
* security: Pin CI fixtures and shared test dependencies, disable persisted checkout credentials, and limit workflow permissions and runtime
* security: Replace the confirmation pages' inline `onClick='cactiReturnTo()'` Cancel buttons with the CSP-safe `cactiReturnTo` class so the pages no longer trip Cacti's Content-Security-Policy script-src-attr directive; see the README for the one-time `applySkin()` snippet needed when running Cacti below 1.2.31 (1.2.31+ binds the class automatically)
* security: Move the filter selects' inline onChange handlers into the ready block (and bind them via change, not click) so the page no longer trips Cacti's Content-Security-Policy script-src-attr directive
* dev: Measure CI coverage with xdebug instead of pcov so the plugin's own sources are instrumented (pcov auto-scopes to the Composer root and skipped cacti/plugins/, leaving the patch-coverage gate with nothing to measure)
* dev: Enforce patch coverage of changed lines in CI and remove the inert COMPOSER_ROOT_VERSION env from the Pest step
* security: Add a version-safe CSP nonce (`plugin_tags_csp_nonce()`) to every inline `<script>` tag so pages stay compatible with Cacti's Content-Security-Policy nonce enforcement, while falling back cleanly on older Cacti releases that lack the `CactiSecureHeaders` class
* chore: Harmonize CI workflow, issue/PR templates, and PHP-compatibility test structure with the shared Cacti plugin baseline
* Initial public release

-----------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.

