<?php
/**
 * Plugin Name: Calico Spam Guard for Gravity Forms
 * Description: Quarantine Gravity Forms spam using email, content, link and IP rules.
 * Version: 0.3.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Calico Design
 * License: GPL-2.0-or-later
 * Text Domain: calico-gf-spam-guard
 * Update URI: https://github.com/CalicoDesignCo/calico-gf-spam-guard
 */

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/includes/class-cgsg-updater.php';
CGSG_Updater::register( __FILE__ );
require_once __DIR__ . '/includes/class-cgsg-engine.php';
require_once __DIR__ . '/includes/class-cgsg-plugin.php';
new CGSG_Plugin();
