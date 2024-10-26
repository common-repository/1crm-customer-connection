<?php

function onecrm_p_dashboard_menu() {
	add_submenu_page(
		'onecrm_p',
		__('1CRM Customer Connection Dashboard Settings', ONECRM_P_TEXTDOMAIN),
		__('Dashboard Settings', ONECRM_P_TEXTDOMAIN),
		'manage_options', 
		'onecrm_p_dashboard',
		'onecrm_p_dashboard' );
	wp_enqueue_script('postbox');
}

add_action( 'admin_menu', 'onecrm_p_dashboard_menu' );
add_action( 'current_screen', 'onecrm_p_save_dashboard' );

function onecrm_p_dashboard() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$auth =  OneCRM\Portal\Auth\OneCrm::instance();
	$cl = $auth->getAdminClient();
?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<?php if ($cl) {
		onecrm_p_render_dashboard_config(); 
		}?>
		<?php if (!$cl) onecrm_p_render_dashboard_warning() ; ?>
	</div>
<?php
}

function onecrm_p_render_dashboard_fields($type, $modinfo, $fields) {
	$module = $modinfo['module'];
	$saved = get_option('onecrm_p_dashboard_config');
	$saved = array_reduce($saved, function($carry, $item) use ($module) {
		return $item['module']  == $module ? $item : $carry;
	});
	$saved = $saved ? $saved : array(
		'enabled' => false,
		'list' => array(),
		'detail' => array(),
		'create' => array(),
		'items' => array(),
	);
	$fields_config = isset($saved[$type]) ? $saved[$type] : array();


	$list_options = $modinfo[$type];
	$show = array();

	//Add additional custom fields by module
	add_custom_fields($module, $fields);

	foreach ($fields as $f) {
		$fname = $f['name'];
		if (!in_array($fname, $list_options['enabled_fields']) || !is_array($fields_config)) continue;
		$field_config = array_reduce($fields_config, function($carry, $item) use ($fname) {
			static $order = 0;
			$item['order'] = $order++;
			return $item['field']  == $fname ? $item : $carry;
		}, array('field' => $fname, 'enabled' => false, 'order' => 99999));

		$show[$fname] = array(
			'name' => $fname,
			'label' => $f['vname'],
			'fixed' => in_array($fname, $list_options['fixed_fields']),
			'enabled' => $field_config['enabled'],
			'order' => $field_config['order'],
		);
	}

	uasort($show, function($a, $b) use($list_options) {
		return $a['order'] - $b['order'];
	});

	if($module == 'KBArticles') {
		$col = get_option( 'onecrm_help_css_color' );
		$font = get_option( 'onecrm_help_css_font' );
		$headcol = get_option( 'onecrm_help_css_head_font_col' );
		$textcol = get_option( 'onecrm_help_css_p_font_col' );
		$themecol = get_option( 'onecrm_help_theme_col' );
		$detail_page = get_option( 'onecrm_help_detail_page' );
	?>
		<div>
			<table class="wp-list-table widefat fixed" style="border: none">
				<tbody class="onecrm-p-fields">
					<tr><td>
							<p><b>☰ Shortcode: </b>[onecrm_kb_articles] </p>
					</td></tr>
					<tr><td>
						<p><b>☰ Index page: </b>
						<?php 
						wp_dropdown_pages([
							'name' => 'onecrm_help_index_page', 'echo' => 1, 
							'show_option_none' => __( '&mdash; None &mdash;', ONECRM_P_TEXTDOMAIN ),
							'option_none_value' => '0', 'selected' => get_option( 'onecrm_help_index_page' ),
						])
						?>
					</td></tr>
					<tr><td>
						<p><b>☰ Detail page: </b>
						<?php 
						wp_dropdown_pages([
							'name' => 'onecrm_help_detail_page', 'echo' => 1, 
							'show_option_none' => __( '&mdash; None &mdash;', ONECRM_P_TEXTDOMAIN ),
							'option_none_value' => '0', 'selected' => get_option( 'onecrm_help_detail_page' ),
						])
						?>
					</td></tr>
					<tr><td>
						<?php echo onecrm_p_render_checkbox_option('onecrm_help_theme_col', 'Use custom colors')?>
					</td></tr>
				</tbody>
				<tbody class="onecrm-p-fields" id="onecrm-p-theme-options">
					<tr><td>
						<?php echo onecrm_p_render_color_option("onecrm_help_css_color", "Background Color")?>
					</td></tr>
					<tr><td>
						<?php echo onecrm_p_render_color_option("onecrm_help_css_head_font_col", "Article/Category Title Color")?>
					</td></tr>
					<tr><td>
						<?php echo onecrm_p_render_color_option("onecrm_help_css_p_font_col", "Body Text Color")?>
					</td></tr>
					<tr><td>
						<?php echo onecrm_p_render_color_option("onecrm_help_css_p_counter_col", "Articles Counter Text Color")?>
					</td></tr>
					<tr><td>
						<?php echo onecrm_p_render_color_option("onecrm_help_css_p_icon_col_active", "View Mode Buttons Active Color")?>
					</td></tr>
					<tr><td>
						<?php echo onecrm_p_render_color_option("onecrm_help_css_p_icon_col_inactive", "View Mode Buttons Inctive Color")?>
					</td></tr>
					<tr><td>
						<?php echo onecrm_p_render_checkbox_option("onecrm_help_css_p_icon_border", "Border Around Active Mode Button")?>
					</td></tr>
					<tr><td>
						<?php echo onecrm_p_render_checkbox_option('onecrm_help_css_shadows', 'Article/category summary blocks drop shadows')?>
					</td></tr>
					<tr><td>
						<?php echo onecrm_p_render_checkbox_option('onecrm_help_css_borders', 'Borders around article/category summary blocks')?>
					</td></tr>
				</tbody>
				<tbody class="onecrm-p-fields" id="onecrm-p-border-options">
					<tr><td>
						<?php echo onecrm_p_render_color_option("onecrm_help_css_border_color", "Border Color")?>
					</td></tr>
					<tr><td>
						<?php echo onecrm_p_render_checkbox_option('onecrm_help_css_borders_round', 'Rounded borders')?>
					</td></tr>
				</tbody>
				<tbody class="onecrm-p-fields">
					<tr><td>
						<p><b>☰ Custom CSS </b></p>
						<textarea name="onecrm_help_custom_css" style="width:100%; min-height: 200px;"><?php
							echo htmlspecialchars(get_option("onecrm_help_custom_css"))
						?></textarea>
					</td></tr>
				</tbody>
			</table>
		</div>
	<?php
	}
	else {

?>
	<div style="max-height: 300px; overflow-y: auto">
		<table class="wp-list-table widefat fixed" style="border: none">
			<tbody class="onecrm-p-fields" id="onecrm-p-fields-<?php echo $type . '_' . $module?>">
				<?php foreach ($show as $k => $v) : ?>
					<tr id="<?php echo $type . '_' . $module . '_' . $k?>">
						<td class="onecrm-p-handle">☰</td>
						<td>
							<label for="onecrm-p-field-<?php echo $type . '-' . $modinfo['module'] . '-' . $k?>">
							<input value="1" class="onecrm-p-toggle-field" type="checkbox" id="onecrm-p-field-<?php echo $type . '-' . $modinfo['module'] . '-' . $k?>"
							<?php if ($v['fixed']) echo ' disabled="true" checked="true" ';
							elseif ($v['enabled']) echo ' checked="true" '; ?>
							>
							<?php echo $v['label'] ?>
							</label>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php
	}
}

