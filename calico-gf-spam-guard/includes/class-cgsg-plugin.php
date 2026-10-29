<?php
defined( 'ABSPATH' ) || exit;

class CGSG_Plugin {
    const OPTION = 'cgsg_settings';
    const LOG = 'cgsg_activity';
    private $pending = array();
    private $alerted = array();

    public function __construct() {
        add_filter( 'gform_entry_is_spam', array( $this, 'check_spam' ), 50, 3 );
        add_filter( 'gform_disable_notification', array( $this, 'disable_notification' ), 50, 4 );
        add_action( 'gform_after_submission', array( $this, 'quarantine_alert' ), 50, 2 );
        add_action( 'admin_menu', array( $this, 'menu' ), 30 );
        add_action( 'admin_post_cgsg_save', array( $this, 'save' ) );
        add_action( 'admin_post_cgsg_export', array( $this, 'export' ) );
        add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
    }

    public function settings() {
        return wp_parse_args( get_option( self::OPTION, array() ), array( 'enabled' => true, 'test_mode' => false, 'retention' => 30, 'rules' => array(), 'alert_enabled' => false, 'alert_recipients' => array() ) );
    }

    public function check_spam( $is_spam, $form, $entry ) {
        // Never clear the decision made by another spam provider.
        if ( $is_spam ) { return true; }
        $settings = $this->settings();
        if ( ! $settings['enabled'] ) { return $is_spam; }
        $ip = $entry['ip'] ?? '';
        if ( ! $ip && class_exists( 'GFFormsModel' ) ) { $ip = GFFormsModel::get_ip(); }
        $hits = CGSG_Engine::evaluate( $settings['rules'], $form, $entry, $ip );
        if ( ! $hits ) { return $is_spam; }
        $labels = implode( ', ', array_column( $hits, 'label' ) );
        $reason = 'Matched rules: ' . $labels;
        if ( ! $settings['test_mode'] && method_exists( 'GFCommon', 'set_spam_filter' ) ) {
            GFCommon::set_spam_filter( $form['id'], 'Calico Spam Guard', $reason );
        }
        // No field content, emails, IPs or message bodies are duplicated into our log.
        $log = $this->activity();
        $event_id = wp_generate_uuid4();
        array_unshift( $log, array( 'id' => $event_id, 'time' => time(), 'form' => (int) $form['id'], 'action' => $settings['test_mode'] ? 'Test match — allowed' : 'Marked as spam', 'rules' => array_column( $hits, 'id' ), 'labels' => $labels ) );
        update_option( self::LOG, array_slice( $log, 0, 200 ), false );
        if ( ! $settings['test_mode'] ) {
            $this->pending[(int) $form['id']] = array( 'event' => $event_id, 'entry_id' => (int) ( $entry['id'] ?? 0 ), 'signature' => $this->entry_signature( $entry ), 'reasons' => $this->reasons( $hits, $settings['rules'], $form, $entry, $ip ) );
        }
        return ! $settings['test_mode'];
    }

    private function entry_signature( $entry ) {
        $fields = array();
        foreach ( $entry as $key => $value ) {
            if ( preg_match( '/^\d+(?:\.\d+)?$/', (string) $key ) ) { $fields[(string) $key] = $value; }
        }
        ksort( $fields );
        return hash( 'sha256', serialize( $fields ) );
    }

    private function reasons( $hits, $rules, $form, $entry, $ip ) {
        $reasons = array();
        foreach ( $rules as $rule ) {
            if ( ! in_array( $rule['id'], array_column( $hits, 'id' ), true ) ) { continue; }
            $details = array();
            foreach ( $rule['values'] as $value ) {
                $single = $rule;
                $single['values'] = array( $value );
                if ( CGSG_Engine::evaluate( array( $single ), $form, $entry, $ip ) ) { $details[] = $value; }
            }
            $description = $rule['label'] . ': ' . CGSG_Engine::types()[$rule['type']];
            if ( $rule['type'] === 'any_link' ) { $description .= ' detected'; }
            elseif ( $rule['type'] === 'link_count' ) { $description .= ' ' . ( $rule['values'][0] ?? '0' ); }
            else { $description .= ' matched ' . implode( ', ', $details ); }
            if ( $rule['fields'] ) { $description .= ' (field IDs: ' . implode( ', ', $rule['fields'] ) . ')'; }
            $reasons[] = $description;
        }
        return $reasons;
    }

