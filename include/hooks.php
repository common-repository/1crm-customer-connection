<?php
/*
 * wp user login and send user detail to crm
 * */
require_once __DIR__ .  '/kbmodule.php';

add_action('wp_ajax_onecrm_p_model_save', function(){\OneCRM\Portal\Ajax::model_save();});
add_action('wp_ajax_onecrm_p_model_create', function(){\OneCRM\Portal\Ajax::model_create();});
add_action('wp_ajax_onecrm_p_personal_data_erase', function(){\OneCRM\Portal\Ajax::erase_personal_data();});

if ( ! function_exists( 'onecrm_p_user_login' ) ) {
	function onecrm_p_user_login($login) {
		$one_crm = \OneCRM\Portal\Auth\OneCrm::instance();
		$current_user = get_user_by('login', $login);
		$company_name = get_user_meta($current_user->ID, 'onecrm_p_company_name', true);
		$email = $current_user->user_email;

		if(!get_user_meta($current_user->ID, 'onecrm_p_contact_id', true)) {
			$first_name = get_user_meta($current_user->ID, 'first_name', true);
			$last_name = get_user_meta($current_user->ID, 'last_name', true);


			$account_id = $contact_id = null;
			$company_id = null;
			if($company_name){
				$data = [
					'name' => $company_name
				];
				$result = $one_crm->postData('Account', $data);
				if (!$result) return;
				$company_id = $result['id'];
			}
			//check if user exists
			$filters = ['any_email' => $email];
			$result = $one_crm->filterData('Contact', $filters);
			if (!$result) return;
			if($result['count'] == 0){
				$data = [
					'first_name' => $first_name,
					'last_name' => $last_name,
					'email1' => $email,
					'primary_account_id' => $company_id,
				];

				//add crm user
				$result = $one_crm->postData('Contact', $data);
				if (!$result) return;

				if($result['status'] == 'success'){
					update_user_meta( $current_user->ID, 'onecrm_p_contact_id', $result['id'] );
					update_user_meta( $current_user->ID, 'onecrm_p_account_id', $company_id );
					$account_id = $company_id;
					$contact_id = $result['id'];
				}
			} else {
				$result = $result['result'][0];
				update_user_meta( $current_user->ID, 'onecrm_p_account_id', $result['primary_account_id'] );
				update_user_meta( $current_user->ID, 'onecrm_p_contact_id', $result['id'] );
				$account_id = $result['primary_account_id'];;
				$contact_id = $result['id'];
			}
			if ($account_id && $contact_id) {
				$client = \OneCRM\Portal\Auth\OneCrm::instance()->getAdminClient();
				if ($client) {
					$cust_data = [
						'customer' => [
							'account_name' => $company_name,
							'first_name' => $first_name,
							'last_name' => $last_name,
							'email' => $email,
						],
						'account_id' => $account_id,
						'contact_id' => $contact_id,
					];
					try {
						$result = $client->post('/subscription/create_customer', $cust_data);
					} catch(Exception $e) {
					}
				}
			}
		}
			
		$account_id = get_user_meta($current_user->ID, 'onecrm_p_account_id', true);
		$company_name = get_user_meta($current_user->ID, 'onecrm_p_company_name', true);
		
		if($account_id && !$company_name) {
			$client = \OneCRM\Portal\Auth\OneCrm::instance()->getAdminClient();
			if ($client) {
				$model = $client->model('Account');
				try {
					$account = $model->get($account_id);
					update_user_meta( $current_user->ID, 'onecrm_p_company_name', $account['name']);
				} catch (\Exception $e) {
				}
			}
		}
	}
	add_action('wp_login', 'onecrm_p_user_login', 99);
}

/*
 * wp user add custom fields to register page
 * */
