<?php
/**
 * Plugin Name:		1CRM Customer Connection
 * Plugin URI:		https://1crm.com/1crm-wordpress-customer-connection/
 * Description:		The easiest way to connect 1CRM with WordPress.
 * Version:		1.0.4
 * Author:		1CRM Systems Corp.
 * Text Domain:		onecrm-customer-connction
 * Domain Path: /languages
 * License:		GPLv2
 * License URI:		http://www.gnu.org/licenses/gpl-2.0.html
 */

use OneCRM\Portal\ShortCodes;
use OneCRM\Portal\Ajax;
use \OneCRM\Portal\Booking\AppointmentsManager;

define ('ONECRM_P_PATH', __DIR__);
define ('ONECRM_P_LANG_PATH', basename(__DIR__) . '/languages');
define('ONECRM_P_ADMIN_DIR', ONECRM_P_PATH . '/admin');
define ('ONECRM_P_ENTRY', true);
define ('ONECRMP_PLUGIN_URL', untrailingslashit(plugins_url('', __FILE__ )));
define ('ONECRM_P_TEXTDOMAIN', 'onecrm-customer-connection');
define ('ONECRM_P_PRODUCTION', false);
define ('ONECRM_P_BOOKING_CRON_NAME', 'onecrm_p_booking_app_updater');

// finds the vendor classes, and \OneCRM\Portal\* classes in the include/class folder
require_once __DIR__ . '/vendor/autoload.php';

require_once ONECRM_P_PATH . '/include/index.php';
require_once ONECRM_P_PATH . '/include/util.php';

$temp_path =  ONECRM_P_PATH . '/onecrm_portal_temp.php';
if (is_readable($temp_path))
	require_once $temp_path;

AppointmentsManager::add_updater_cron_job();

if (is_admin()) {
	require_once ONECRM_P_PATH . '/include/admin-hooks.php';
	require_once ONECRM_P_ADMIN_DIR . '/admin.php';
} else {
	require_once ONECRM_P_PATH . '/include/hooks.php';
	require_once ONECRM_P_PATH . '/include/login.php';

	if (ONECRM_P_PRODUCTION) {
		$shortCodes = new ShortCodes('contact');
	} else {
		$shortCodes = new ShortCodes('admin');
	}
	$shortCodes->register();

	add_action('wp_enqueue_scripts', function(){
		wp_enqueue_style('render-styles', ONECRMP_PLUGIN_URL.'/css/render.css');
		wp_enqueue_style('helpmod-fa', ONECRMP_PLUGIN_URL.'/css/fa.css');
	});
	

}

function register_onecrm_widget() {
	require_once __DIR__ .  '/include/class/CustomerWidget.php';
	$class_name = '\\OneCRM\\Portal\\CustomerWidget';
    register_widget($class_name);
	add_shortcode('onecrm_p_customer_info',	[$class_name, 'shortcode']);
}
add_action( 'widgets_init', 'register_onecrm_widget' );
add_action( 'init', function() {
	load_plugin_textdomain(ONECRM_P_TEXTDOMAIN, false, ONECRM_P_LANG_PATH);
});



