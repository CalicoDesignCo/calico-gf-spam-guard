<?php defined( 'ABSPATH' ) || exit; ?>
        <h2>Rules</h2><p>Each enabled rule is independent: a match on any rule quarantines the entry. Values within a rule use OR matching. Text and email matching ignore letter case; domains include subdomains.</p>
        <table class="widefat striped"><thead><tr><th>Rule</th><th>Type</th><th>Scope</th><th>Values</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ( $settings['rules'] as $rule ) : ?>
        <tr><td><?php echo esc_html( $rule['label'] ); ?><br><small><?php echo $rule['enabled'] ? 'Enabled' : 'Disabled'; ?></small></td>
        <td><?php echo esc_html( CGSG_Engine::types()[$rule['type']] ); ?></td>
        <td><?php echo esc_html( $rule['form_id'] ? 'Form ' . $rule['form_id'] : 'All forms' ); ?><br><?php echo esc_html( $rule['fields'] ? 'Fields ' . implode( ', ', $rule['fields'] ) : 'All eligible fields' ); ?></td>
        <td><?php echo esc_html( implode( ', ', $rule['values'] ) ); ?></td>
        <td><a class="button" href="<?php echo esc_url( add_query_arg( 'edit', $rule['id'], $this->url( 'rules' ) ) ); ?>#rule-editor">Edit</a>
        <?php foreach ( array( 'toggle' => $rule['enabled'] ? 'Disable' : 'Enable', 'delete' => 'Delete' ) as $op => $text ) { $this->start_form( $op ); echo '<input type="hidden" name="id" value="' . esc_attr( $rule['id'] ) . '"><button class="button" type="submit">' . esc_html( $text ) . '</button></form>'; } ?></td></tr>
        <?php endforeach; if ( ! $settings['rules'] ) { echo '<tr><td colspan="5">No rules yet. Add a rule below to begin.</td></tr>'; } ?>
        </tbody></table>
        <h2 id="rule-editor"><?php echo $editing ? 'Edit rule' : 'Add rule'; ?></h2>
        <?php $this->start_form( 'rule' ); ?>
        <input type="hidden" name="edit_id" value="<?php echo esc_attr( $draft['id'] ); ?>">
        <table class="form-table"><tr><th><label for="cgsg-label">Rule name</label></th><td><input class="regular-text" id="cgsg-label" name="label" required maxlength="120" value="<?php echo esc_attr( $draft['label'] ); ?>"></td></tr>
        <tr><th><label for="cgsg-type">Rule type</label></th><td><select name="type" id="cgsg-type"><?php foreach ( CGSG_Engine::types() as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '" ' . selected( $draft['type'], $value, false ) . '>' . esc_html( $label ) . '</option>'; } ?></select></td></tr>
        <tr><th><label for="cgsg-form">Form</label></th><td><select name="form_id" id="cgsg-form"><option value="0">All forms</option><?php foreach ( $forms as $form ) { echo '<option value="' . esc_attr( $form['id'] ) . '" ' . selected( $draft['form_id'], $form['id'], false ) . '>' . esc_html( $form['title'] . ' (#' . $form['id'] . ')' ) . '</option>'; } ?></select></td></tr>
        <tr><th><label for="cgsg-fields">Field IDs (optional)</label></th><td><input id="cgsg-fields" name="fields" value="<?php echo esc_attr( implode( ',', $draft['fields'] ) ); ?>"><p class="description">Comma-separated parent field IDs, e.g. 2,5. Requires one selected form. Leave empty for eligible fields. IP rules ignore fields.</p></td></tr>
        <tr><th><label for="cgsg-values">Values</label></th><td><textarea id="cgsg-values" name="values" rows="6" class="large-text code"><?php echo esc_textarea( implode( "\n", $draft['values'] ) ); ?></textarea><p class="description">One value per line. Domains: example.com. IPs: 203.0.113.4 or 203.0.113.0/24. Any link needs no value. Link count needs one number: 2 means quarantine at 3 or more links.</p></td></tr>
        <tr><th><label for="cgsg-allow">Allowed link domains</label></th><td><textarea id="cgsg-allow" name="allow_domains" rows="3" class="large-text code"><?php echo esc_textarea( implode( "\n", $draft['allow_domains'] ) ); ?></textarea><p class="description">One domain per line. Excludes those links from this rule only. Applies to link rules.</p></td></tr></table>
        <p><label><input type="checkbox" name="enabled" value="1" <?php checked( $draft['enabled'] ); ?>> Rule enabled</label></p>
        <?php submit_button( $editing ? 'Save rule' : 'Add rule' ); ?></form>
        <?php if ( $editing ) { echo '<p><a href="' . esc_url( $this->url( 'rules' ) ) . '">Cancel editing</a></p>'; } ?>
        <details><summary>Form field reference</summary><p>Email rules check email fields by default. Content rules check supported visible data fields. Password, hidden, HTML, upload and payment fields are excluded. Whole-word matching avoids matching “ass” inside “class”.</p>
        <?php foreach ( $forms as $form ) { echo '<h3>' . esc_html( $form['title'] . ' (#' . $form['id'] . ')' ) . '</h3><ul>'; foreach ( $form['fields'] as $field ) { echo '<li>' . esc_html( $field->id . ': ' . $field->label . ' (' . $field->get_input_type() . ')' ) . '</li>'; } echo '</ul>'; } ?></details>
        <?php $this->tester( $forms, $settings ); ?>
        <h2>Import / export</h2><p>Exports include rule values such as blocked emails and IPs. Imports append rules and generate new IDs; form and field IDs must match this site.</p>
        <p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cgsg_export' ), 'cgsg' ) ); ?>">Export rules (JSON)</a></p>
        <?php $this->start_form( 'import' ); ?><label for="cgsg-json">Paste an exported rules file</label><textarea id="cgsg-json" class="large-text code" name="json" rows="5" required></textarea><?php submit_button( 'Import rules', 'secondary' ); ?></form>