if ( ! function_exists( 'onecrm_p_add_registration_fields' ) ) {
	function onecrm_p_add_registration_fields() {
		$onecrm_p_company_name = sanitize_text_field(onecrm_get_default($_POST, 'onecrm_p_company_name', ''));
		$first_name			= sanitize_text_field(onecrm_get_default($_POST, 'first_name', ''));
		$last_name			 = sanitize_text_field(onecrm_get_default($_POST, 'last_name', ''));
		?>

		<p>
			<label for="onecrm_p_company_name"><?php _e( 'Company name', 'onecrm_p' ) ?><br/>
				<input type="text" name="onecrm_p_company_name" id="onecrm_p_company_name" class="input"
					value="<?php echo esc_attr( stripslashes( $onecrm_p_company_name ) ); ?>" size="25"/></label>
		</p>

		<p>
			<label for="first_name"><?php _e( 'First name', 'onecrm_p' ) ?><br/>
				<input type="text" name="first_name" id="first_name" class="input"
					value="<?php echo esc_attr( stripslashes( $first_name ) ); ?>" size="25"/></label>
		</p>

		<p>
			<label for="last_name"><?php _e( 'Last name', 'onecrm_p' ) ?><br/>
				<input type="text" name="last_name" id="last_name" class="input"
					value="<?php echo esc_attr( stripslashes( $last_name ) ); ?>" size="25"/></label>
		</p>

		<?php
	}
	add_action( 'register_form', 'onecrm_p_add_registration_fields' );
}

/*
 * wp user catch registration form errors
 * */
if ( ! function_exists( 'onecrm_p_registration_errors' ) ) {
	function onecrm_p_registration_errors( $errors, $sanitized_user_login, $user_email ) {

		if ( empty( $_POST['first_name'] ) || ! empty( $_POST['first_name'] ) && trim( sanitize_text_field($_POST['first_name']) ) == '' ) {
			$errors->add( 'first_name_error', sprintf( '<strong>%s</strong>: %s', __( 'ERROR', 'onecrm_p' ), __( 'Please enter your first name.', 'onecrm_p' ) ) );

		}
		if ( empty( $_POST['last_name'] ) || ! empty( $_POST['last_name'] ) && trim( sanitize_text_field($_POST['last_name']) ) == '' ) {
			$errors->add( 'last_name_error', sprintf( '<strong>%s</strong>: %s', __( 'ERROR', 'onecrm_p' ), __( 'Please enter your last name.', 'onecrm_p' ) ) );

		}
		if ( empty( $_POST['onecrm_p_company_name'] ) || ! empty( $_POST['onecrm_p_company_name'] ) && trim( sanitize_text_field($_POST['onecrm_p_company_name'] )) == '' ) {
			$errors->add( 'onecrm_p_company_name_error', sprintf( '<strong>%s</strong>: %s', __( 'ERROR', 'onecrm_p' ), __( 'Please enter your company name.', 'onecrm_p' ) ) );

		}

		return $errors;
	}
	add_filter( 'registration_errors', 'onecrm_p_registration_errors', 10, 3 );
}

/*
 * wp user store registration extra fields
 * */
if ( ! function_exists( 'onecrm_p_user_register' ) ) {
	function onecrm_p_user_register( $user_id ) {
		if ( ! empty( $_POST['first_name'] ) ) {
			update_user_meta( $user_id, 'first_name', sanitize_text_field( $_POST['first_name'] ) );
		}
		if ( ! empty( $_POST['last_name'] ) ) {
			update_user_meta( $user_id, 'last_name', sanitize_text_field( $_POST['last_name'] ) );
		}
		if ( ! empty( $_POST['onecrm_p_company_name'] ) ) {
			update_user_meta( $user_id, 'onecrm_p_company_name', sanitize_text_field( $_POST['onecrm_p_company_name'] ) );
		}
	}
	add_action( 'user_register', 'onecrm_p_user_register' );
}



