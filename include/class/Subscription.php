<?php

/**
 * Subscriptions management shortcodes
 */

namespace OneCRM\Portal;

class Subscription {

	private static $instance;

	const PER_PAGE = 5;

	const COMPARE_1CRM_EDITIONS_LINK = 'https://1crm.com/editions-and-pricing/';

	public static function register() {
		$s = self::getInstance();
		add_shortcode('onecrm_p_signup',  [$s, 'signup_shortcode']);
		add_shortcode('onecrm_subscriptions',  [$s, 'subscriptions_shortcode']);
	}

	public static function getInstance() {
		if (!self::$instance) self::$instance = new self;
		return self::$instance;
	}

	public function signup_shortcode($attr) {
		try {
			$client = Auth\OneCrm::instance()->getContactClient(true);
		} catch (Auth\ConfigError $e) {
			$ret =  $this->displayAuthErrors($e->getErrors(), true);
			if ($ret) return $ret;
		}
		$products = get_option('onecrm_enabled_products');
		$symbol = get_option('onecrm_currency_symbol');
		if (!$symbol) $symbol = '$';
		$symbol_after = get_option('onecrm_currency_after');
		if (!is_array($products)) $products = [];
		$uniq = uniqid('');
		list ($req, $signature) = onecrm_signed_request(['uniq' => $uniq]);
		$init_data = $this->getProductsMeta($products);
		if (!empty($attr['edit']) && is_array($attr['edit'])) {
			$edit = $attr['edit'];
			$plan_id = $attr['edit']['plan'];
			$prod_id = null;
			foreach ($init_data['prod_meta']['products'] as $pid => $pdata) {
				foreach ($pdata['plans'] as $plid => $_) {
					if ($plid === $plan_id) {
						$prod_id = $pid;
						break 2;
					}
				}
			}
			$edit['product'] = $prod_id;
			$init_data['edit'] = $edit;
		}
		$init_data['settings'] = compact('symbol', 'symbol_after');
		$init_data['signature'] = [
			'request' => $req,
			'token' => $signature,
		];
		$init_data['compare_1crm_editions_url'] = self::COMPARE_1CRM_EDITIONS_LINK;

		$return_page = (int)get_option( 'onecrm_subscriptions_page' );
		if ($return_page) {
			$return_url = get_the_permalink($return_page);
			$param = empty($attr['edit']['subscription_id']) ? 'created' : 'updated';
			$init_data['return_url'] = add_query_arg([$param => 1], $return_url);
		}

		if (!empty($_GET['token'])) {
			if (!empty($_GET['popup'])) {
				$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
				if ( strpos( $current_url, '?' ) !== false ) {
					list( $current_url, $unused ) = explode( '?', $current_url, 2 );
				}
				$current_url = add_query_arg(['token' => sanitize_key($_GET['token'])], $current_url);
				echo sprintf(
					'<script>if(window.top.ONECRM_REMOVE_DIMMER) window.top.ONECRM_REMOVE_DIMMER(%s);</script>',
					json_encode($current_url)
				);
				exit;
			}
			$user_id = get_current_user_id();
			$the_data = get_user_meta($user_id, 'onecrm_p_signup_data', true);
			if (is_array($the_data)) {
				if (!empty($the_data['continue_on_login'])) {
					unset($the_data['continue_on_login']);
					update_user_meta($user_id, 'onecrm_p_signup_data', $the_data);
				}
				if ($the_data['token'] === sanitize_key($_GET['token'])) {
					$init_data['continue_subscription'] = $the_data['data'];
				}
			}
		}

			add_action('wp_footer',function() {
			wp_enqueue_script('onecrm-subscription-scripts', ONECRMP_PLUGIN_URL . '/js/subscriptions.js', ['jquery', 'jquery-ui-tooltip']);
			wp_enqueue_style('onecrm-subscriptions-css', ONECRMP_PLUGIN_URL . '/css/subscriptions.css');
		});

		add_action('wp_print_footer_scripts',function () use ($init_data) {
			echo '<script>';
			echo 'window.ONECRM_LANGUAGE = ';
			echo json_encode(include(ONECRM_P_PATH . '/include/js_language.php'));
			echo ';';
			echo 'new window.OneCRMSubscriptions("#onecrm-signup-container", '
				. json_encode($init_data) 
				. ');';
			echo '</script>';
		});
		$ret = '<form onsubmit="return false;"><div id="onecrm-signup-container"></div></form>';
		return $ret;
	}