function add_custom_fields($module, &$fields) {
	if ($module == 'Invoice' ) {
		$fields['paypal_button'] = [
			'name' => 'paypal_button',
			'vname' => __('PayPal Button', ONECRM_P_TEXTDOMAIN)
		];
	}
}

function onecrm_p_render_color_option($name, $label) {
	$value = get_option($name);
?>
	<label for="<?php echo $name?>">
	<b>☰  </b>
	<input type="color" id="<?php echo $name?>" name="<?php echo $name?>" value="<?php echo $value; ?>">
	<b> <?php echo $label?> </b>
	</label>
<?php
}

function onecrm_p_render_checkbox_option($name, $label) {
	$value = get_option($name);
?>
	<label for="<?php echo $name?>">
	<b>☰  </b>
	<input type="checkbox" id="<?php echo $name?>" name="<?php echo $name?>" value="1" <?php if($value) echo 'checked'; ?>>
	<b> <?php echo $label?></b>
	</label>
<?php
}


function onecrm_p_render_dashboard_module($_, $data) {
	$modinfo = $data['args'][0];
	$module = $modinfo['module'];
	$fields = $data['args'][1]['fields'];
	$saved = get_option('onecrm_p_dashboard_config');
	$saved = array_reduce($saved, function($carry, $item) use ($module) {
		return $item['module']  == $module ? $item : $carry;
	});
	$saved = $saved ? $saved : array(
		'enabled' => false,
		'list' => array(),
		'detail' => array(),
		'create' => array(),
		'items' => array(),
	);

	if($module == 'KBArticles') {
		onecrm_p_render_dashboard_fields('list', $modinfo, $fields);
	}
	else {
?>
<label for="dashboard-module-<?php echo $module?>">
<input class="onecrm-p-toggle-module" type="checkbox" id="dashboard-module-<?php echo $module?>" <?php if ($saved['enabled']) echo ' checked="true" ';?>>
	<?php echo __('Enabled', ONECRM_P_TEXTDOMAIN)?>
	</label><p></p>
<div class="onecrm-p-tabset">

	<?php if($module == 'Invoice') {
		$paypal_link = get_option( 'onecrm_paypal_link' );
		?>
        <div>
            <p><b>☰ <?php echo __('PayPal Link', ONECRM_P_TEXTDOMAIN) ?>: </b>
            <input type="text" name="onecrm_paypal_link" id="onecrm_paypal_link" value="<?php echo htmlspecialchars($paypal_link);?>">
            </p>
        </div>
	<?php } ?>

    <div class="onecrm-p-tabs">
		<a class="active onecrm-p-tab" data-tab="<?php echo $module?>-list" href="#"><?php echo __('List Fields', ONECRM_P_TEXTDOMAIN)?></a>
		<a class="onecrm-p-tab" data-tab="<?php echo $module?>-detail" href="#"><?php echo __('Detail Fields', ONECRM_P_TEXTDOMAIN)?></a>
		<?php if (!empty($modinfo['can_create'])) : ?>
			<a class="onecrm-p-tab" data-tab="<?php echo $module?>-create" href="#"><?php echo __('Data Entry Fields', ONECRM_P_TEXTDOMAIN)?></a>
		<?php endif; ?>
		<?php if (!empty($modinfo['items'])) : ?>
			<a class="onecrm-p-tab" data-tab="<?php echo $module?>-items" href="#"><?php echo __('Line Items', ONECRM_P_TEXTDOMAIN)?></a>
		<?php endif; ?>
		
	</div>

	<div class="onecrm-p-tabs-content">
		<div class="active onecrm-p-tab-content" data-tab="<?php echo $module?>-list">
			<?php onecrm_p_render_dashboard_fields('list', $modinfo, $fields) ?>
		</div>
		<div class="onecrm-p-tab-content" data-tab="<?php echo $module?>-detail">
			<?php onecrm_p_render_dashboard_fields('detail', $modinfo, $fields) ?>
		</div>
		<?php if (!empty($modinfo['can_create'])) : ?>
			<div class="onecrm-p-tab-content" data-tab="<?php echo $module?>-create">
				<?php onecrm_p_render_dashboard_fields('create', $modinfo, $fields) ?>
			</div>
		<?php endif; ?>
		<?php if (!empty($modinfo['items'])) : ?>
			<div class="onecrm-p-tab-content" data-tab="<?php echo $module?>-items">
				<?php  onecrm_p_render_dashboard_fields('items', $modinfo, onecrm_p_get_items_fields()) ?>
			</div>
		<?php endif; ?>

	</div>
</div>
<?php
	}
}

