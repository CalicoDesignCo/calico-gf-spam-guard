# Calico Spam Guard for Gravity Forms

Quarantine unwanted Gravity Forms submissions using configurable email, domain, message, link and IP rules.

## Version 0.2.0

- Separate Settings, Rules and Activity Log tabs under Gravity Forms.
- Activity history with 20 events per page.
- Optional quarantine alerts to multiple recipients, including the original message, matching reasons and a review link.
- A field dropdown and sample-value tester.
- Confirmed reset of plugin settings, rules and logs, retaining Gravity Forms forms and entries.

Rules support individual forms and selected fields, whole words/phrases, link allowlists, and IPv4/IPv6 CIDR ranges. Test mode records matches without quarantining them. Native spam handling suppresses normal notifications and feeds; custom integrations require site-specific verification.

## Installation and updates

Requires WordPress 6.2+, PHP 7.4+ and Gravity Forms 2.7+ (purchased separately). Upload the installable `calico-gf-spam-guard-0.2.0.zip` through Plugins → Add New → Upload Plugin. When replacing an existing installation, choose to replace the current plugin. Existing settings and rules are retained.

Open Forms → Spam Rules. Quarantine alerts are off until enabled with recipient addresses in Settings. Review quarantines in Forms → Entries → Spam. The plugin does not include automatic GitHub updates.

The repository's source archive is not the installable WordPress package. The production source is in `calico-gf-spam-guard/`. See its [readme](calico-gf-spam-guard/readme.txt) for configuration, limits, data retention and recovery details.

## Development checks

Run `php tests/alerts.php` for the full isolated suite. It includes the engine and admin tests: 146 assertions passed on PHP 7.4.33 and PHP 8.3.33. Tests use WordPress and Gravity Forms doubles. Live integration behavior and email inbox delivery require verification on the target site; the earlier build was reported working by its user.

Build the installable ZIP with `package.ps1`. Production packages exclude tests and development files.

Licensed GPL-2.0-or-later. This independent add-on is not affiliated with Gravity Forms.