	public function getProductsMeta($ids) {
		$meta = $this->fetchProductsMeta($ids);
		$user_id = get_current_user_id();
		$meta['customer_data']['user_id'] = $user_id;
		if ($user_id) {
			$user = wp_get_current_user();
			$meta['customer_data']['email'] = $user->get('user_email');
			$meta['customer_data']['first_name'] = get_user_meta($user_id, 'first_name', true);
			$meta['customer_data']['last_name'] = get_user_meta($user_id, 'last_name', true);
			$meta['customer_data']['account_name'] = get_user_meta($user_id, 'onecrm_p_company_name', true);
		}
		if ($user_id && empty($meta['customer_data']['customer_id'])) {
			$client = Auth\OneCrm::instance()->getAdminClient();
			if ($client) {
				$cust_data = [
					'customer' => [
						'account_name' => $meta['customer_data']['account_name'],
						'first_name' => $meta['customer_data']['first_name'],
						'last_name' => $meta['customer_data']['last_name'],
						'email' => $meta['customer_data']['email'],
					],
					'account_id' => get_user_meta($user_id, 'onecrm_p_account_id', true),
					'contact_id' => get_user_meta($user_id, 'onecrm_p_contact_id', true),
				];
				try {
					$result = $client->post('/subscription/create_customer', $cust_data);
					$meta['customer_data']['customer_id'] = $result['id'];
				} catch (\Exception $e) {
				}
			}
		}
		$init_data = [
			'prod_meta' => $meta,
		];
		return $init_data;
	}

	protected function fetchProductsMeta($ids) {
		$client = Auth\OneCrm::instance()->getAdminClient();
		if (!$client) return [];
		$params = ['products' => $ids];
		$user_id = get_current_user_id();
		if ($user_id) {
			$contact_id = get_user_meta( $user_id, 'onecrm_p_contact_id', true);
			if ($contact_id)
				$params['contact_id'] = $contact_id;
		}
		try {
			$meta = $client->get('/subscription/meta/signup', $params);
			return $meta;
		} catch (\Exception $e) {
			return [];
		}
	}
	
