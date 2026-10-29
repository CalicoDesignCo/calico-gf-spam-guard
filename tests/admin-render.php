<?php
// Render the admin page with escaped hostile input, using WordPress API doubles.
ob_start();
require __DIR__ . '/run.php';
ob_end_clean();
$authorized = true;
$nonce_valid = true;
class TestRedirect extends RuntimeException {}
class TestDenied extends RuntimeException {}
function current_user_can( $cap ) { global $authorized; return $authorized; }
function check_admin_referer( $action ) { global $nonce_valid; if ( ! $nonce_valid ) { throw new TestDenied( 'Invalid nonce' ); } }
function wp_safe_redirect( $url ) { throw new TestRedirect( $url ); }
function wp_die( $message, $title = '', $args = array() ) { throw new TestDenied( $message ); }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_attr( $value ); }
function esc_textarea( $value ) { return esc_html( $value ); }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="stub-nonce">'; }
function wp_nonce_url( $url, $action ) { return $url . '&_wpnonce=stub-nonce'; }
function selected( $a, $b, $echo = true ) { $value = (string) $a === (string) $b ? 'selected="selected"' : ''; if ( $echo ) { echo $value; } return $value; }
function checked( $a, $b = true, $echo = true ) { $value = $a == $b ? 'checked="checked"' : ''; if ( $echo ) { echo $value; } return $value; }
function submit_button( $label, $type = 'primary' ) { echo '<p><button type="submit">' . esc_html( $label ) . '</button></p>'; }
function add_query_arg( $key, $value, $url ) { return $url . '&' . urlencode( $key ) . '=' . urlencode( $value ); }
function wp_date( $format, $time ) { return gmdate( $format, $time ); }
function wp_unslash( $value ) { return $value; }
class GFAPI {
    public static function get_forms() {
        $field = new TestField( 2, 'textarea' );
        $field->label = '<script>alert("field")</script>';
        $email = new TestField( 1, 'email' ); $email->label = 'Email';
        $name = new TestField( 5, 'name' ); $name->label = 'Name'; $name->inputs = array( array( 'id' => '5.3', 'label' => 'First' ), array( 'id' => '5.6', 'label' => 'Last' ) );
        return array( array( 'id' => 1, 'title' => '<script>alert("form")</script>', 'fields' => array( $field, $email, $name, new TestField( 4, 'password' ), new TestField( 6, 'hidden' ) ) ) );
    }
    public static function get_form( $id ) { return $id === 1 ? self::get_forms()[0] : false; }
}
$options[CGSG_Plugin::OPTION] = array( 'rules' => array( rule( 'contains', array( '<script>alert("value")</script>' ), array( 'label' => '<script>alert("rule")</script>' ) ) ) );
$options[CGSG_Plugin::LOG] = array( array( 'time' => time(), 'form' => 1, 'action' => '<script>alert("action")</script>', 'labels' => '<script>alert("log")</script>' ) );
$_GET = array( 'tab' => 'rules' );
ob_start();
$plugin->page();
$html = ob_get_clean();
expect( strpos( $html, '<script>' ) === false, 'admin output escapes scripts' );
expect( strpos( $html, '&lt;script&gt;' ) !== false, 'escaped content displayed' );
expect( strpos( $html, 'name="_wpnonce"' ) !== false, 'admin forms include nonce' );
expect( strpos( $html, 'Rule tester' ) !== false, 'admin includes tester' );
expect( strpos( $html, 'Import / export' ) !== false, 'admin includes portability' );
expect( strpos( $html, 'name="test_field"' ) !== false, 'tester has field dropdown' );
expect( strpos( $html, 'name="test_values"' ) === false, 'JSON tester removed' );
expect( strpos( $html, 'value="5.3"' ) !== false, 'tester supports compound inputs' );
expect( strpos( $html, 'Quarantine email alerts' ) === false, 'settings absent from rules tab' );
expect( strpos( $html, 'class="cgsg-event"' ) === false, 'log absent from rules tab' );
$_GET['edit'] = 'r1';
ob_start(); $plugin->page(); $edited = ob_get_clean();
expect( strpos( $edited, 'name="edit_id" value="r1"' ) !== false, 'existing rule editor opens' );
expect( strpos( $edited, '<script>' ) === false, 'editor escapes textarea and attribute values' );
$clean = new ReflectionMethod( $plugin, 'clean_rule' );
$clean->setAccessible( true );
foreach ( array( array( 'form_id' => 99 ), array( 'form_id' => 1, 'fields' => '999' ) ) as $bad ) {
    try { $clean->invoke( $plugin, array_merge( $valid, $bad ) ); expect( false, 'missing form/field rejected' ); }
    catch ( InvalidArgumentException $e ) { expect( true, 'missing form/field rejected' ); }
}
$_GET = array();
ob_start(); $plugin->page(); $settings_html = ob_get_clean();
expect( strpos( $settings_html, 'Quarantine email alerts' ) !== false, 'settings remains default tab' );
expect( strpos( $settings_html, 'Delete all logs and data' ) !== false, 'settings includes reset' );
expect( strpos( $settings_html, 'name="confirmation"' ) !== false, 'reset requires typed confirmation' );
expect( strpos( $settings_html, 'id="rule-editor"' ) === false, 'rules absent from settings tab' );
$events = array();
for ( $i = 1; $i <= 41; $i++ ) { $events[] = array( 'time' => time(), 'form' => 1, 'action' => 'Matched', 'labels' => '[event-' . $i . ']' ); }
$options[CGSG_Plugin::LOG] = $events;
foreach ( array( 1 => 20, 2 => 20, 3 => 1, 99 => 1, 0 => 20 ) as $page_number => $expected ) {
    $_GET = array( 'tab' => 'activity', 'log_page' => $page_number );
    ob_start(); $plugin->page(); $log_html = ob_get_clean();
    expect( substr_count( $log_html, 'class="cgsg-event"' ) === $expected, 'pagination page ' . $page_number );
    expect( strpos( $log_html, 'id="rule-editor"' ) === false, 'rules absent from activity' );
    if ( $page_number === 2 ) { expect( strpos( $log_html, '[event-21]' ) !== false && strpos( $log_html, '[event-1]' ) === false, 'second page is correct slice' ); }
}
$_GET = array( 'tab' => '../../bad' );
ob_start(); $plugin->page(); $safe_html = ob_get_clean();
expect( strpos( $safe_html, 'Quarantine email alerts' ) !== false, 'unknown tab falls back to settings' );
$options[CGSG_Plugin::OPTION] = array( 'rules' => array( rule( 'email', array( 'bad@example.com' ) ), rule( 'contains', array( 'spam' ) ) ) );
$_GET = array( 'tab' => 'rules' );
$_POST = array( 'cgsg_test' => 1, 'test_form' => 1, 'test_field' => '1', 'test_value' => 'bad@example.com' );
ob_start(); $plugin->page(); $test_html = ob_get_clean();
expect( strpos( $test_html, 'This value would quarantine' ) !== false, 'email dropdown test matches' );
expect( strpos( $test_html, '>bad@example.com</textarea>' ) !== false, 'tester preserves sample value' );
expect( count( $options[CGSG_Plugin::LOG] ) === 41, 'tester creates no log events' );
$_POST['test_field'] = '6';
ob_start(); $plugin->page(); $invalid_html = ob_get_clean();
expect( strpos( $invalid_html, 'Select a valid form and field' ) !== false, 'tester rejects forged hidden field' );
$_POST = array( 'op' => 'settings', 'enabled' => 1, 'alert_enabled' => 1, 'alert_recipients' => "ONE@example.com, two@example.com\nONE@example.com" );
try { $plugin->save(); expect( false, 'save redirects' ); } catch ( TestRedirect $e ) { expect( strpos( $e->getMessage(), 'saved=1' ) !== false, 'settings saved' ); }
expect( $plugin->settings()['alert_recipients'] === array( 'one@example.com', 'two@example.com' ), 'recipient validation and deduplication' );
expect( count( $plugin->settings()['rules'] ) === 2, 'saving settings preserves existing rules' );
$before = $options;
$_POST['alert_recipients'] = "bad@example.com\r\nBcc: injected@example.com";
try { $plugin->save(); expect( false, 'invalid recipient rejected' ); } catch ( TestDenied $e ) { expect( $options === $before, 'invalid recipients cannot partially save settings' ); }
$_POST = array( 'op' => 'reset', 'confirmation' => 'no' );
try { $plugin->save(); expect( false, 'unconfirmed reset rejected' ); } catch ( TestDenied $e ) { expect( $options === $before, 'reset confirmation enforced' ); }
$_POST['confirmation'] = 'DELETE';
$nonce_valid = false;
try { $plugin->save(); expect( false, 'CSRF reset rejected' ); } catch ( TestDenied $e ) { expect( $options === $before, 'reset nonce enforced' ); }
$nonce_valid = true; $authorized = false;
try { $plugin->save(); expect( false, 'unauthorized reset rejected' ); } catch ( TestDenied $e ) { expect( $options === $before, 'reset capability enforced' ); }
$authorized = true;
$options['unrelated_form_data'] = array( 'keep' => true );
try { $plugin->save(); expect( false, 'reset redirects' ); } catch ( TestRedirect $e ) { expect( strpos( $e->getMessage(), 'reset=1' ) !== false, 'reset redirects to settings' ); }
expect( ! isset( $options[CGSG_Plugin::OPTION] ) && ! isset( $options[CGSG_Plugin::LOG] ), 'reset deletes plugin options' );
expect( $options['unrelated_form_data'] === array( 'keep' => true ), 'reset retains unrelated data' );
expect( ! $plugin->settings()['rules'] && ! $plugin->settings()['alert_enabled'], 'reset restores blank rules and alerts off' );
$_POST = array(); $_GET = array();
echo "PASS: admin rendering and escaping; $count total assertions\n";
