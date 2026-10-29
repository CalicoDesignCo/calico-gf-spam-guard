<?php defined( 'ABSPATH' ) || exit; ?>
<h2>Protection settings</h2>
<p>Matching submissions are saved in <strong>Forms → Entries → Spam</strong>, with normal form notifications and add-on feeds suppressed. Existing honeypot discard settings can still discard submissions before these rules run.</p>
<?php $this->start_form( 'settings' ); ?>
<p><label><input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ); ?>> Enable rule checking</label></p>
<p><label><input type="checkbox" name="test_mode" value="1" <?php checked( $settings['test_mode'] ); ?>> Test mode — log matches while allowing submissions, normal notifications and feeds</label></p>
<p><label>Activity retention <input type="number" name="retention" min="1" max="90" value="<?php echo esc_attr( $settings['retention'] ); ?>"> days</label></p>
<p class="description">Maximum 200 recent events, displayed 20 per page. Expired events are removed on the next rule match or Activity Log visit. Gravity Forms entry retention is separate.</p>
<h2>Quarantine email alerts</h2>
<p><label><input type="checkbox" name="alert_enabled" value="1" <?php checked( $settings['alert_enabled'] ); ?>> Email me when Spam Guard quarantines a submission</label></p>
<p><label for="cgsg-alert-recipients">Recipients</label></p>
<textarea id="cgsg-alert-recipients" name="alert_recipients" class="large-text" rows="3" placeholder="person@example.com, another@example.com"><?php echo esc_textarea( implode( "\n", $settings['alert_recipients'] ) ); ?></textarea>
<p class="description">One or more email addresses, separated by commas or new lines (maximum 20). Alerts include the original submitted message and other supported fields, the matching rule details, and an entry review link. Passwords, hidden fields, file uploads and payment fields are omitted. Recipients can see the configured recipient list.</p>
<p class="description">Alerts are separate from normal form notifications and use this site's WordPress email service. They are off by default and do not send for test mode, the rule tester, or spam flagged only by another provider. Activity records mail acceptance or failure; acceptance does not confirm inbox delivery.</p>
<?php submit_button( 'Save settings' ); ?></form>
<hr>
<h2>Start from scratch</h2>
<p><strong>Delete all logs and data</strong> removes this plugin's rules, settings, alert recipients and activity logs. Gravity Forms forms, submissions and entry notes are retained. This cannot be undone; export your rules first if you may need them.</p>
<?php $this->start_form( 'reset' ); ?>
<p><label for="cgsg-confirmation">Type <strong>DELETE</strong> to confirm </label><input id="cgsg-confirmation" name="confirmation" autocomplete="off" pattern="DELETE" required></p>
<?php submit_button( 'Delete all logs and data', 'delete' ); ?></form>
