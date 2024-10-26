<?php
use OneCRM\Portal\Locale;

function onecrm_p_settings_init() {
	register_setting( 'onecrm_p', 'onecrm_p_options', 'onecrm_p_validate_options' );

	add_settings_section(
		'onecrm_p_section_developers',
		'',
		'onecrm_p_section_developers_cb',
		'onecrm_p'
	);

	add_settings_field(
		'onecrm_p_api_url',
		__( 'API URL', ONECRM_P_TEXTDOMAIN ),
		'onecrm_p_field_input_cb',
		'onecrm_p',
		'onecrm_p_section_developers',
		[
			'label_for' => 'onecrm_p_api_url',
			'class' => 'onecrm_p_row',
			'onecrm_p_custom_data' => 'custom',
			'onecrm_p_required' => 'required',
			'placeholder' => 'ex. https://demo.1crmcloud.com/api.php'
		]
	);
	add_settings_field(
		'onecrm_p_api_client_id',
		__( 'API Client ID', ONECRM_P_TEXTDOMAIN ),
		'onecrm_p_field_input_cb',
		'onecrm_p',
		'onecrm_p_section_developers',
		[
			'label_for' => 'onecrm_p_api_client_id',
			'class' => 'onecrm_p_row',
			'onecrm_p_custom_data' => 'custom',
			'onecrm_p_required' => 'required',
		]
	);
	add_settings_field(
		'onecrm_p_api_secret',
		__( 'API Secret', ONECRM_P_TEXTDOMAIN ),
		'onecrm_p_field_input_cb',
		'onecrm_p',
		'onecrm_p_section_developers',
		[
			'label_for' => 'onecrm_p_api_secret',
			'class' => 'onecrm_p_row',
			'onecrm_p_custom_data' => 'custom',
			'onecrm_p_required' => 'required',
		]
	);
}



function onecrm_p_section_developers_cb( $args ) {
}

function onecrm_p_field_input_cb( $args ) {
	$options = get_option( 'onecrm_p_options' );
	?>
	<input id="<?php echo esc_attr( $args['label_for'] ); ?>"
		   data-custom="<?php echo esc_attr( $args['onecrm_p_custom_data'] ); ?>"
		   name="onecrm_p_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
		   value="<?php echo esc_attr(isset($options[ $args['label_for'] ]) ? $options[ $args['label_for'] ] : '') ?>"
		   required="<?php echo esc_attr( $args['onecrm_p_required'] ); ?>"
		   placeholder="<?php echo esc_attr( isset($args['placeholder']) ? $args['placeholder'] : '' ); ?>"
		   size="40"
	/>
	<?php
}

function onecrm_p_options_page() {
	add_menu_page(
		__('1CRM Customer Connection Settings', ONECRM_P_TEXTDOMAIN),
		__('1CRM Customer Connection', ONECRM_P_TEXTDOMAIN),
		'manage_options',
		'onecrm_p',
		'onecrm_p_options_page_html'
	);
}

add_action( 'admin_menu', 'onecrm_p_options_page' );
add_action( 'admin_init', 'onecrm_p_settings_init' );

function onecrm_p_validate_options($input) {
	$auth =  OneCRM\Portal\Auth\OneCrm::instance($input);
	/** @var OneCRM\APIClient\Client $cl; */
	$cl = $auth->getAdminClient();
	if (!$cl) {
		add_settings_error('onecrm_p_messages', 'onecrm_p_message', __('1CRM authentication failed. Please check URL, Client ID and API Secret', ONECRM_P_TEXTDOMAIN));
	} else {
		try {
			$input["locale"] = $cl->get('/meta/locale');
			Locale::instance($input["locale"]);
		} catch (Exception $e) {
			add_settings_error('onecrm_p_messages', 'onecrm_p_message', __('Please check that 1CRM version is 8.6 or greater and that you have a subscription to the Customer Connection portal added to your 1CRM license key.', ONECRM_P_TEXTDOMAIN));
		}
	}
	return $input;
}


function onecrm_p_options_page_html() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}


	settings_errors( 'onecrm_p_messages' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'onecrm_p' );
			do_settings_sections( 'onecrm_p' );
			submit_button( 'Save Settings' );
			?>
		</form>
	</div>
	<?php
}

