=== Calico Spam Guard for Gravity Forms ===
Contributors: calicodesign
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later

Quarantine submissions using configurable email, content, link and IP rules.

== Installation ==
Upload the plugin ZIP in Plugins > Add New > Upload Plugin and activate it alongside Gravity Forms 2.7 or later. Open Forms > Spam Rules. No blocklist is supplied: add rules before expecting protection. Rule checking is on by default; test mode is off. Only administrators can manage this plugin.

To update from 0.1.0, upload the new ZIP and choose to replace the existing plugin. Existing rules and settings are retained. Settings, Rules and Activity Log now have separate tabs on the same page under Gravity Forms. Settings is the default tab. Quarantine alerts default to off.

== Rules ==
Create named rules for exact email addresses, email domains, text containing a phrase, whole words/phrases, exact field values, any link, link domains, link count thresholds and IPv4/IPv6 addresses or CIDR ranges.

Each rule may target all forms or one form. Field IDs can be specified for a single form. Use parent IDs (e.g. 5); compound inputs such as 5.3 are included automatically. Email rules default to email fields. Content rules inspect supported data fields. Passwords, hidden fields, HTML, files and payment fields are excluded. IP rules ignore field targeting.

One value per line; any matching value triggers the rule. All enabled rules use OR logic. Matching ignores case. Domain rules match the listed domain and its subdomains, with domain boundaries enforced. Rules do not use regular expressions. Whole-word boundaries recognize Unicode letters and numbers. Exact matching trims leading/trailing whitespace. Text checks decode HTML entities and strip tags.

Link rules recognize HTTP(S), www and bare domain formats. Allowed domains apply only to the link rule containing them and exclude matching subdomains too. Link count counts detected occurrences, including repeated URLs, across eligible fields. HTML may contain the same URL in both its href and label, which counts twice. Unusual/obfuscated URLs (e.g. example [dot] com) are not guaranteed to be detected. Target the message field when email or website fields should not affect a link rule.

IP checks use Gravity Forms' resolved submitter IP; when IP storage is disabled they use Gravity Forms' live IP resolver without recording it in this plugin's activity log. Configure any reverse proxy IP handling through Gravity Forms/site infrastructure. Shared IPs can represent multiple legitimate users.

== Quarantine and review ==
Matches return spam through gform_entry_is_spam. Gravity Forms handles spam storage, normal notification suppression and add-on feed suppression. The plugin records the rule names using GFCommon::set_spam_filter. Its notification filter also suppresses notifications for entries whose current status is spam. It does not globally filter wp_mail.

Review entries in Forms > Entries > Spam. If an entry is legitimate, adjust the rule and use Gravity Forms to mark the entry as not spam. Restoring does not automatically resend missed notifications or replay feeds; review and perform those actions deliberately through Gravity Forms and the relevant add-on.

The plugin never clears another provider's spam verdict. Existing honeypot discard settings or plugins that abort submissions can prevent an entry from being saved before this plugin can quarantine it. Configure Gravity Forms' honeypot to save spam if all such submissions should be retained. This plugin does not override another provider's abort decision.

Custom gform_after_submission code, external webhooks, payment add-ons and other integrations must be verified on the target site. Spam-hook behavior is documented by Gravity Forms; live behavior has not been verified for this local build. Direct GFAPI::add_entry imports and third-party endpoints that bypass normal submission handling are outside this plugin's submission interception. Save and Continue and Partial Entries can save data before final submission; these paths are not blocked by this first version.

== Test mode and rule tester ==
Test mode logs rule matches without marking entries spam or disabling their normal notifications/feeds. Other spam providers continue to act. On the Rules tab, choose a form and click Load fields, select a field from the dropdown, then enter a sample email, message or link. Select Submitter IP address to test IP rules. Compound fields offer their individual inputs by name. Only the chosen field is populated; other fields are empty. The tester runs saved enabled rules without creating entries, sending notifications or recording activity. No JSON is needed.

== Quarantine email alerts ==
In Settings, enable quarantine alerts and enter one or more recipient email addresses separated by commas, semicolons or new lines (maximum 20). All recipients can see the configured recipient list. The plugin validates and deduplicates addresses before saving; headers cannot be supplied through the recipient field. These settings do not affect normal Gravity Forms notifications, which remain suppressed for spam.

The alert is sent through wp_mail after gform_after_submission confirms a saved entry with spam status, only for entries matched by this plugin during the same submission. Its subject identifies quarantine. The plain-text body includes the form, entry ID, matching rule names/types/values, original message and supported submitted fields, and a protected admin review link. Passwords, hidden/administrative fields, uploads and payment fields are omitted. Raw message text is preserved as untrusted plain text, never inserted into mail headers or executed as HTML. Links in the message may still be made clickable by the recipient's email client.

Alerts do not send for test mode, the rule tester, spam detected only by another provider, or entries discarded before saving. This feature alerts on newly quarantined final submissions, not old entries or later manual status changes. It does not reply to the submitter or replay normal notifications/feeds. Duplicate hook calls in the same request do not send duplicate alerts. No background delivery queue or automatic retries are added. The Activity Log records mail acceptance or failure; acceptance by wp_mail is not proof of inbox delivery. Use the site's email/SMTP service and verify a real test email after enabling the feature.

== Activity and data ==
Activity has its own tab, with 20 events per page, newest first, capped at 200 recent events. It holds the timestamp, form ID, rule IDs/names and result; for newly saved quarantines it also records the entry ID and alert status. Older log rows are preserved and show a dash for unavailable alert status. Retention is 1-90 days (default 30); expired rows are pruned on the next rule match or Activity Log visit, not by a scheduled daily job. It is a recent diagnostic log, not an audit system; concurrent requests may overwrite a log event. No email, IP, field values or message bodies are duplicated into activity. Avoid personal data in rule names. Gravity Forms owns submission data and its retention settings.

Rules may contain personal data such as blocked emails and IPs. Exports include those values; store exported JSON appropriately. Imports append rules with new IDs and validate form/field references on this site. Imports do not change global protection settings. Duplicate rules are possible when importing the same file repeatedly.

Deactivation and plugin deletion preserve settings/activity so reinstalling retains configuration. Clear the activity log on the Activity Log tab. To start from scratch, open Settings and use Delete all logs and data, typing DELETE to confirm. The server requires administrator access and a valid nonce before deleting the cgsg_settings and cgsg_activity options. This removes rules, settings, alert recipients and activity, returning to default settings with no rules and alerts off. It never deletes Gravity Forms forms, submissions or entry notes. Reset cannot be undone; export rules first if needed.

No external service, subscription, remote reputation list, tracking or front-end script is used.

== Scope ==
Version 0.2.0 includes rules, form/field targeting, enable/disable, editing, JSON rule import/export, quarantine reasons and optional alerts, a paginated activity log, test mode, a dropdown rule tester, and a confirmed plugin-data reset. Rate limiting, duplicate-message detection and entry-screen quick blocking are future enhancements, not included in this build.

== Changelog ==
= 0.2.0 =
Separate Settings, Rules and Activity Log tabs under Gravity Forms. Activity pagination at 20 events per page. Optional quarantine alerts with original message and detailed matching reasons. Field-dropdown tester replaces JSON entry. Confirmed plugin-data reset. Existing settings/rules/logs retained on upgrade.

= 0.1.0 =
Initial local review build. Installation on a WordPress site and connected-integration checks remain pending.