if (!function_exists('onecrm_p_general_actions')) {
	function onecrm_p_general_actions() {
		if (!empty($_GET['onecrm_file_download']) && !empty($_GET['token'])) {
			onecrm_p_download_action();
			return;
		}
		if (!empty($_GET['onecrm_pdf_generate']) && !empty($_GET['token'])) {
			onecrm_p_pdf_gen_action();
			return;
		}
		if (!empty($_POST['onecrm_post_action'])) {
			onecrm_post_action();
			return;
		}
	}

	function onecrm_p_download_action() {
		if (!empty($_GET['onecrm_file_download']) && !empty($_GET['token'])) {
			$client = \OneCRM\Portal\Auth\OneCrm::instance()->getAdminClient();
			if ($client) {
				$id = sanitize_key($_GET['onecrm_file_download']);
				$token = wp_get_session_token();
				if (sha1($token . $id) === sanitize_key($_GET['token'])) {
					try {
						$model = !empty($_GET['doc']) ? $client->model('Document') : $client->model('Note');
						$file = $model->get($id);
						if ($file) {
							$type = !empty($_GET['type']) ? sanitize_key($_GET['type']) : $file['file_mime_type'];
							$files = $client->files();
							$body = !empty($_GET['doc']) ? $files->download('Document', $id) : $files->download('Note', $id);
							header('Content-Type: ' . $type);
							while (!$body->eof()) {
								$data = $body->read(16384);
								echo $data;
							}
							exit;
						}
					} catch (Exception $e) {
					}
				}
			}
		}
	}

	function onecrm_p_pdf_gen_action() {
		if (!empty($_GET['onecrm_pdf_generate']) && !empty($_GET['token']) && !empty($_GET['model'])) {
			$client = \OneCRM\Portal\Auth\OneCrm::instance()->getAdminClient();
			if ($client) {
				$id = sanitize_key($_GET['onecrm_pdf_generate']);
				$model = sanitize_text_field($_GET['model']);
				$token = wp_get_session_token();

				if (sha1($token . $id) === sanitize_text_field( $_GET['token'])) {

					//Print Personal Data PDF
					if (isset($_GET['personal_data'])) {
						$endpoint = '/printer/pdf/personal/' . $model . '/' . $id;
					} else {
						$endpoint = '/printer/pdf/' . $model . '/' . $id;
					}

					$options = [
						'skip_body_parsing' => true
					];

					try {
						$body = $client->request('GET', $endpoint, $options);
						header('Content-Type: application/pdf');

						while (!$body->eof()) {
							$data = $body->read(16384);
							echo $data;
						}
						exit;
					} catch (Exception $e) {
					}
				}

			}
		}
	}

	function onecrm_post_action() {
		if (empty($_POST['request']) || empty($_POST['token'])) {
			return;
		}
		$token = wp_get_session_token();
		if (sha1($token . wp_unslash($_POST['request'])) !== sanitize_text_field($_POST['token'])) {
			return;
		}
		parse_str(base64_decode(wp_unslash($_POST['request'])), $req);
		switch (sanitize_key($_POST['onecrm_post_action'])) {
			case 'create_note':
				return onecrm_p_create_note($req);
				break;
			case 'set_account_name':
				return onecrm_p_set_account_name($req);
				break;
			case 'signup_create':
				return onecrm_p_signup_create($req);
				break;
			case 'signup_login':
				return onecrm_p_signup_login($req);
				break;
			case 'signup_register':
				return onecrm_p_signup_register($req);
				break;
			case 'signup_add_card':
				return onecrm_p_signup_add_card($req);
				break;
		}
	}

	function onecrm_p_create_note($req) {
		$client = OneCRM\Portal\Auth\OneCrm::instance()->getAdminClient();
		if (!$client) return;
		$file_id = null;
		try {
			if (!empty($_FILES['file']) && empty($_FILES['file']['error'])) {
				$res = fopen($_FILES['file']['tmp_name'], 'r');
				if ($res) {
					$files = $client->files();
					$file_id = $files->upload($res, $_FILES['file']['name'], $_FILES['file']['type']);
				}
			}

			$model = $client->model('Note');

			$user = wp_get_current_user();
			$contact_id = get_user_meta($user->ID, 'onecrm_p_contact_id', true);

			$data = [
				'name' => sanitize_text_field($_POST['name']),
				'description' => sanitize_text_field($_POST['description']),
				'filename' => $file_id,
				'parent_type' => $req['module'],
				'parent_id' => $req['id'],
				'portal_flag' => 1,
                'contact_id' => $contact_id,
			];
			$model->create($data);
		} catch (Exception $e) {
			return;
		}
		header('Location: ' . $req['return']);
		exit;
	}

	function onecrm_p_set_account_name($req) {
		$user_id = get_current_user_id();
		if (!$user_id) return;
		$contact_id = get_user_meta( $user_id, 'onecrm_p_contact_id', true);
		$account_id = get_user_meta( $user_id, 'onecrm_p_account_id', true);
		$client = OneCRM\Portal\Auth\OneCrm::instance()->getAdminClient();
		if (!$client) return;
		$name = trim(sanitize_text_field($_POST['company_name']));
		$first_name = trim(sanitize_text_field($_POST['first_name']));
		$last_name = trim(sanitize_text_field($_POST['last_name']));
		if (!strlen($name)) return;
		update_user_meta($user_id, 'onecrm_p_company_name', $name);
		if (!strlen($first_name)) return;
		update_user_meta($user_id, 'first_name', $first_name);
		if (!strlen($last_name)) return;
		update_user_meta($user_id, 'last_name', $last_name);
		$account_created = false;
		if (!$account_id) {
			try {
				$model = $client->model('Account');
				$data = [
					'name' => $name,
					'primary_contact_id' => $contact_id,
				];
				$account_id = $model->create($data);
				$account_created = true;
				update_user_meta($user_id, 'onecrm_p_account_id', $account_id );
				if ($contact_id) {
					$model = $client->model('Contact');
					$data = [
						'primary_account_id' => $id,
					];
					$model->update($contact_id, $data);
				}
			} catch (Exception $e) {
				return;
			}
		}
		if (!$contact_id) {
			try {
				$model = $client->model('Contact');
				$data = [
					'first_name' => $first_name,
					'last_name' => $last_name,
					'primary_account_id' => $account_id,
				];
				$contact_id = $model->create($data);
				update_user_meta($user_id, 'onecrm_p_contact_id', $contact_id);
				if ($account_created) {
					$model = $client->model('Account');
					$data = [
						'primary_contact_id' => $contact_id,
					];
					$model->update($account_id, $data);
				}
			} catch (Exception $e) {
				return;
			}
		}
		header('Location: ' . $req['return']);
		exit;
	}

	function onecrm_p_sanitize_postdata_multiline($postdata) {
		if (is_string($postdata)) {
			return sanitize_textarea_field($postdata);
		}
		if (is_array($postdata)) {
			return array_map('onecrm_p_sanitize_postdata_multiline', $postdata);
		}
	}

	function onecrm_p_sanitize_postdata($postdata) {
		if (is_string($postdata)) {
			return sanitize_text_field($postdata);
		}
		if (is_array($postdata)) {
			return array_map('onecrm_p_sanitize_postdata', $postdata);
		}
	}

	function onecrm_p_signup_add_card($req) {
		$client =  \OneCRM\Portal\Auth\OneCrm::instance()->getAdminClient();
		if (!$client) exit;
		$token = uniqid('');
		$user_id = get_current_user_id();
		update_user_meta($user_id, 'onecrm_p_signup_data', [
			'token' => $token,
			'data' => onecrm_p_sanitize_postdata($_POST['data']),
		]);
		$cid = sanitize_text_field($_POST['data']['customer']['customer_id']);
		$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$current_url = add_query_arg(['token' => $token, 'popup' => '1'], $current_url);
		header('Content-Type: application/json');
		try {
			$result = $client->post('/subscription/add_card', ['customer_id' => $cid, 'return_url' => $current_url]);
		} catch (\Exception $e) {
			$result = ['error' => $e->getMessage()];
		}
		echo json_encode($result);
		exit;
	}

	function onecrm_p_signup_register($req) {
		$client =  \OneCRM\Portal\Auth\OneCrm::instance()->getAdminClient();
		if (!$client) exit;

		$token = uniqid('');
		$data = [
			'user_email' => sanitize_email($_POST['email']),
			'first_name' => sanitize_text_field($_POST['first_name']),
			'last_name' => sanitize_text_field($_POST['last_name']),
			'user_login' => sanitize_text_field($_POST['user_name']),
		];
		$res = wp_insert_user($data);
		if (! ($res instanceof  WP_Error) ) {
			update_user_meta($res, 'onecrm_p_company_name', sanitize_text_field($_POST['account_name']));

			$password_key = '';
			$password_user = '';

			add_action('retrieve_password_key', function($username, $key) use (&$password_key, &$password_user) {
				$password_key = $key;
				$password_user = $username;
			}, 10, 2);
			
			add_filter('wp_new_user_notification_email', function($email, $user, $blogname) use (&$password_key, &$password_user) {
				$tpl = trim(get_option('onecrm_welcome_template'));
				if ($tpl) {
					$url = network_site_url("wp-login.php?action=rp&key=$password_key&login=" . rawurlencode($password_user), 'login');
					$message = str_replace('[url]', $url, $tpl);
					$message = str_replace('[username]', $password_user, $message);
					$email['message'] =  $message;
				}
				return $email;
			}, 99999, 3);
			
			wp_new_user_notification( $res, null, 'user' );

			$account_id = null;

			$model = $client->model('Contact');
			$existing = $model->getList(['fields' => ['email1', 'primary_account_id'], 'filters' => ['any_email' => sanitize_email($_POST['email'])]], 0, 200);
			$link_contact = null;
			foreach ($existing->getRecords() as $contact) {
				if ($contact['email1'] == sanitize_email($_POST['email'])) {
					$link_contact = $contact;
					break;
				}
			}

			$created_contact = false;
			$no_primary_account = false;
			if ($link_contact) {
				$contact_id = $link_contact['id'];
				$account_id = $link_contact['primary_account_id'];
			} else {
				$contact_id = $model->create([
					'first_name' => sanitize_text_field($_POST['first_name']),
					'last_name' => sanitize_text_field($_POST['last_name']),
					'email1' => sanitize_email($_POST['email']),
				]);
				$created_contact = true;
			}

			$model = $client->model('Account');
			if ($account_id) {
				try {
					$existing = $model->get($account_id);
				} catch (\Exception $e) {
					$no_primary_account = true;
					$account_id = null;
				}
			}
			if (!$account_id) {
				$account_id = $model->create([
					'name' => sanitize_text_field($_POST['account_name']),
					'primary_contact_id' => $contact_id,
				]);
				$no_primary_account = true;
				$model->addRelated($account_id, 'contacts', [$contact_id]);

			}

			if ($created_contact || $no_primary_account) {
				$model = $client->model('Contact');
				$model->update($contact_id, ['primary_account_id' => $account_id]);
			}

			update_user_meta($res, 'onecrm_p_contact_id', $contact_id);
			update_user_meta($res, 'onecrm_p_account_id', $account_id);

			update_user_meta($res, 'onecrm_p_signup_data', [
				'continue_on_login' => 1,
				'token' => $token,
				'data' => onecrm_p_sanitize_postdata($_POST['data']),
			]);

			$cust_data = [
				'customer' => [
					'account_name' => sanitize_text_field($_POST['account_name']),
					'first_name' => sanitize_text_field($_POST['first_name']),
					'last_name' => sanitize_text_field($_POST['last_name']),
					'email' => sanitize_email($_POST['email']),
				],
				'account_id' => $account_id,
				'contact_id' => $contact_id,
			];
			$result = $client->post('/subscription/create_customer', $cust_data);

			$s =  OneCRM\Portal\Subscription::getInstance();

		} else {
			$data = $res;
		}
		header('Content-Type: application/json');
		echo json_encode($data);
		exit;
	}

	function onecrm_p_signup_create($req) {
		$user = wp_get_current_user();
		$contact_id = get_user_meta($user->ID, 'onecrm_p_contact_id', true);
		$account_id = get_user_meta($user->ID, 'onecrm_p_account_id', true);

		$client = \OneCRM\Portal\Auth\OneCrm::instance()->getAdminClient();
		if ($client) {
			$postData = [
				'customer' => onecrm_p_sanitize_postdata($_POST['customer']),
				'contact_id' => $contact_id,
				'account_id' => $account_id,
				'subscription' => onecrm_p_sanitize_postdata($_POST['subscription']),
				'subscription_id' => sanitize_text_field($_POST['subscription_id']),
			];
			$ret = $client->post('/subscription/signup', $postData);
		}
		header('Content-Type: application/json');
		echo json_encode($ret);
		exit;
	}

	function onecrm_p_signup_login($req) {
		header('Content-Type: application/json');

		$creds = [
			'user_login' => sanitize_email($_POST['email']),
			'user_password' => sanitize_text_field($_POST['password']),
		];
		$res = wp_signon($creds);
		if ($res instanceof WP_User) {
			wp_set_current_user($res->ID);
			$s =  OneCRM\Portal\Subscription::getInstance();
			$data =  $s->getProductsMeta(array_map('sanitize_text_field', $_POST['products']));
		} else {
			$data = $res;
		}
		echo json_encode($data);
		exit;
	}

	add_action( 'init', 'onecrm_p_general_actions' );


	function onecrm_p_redirect_to_signup($redirect_to, $requested_redirect_to, $user) {
		if ($user instanceof WP_User) {
			$meta = get_user_meta($user->ID, 'onecrm_p_signup_data', true);
			if (is_array($meta) && !empty($meta['continue_on_login'])) {
				$signup_page = (int)get_option( 'onecrm_signup_page' );
				if ($signup_page) {
					$signup_url = get_the_permalink($signup_page);
					$signup_url = add_query_arg(['token' => $meta['token']], $signup_url);
					return $signup_url;
				}
			}
		}
		return $redirect_to;
	}

	add_filter( 'login_redirect', 'onecrm_p_redirect_to_signup', 9999999, 3);

	function onecrm_p_exclude_menu_items( $items, $menu, $args ) {
		static $is_partner = null;
		if (is_null($is_partner)) {
			$is_partner = false;
			try {
				$client = \OneCRM\Portal\Auth\OneCrm::instance()->getContactClient(false);
				if (!$client) {
					$is_partner = false;
				} else {
					$user = $client->me();
					$is_partner = !empty($user['is_partner']);
				}
			} catch (\Exception $e) {
			}
		}
		if ($is_partner) {
			return $items;
		}

		$partner_models = [];
		$modules = onecrm_p_get_all_modules();
		foreach ($modules as $mod) {
			if (!empty($mod['partners_only'])) {
				$partner_models[] = $mod['model'];
			}
		}
		foreach ( $items as $key => $item ) {
			if ($item->object == 'post' || $item->object == 'page') {
				$post = get_post($item->object_id);
				$content = $post->post_content;
				if (preg_match_all('~\[\s*onecrm_p_dashboard\s+model\s*=\s*"(.+)"\s*]~U', $content, $m)) {
					$models = $m[1];
					if (count(array_intersect($models, $partner_models)) == count($models)) {
						unset($items[$key]);
					}
				}
				
			}
			if ( $item->object_id == 168 ) unset( $items[$key] );
		}
		return $items;
	}

	add_filter( 'wp_get_nav_menu_items', 'onecrm_p_exclude_menu_items', null, 3 );

}



