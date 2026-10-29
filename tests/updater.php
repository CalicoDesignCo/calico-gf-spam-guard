<?php
define( 'ABSPATH', __DIR__ );
$cache = false; $response = array( 'code' => 200, 'body' => '' ); $requests = 0; $allowed = true;
function plugin_basename( $file ) { return 'calico-gf-spam-guard/' . basename( $file ); }
function add_filter( $name, $callback, $priority = 10, $args = 1 ) {}
function add_action( $name, $callback, $priority = 10, $args = 1 ) {}
function current_user_can( $cap ) { global $allowed; return $allowed; }
function get_site_transient( $key ) { global $cache; return $cache; }
function set_site_transient( $key, $value, $ttl ) { $GLOBALS['cache'] = $value; $GLOBALS['ttl'] = $ttl; }
function delete_site_transient( $key ) { $GLOBALS['cache'] = false; }
function wp_safe_remote_get( $url, $args ) { ++$GLOBALS['requests']; $GLOBALS['request_args'] = $args; return $GLOBALS['response']; }
function is_wp_error( $response ) { return false === $response; }
function wp_remote_retrieve_response_code( $response ) { return $response['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function esc_url( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
require __DIR__ . '/../calico-gf-spam-guard/includes/class-cgsg-updater.php';
CGSG_Updater::register( __DIR__ . '/../calico-gf-spam-guard/calico-gf-spam-guard.php' );
$count = 0;
function check( $ok, $label ) { global $count; ++$count; if ( ! $ok ) { fwrite( STDERR, "FAIL: $label\n" ); exit( 1 ); } }
$release = array( 'id' => 1, 'tag_name' => 'v0.3.0', 'draft' => false, 'prerelease' => false, 'assets' => array( array( 'name' => 'calico-gf-spam-guard.zip', 'state' => 'uploaded', 'size' => 123, 'browser_download_url' => CGSG_Updater::REPO . '/releases/download/v0.3.0/calico-gf-spam-guard.zip' ) ) );
check( CGSG_Updater::parse( $release )['version'] === '0.3.0', 'Stable release parsed' );
foreach ( array( 'draft', 'prerelease' ) as $flag ) { $bad = $release; $bad[$flag] = true; check( false === CGSG_Updater::parse( $bad ), "$flag excluded" ); }
foreach ( array( 'v0.3.0-rc1', 'main', 'v0.3', array(), null, true ) as $tag ) { $bad = $release; $bad['tag_name'] = $tag; check( false === CGSG_Updater::parse( $bad ), 'Invalid tag excluded without fatal error' ); }
foreach ( array( null, false, 'invalid', array() ) as $bad ) { check( false === CGSG_Updater::parse( $bad ), 'Malformed release rejected' ); }
foreach ( array( null, 'invalid', array() ) as $assets ) { $bad = $release; $bad['assets'] = $assets; check( false === CGSG_Updater::parse( $bad ), 'Missing/malformed assets rejected' ); }
foreach ( array( 'https://evil.example/package.zip', CGSG_Updater::REPO . '/releases/latest/download/calico-gf-spam-guard.zip' ) as $url ) { $bad = $release; $bad['assets'][0]['browser_download_url'] = $url; check( false === CGSG_Updater::parse( $bad ), 'Foreign or unpinned package rejected' ); }
foreach ( array( 0, -1, 'bad', array() ) as $size ) { $bad = $release; $bad['assets'][0]['size'] = $size; check( false === CGSG_Updater::parse( $bad ), 'Invalid asset size rejected' ); }
$bad = $release; $bad['assets'][0]['name'] = 'source.zip'; check( false === CGSG_Updater::parse( $bad ), 'Wrong asset rejected' );
$bad = $release; $bad['assets'][0]['state'] = 'new'; check( false === CGSG_Updater::parse( $bad ), 'Incomplete asset rejected' );
$response['body'] = json_encode( $release );
$plugin = 'calico-gf-spam-guard/calico-gf-spam-guard.php';
$result = CGSG_Updater::update( false, array( 'Version' => '0.2.0' ), $plugin, array() );
check( '0.3.0' === $result['version'] && false === strpos( $result['package'], '/latest/' ), 'Older installation gets pinned update' );
check( false === CGSG_Updater::update( false, array( 'Version' => '0.3.0' ), $plugin, array() ), 'Equal version not offered' );
check( false === CGSG_Updater::update( false, array( 'Version' => '1.0.0' ), $plugin, array() ), 'Downgrade not offered' );
check( 'untouched' === CGSG_Updater::update( 'untouched', array( 'Version' => '0' ), 'other/other.php', array() ), 'Other GitHub plugins untouched' );
check( $requests === 1 && $GLOBALS['ttl'] === 21600, 'Successful metadata cached' );
check( $GLOBALS['request_args']['sslverify'] && $GLOBALS['request_args']['timeout'] === 8 && $GLOBALS['request_args']['limit_response_size'] === 524288, 'Bounded TLS request' );
check( ! isset( $GLOBALS['request_args']['headers']['Authorization'] ), 'No token shipped' );
check( ! isset( $result['autoupdate'] ), 'Automatic update preference left to admin' );
check( CGSG_Updater::details( false, 'plugin_information', (object) array( 'slug' => CGSG_Updater::SLUG ) )->version === '0.3.0', 'Plugin details populated' );
check( 'original' === CGSG_Updater::details( 'original', 'plugin_information', (object) array( 'slug' => 'other' ) ), 'Other details untouched' );
foreach ( array( array( 'code' => 403, 'body' => '{}' ), array( 'code' => 200, 'body' => '{invalid' ), false ) as $failure ) {
    $cache = false; $response = $failure;
    check( false === CGSG_Updater::release() && $GLOBALS['ttl'] === 900, 'API failure safely cached' );
    $before = $requests; CGSG_Updater::release(); check( $requests === $before, 'Negative cache avoids repeated failed requests' );
}
$_GET['force-check'] = 1; CGSG_Updater::refresh(); check( false === $cache, 'Check again refreshes updater cache' );
$cache = array( 'release' => false ); $allowed = false; CGSG_Updater::refresh(); check( is_array( $cache ), 'Refresh respects update capability' );
echo "PASS: updater $count assertions\n";
