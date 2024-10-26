<?php

function onecrm_p_get_items_fields() {
	static $fields = array(
		'name' => array(
			'name' => 'name',
			'vname' => 'Item Name',
		),
		'quantity' => array(
			'name' => 'quantity',
			'vname' => 'Quantity',
		),
		'mfr_part_no' => array(
			'name' => 'mfr_part_no',
			'vname' => 'Mfr Part No',
		),
		'serial_no' => array(
			'name' => 'serial_no',
			'vname' => 'Serial No',
		),
		'unit_price' => array(
			'name' => 'unit_price',
			'vname' => 'Unit Price',
		),
		'ext_price' => array(
			'name' => 'ext_price',
			'vname' => 'Ext. Price',
		),
	);
	return $fields;
}

function onecrm_p_get_all_modules($add_contacts = false) {
	static $modules;
	if (!$modules) $modules = array(
		array(
			'module' => 'Cases',
			'model' => 'aCase',
			'plural' => __( 'Cases', ONECRM_P_TEXTDOMAIN),
			'singular' => 'Case',
			'can_create' => true,
			'display_notes' => true,
			'add_notes' => true,
			'display_contacts' => true,
			'add_filters' => [
				'view_closed',
			],
			'list' => array(
				'enabled_fields' => array(
					'name',
					'case_number',
					'date_closed',
					'date_entered',
					'status',
					'priority',
					'category',
					'account'
				),
				'fixed_fields' => array(
					'case_number',
					'name',
				),
			),
			'detail' => array(
				'enabled_fields' => array(
					'name',
					'case_number',
					'date_closed',
					'date_entered',
					'status',
					'priority',
					'description',
					'category',
					'account'
				),
				'fixed_fields' => array(
					'case_number',
					'name',
					'description',
				),
			),
			'create' => array(
				'enabled_fields' => array(
					'name',
					'category',
					'description',
				),
				'fixed_fields' => array(
					'name',
					'description',
				),
				'account_fields' => array(
					'account_id' => 'account_id',
					'contact_id' => 'cust_contact_id',
				),
			),
		),
		array(
			'module' => 'Bugs',
			'model' => 'Bug',
			'plural' => __( 'Software Bugs', ONECRM_P_TEXTDOMAIN),
			'singular' => 'Bug',
			'display_notes' => true,
			'add_notes' => true,
			'can_create' => true,
			'add_filters' => [
				'view_closed',
			],
			'list' => array(
				'enabled_fields' => array(
					'name',
					'bug_number',
					'date_entered',
					'status',
					'priority',
					'type',
				),
				'fixed_fields' => array(
					'bug_number',
					'name',
				),
			),
			'detail' => array(
				'enabled_fields' => array(
					'name',
					'bug_number',
					'date_entered',
					'status',
					'priority',
					'description',
					'work_log',
					'type',
				),
				'fixed_fields' => array(
					'bug_number',
					'name',
					'description',
				),
			),
			'create' => array(
				'enabled_fields' => array(
					'name',
					'type',
					'description',
				),
				'fixed_fields' => array(
					'name',
					'description',
				),
				'account_fields' => array(
					'account_id' => 'account_id',
					'contact_id' => 'contact_id',
				),
			),
		),
		array(
			'module' => 'Quotes',
			'model' => 'Quote',
			'plural' => __( 'Quotes', ONECRM_P_TEXTDOMAIN),
			'singular' => 'Quote',
			'display_contacts' => false,
			'display_notes' => true,
			'add_notes' => true,
			'pdf_print' => true,
			'add_filters' => [
				'valid_until',
				'filter_text',
				'view_closed',
			],
			'list' => array(
				'enabled_fields' => array(
					'name',
					'full_number',
					'quote_stage',
					'amount',
					'valid_until',
				),
				'fixed_fields' => array(
					'full_number',
					'name',
				),
			),
			'detail' => array(
				'enabled_fields' => array(
					'name',
					'full_number',
					'quote_stage',
					'amount',
					'valid_until',
					'currency',
					'shipping_provider',
					'terms',
					'description',
				),
				'fixed_fields' => array(
					'name',
					'description',
				),
			),
			'items' => array(
				'enabled_fields' => array(
					'name',
					'quantity',
					'mfr_part_no',
					'serial_no',
					'unit_price',
					'ext_price',
				),
				'fixed_fields' => array(
					'name',
					'quantity',
				),
			),
		),
		array(
			'module' => 'Invoice',
			'model' => 'Invoice',
			'plural' => __( 'Invoices', ONECRM_P_TEXTDOMAIN),
			'singular' => 'Invoice',
			'display_contacts' => false,
			'display_notes' => true,
			'add_notes' => false,
			'pdf_print' => true,
			'add_filters' => [
				'due_date',
				'filter_text',
				'view_closed',
			],
			'list' => array(
				'enabled_fields' => array(
					'name',
					'full_number',
					'amount',
					'shipping_stage',
					'due_date',
					'amount_due',
					'paypal_button'
				),
				'fixed_fields' => array(
					'full_number',
					'name',
				),
			),
			'detail' => array(
				'enabled_fields' => array(
					'name',
					'full_number',
					'amount',
					'amount_due',
					'currency',
					'shipping_stage',
					'shipping_provider',
					'description',
					'paypal_button'
				),
				'fixed_fields' => array(
					'name',
					'description',
				),
			),
			'items' => array(
				'enabled_fields' => array(
					'name',
					'quantity',
					'mfr_part_no',
					'serial_no',
					'unit_price',
					'ext_price',
				),
				'fixed_fields' => array(
					'name',
					'quantity',
				),
			),
		),
		array(
			'module' => 'Project',
			'model' => 'Project',
			'plural' => __( 'Projects', ONECRM_P_TEXTDOMAIN),
			'singular' => 'Project',
			'display_contacts' => true,
			'display_notes' => true,
			'add_notes' => true,
			'add_filters' => [
				'view_closed',
			],
			'list' => array(
				'enabled_fields' => array(
					'name',
					'date_starting',
					'date_ending',
					'project_phase',
					'percent_complete',
				),
				'fixed_fields' => array(
					'name',
				),
			),
			'detail' => array(
				'enabled_fields' => array(
					'name',
					'date_starting',
					'date_ending',
					'project_phase',
					'percent_complete',
					'description',
				),
				'fixed_fields' => array(
					'name',
					'description',
				),
			),
		),
		array(
			'partners_only' => true,
			'module' => 'Opportunities',
			'model' => 'Opportunity',
			'plural' => __( 'Opportunities', ONECRM_P_TEXTDOMAIN),
			'singular' => 'Opportunity',
			'add_filters' => [
				'view_closed',
			],
			'list' => array(
				'enabled_fields' => array(
					'name',
					'full_number',
					'date_closed',
					'sales_stage',
					'probability',
					'amount',
					'account',
				),
				'fixed_fields' => array(
					'name',
					'account',
					'date_closed',
					'amount',
				),
			),
			'detail' => array(
				'enabled_fields' => array(
					'name',
					'full_number',
					'date_closed',
					'sales_stage',
					'probability',
					'amount',
					'account',
					'description',
				),
				'fixed_fields' => array(
					'name',
					'account',
					'date_closed',
					'amount',
					'description',
				),
			),
		),
		array(
			'partners_only' => true,
			'module' => 'Leads',
			'model' => 'Lead',
			'plural' => __( 'Leads', ONECRM_P_TEXTDOMAIN),
			'singular' => 'Lead',
			'add_filters' => [
				'filter_text',
				'view_closed',
			],
			'list' => array(
				'enabled_fields' => array(
					'name',
				),
				'fixed_fields' => array(
					'name',
				),
			),
			'detail' => array(
				'enabled_fields' => array(
					'name',
					'first_name',
					'last_name',
					'description',
				),
				'fixed_fields' => array(
					'description',
				),
			),
		),
		array(
			'partners_only' => true,
			'module' => 'Accounts',
			'model' => 'Account',
			'plural' => __( 'Accounts', ONECRM_P_TEXTDOMAIN),
			'singular' => 'Account',
			'add_filters' => [
				'filter_text'
			],
			'list' => array(
				'enabled_fields' => array(
					'name',
				),
				'fixed_fields' => array(
					'name',
				),
			),
			'detail' => array(
				'enabled_fields' => array(
					'name',
					'description',
				),
				'fixed_fields' => array(
					'description',
				),
			),
		),
		array(
			'module' => 'KBArticles',
			'model' => 'KBArticle',
			'plural' => __( 'Articles', ONECRM_P_TEXTDOMAIN),
			'singular' => 'KBArticle',
			'list' => array(
				'enabled_fields' => array(
					'name',
					'summary',
					'description',
					'slug',
					'category',
				),
				'fixed_fields' => array(
					'name',
				),
			),
			'detail' => array(
				'enabled_fields' => array(
					'name',
					'summary',
					'description',
					'slug',
					'category',
				),
				'fixed_fields' => array(
					'name',
					'description',
				),
			),
		),
	);

	if ($add_contacts)
		$modules[] = onecrm_p_get_contacts_data();

	return $modules;
}

function onecrm_p_get_contacts_data() {
	return array(
		'module' => 'Contacts',
		'model' => 'Contact',
		'plural' => __( 'Contacts', ONECRM_P_TEXTDOMAIN),
		'singular' => 'Contact',
		'can_create' => true,
		'personal_data' => true,

		'list' => array(),
		'detail' => array(),

		'create' => array(
			'enabled_fields' => array(),
			'fixed_fields' => array(
				'first_name',
				'last_name',
				'title',
				'phone_work',
				'phone_home',
				'phone_mobile',
				'email1',
				'email2',
				'department',
				'business_role',
				'birthdate'
			),
		),
	);
}

function onecrm_p_module_for_model($model) {
	$all = onecrm_p_get_all_modules();
	return array_reduce($all, function($carry, $item) use ($model) {
		return $item['model'] === $model ? $item['module'] : $carry;
	});
}

