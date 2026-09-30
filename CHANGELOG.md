# ChangeLog

--- develop ---

* dev: Measure CI coverage with xdebug instead of pcov so the plugin's own sources are instrumented (pcov auto-scopes to the Composer root and skipped cacti/plugins/, leaving the patch-coverage gate with nothing to measure)
* dev: Enforce patch coverage of changed lines in CI and remove the inert COMPOSER_ROOT_VERSION env from the Pest step
* security: Add a version-safe CSP nonce (`plugin_tags_csp_nonce()`) to every inline `<script>` tag so pages stay compatible with Cacti's Content-Security-Policy nonce enforcement, while falling back cleanly on older Cacti releases that lack the `CactiSecureHeaders` class
* chore: Harmonize CI workflow, issue/PR templates, and PHP-compatibility test structure with the shared Cacti plugin baseline
* Initial public release

-----------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.