	public function subscriptions_shortcode($attr) {
		$user_id = get_current_user_id();

		if (!empty($_GET['edit'])) {
			return $this->editSubscription($attr, sanitize_key($_GET['edit']));
		}
		
		if (!empty($_GET['cancel'])) {
			return $this->cancelSubscription($attr, sanitize_key($_GET['cancel']));
		}
		
		try {
			$client = Auth\OneCrm::instance()->getContactClient(true);
			if (!$client) return false;
		} catch (Auth\ConfigError $e) {
			return $this->displayAuthErrors($e->getErrors());
		}

		if (!$client) return false;
		$fields_meta = $client->get("/meta/fields/Subscription");
		$fields_meta['fields']['_edit'] = [
			'name' => '_edit',
			'type' => '_custom',
			'vname' => ' ',
		];
		$fields_meta['fields']['name']['type'] = '_custom';
		$fields_meta['fields']['name']['vname'] = __('Details', ONECRM_P_TEXTDOMAIN);
		$fields_meta['fields']['state']['type'] = '_custom';

		//Paginate attributes
		$page = (int)sanitize_key($_GET['page_number']);
		if ($page < 1)
			$page = 1;
		$perPage = self::PER_PAGE;
		$start = ($page - 1) * $perPage;
		if ($start < 0) $start = 0;
		$curPage = (int)($start / $perPage) + 1;

		$fields = ['name', 'plan', 'state', '_edit'];

		//Add filters dat from user's input
		$list_filters = ['filter_text', 'plan', 'view_closed'];
		$filters = $this->get_input_filters($list_filters, $fields_meta['filters']);

		$model = $client->model('Subscription');

		$list = $model->getList([
			'fields' => ['name', '_edit', 'has_unpaid_invoices', 'state', 'native', 'plan'],
			'filters' => $filters,
		], $start, $perPage);

		$rows = $list->getRecords();
		
		$rows = array_map(function($row) {
			$row['_edit'] = '';
			return $row;
		},	$rows);

		$pages = new Renderer\Pagination($curPage, $perPage, $list->totalResults(), '?page_number=%s');

		$ListView = new Renderer\ListView([
			"detail_available" => false, 
			"model" => 'Susbcription',
			"link_template" => $link_template,
			"link_template_data" => $link_template_data,
			'custom_render' => [$this, 'renderCustomField'],
		]);
		if (!empty($_GET['created']) || !empty($_GET['updated']) || !empty($_GET['cancelled'])) {
			if (!empty($_GET['created'])) {
				$text = __(
					'Your subscription was created. It can take up to a minute for the subscription to appear in the subscriptions list. Please click <a href="%s">here</a> in a while to refresh the list'
					/* translators: %s will breplaced with an URL */
					,
					ONECRM_P_TEXTDOMAIN
				);
			} elseif (!empty($_GET['cancelled'])) {
				$text = __(
					'Your subscription was cancelled. It can take up to a minute for the subscription status to update. Please click <a href="%s">here</a> in a while to refresh the list'
					/* translators: %s will breplaced with an URL */
					,
					ONECRM_P_TEXTDOMAIN
				);
			} else {
				$text = __(
					'It can take up to a minute for the subscription to be updated. Please click <a href="%s">here</a> in a while to refresh the list'
					/* translators: %s will breplaced with an URL */
					,
					ONECRM_P_TEXTDOMAIN
				);
			}
			$url = get_the_permalink();
			echo '<p>';
			echo sprintf($text, $url);
			echo '</p>';
		}
		echo '<div class="onecrm">';
		echo '<h3>' . __('Subscriptions', ONECRM_P_TEXTDOMAIN). '</h3>';

		$signup_page = (int)get_option( 'onecrm_signup_page' );
		$create_button = null;

		if ($signup_page) {
			$signup_url = get_the_permalink($signup_page);
			$create_button = '<button onclick="window.location.href=\'' . $signup_url . '\'; return false;" class="onecrm-p-create button">' . __('Create New', ONECRM_P_TEXTDOMAIN)  . '</button>';
		}

		$FilterFormView = new FilterFormView([]);

		echo $FilterFormView->render(
			[
				'filters' => $list_filters,
				'input' => onecrm_p_sanitize_postdata_multiline($_POST),
				'def' => $fields_meta['filters'],
				'model' => ['Susbcription'],
				'create_button' => $create_button,
				'page_url' => $_SERVER['REQUEST_URI']
			]
		);

		echo $pages->render();
		echo $ListView->render(compact("rows", "fields", "fields_meta"));
		echo $pages->render();

		echo '</div>';
	}

