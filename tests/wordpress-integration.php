<?php
// Disposable Playground only. Real WordPress APIs, fixture GitHub transport, no Gravity Forms installed.
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
$checks = array();
function verify_cgsg( $ok, $label ) { global $checks; if ( ! $ok ) { throw new Exception( 'FAIL: ' . $label ); } $checks[] = $label; }
wp_set_current_user( 1 );
$plugin = 'calico-gf-spam-guard/calico-gf-spam-guard.php';
$zip = '/artifacts/calico-gf-spam-guard.zip';
$installer = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
$installed = $installer->install( $zip );
verify_cgsg( $installed === true, 'Exact release ZIP installs on clean WordPress' );
$activation = activate_plugin( $plugin );
verify_cgsg( ! is_wp_error( $activation ) && is_plugin_active( $plugin ), 'Plugin activates cleanly without Gravity Forms; dependency warning is nonfatal' );
$path = WP_PLUGIN_DIR . '/' . $plugin;
$data = get_plugin_data( $path );
verify_cgsg( $data['Version'] === '0.3.0' && $data['UpdateURI'] === CGSG_Updater::REPO, 'Version and Update URI read by WordPress' );
verify_cgsg( has_filter( 'update_plugins_github.com', array( 'CGSG_Updater', 'update' ) ) !== false, 'Native updater hook registered' );
$settings = array( 'enabled' => true, 'test_mode' => true, 'retention' => 14, 'rules' => array( array( 'id' => 'preserved-rule', 'enabled' => true, 'label' => 'Preserve this rule', 'type' => 'email', 'values' => array( 'bad@example.com' ), 'form_id' => 0, 'fields' => array(), 'allow_domains' => array() ) ), 'alert_enabled' => false, 'alert_recipients' => array( 'admin@example.com' ) );
$activity = array( array( 'time' => time(), 'form' => 1, 'action' => 'Test match — allowed', 'rules' => array( 'preserved-rule' ), 'labels' => 'Preserve this rule' ) );
update_option( CGSG_Plugin::OPTION, $settings ); update_option( CGSG_Plugin::LOG, $activity );
verify_cgsg( ( new CGSG_Plugin() )->settings()['rules'][0]['id'] === 'preserved-rule', 'Settings read through real WordPress storage' );
// A header-only older-version fixture exercises the update path without publishing a fake release.
$source = file_get_contents( $path );
file_put_contents( $path, str_replace( 'Version: 0.3.0', 'Version: 0.2.9', $source ) );
wp_clean_plugins_cache( true );
$metadata = array( 'id' => 1, 'tag_name' => 'v0.3.0', 'draft' => false, 'prerelease' => false, 'assets' => array( array( 'name' => 'calico-gf-spam-guard.zip', 'state' => 'uploaded', 'size' => filesize( $zip ), 'browser_download_url' => CGSG_Updater::REPO . '/releases/download/v0.3.0/calico-gf-spam-guard.zip' ) ) );
add_filter( 'pre_http_request', static function( $pre, $args, $url ) use ( $metadata ) {
    if ( $url === CGSG_Updater::API ) { return array( 'headers' => array(), 'body' => wp_json_encode( $metadata ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() ); }
    if ( strpos( $url, 'api.wordpress.org/plugins/update-check/' ) !== false ) { return array( 'headers' => array(), 'body' => '{"plugins":{},"translations":[],"no_update":{}}', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() ); }
    return $pre;
}, 10, 3 );
delete_site_transient( CGSG_Updater::CACHE );
wp_update_plugins();
$updates = get_site_transient( 'update_plugins' );
verify_cgsg( isset( $updates->response[$plugin] ) && $updates->response[$plugin]->new_version === '0.3.0', 'WordPress offers new version to isolated older updater fixture' );
$package = $updates->response[$plugin]->package;
verify_cgsg( $package === CGSG_Updater::REPO . '/releases/download/v0.3.0/calico-gf-spam-guard.zip', 'WordPress offers exact version-pinned release asset' );
add_filter( 'upgrader_pre_download', static function( $reply, $url ) use ( $package, $zip ) {
    if ( $url !== $package ) { return $reply; }
    $temporary = wp_tempnam( 'cgsg-package.zip' ); copy( $zip, $temporary ); return $temporary;
}, 10, 2 );
$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
$result = $upgrader->bulk_upgrade( array( $plugin ) );
verify_cgsg( is_array( $result ) && ! empty( $result[$plugin] ) && ! is_wp_error( $result[$plugin] ), 'Real WordPress Plugin_Upgrader completes upgrade' );
wp_clean_plugins_cache( true );
verify_cgsg( get_plugin_data( $path )['Version'] === '0.3.0', 'Upgrade restores actual release version' );
verify_cgsg( is_plugin_active( $plugin ), 'Plugin remains activated' );
verify_cgsg( get_option( CGSG_Plugin::OPTION ) === $settings, 'Rules, settings and alert recipients survive upgrade' );
verify_cgsg( get_option( CGSG_Plugin::LOG ) === $activity, 'Activity survives upgrade' );
file_put_contents( '/verification/wordpress-results.json', wp_json_encode( array( 'wordpress' => $GLOBALS['wp_version'], 'php' => PHP_VERSION, 'checks' => $checks, 'transport' => 'Fixture GitHub metadata and local exact ZIP; real WordPress installation/update APIs. Gravity Forms absent; form handling separately covered by isolated tests.' ), JSON_PRETTY_PRINT ) );