    public function quarantine_alert( $entry, $form ) {
        $form_id = (int) $form['id'];
        $entry_id = (int) ( $entry['id'] ?? 0 );
        $pending = $this->pending[$form_id] ?? null;
        // Wait for the persisted spam status; do not alert from the evaluation hook.
        if ( ! $pending || ! $entry_id || ( $entry['status'] ?? '' ) !== 'spam' || isset( $this->alerted[$entry_id] ) ) { return; }
        if ( $pending['entry_id'] ? $pending['entry_id'] !== $entry_id : $pending['signature'] !== $this->entry_signature( $entry ) ) { return; }
        unset( $this->pending[$form_id] );
        $this->alerted[$entry_id] = true;
        $settings = $this->settings();
        $result = 'Alerts disabled';
        if ( $settings['enabled'] && ! $settings['test_mode'] && $settings['alert_enabled'] && $settings['alert_recipients'] ) {
            $body = "A Gravity Forms submission was quarantined as spam by Calico Spam Guard.\nNormal form notifications and feeds were suppressed.\n\n";
            $body .= 'Form: ' . ( $form['title'] ?? '' ) . ' (#' . $form_id . ")\nEntry: #" . $entry_id . "\n\nWHY IT WAS FLAGGED\n- " . implode( "\n- ", $pending['reasons'] );
            $body .= "\n\nORIGINAL SUBMISSION\n";
            foreach ( $form['fields'] as $field ) {
                // Include the submitted message verbatim in plain text; omit secret/administrative fields.
                if ( ! in_array( $field->get_input_type(), CGSG_Engine::field_types(), true ) || ( $field->visibility ?? '' ) === 'administrative' ) { continue; }
                foreach ( $entry as $key => $value ) {
                    if ( ! is_scalar( $value ) || $value === '' || ( (string) $key !== (string) $field->id && strpos( (string) $key, $field->id . '.' ) !== 0 ) ) { continue; }
                    $body .= ( $field->label ?: 'Field' ) . ' [' . $key . "]:\n" . (string) $value . "\n\n";
                }
            }
            $body .= "Review entry:\n" . admin_url( 'admin.php?page=gf_entries&view=entry&id=' . $form_id . '&lid=' . $entry_id );
            $body .= "\n\nThis alert contains untrusted submitted content. It does not send a reply to the submitter.\n";
            $title = sanitize_text_field( $form['title'] ?? 'Form' );
            $sent = wp_mail( $settings['alert_recipients'], '[Quarantined] ' . $title . ' — entry #' . $entry_id, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
            $result = $sent ? 'Alert accepted by mail system' : 'Alert failed — check site email delivery';
        }
        $log = $this->activity();
        foreach ( $log as &$row ) {
            if ( ( $row['id'] ?? '' ) === $pending['event'] ) { $row['entry_id'] = $entry_id; $row['alert'] = $result; break; }
        }
        unset( $row );
        update_option( self::LOG, $log, false );
    }

    private function recipients( $value ) {
        if ( ! is_string( $value ) || strlen( $value ) > 10000 ) { throw new InvalidArgumentException( 'Invalid alert recipients.' ); }
        $recipients = preg_split( '/[\s,;]+/', trim( $value ), -1, PREG_SPLIT_NO_EMPTY );
        foreach ( $recipients as &$email ) {
            if ( ! is_email( $email ) ) { throw new InvalidArgumentException( 'Enter valid recipient email addresses, separated by commas or new lines.' ); }
            $email = strtolower( $email );
        }
        unset( $email );
        $recipients = array_values( array_unique( $recipients ) );
        if ( count( $recipients ) > 20 ) { throw new InvalidArgumentException( 'Maximum 20 alert recipients.' ); }
        return $recipients;
    }

    private function reset_data( $confirmation ) {
        if ( $confirmation !== 'DELETE' ) { throw new InvalidArgumentException( 'Type DELETE to confirm the reset.' ); }
        // Only our two options; never delete Gravity Forms forms, entries or entry notes.
        delete_option( self::LOG );
        delete_option( self::OPTION );
        $this->pending = array();
        $this->alerted = array();
    }

    public function disable_notification( $disabled, $notification, $form, $entry ) {
        // Also covers delayed notification attempts while the entry remains spam.
        return $disabled || ( isset( $entry['status'] ) && $entry['status'] === 'spam' );
    }

    public function activity() {
        $cutoff = time() - (int) $this->settings()['retention'] * DAY_IN_SECONDS;
        $stored = get_option( self::LOG, array() );
        $kept = array_values( array_filter( $stored, function( $row ) use ( $cutoff ) { return $row['time'] >= $cutoff; } ) );
        if ( $kept !== $stored ) { update_option( self::LOG, $kept, false ); }
        return $kept;
    }

    public function dependency_notice() {
        if ( ! class_exists( 'GFForms' ) && current_user_can( 'manage_options' ) ) {
            echo '<div class="notice notice-warning"><p>Calico Spam Guard requires Gravity Forms. Its rules are inactive until Gravity Forms is available.</p></div>';
        }
    }

    public function menu() {
        if ( class_exists( 'GFForms' ) ) {
            add_submenu_page( 'gf_edit_forms', 'Spam Rules', 'Spam Rules', 'manage_options', 'cgsg', array( $this, 'page' ) );
        } else {
            add_options_page( 'Spam Rules', 'Spam Rules', 'manage_options', 'cgsg', array( $this, 'page' ) );
        }
    }

    private function authorize() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Administrator access required.', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'cgsg' );
    }

