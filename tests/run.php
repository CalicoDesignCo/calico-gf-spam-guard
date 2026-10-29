<?php
// Isolated behavior tests: no WordPress or Gravity Forms installation is claimed.
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
$options = array();
$hooks = array();
function add_filter( $name, $callback, $priority = 10, $args = 1 ) { global $hooks; $hooks[$name] = array( $callback, $args ); }
function add_action( $name, $callback, $priority = 10, $args = 1 ) { add_filter( $name, $callback, $priority, $args ); }
function get_option( $name, $default = false ) { global $options; return $options[$name] ?? $default; }
function update_option( $name, $value, $autoload = null ) { global $options; $options[$name] = $value; }
function delete_option( $name ) { global $options; unset( $options[$name] ); }
function wp_parse_args( $value, $defaults ) { return array_merge( $defaults, $value ); }
function sanitize_text_field( $value ) { return is_scalar( $value ) ? trim( strip_tags( $value ) ) : ''; }
function absint( $value ) { return abs( (int) $value ); }
function is_email( $value ) { return filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function wp_generate_uuid4() { static $id = 0; return 'test-' . ++$id; }
class GFCommon {
    public static $reason = null;
    public static function set_spam_filter( $form, $name, $reason ) { self::$reason = $reason; }
}
class GFFormsModel { public static function get_ip() { return '203.0.113.8'; } }
class TestField {
    public $id;
    public $label = '';
    public $inputs = null;
    private $type;
    public function __construct( $id, $type ) { $this->id = $id; $this->type = $type; }
    public function get_input_type() { return $this->type; }
}
require __DIR__ . '/../calico-gf-spam-guard/includes/class-cgsg-engine.php';
require __DIR__ . '/../calico-gf-spam-guard/includes/class-cgsg-plugin.php';
$count = 0;
function expect( $condition, $message ) {
    global $count;
    $count++;
    if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
}
function rule( $type, $values = array(), $extra = array() ) {
    return array_merge( array( 'id' => 'r1', 'label' => 'Blocked', 'enabled' => true, 'type' => $type, 'values' => $values, 'fields' => array(), 'form_id' => 0, 'allow_domains' => array() ), $extra );
}
$form = array( 'id' => 1, 'fields' => array( new TestField( 1, 'email' ), new TestField( 2, 'textarea' ), new TestField( 3, 'text' ), new TestField( 4, 'password' ), new TestField( 5, 'name' ), new TestField( 6, 'hidden' ) ) );
function matches( $rule, $entry, $ip = '' ) { global $form; return count( CGSG_Engine::evaluate( array( $rule ), $form, $entry, $ip ) ) > 0; }
expect( matches( rule( 'email', array( 'bad@example.com' ) ), array( 1 => 'BAD@EXAMPLE.COM' ) ), 'email ignores case' );
expect( ! matches( rule( 'email', array( 'bad@example.com' ) ), array( 2 => 'bad@example.com' ) ), 'default email scope only email fields' );
expect( matches( rule( 'email', array( 'bad@example.com' ), array( 'fields' => array( '3' ) ) ), array( 3 => 'bad@example.com' ) ), 'explicit email field scope' );
expect( matches( rule( 'email_domain', array( 'example.com' ) ), array( 1 => 'x@sub.example.com' ) ), 'email subdomains' );
expect( ! matches( rule( 'email_domain', array( 'example.com' ) ), array( 1 => 'x@notexample.com' ) ), 'email domain boundary' );
expect( matches( rule( 'contains', array( 'SEO service' ) ), array( 2 => 'Our seo services are available' ) ), 'contains phrase' );
expect( matches( rule( 'contains', array( 'café' ) ), array( 2 => 'CAFÉ' ) ), 'Unicode case folding' );
expect( matches( rule( 'contains', array( 'a+b' ) ), array( 2 => 'a+b' ) ), 'literal punctuation is escaped' );
expect( ! matches( rule( 'word', array( 'ass' ) ), array( 2 => 'class' ) ), 'word boundary avoids substring' );
expect( matches( rule( 'word', array( 'buy now' ) ), array( 2 => 'Please BUY NOW!' ) ), 'whole phrase' );
expect( ! matches( rule( 'word', array( 'cat' ) ), array( 2 => 'caté' ) ), 'Unicode boundary' );
expect( matches( rule( 'exact', array( 'hello' ) ), array( 2 => ' HELLO ' ) ), 'exact trims' );
expect( ! matches( rule( 'exact', array( 'hello' ) ), array( 2 => 'hello there' ) ), 'exact is not contains' );
expect( matches( rule( 'contains', array( 'spam' ) ), array( '5.3' => 'spam' ) ), 'compound fields' );
expect( ! matches( rule( 'contains', array( 'spam' ) ), array( 4 => 'spam', 6 => 'spam' ) ), 'passwords and hidden fields excluded' );
expect( ! matches( rule( 'contains', array( 'spam' ), array( 'fields' => array( '2' ) ) ), array( 3 => 'spam' ) ), 'field targeting' );
expect( ! matches( rule( 'contains', array( 'spam' ), array( 'form_id' => 2 ) ), array( 2 => 'spam' ) ), 'form targeting' );
expect( ! matches( rule( 'contains', array( 'spam' ), array( 'enabled' => false ) ), array( 2 => 'spam' ) ), 'disabled rule' );
foreach ( array( 'https://bad.example/path', 'www.bad.example', 'bad.example', '<a href="https://bad.example">click</a>', 'https://203.0.113.8/path' ) as $url ) {
    expect( matches( rule( 'any_link' ), array( 2 => $url ) ), 'link format: ' . $url );
}
expect( ! matches( rule( 'any_link' ), array( 1 => 'person@example.com', 2 => 'No link here.' ) ), 'email is not a link' );
expect( ! matches( rule( 'any_link', array(), array( 'allow_domains' => array( 'good.example' ) ) ), array( 2 => 'https://sub.good.example' ) ), 'allowed link subdomain' );
expect( matches( rule( 'any_link', array(), array( 'allow_domains' => array( 'good.example' ) ) ), array( 2 => 'https://good.example.evil.test' ) ), 'allow domain boundary' );
expect( matches( rule( 'link_domain', array( 'bad.example' ) ), array( 2 => 'https://sub.bad.example/path' ) ), 'link domain subdomains' );
expect( ! matches( rule( 'link_domain', array( 'bad.example' ) ), array( 2 => 'https://notbad.example' ) ), 'link domain boundary' );
expect( matches( rule( 'link_count', array( '1' ) ), array( 2 => 'https://a.example https://b.example' ) ), 'link count above threshold' );
expect( ! matches( rule( 'link_count', array( '1' ) ), array( 2 => 'https://a.example' ) ), 'link count at threshold' );
expect( ! matches( rule( 'link_count', array( '1' ), array( 'allow_domains' => array( 'a.example' ) ) ), array( 2 => 'https://a.example https://b.example' ) ), 'allowed links excluded from count' );
foreach ( array(
    array( '203.0.113.8', '203.0.113.8', true ), array( '203.0.113.8', '203.0.113.0/24', true ),
    array( '203.0.114.8', '203.0.113.0/24', false ), array( '203.0.113.8', '203.0.113.0/25', true ),
    array( '203.0.113.200', '203.0.113.0/25', false ), array( '2001:db8::1', '2001:db8::/32', true ),
    array( '2001:db9::1', '2001:db8::/32', false ), array( '::1', '::1', true ),
    array( '::1', '0.0.0.0/0', false ), array( '203.0.113.8', '0.0.0.0/0', true ),
    array( '203.0.113.8', '203.0.113.8/33', false ), array( '::1', '::/129', false ),
    array( 'bad', '0.0.0.0/0', false ), array( '203.0.113.8', '203.0.113.8/no', false ),
) as $case ) { expect( CGSG_Engine::ip_matches( $case[0], $case[1] ) === $case[2], 'IP case ' . implode( ' ', $case ) ); }
expect( matches( rule( 'ip', array( '203.0.113.0/24' ) ), array(), '203.0.113.8' ), 'IP engine integration' );
$plugin = new CGSG_Plugin();
expect( $hooks['gform_entry_is_spam'][1] === 3, 'spam hook argument count' );
expect( $hooks['gform_disable_notification'][1] === 4, 'notification hook argument count' );
$options[CGSG_Plugin::OPTION] = array( 'rules' => array( rule( 'contains', array( 'spam' ) ) ) );
expect( $plugin->check_spam( false, $form, array( 2 => 'spam' ) ) === true, 'quarantine marks as spam' );
expect( GFCommon::$reason === 'Matched rules: Blocked', 'Gravity Forms reason recorded without content' );
expect( count( $options[CGSG_Plugin::LOG] ) === 1, 'match logged' );
expect( ! isset( $options[CGSG_Plugin::LOG][0]['ip'] ), 'log does not duplicate IP' );
expect( ! isset( $options[CGSG_Plugin::LOG][0]['entry'] ), 'log does not duplicate entry' );
expect( $plugin->check_spam( false, $form, array( 2 => 'real lead' ) ) === false, 'legitimate submission allowed' );
expect( count( $options[CGSG_Plugin::LOG] ) === 1, 'no logging of nonmatches' );
$options[CGSG_Plugin::OPTION]['test_mode'] = true;
GFCommon::$reason = null;
expect( $plugin->check_spam( false, $form, array( 2 => 'spam' ) ) === false, 'test mode does not quarantine' );
expect( GFCommon::$reason === null, 'test mode does not set GF spam reason' );
expect( $options[CGSG_Plugin::LOG][0]['action'] === 'Test match — allowed', 'test result logged' );
expect( $plugin->check_spam( true, $form, array() ) === true, 'other provider spam never cleared in test mode' );
$options[CGSG_Plugin::OPTION]['enabled'] = false;
expect( $plugin->check_spam( false, $form, array( 2 => 'spam' ) ) === false, 'global switch' );
expect( $plugin->check_spam( true, $form, array() ) === true, 'other provider spam never cleared when disabled' );
expect( $plugin->disable_notification( false, array(), $form, array( 'status' => 'spam' ) ), 'spam notification blocked' );
expect( ! $plugin->disable_notification( false, array(), $form, array( 'status' => 'active' ) ), 'restored entry can send notification' );
expect( $plugin->disable_notification( true, array(), $form, array( 'status' => 'active' ) ), 'preserves other notification suppression' );
$options[CGSG_Plugin::LOG][] = array( 'time' => time() - 31 * DAY_IN_SECONDS );
expect( count( $plugin->activity() ) === 2, 'expired activity pruned' );
$clean = new ReflectionMethod( $plugin, 'clean_rule' );
$clean->setAccessible( true );
$valid = array( 'type' => 'word', 'label' => 'Test rule', 'enabled' => 1, 'values' => "spam\nspam\nspam offer", 'fields' => '', 'form_id' => 0 );
expect( count( $clean->invoke( $plugin, $valid )['values'] ) === 2, 'rule values deduplicated' );
foreach ( array(
    array( 'type' => 'unknown' ), array( 'label' => '' ), array( 'fields' => '2' ),
    array( 'type' => 'email', 'values' => 'broken' ), array( 'type' => 'ip', 'values' => '203.0.113.1/33' ),
    array( 'type' => 'link_count', 'values' => '-1' ), array( 'type' => 'link_count', 'values' => "1\n2" ),
    array( 'type' => 'email_domain', 'values' => 'https://example.com' ), array( 'allow_domains' => 'bad/path' ),
    array( 'values' => array( 'bad' ) ),
) as $bad ) {
    try { $clean->invoke( $plugin, array_merge( $valid, $bad ) ); expect( false, 'invalid rule rejected' ); }
    catch ( InvalidArgumentException $e ) { expect( true, 'invalid rule rejected' ); }
}
$options[CGSG_Plugin::OPTION] = array( 'rules' => array( rule( 'ip', array( '203.0.113.8' ) ) ) );
expect( $plugin->check_spam( false, $form, array() ) === true, 'IP fallback when IP storage disabled' );
for ( $i = 0; $i < 205; $i++ ) { $plugin->check_spam( false, $form, array() ); }
expect( count( $options[CGSG_Plugin::LOG] ) === 200, 'log storage bounded' );
echo "PASS: $count assertions\n";