function onecrm_p_render_subscriptions_config($_, $params) {
	$products = $params['args'][0];
	$ep = get_option('onecrm_enabled_products');
	$symbol = get_option('onecrm_currency_symbol');
	$product_label = get_option('onecrm_product_label');
	$plan_label = get_option('onecrm_plan_label');
	$addons_label = get_option('onecrm_addons_label');
	$addons_description = get_option('onecrm_addons_description');
	$welcome_template = get_option('onecrm_welcome_template');
	if (!$symbol) $symbol = '$';
	$after = get_option('onecrm_currency_after');
	$saved = get_option('onecrm_p_dashboard_config');
	if (!$ep) $ep = [];
?>
		<div>
			<table class="wp-list-table widefat fixed" style="border: none">
				<tbody class="onecrm-p-fields">
					<tr><td>
							<p><b>☰ Shortcode: </b>[onecrm_subscriptions] </p>
					</td></tr>
					<tr><td>
						<p><b>☰ Subscription management page: </b>
						<?php 
						wp_dropdown_pages([
							'name' => 'onecrm_subscriptions_page', 'echo' => 1, 
							'show_option_none' => __( '&mdash; None &mdash;', ONECRM_P_TEXTDOMAIN ),
							'option_none_value' => '0', 'selected' => get_option( 'onecrm_subscriptions_page' ),
						])
						?>
					</td></tr>
					<tr><td>
						<p><b>☰ Subscription create page: </b>
						<?php 
						wp_dropdown_pages([
							'name' => 'onecrm_signup_page', 'echo' => 1, 
							'show_option_none' => __( '&mdash; None &mdash;', ONECRM_P_TEXTDOMAIN ),
							'option_none_value' => '0', 'selected' => get_option( 'onecrm_signup_page' ),
						])
						?>
					</td></tr>
					<tr><td valign="top">
						<b>☰ Currency symbol: </b>
						<input type="text" name="onecrm_currency_symbol" id="onecrm_currency_symbol" value="<?php echo htmlspecialchars($symbol);?>">
					</td></tr>
					<tr><td valign="top">
						<b>☰ Currency symbol after amount: </b>
						<input type="checkbox" name="onecrm_currency_after" id="onecrm_currency_after" value="1" <?php echo $after ? 'checked="checked"' : '';?>>
					</td></tr>
					<tr><td valign="top">
						<b>☰ Enabled products: </b>
						<select id="onecrm_enabled_products" name="onecrm_enabled_products[]" multiple="multiple">
						<?php
						foreach ($products as $pid => $pname) {
							$selected = in_array($pid, $ep) ? 'selected="selected"' : '';
							echo '<option value="' . $pid . '" ' . $selected . '>';
							echo htmlspecialchars($pname);
							echo '</option>';
						}
						?>
						</select>
					</td></tr>
					<tr><td valign="top">
						<b>☰ Product select label: </b>
						<input type="text" name="onecrm_product_label" id="onecrm_product_label" value="<?php echo htmlspecialchars($product_label);?>">
					</td></tr>
					<tr><td valign="top">
						<b>☰ Plan select label: </b>
						<input type="text" name="onecrm_plan_label" id="onecrm_plan_label" value="<?php echo htmlspecialchars($plan_label);?>">
					</td></tr>
					<tr><td valign="top">
						<b>☰ Addons label: </b>
						<input type="text" name="onecrm_addons_label" id="onecrm_addons_label" value="<?php echo htmlspecialchars($addons_label);?>">
					</td></tr>
					<tr><td valign="top">
						<b>☰ Addons description: </b>
						<input type="text" name="onecrm_addons_description" id="onecrm_addons_description" value="<?php echo htmlspecialchars($addons_description);?>">
					</td></tr>
					<tr><td valign="top">
						<b>☰ New customer email (use [url] to insert confirmation link, [username] to insert the user name): </b><br>
						<textarea rows="15" cols="40" name="onecrm_welcome_template" id="onecrm_welcome_template" ><?php echo htmlspecialchars($welcome_template);?></textarea>
					</td></tr>
				</tbody>
			</table>
		</div>
<?php
}

