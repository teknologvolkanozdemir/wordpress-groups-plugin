<?php
/**
 * Plugin Name: WordPress Groups Plugin
 * Description: Gizlilik denetimleri, tartışmalar ve isteğe bağlı özel mesajlaşma içeren erişilebilir gruplar.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: WordPress Groups Plugin contributors
 * License: GPL-3.0-or-later
 * Text Domain: wordpress-groups-plugin
 *
 * @package Wordpress_Groups_Plugin
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-wpg-plugin.php';

register_activation_hook( __FILE__, array( 'WPG_Plugin', 'install' ) );
WPG_Plugin::boot();