    private function url( $tab = 'settings' ) {
        $url = admin_url( class_exists( 'GFForms' ) ? 'admin.php?page=cgsg' : 'options-general.php?page=cgsg' );
        return $tab === 'settings' ? $url : add_query_arg( 'tab', $tab, $url );
    }

    private function lines( $value ) {
        if ( ! is_string( $value ) || strlen( $value ) > 50000 ) { throw new InvalidArgumentException( 'A rule value is too large or invalid.' ); }
        return array_values( array_unique( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $value ) ), function( $v ) { return $v !== ''; } ) ) );
    }

    private function valid_domain( $value ) {
        return (bool) filter_var( $value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) && strpos( $value, '.' ) !== false;
    }

    private function clean_rule( $input ) {
        if ( ! is_array( $input ) ) { throw new InvalidArgumentException( 'Invalid rule.' ); }
        $type = isset( $input['type'] ) && is_string( $input['type'] ) ? $input['type'] : '';
        if ( ! isset( CGSG_Engine::types()[$type] ) ) { throw new InvalidArgumentException( 'Choose a supported rule type.' ); }
        $label = sanitize_text_field( is_string( $input['label'] ?? null ) ? $input['label'] : '' );
        if ( $label === '' || strlen( $label ) > 120 ) { throw new InvalidArgumentException( 'Enter a rule name of up to 120 characters.' ); }
        $values = $this->lines( $input['values'] ?? '' );
        $allow = $this->lines( $input['allow_domains'] ?? '' );
        $form_id = absint( $input['form_id'] ?? 0 );
        $fields = preg_split( '/[\s,]+/', trim( is_string( $input['fields'] ?? null ) ? $input['fields'] : '' ), -1, PREG_SPLIT_NO_EMPTY );
        foreach ( $fields as $id ) {
            if ( ! ctype_digit( $id ) || (int) $id < 1 ) { throw new InvalidArgumentException( 'Field IDs must be positive whole numbers, separated by commas.' ); }
        }
        $fields = array_values( array_unique( array_map( function( $v ) { return (string) (int) $v; }, $fields ) ) );
        if ( $fields && ! $form_id ) { throw new InvalidArgumentException( 'Choose one form when targeting specific fields.' ); }
        if ( $form_id && class_exists( 'GFAPI' ) ) {
            $form = GFAPI::get_form( $form_id );
            if ( ! $form ) { throw new InvalidArgumentException( 'The selected form does not exist.' ); }
            $available = array_map( function( $field ) { return (string) $field->id; }, $form['fields'] );
            if ( array_diff( $fields, $available ) ) { throw new InvalidArgumentException( 'One or more field IDs do not exist on this form.' ); }
        }
        if ( $type !== 'any_link' && ! $values ) { throw new InvalidArgumentException( 'Enter at least one value.' ); }
        if ( $type === 'link_count' && ( count( $values ) !== 1 || ! ctype_digit( $values[0] ) || (int) $values[0] > 1000 ) ) { throw new InvalidArgumentException( 'Link count must be one number from 0 to 1000.' ); }
        if ( in_array( $type, array( 'email_domain', 'link_domain' ), true ) ) {
            $values = array_map( array( 'CGSG_Engine', 'domain' ), $values );
            foreach ( $values as $value ) {
                if ( ! $this->valid_domain( $value ) ) { throw new InvalidArgumentException( 'Enter domains such as example.com, without @, paths or wildcards.' ); }
            }
        }
        foreach ( $allow as &$domain ) {
            $domain = CGSG_Engine::domain( $domain );
            if ( ! $this->valid_domain( $domain ) ) { throw new InvalidArgumentException( 'An allowed link domain is invalid.' ); }
        }
        unset( $domain );
        foreach ( $values as $value ) {
            if ( strlen( $value ) > 500 ) { throw new InvalidArgumentException( 'Each value must be at most 500 characters.' ); }
            if ( $type === 'email' && ! is_email( $value ) ) { throw new InvalidArgumentException( 'Enter complete email addresses.' ); }
            if ( $type === 'ip' && ! CGSG_Engine::ip_matches( explode( '/', $value )[0], $value ) ) { throw new InvalidArgumentException( 'An IP address or CIDR range is invalid.' ); }
        }
        return array( 'id' => wp_generate_uuid4(), 'enabled' => ! empty( $input['enabled'] ), 'label' => $label, 'type' => $type, 'form_id' => $form_id, 'fields' => $fields, 'values' => $values, 'allow_domains' => $allow );
    }

    public function save() {
        $this->authorize();
        $input = wp_unslash( $_POST );
        $settings = $this->settings();
        $op = isset( $input['op'] ) && is_string( $input['op'] ) ? $input['op'] : '';
        try {
            if ( $op === 'settings' ) {
                $settings['enabled'] = ! empty( $input['enabled'] );
                $settings['test_mode'] = ! empty( $input['test_mode'] );
                $settings['retention'] = max( 1, min( 90, absint( $input['retention'] ?? 30 ) ) );
                $settings['alert_recipients'] = $this->recipients( $input['alert_recipients'] ?? '' );
                $settings['alert_enabled'] = ! empty( $input['alert_enabled'] );
                if ( $settings['alert_enabled'] && ! $settings['alert_recipients'] ) { throw new InvalidArgumentException( 'Add at least one recipient before enabling quarantine alerts.' ); }
            } elseif ( $op === 'reset' ) {
                $this->reset_data( $input['confirmation'] ?? '' );
                wp_safe_redirect( add_query_arg( 'reset', '1', $this->url() ) );
                exit;
            } elseif ( $op === 'rule' ) {
                $rule = $this->clean_rule( $input );
                $edit_id = sanitize_text_field( $input['edit_id'] ?? '' );
                if ( $edit_id ) {
                    $found = false;
                    foreach ( $settings['rules'] as &$existing ) {
                        if ( $existing['id'] === $edit_id ) { $rule['id'] = $edit_id; $existing = $rule; $found = true; break; }
                    }
                    unset( $existing );
                    if ( ! $found ) { throw new InvalidArgumentException( 'The rule no longer exists. Reload and try again.' ); }
                } else {
                    if ( count( $settings['rules'] ) >= 200 ) { throw new InvalidArgumentException( 'Maximum 200 rules reached.' ); }
                    $settings['rules'][] = $rule;
                }
            } elseif ( $op === 'delete' || $op === 'toggle' ) {
                $id = sanitize_text_field( $input['id'] ?? '' );
                foreach ( $settings['rules'] as $key => &$rule ) {
                    if ( $rule['id'] !== $id ) { continue; }
                    if ( $op === 'delete' ) { unset( $settings['rules'][$key] ); } else { $rule['enabled'] = ! $rule['enabled']; }
                    break;
                }
                unset( $rule );
                $settings['rules'] = array_values( $settings['rules'] );
            } elseif ( $op === 'clear' ) {
                delete_option( self::LOG );
            } elseif ( $op === 'import' ) {
                $json = $input['json'] ?? '';
                if ( ! is_string( $json ) || strlen( $json ) > 500000 ) { throw new InvalidArgumentException( 'Import is too large.' ); }
                $data = json_decode( $json, true );
                if ( ! is_array( $data ) || ( $data['format'] ?? '' ) !== 'cgsg-1' || ! is_array( $data['rules'] ?? null ) || count( $data['rules'] ) + count( $settings['rules'] ) > 200 ) { throw new InvalidArgumentException( 'Invalid export or more than 200 total rules.' ); }
                $new = array();
                foreach ( $data['rules'] as $raw ) {
                    if ( ! is_array( $raw ) || ! is_array( $raw['values'] ?? null ) || ! is_array( $raw['fields'] ?? null ) || ! is_array( $raw['allow_domains'] ?? null ) ) { throw new InvalidArgumentException( 'Invalid imported rule.' ); }
                    foreach ( array_merge( $raw['values'], $raw['fields'], $raw['allow_domains'] ) as $v ) {
                        if ( ! is_string( $v ) ) { throw new InvalidArgumentException( 'Invalid imported value.' ); }
                    }
                    $raw['values'] = implode( "\n", $raw['values'] );
                    $raw['fields'] = implode( ',', $raw['fields'] );
                    $raw['allow_domains'] = implode( "\n", $raw['allow_domains'] );
                    $new[] = $this->clean_rule( $raw );
                }
                $settings['rules'] = array_merge( $settings['rules'], $new );
            } else { throw new InvalidArgumentException( 'Unknown action.' ); }
            update_option( self::OPTION, $settings, false );
            $tab = $op === 'settings' ? 'settings' : ( $op === 'clear' ? 'activity' : 'rules' );
            wp_safe_redirect( add_query_arg( 'saved', '1', $this->url( $tab ) ) );
            exit;
        } catch ( InvalidArgumentException $e ) {
            wp_die( esc_html( $e->getMessage() ) . ' Use your browser Back button to correct the form.', 'Spam Rules', array( 'back_link' => true, 'response' => 400 ) );
        }
    }

    public function export() {
        $this->authorize();
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="calico-spam-rules.json"' );
        echo wp_json_encode( array( 'format' => 'cgsg-1', 'rules' => $this->settings()['rules'] ), JSON_PRETTY_PRINT );
        exit;
    }

    private function start_form( $op ) {
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'cgsg' );
        echo '<input type="hidden" name="action" value="cgsg_save"><input type="hidden" name="op" value="' . esc_attr( $op ) . '">';
    }

    public function page() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $settings = $this->settings();
        $forms = class_exists( 'GFAPI' ) ? GFAPI::get_forms() : array();
        $tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';
        if ( ! in_array( $tab, array( 'settings', 'rules', 'activity' ), true ) ) { $tab = 'settings'; }
        $editing = null;
        $edit_id = isset( $_GET['edit'] ) && is_string( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : '';
        if ( $edit_id ) { $tab = 'rules'; }
        foreach ( $settings['rules'] as $rule ) { if ( $rule['id'] === $edit_id ) { $editing = $rule; } }
        $draft = $editing ?: array( 'id' => '', 'enabled' => true, 'label' => '', 'type' => 'email', 'form_id' => 0, 'fields' => array(), 'values' => array(), 'allow_domains' => array() );
        echo '<div class="wrap" style="max-width:1150px"><h1>Calico Spam Guard</h1><nav class="nav-tab-wrapper" aria-label="Spam Guard tabs">';
        foreach ( array( 'settings' => 'Settings', 'rules' => 'Rules', 'activity' => 'Activity Log' ) as $key => $label ) {
            echo '<a class="nav-tab' . ( $tab === $key ? ' nav-tab-active' : '' ) . '" ' . ( $tab === $key ? 'aria-current="page" ' : '' ) . 'href="' . esc_url( $this->url( $key ) ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav>';
        if ( isset( $_GET['saved'] ) ) { echo '<div class="notice notice-success inline"><p>Saved.</p></div>'; }
        if ( isset( $_GET['reset'] ) ) { echo '<div class="notice notice-success inline"><p>All Spam Guard rules, settings, alert recipients and logs were deleted. Gravity Forms forms and entries were retained.</p></div>'; }
        // Fixed allowlist above prevents including arbitrary paths.
        include __DIR__ . '/views/' . $tab . '.php';
        echo '</div>';
    }

    private function field_choices( $form ) {
        $choices = array( '__ip' => 'Submitter IP address' );
        foreach ( $form['fields'] as $field ) {
            if ( ! in_array( $field->get_input_type(), CGSG_Engine::field_types(), true ) ) { continue; }
            $label = $field->label ?: 'Field ' . $field->id;
            if ( ! empty( $field->inputs ) && is_array( $field->inputs ) ) {
                foreach ( $field->inputs as $input ) {
                    if ( ! empty( $input['isHidden'] ) ) { continue; }
                    $choices[(string) $input['id']] = $label . ' — ' . ( $input['label'] ?? $input['id'] );
                }
            } else {
                $choices[(string) $field->id] = $label . ' (' . $field->get_input_type() . ')';
            }
        }
        return $choices;
    }

    private function tester( $forms, $settings ) {
        echo '<h2>Rule tester</h2><p>Choose a form and field, then enter a sample value. Only that field is tested; other fields are empty. No entry, email or log event is created.</p>';
        $test_form = isset( $_POST['cgsg_test'] ) ? absint( $_POST['test_form'] ?? 0 ) : absint( $_GET['test_form'] ?? ( $forms[0]['id'] ?? 0 ) );
        $form = class_exists( 'GFAPI' ) ? GFAPI::get_form( $test_form ) : false;
        $choices = $form ? $this->field_choices( $form ) : array();
        $chosen = isset( $_POST['test_field'] ) && is_string( $_POST['test_field'] ) ? wp_unslash( $_POST['test_field'] ) : '';
        if ( $chosen === '' ) {
            foreach ( $choices as $key => $unused ) { if ( $key !== '__ip' ) { $chosen = (string) $key; break; } }
            if ( $chosen === '' ) { $chosen = '__ip'; }
        }
        $value = isset( $_POST['test_value'] ) && is_string( $_POST['test_value'] ) ? wp_unslash( $_POST['test_value'] ) : '';
        if ( isset( $_POST['cgsg_test'] ) ) {
            check_admin_referer( 'cgsg_test' );
            if ( ! $form || ! array_key_exists( $chosen, $choices ) || strlen( $value ) > 50000 ) {
                echo '<div class="notice notice-error inline"><p>Select a valid form and field, and enter a sample up to 50,000 bytes.</p></div>';
            } elseif ( $chosen === '__ip' && ! filter_var( trim( $value ), FILTER_VALIDATE_IP ) ) {
                echo '<div class="notice notice-error inline"><p>Enter a valid IPv4 or IPv6 address.</p></div>';
            } else {
                $entry = $chosen === '__ip' ? array() : array( $chosen => $value );
                $hits = CGSG_Engine::evaluate( $settings['rules'], $form, $entry, $chosen === '__ip' ? trim( $value ) : '' );
                $result = $hits ? 'Matched: ' . implode( ', ', array_column( $hits, 'label' ) ) . '.' : 'No rules matched.';
                if ( ! $settings['enabled'] ) { $result .= ' Protection is currently disabled.'; }
                elseif ( $settings['test_mode'] ) { $result .= ' Test mode is active: submissions would be allowed.'; }
                elseif ( $hits ) { $result .= ' This value would quarantine a submission.'; }
                echo '<div class="notice notice-info inline"><p>' . esc_html( $result ) . '</p></div>';
            }
        }
        echo '<form method="get" action="' . esc_url( admin_url( class_exists( 'GFForms' ) ? 'admin.php' : 'options-general.php' ) ) . '"><input type="hidden" name="page" value="cgsg"><input type="hidden" name="tab" value="rules"><p><label for="cgsg-test-form">Form </label><select name="test_form" id="cgsg-test-form">';
        foreach ( $forms as $item ) { echo '<option value="' . esc_attr( $item['id'] ) . '" ' . selected( $test_form, $item['id'], false ) . '>' . esc_html( $item['title'] ) . '</option>'; }
        echo '</select> <button class="button">Load fields</button></p></form>';
        if ( ! $form ) { echo '<p>No form available to test.</p>'; return; }
        echo '<form method="post" action="' . esc_url( $this->url( 'rules' ) ) . '">';
        wp_nonce_field( 'cgsg_test' );
        echo '<input type="hidden" name="test_form" value="' . esc_attr( $test_form ) . '"><p><label for="cgsg-test-field">Field </label><select id="cgsg-test-field" name="test_field">';
        foreach ( $choices as $key => $label ) { echo '<option value="' . esc_attr( $key ) . '" ' . selected( $chosen, $key, false ) . '>' . esc_html( $label ) . '</option>'; }
        echo '</select></p><p><label for="cgsg-test-value">Value to test</label></p><textarea class="large-text" id="cgsg-test-value" name="test_value" rows="4" required placeholder="Enter an email address, message, link or IP address">' . esc_textarea( $value ) . '</textarea><p><button class="button" name="cgsg_test" value="1">Test rules</button></p></form>';
    }
}