	protected function cancelSubscription($attr, $sid) {
		try {
			$client = Auth\OneCrm::instance()->getContactClient(true);
			if (!$client) return false;
		} catch (Auth\ConfigError $e) {
			return $this->displayAuthErrors($e->getErrors());
		}

		$client = Auth\OneCrm::instance()->getAdminClient();
		if (!$client) return '';

		if (!empty($_POST['cancel_at'])) {
			$data = [
				'subscription_id' => $sid,
				'end_of_term' => $_POST['cancel_at'] == 'end_of_term',
				'credits' => 'prorate',
			];
			try {
				$result = $client->post('/subscription/cancel', $data);
				if (empty($result['error'])) {
					$url = add_query_arg(['cancelled' => 1],  get_the_permalink());
					wp_redirect($url);
					exit;
				}
				echo $result['error'];
			} catch (\Exception $e) {
				echo $e->getMessage();
			}
		}


		$subsc_data = $client->get('/subscription/details/' . $sid);
		echo '<form method="POST"><p>';
		echo '<p>' . __('You are about to cancel a subscription', ONECRM_P_TEXTDOMAIN) . '</p>';
		echo '<p><b>' . esc_html($subsc_data['name']) . '</b></p>';
		echo '<p>';
		echo '<label><input type="radio" name="cancel_at" value="now"> ' .  __('Cancel immediately', ONECRM_P_TEXTDOMAIN)  . '</label>';
		echo '<br><label><input type="radio" name="cancel_at" value="end_of_term" checked="checked"> ' .  __('Cancel at the end of current term', ONECRM_P_TEXTDOMAIN)  . '</label>';
		echo '</p>';
		echo '<a href="' . get_the_permalink() . '">' . __('Return to subscriptions list', ONECRM_P_TEXTDOMAIN) . '</a>&nbsp;';
		echo "<button class='onecrm-p-create button'>" . __('Cancel subscription', ONECRM_P_TEXTDOMAIN) . "</button>";
		echo '</p></form>';
	}

	protected function editSubscription($attr, $sid) {
		try {
			$client = Auth\OneCrm::instance()->getContactClient(true);
			if (!$client) return false;
		} catch (Auth\ConfigError $e) {
			return $this->displayAuthErrors($e->getErrors());
		}

		$client = Auth\OneCrm::instance()->getAdminClient();
		if (!$client) return [];
		$user_id = get_current_user_id();
		$account_id = get_user_meta( $user_id, 'onecrm_p_account_id', true);
		$subsc_data = $client->get('/subscription/details/' . $sid);
		if ($subsc_data['account_id'] !== $account_id) return;
		if (!is_array($attr)) {
			$attr = [];
		}
		$attr['edit'] = [
			'addons' => $subsc_data['addons'],
			'options' => $subsc_data['options'],
			'plan' => $subsc_data['plan_id'],
			'qty' => $subsc_data['quantity'],
			'subscription_id' => $subsc_data['pp_subs_id'],
			'invoice_notes' => isset($subsc_data['invoice_notes']) ? $subsc_data['invoice_notes'] : '',
		];
		return $this->signup_shortcode($attr);
	}

	public function renderCustomField($field, $model, $meta) {
		switch ($field) {
			case 'name':
				$ret = htmlspecialchars($model[$field]);
				if (empty($model['_formatted'])) return $ret;

				$f = $model['_formatted'];
				$ret .= '<br>';
				if (!empty($f['qty_label'])) $ret .= $f['qty_label'];
				else $ret .= __('Quantity', ONECRM_P_TEXTDOMAIN);
				$ret .= ': ' . $f['quantity'];

				if (!empty($f['addons'])) {
					foreach ($f['addons'] as $a) {
						$ret .= '<br>' . __('Addon', ONECRM_P_TEXTDOMAIN) . ': ';
						$ret .= htmlspecialchars($a['name']);
						if ($a['quantity'] > 1) {
							$ret .= " (x{$a['quantity']})";
						}
					}
				}
				if (!empty($f['current_term_start'])) {
					$ret .= '<br>';
					$ret .= __('Current term start', ONECRM_P_TEXTDOMAIN) . ': ';
					$ret .= $f['current_term_start'];
				}

				if (!empty($f['current_term_end'])) {
					$ret .= '<br>';
					$ret .= __('Current term end', ONECRM_P_TEXTDOMAIN) . ': ';
					$ret .= $f['current_term_end'];
				}

				if (!empty($f['due_invoices_count'])) {
					$ret .= '<br>';
					$ret .= __('Unpaid invoices', ONECRM_P_TEXTDOMAIN) . ': ';
					$ret .= $f['due_invoices_count'];
				}

				return $ret;
			case '_edit':
				if ($model['state'] != 'active') return '';
				$url = add_query_arg(['edit' => $model['id']],  get_the_permalink());
				$ret = "<a href=\"$url\">" . __('Edit', ONECRM_P_TEXTDOMAIN) . "</a>";
				$url = add_query_arg(['cancel' => $model['id']],  get_the_permalink());
				$ret .= "<br><a href=\"$url\">" . __('Cancel', ONECRM_P_TEXTDOMAIN) . "</a>";
				return $ret;
			case 'state':
				$value = array_filter($meta['fields'][$field]['options'], function($value) use ($model, $field) {
					return $value['value'] == $model[$field];
				});
				$value = current($value);
				$value = $value ? $value['label'] : $model[$field];
				if (!empty($mode['has_unpaid_invoices'])) {
					$value = __('Payment Failed', ONECRM_P_TEXTDOMAIN);
					$cls = 'failed';
				} else {
					$cls = $model[$field] == 'active' ? 'active' : 'inactive';
				}
				return '<span class="onecrm-subscription-status-' . $cls . '">' . htmlspecialchars($value) . '</span>';
			case 'plan':
				return htmlspecialchars($model[$field]);
		}
	}

