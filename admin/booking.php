<?php

function onecrm_p_booking_settings_init() {
	register_setting( 'onecrm_p_booking', 'onecrm_p_booking' );

	add_settings_section(
		'onecrm_p_section_booking_sync',
		'',
		'onecrm_p_section_booking_cb',
		'onecrm_p_booking'
	);

	add_settings_field(
		'onecrm_p_booking_app_sync',
		__( 'Enable Booking Calendar Synchronization with 1CRM', ONECRM_P_TEXTDOMAIN ),
		'onecrm_p_field_check_cb',
		'onecrm_p_booking',
		'onecrm_p_section_booking_sync',
		[
			'name' => 'onecrm_p_booking_app_sync',
			'class' => 'onecrm_p_row',
			'onecrm_p_custom_data' => 'custom'
		]
	);

}

function onecrm_p_section_booking_cb( $args ) {}

function onecrm_p_field_check_cb( $args ) {
    $name = $args['name'];
	$value = get_option( $name );
	?>
    <label for="<?php echo $name?>">
        <input type="checkbox" id="<?php echo $name?>" name="<?php echo $name?>" value="1" <?php if($value) echo 'checked'; ?>>
    </label>
	<?php
}

function onecrm_p_booking_page() {
	add_submenu_page(
        'onecrm_p',
		__('Booking Calendar', ONECRM_P_TEXTDOMAIN),
		__('Booking Calendar', ONECRM_P_TEXTDOMAIN),
		'manage_options',
		'onecrm_p_booking',
		'onecrm_p_booking_page_html'
	);
}

add_action( 'wp_ajax_onecrm_p_run_booking_sync', function(){\OneCRM\Portal\Ajax::run_booking_calendar_sync();}) ;
add_action( 'admin_menu', 'onecrm_p_booking_page' );
add_action( 'admin_init', 'onecrm_p_booking_settings_init' );
add_action( 'current_screen', 'onecrm_p_save_booking' );


function onecrm_p_booking_page_html() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$auth =  OneCRM\Portal\Auth\OneCrm::instance();
	$cl = $auth->getAdminClient();

	if (! $cl) {
		?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php onecrm_p_render_booking_warning(); ?>
        </div>
		<?php

        exit;
    }

	settings_errors( 'onecrm_p_messages' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <div class="" id="booking-msg"></div>
        <form method="post">
            <input type="hidden" name="onecrm_p_booking_config" value="1">
            <?php
			settings_fields( 'onecrm_p_booking' );
			do_settings_sections( 'onecrm_p_booking' );
			?>
            <p class="submit">
                <input type="submit"  name="submit" id="onecrm-p-booking-submit" class="button button-primary" value="
                <?php _e("Save Settings", ONECRM_P_TEXTDOMAIN); ?>">
                &nbsp;<button type="button" id="onecrm-p-resync-booking" class="button button-primary">
                <?php _e("Resync Manually", ONECRM_P_TEXTDOMAIN); ?></button>
            </p>
        </form>
	</div>
	<?php
}

function onecrm_p_save_booking() {
	$screen = get_current_screen();
	if ($screen->id != '1crm-customer-connection_page_onecrm_p_booking')
		return;

	if (empty($_POST['onecrm_p_booking_config'])) {
		return;
	}

	$options = [
        'onecrm_p_booking_app_sync',
    ];

	foreach ($options as $opt) {
	    $value = isset($_POST[$opt]) ? sanitize_textarea_field($_POST[$opt]) : null;

		if (get_option($opt) !== false) {
			update_option( $opt, $value );
		} else {
			add_option( $opt, $value );
		}
	}

    $url = add_query_arg( array(), menu_page_url( 'onecrm_p_booking', false ) );
    wp_redirect($url);
    exit;
}

function onecrm_p_render_booking_warning() {
	?>
    <div class="notice notice-error"><p>
			<?php
			$url = add_query_arg( array(), menu_page_url( 'onecrm_p', false ) );
			$format = __('1CRM authentication info is not configured properly. Please configure it <a href="%s">here</a>', ONECRM_P_TEXTDOMAIN);
			echo sprintf($format, $url);
			?>
        </p></div>
	<?php
}