function onecrm_p_render_dashboard_config() {
	ob_start();
	$auth =  OneCRM\Portal\Auth\OneCrm::instance();
	$cl = $auth->getAdminClient();
	$modules = onecrm_p_get_all_modules();
	$saved = get_option('onecrm_p_dashboard_config');
	$saved = $saved ? $saved : array();
	$saved_order = array_map(function($item) {return $item['module'];}, $saved);
	uasort($modules, function($a, $b) use ($saved_order) {
		$ao = array_search($a['module'], $saved_order);
		$ao = $ao === false ? 99999 : $ao;
		$bo = array_search($b['module'], $saved_order);
		$bo = $bo === false ? 99999 : $bo;
		return $ao - $bo;
	});
	foreach ($modules as $m) {
		$model = $cl->model($m['model']);
		try {
			$metadata = $model->metadata();
		} catch (Exception $e) {
			ob_end_clean();
			onecrm_p_render_dashboard_error($e->getMessage());
			return;
		}

		add_meta_box(
			'onecrm_p_dashboard_' . $m['module'],
			$m['plural'],
			'onecrm_p_render_dashboard_module',
			null,
			'advanced',
			'default',
			array($m, $metadata)
		);
	}
	if (defined('ONECRM_P_SUBSCRIPTIONS')) {
		try {
			$model = $cl->model('Plan');
			$plans = $model->getList(['fields' => ['product']])->getRecords();
			$products = [];
			foreach ($plans as $plan) {
				if (!empty($plan['product'])) {
					$products[$plan['product_id']] = $plan['product'];
				}
			}
			add_meta_box(
				'onecrm_p_dashboard_subscriptions',
				__('Subscription management'),
				'onecrm_p_render_subscriptions_config',
				null,
				'advanced',
				'default',
				array($products)
			);
		} catch (Exception $e) {
			ob_end_clean();
			onecrm_p_render_dashboard_error($e->getMessage());
			return;
		}
	}


	echo '<form method="post">';
	echo '<input type="hidden" name="config" id="onecrm-p-dashboard-config">';
	echo '<div id="poststuff" class="metabox-holder" style="max-width: 800px">';

	$screen = get_current_screen();
	do_meta_boxes( $screen,  'advanced', null );
	echo '</div>';

	echo '<p class="submit"><button type="buttom" id="submit-dashboard" class="button button-primary">' 
		. __("Save Dashboard Settings", ONECRM_P_TEXTDOMAIN) . '</button></p>';
	echo '</form>';
	echo ob_get_clean();

}