	function displayAuthErrors($errors, $no_login = false) {
		if (in_array('login', $errors) && !$no_login) {
			$ret = '<p>';
			$ret .= __('You need to login in order to access this area', ONECRM_P_TEXTDOMAIN);
			$ret .= '</p>';
			$ret .= '<div style="display:none;" class="onecrm-p-exception">';
			return $ret;
		}
		if (in_array('account', $errors) || in_array('contact', $errors)) {
			$ret = '<p>';
			$ret .= __('Please provide your contact details to access this area', ONECRM_P_TEXTDOMAIN);
			$ret .= '</p>';
			$ret .= '<form method="POST">';
			$ev = new Renderer\EditView([
				'detail_available' => null, 
				'model' => 'Note',
				'columns' => 2,
			]);
			$user_id = get_current_user_id();
			$company_name = get_user_meta( $user_id, 'onecrm_p_company_name', true);
			$first_name = get_user_meta( $user_id, 'first_name', true);
			$last_name = get_user_meta( $user_id, 'last_name', true);

			$ret .= $ev->render([
				'row' => [
					'company_name' => $company_name,
					'first_name' => $first_name,
					'last_name' => $last_name,
				],
				'columns' => 1,
				'fields' => ['first_name', 'last_name', 'company_name'],
				'format_input' => ['company_name', 'first_name', 'last_name'],
				'fields_meta' => [
					'fields' => [
						'first_name' => [
							'name' => 'first_name',
							'type' => 'varchar',
							'vname' => __('First name', ONECRM_P_TEXTDOMAIN),
						],
						'last_name' => [
							'name' => 'last_name',
							'type' => 'varchar',
							'vname' => __('Last name', ONECRM_P_TEXTDOMAIN),
						],
						'company_name' => [
							'name' => 'company_name',
							'type' => 'varchar',
							'vname' => __('Company name', ONECRM_P_TEXTDOMAIN),
						],
					],
				],
			]);
			$ret .= '<br><button class="onecrm-p-create">' . __('Send', ONECRM_P_TEXTDOMAIN) . '</button><br>';
			list ($req, $signature) = onecrm_signed_request([
				'return' => $_SERVER['REQUEST_URI'],
			]);
			$ret .= $ev->create_hidden_input('request', $req);
			$ret .= $ev->create_hidden_input('token', $signature);
			$ret .= $ev->create_hidden_input('onecrm_post_action', 'set_account_name');
			$ret .= '</form></div>';
			$ret .= '<div style="display:none;" class="onecrm-p-exception">';
			return $ret;
		}
	}

	private function get_input_filters($model_filters, $def) {
		$result = [];

		$token = wp_get_session_token();
		if (sha1($token . sanitize_text_field($_POST['request'])) !== sanitize_key($_POST['token']))
			return $result;

		if (isset($_POST['filters']) && is_array($_POST['filters'])) {
			$input = array_map('onecrm_p_sanitize_postdata', $_POST['filters']);

			foreach ($model_filters as $item) {
				if (! isset($def[$item]))
					continue;

				if (isset($input[$item]))
					$result[$item] = $input[$item];
			}
		}

		return $result;
	}
}
