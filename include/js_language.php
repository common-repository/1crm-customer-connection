<?php

$onecrm_config_labels = [
	'onecrm_product_label' => 'Product',
	'onecrm_plan_label' => 'Plan',
	'onecrm_addons_label' => 'Addons',
	'onecrm_addons_description' => '',
];

$onecrm_labels = [
	'Invoice Notes',
	'Update Subscription',
	'Create Subscription',
	'Compare Editions',
	'Addons',
	'Options',
	'Payment method',
	'No cards on file',
	'Manage Payment Methods',
	'Customer details',
	' I am a new customer',
	' I am a returning customer',
	'Email address',
	'User name',
	'First name',
	'Last name',
	'Company name',
	'Password',
	'Login',
	'Register me & Continue',
	'Subtotal: ',
	'Discount: ',
	'Order Total: ',
	'Customer details',
	'Email address',
	'First name',
	'Last name',
	'Company name',
	'From',
	'Invalid value',
	'This option is required',
	'Yes',
	'No',
	[
		'period_Monthly' => 'Monthly',
		'period_Yearly' => 'Yearly',
		'period_Weekly' => 'Weekly',
		'confirm_email' => 'User account created. Email sent for account confirmation and password creation. Click the link in the email to confirm your email address and continue the subscription process.'
	],
];

$translated_labels = array_reduce($onecrm_labels, function($carry, $item) {
	if (is_array($item)) {
		foreach ($item as $k => $v) {
			$carry[$k] = __($v, ONECRM_P_TEXTDOMAIN);
		}
		return $carry;
	}
	$carry[$item] = __($item, ONECRM_P_TEXTDOMAIN);
	return $carry;
}, []);

foreach ($onecrm_config_labels as $opt => $default) {
	$value = get_option($opt);
	if (!$value) $value = __($default, ONECRM_P_TEXTDOMAIN);
	$translated_labels[$opt] = $value;
}

return $translated_labels;