function onecrm_p_render_dashboard_error($e) {
?>
	<div class="notice notice-error"><p>
	<?php echo sprintf(__('An error occured while calling 1CRM API: %s'/* translators: %s will be replaced with the error message */ , ONECRM_P_TEXTDOMAIN), $e); ?>
	</p></div>
<?php
}

function onecrm_p_render_dashboard_warning() {
?>
	<div class="notice notice-error"><p>
	<?php 
		$url = add_query_arg( array(), menu_page_url( 'onecrm_p', false ) );
		$format = __(
			'1CRM authentication info is not configured properly. Please configure it <a href="%s">here</a>'
			/* translators: %s will be replaced by URL */
			, ONECRM_P_TEXTDOMAIN);
		echo sprintf($format, $url);
	?>
	</p></div>
<?php
}

function onecrm_p_save_dashboard() {
	$screen = get_current_screen();
	if ($screen->id != '1crm-customer-connection_page_onecrm_p_dashboard')
		return;
	if (empty($_POST['config'])) {
		return;
	}
	$css_options = [
		'onecrm_help_theme_col',
		'onecrm_help_css_shadows',
		'onecrm_help_css_borders',
		'onecrm_help_css_borders_round',
		'onecrm_help_css_color',
		'onecrm_help_css_head_font_col',
		'onecrm_help_css_p_font_col',
		'onecrm_help_css_p_counter_col',
		'onecrm_help_css_border_color',
		'onecrm_help_detail_page',
		'onecrm_help_index_page',
		'onecrm_help_custom_css',
		'onecrm_help_css_p_icon_col_active',
		'onecrm_help_css_p_icon_col_inactive',
		'onecrm_help_css_p_icon_border',
		'onecrm_subscriptions_page',
		'onecrm_signup_page',
		'onecrm_enabled_products',
		'onecrm_currency_symbol',
		'onecrm_currency_after',
		'onecrm_plan_label',
		'onecrm_product_label',
		'onecrm_addons_label',
		'onecrm_addons_description',
		'onecrm_welcome_template',
        'onecrm_paypal_link'
	];
	foreach ($css_options as $opt) {
		if ($opt == 'onecrm_enabled_products' && isset($_POST[$opt]) && is_array($_POST[$opt])) {
			$value = [];
			foreach ($_POST[$opt] as $v) {
				$value[] = sanitize_textarea_field($v);
			}
		} else {
			$value = sanitize_textarea_field($_POST[$opt]);
		}
		if(get_option($opt) !== false) {
			update_option( $opt, $value );
		} else {
			add_option( $opt, $value );
		}
	}

	$config = json_decode(sanitize_text_field(wp_unslash($_POST['config'])), true);
	if (is_array($config)) {
		update_option('onecrm_p_dashboard_config', $config);
		$url = add_query_arg( array(), menu_page_url( 'onecrm_p_dashboard', false ) );
		wp_redirect($url);
		exit;
	}
}
