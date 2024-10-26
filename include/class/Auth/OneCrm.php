<?php

namespace OneCRM\Portal\Auth;
use OneCRM\APIClient\Authentication;
use OneCRM\APIClient;
use OneCRM\Portal\API\Client;
use OneCRM\APIClient\Error as APIError;

if ( !class_exists( 'OneCrm' ) ) {

	class ConfigError extends \Exception {
		public function __construct($errors) {
			$this->errors = $errors;
		}
		public function getErrors() {
			return $this->errors;
		}
	}

	class OneCrm {

		private static $instance;
		private $client_id;
		private $api_secret;
		private $api_url;
		/** @var Client $client */
		private $client = null;
		/** @var Client $contact_client */
		private $contact_client = null;
		private $options = null;

		public static function instance($options = null) {
			if (!empty($options))
				return new self($options);
			if (!self::$instance) {
				self::$instance = new self;
			}
			return self::$instance;
		}

		/**
		 * OneCrm constructor.
		 * @param array|null $options
		 */
		private function __construct($options = null) {
			if (!empty($options)) {
				$this->options = $options;
			} else {
				$options = get_option( 'onecrm_p_options' );
			}
			$this->client_id = $options['onecrm_p_api_client_id'];
			$this->api_secret = $options['onecrm_p_api_secret'];
			$this->api_url = $options['onecrm_p_api_url'];
		}

		public function getAdminClient($throw = false) {
			if ($this->client === null) {
				try {
					$this->checkAuth($throw);
					if (!$this->client)
						$this->client = false;
				} catch (ConfigError $e) {
					throw $e;
				} catch (\Exception $e) {
					$this->client = false;
				}
			}
			return $this->client;
		}

		public function getContactClient($throw = false) {
			$adminClient = $this->getAdminClient($throw);
			if (!$adminClient)
				return false;
			if ($this->contact_client === null) {
				try {
					$this->checkContactAuth($throw);
					if (!$this->contact_client)
						$this->contact_client = false;
				} catch (ConfigError $e) {
					throw $e;
				} catch (\Exception $e) {
					$this->contact_client = false;
				}
			}
			return $this->contact_client;
		}

		/**
		 * @throws APIError
		 */
		protected function checkAuth($throw = false) {
			$this->client = null;
			if (!$this->client_id || !$this->api_secret || !$this->api_url) {
				if (!$throw) return;
				throw new ConfigError(['config']);
			}
			$refresh = false;
			$check = false;
			$access_token = $this->get_option('onecrm_p_access_token');
			if ($access_token) {
				if (empty($access_token['expires_at']) || $access_token['expires_at'] <= time())
					$refresh = true;
				else
					$check = true;
			}
			if (!$access_token) {
				$access_token = $this->authenticate();
			} elseif ($check) {
				$access_token = $this->checkToken($access_token);
			} elseif ($refresh) {
				$access_token = $this->refreshAuthToken($access_token);
			}
			$this->addOrUpdateOption('onecrm_p_access_token', $access_token);
			$auth = new Authentication\OAuth($access_token);
			$this->client = new Client($this->api_url, $auth);
		}

		protected function checkContactAuth($throw = false) {
			$this->contact_client = null;
			$client = $this->getAdminClient();
			$user_id = get_current_user_id();
			if (!$user_id) {
				if (!$throw) return;
				throw new ConfigError(['login']);
			}
			$errors = [];
			$contact_id = get_user_meta( $user_id, 'onecrm_p_contact_id', true);
			if (!$contact_id) $errors[] = 'contact';
			$account_id = get_user_meta( $user_id, 'onecrm_p_account_id', true);
			if (!$account_id) $errors[] = 'account';
			if (!empty($errors)) {
				if (!$throw) return;
				throw new ConfigError($errors);
			}
			$access_token = get_user_option('onecrm_p_access_token');
			$refresh = false;
			$check = false;
			$self = $this;
			$obtainToken = function() use ($client, $contact_id, $self) {
				$token = $client->post('/portal/auth', ['contact_id' => $contact_id]);
				$self->setTokenExpiration($token);
				return $token;
			};
			if ($access_token) {
				if (empty($access_token['expires_at']) || $access_token['expires_at'] <= time())
					$refresh = true;
				else
					$check = true;
			}
			if (!$access_token) {
				$access_token = $obtainToken();
			} elseif ($check) {
				try {
					$access_token = $this->checkToken($access_token, false);
				} catch (APIError $e) {
					$access_token = $obtainToken();
				}
			} elseif ($refresh) {
				try {
					$access_token = $this->refreshAuthToken($access_token, true, $obtainToken);
				} catch (APIError $e) {
					$access_token = $obtainToken();
				}
			}
			update_user_option($user_id, 'onecrm_p_access_token', $access_token);
			$auth = new Authentication\OAuth($access_token);
			$this->contact_client = new Client($this->api_url, $auth);

		}

		/**
		 * @param $access_token
		 * @param bool $reauthenticate
		 * @return string
		 * @throws APIError
		 */
		protected function checkToken($access_token, $reauthenticate = true) {
			try {
				$auth = new Authentication\OAuth($access_token);
				$client = new APIClient\Client($this->api_url, $auth);
				$client->me();
				return $access_token;
			} catch (APIError $e) {
				return $this->refreshAuthToken($access_token, $reauthenticate);
			}
		}

		/**
		 * get access token
		 *
		 * @return string
		 * @throws APIError
		 */
		public function authenticate() {
			$options = [
				'client_id' => $this->client_id,
				'client_secret' => $this->api_secret,
				'scope' => 'read write profile',
				'owner_type' => 'user'
			];

			$flow = new APIClient\AuthorizationFlow($this->api_url, $options);
			$access_token = $flow->init('client_credentials');
			$this->setTokenExpiration($access_token);
			$this->addOrUpdateOption('onecrm_p_access_token', $access_token);
			return $access_token;
		}

		// 30 seconds safety threshold
		public function setTokenExpiration(&$access_token) {
			$access_token['expires_at'] = $access_token['expires_in'] + time() - 30;
		}

		/*
		 * refresh access token
		 * @return string
		 * */

		/**
		 * @param $access_token
		 * @param bool $reauthenticate
		 * @param \Closure|null $obtain_token_func
		 *
		 * @return string
		 * @throws APIError
		 */
		public function refreshAuthToken($access_token, $reauthenticate = true, $obtain_token_func = null) {
			$options = [
				'client_id' => $this->client_id,
				'client_secret' => $this->api_secret,
				'scope' => 'read write profile'
			];
			try {
				$flow = new APIClient\AuthorizationFlow($this->api_url, $options);
				$new_token = $flow->refreshToken($access_token['refresh_token']);
				$this->setTokenExpiration($new_token);
			} catch (APIError $e) {
				if ($reauthenticate) {
					if ($obtain_token_func) {
						$new_token = $obtain_token_func();
					} else {
						$new_token = $this->authenticate();
					}
				} else {
					throw $e;
				}
			}
			return $new_token;
		}

		/**
		 * get model data
		 *
		 * @param $model_name
		 * @param int|null $limit
		 * @return mixed
		 * @throws APIError
		 */
		public function getData( $model_name, $limit = null ) {
			$client = $this->getAdminClient();
			if (!$client) return false;
			if($access_token = $this->get_option('onecrm_p_access_token')){
				$model = $client->model($model_name);
				$result = $model->getList([], 0, $limit);
				return json_encode($result->getRecords(), JSON_PRETTY_PRINT);
			}
			return false;
		}

		/**
		 * get single model data
		 *
		 * @param string $model_name
		 * @param string $id
		 * @return bool
		 * @throws APIError
		 */
		public function getSingleData($model_name, $id ){
			$client = $this->getAdminClient();
			if (!$client) return false;
			if($access_token = $this->get_option('onecrm_p_access_token')){
				$model = $client->model($model_name);
				$result = $model->get($id);
				return $result;
			}
			return false;
		}

		/**
		 * get model related data
		 *
		 * @param string $model_name
		 * @param string $related_model
		 * @param string $id
		 * @return bool
		 * @throws APIError
		 */
		public function getRelatedData($model_name, $related_model, $id){
			$client = $this->getAdminClient();
			if (!$client) return false;
			if($access_token = $this->get_option('onecrm_p_access_token')){
				$model = $client->model($model_name);
				$result = $model->getRelated($id, $related_model, [], 0, 2);
				return $result->getRecords();
			}
			return false;
		}

		/**
		 * post model related data
		 *
		 * @param string $model_name
		 * @param string $related_model
		 * @param string $id
		 * @param array $related_ids
		 * @return bool
		 * @throws APIError
		 */
		public function postRelatedData($model_name, $related_model, $id, $related_ids){
			$client = $this->getAdminClient();
			if (!$client) return false;
			if($access_token = $this->get_option('onecrm_p_access_token')){
				$model = $client->model($model_name);
				$result = $model->addRelated($id, $related_model, $related_ids);
				return $result;
			}
			return false;
		}

		/**
		 * post model data
		 *
		 * @param string $model_name
		 * @param array $data
		 * @return array|bool
		 * @throws APIError
		 */
		public function postData($model_name, $data){
			$client = $this->getAdminClient();
			if (!$client) return false;
			if($access_token = $this->get_option('onecrm_p_access_token')){
				$model = $client->model($model_name);
				$id = $model->create($data);
				return ['status' => 'success', 'id' => $id];
			}
			return false;
		}

		/**
		 * patch/update model data
		 *
		 * @param string $api_end
		 * @param array $data
		 * @return string|bool
		 * @throws APIError
		 */
		public function patchData($api_end, $data){
			$client = $this->getAdminClient();
			if (!$client) return false;
			if($access_token = $this->get_option('onecrm_p_access_token')) {
				$result = $client->patch( $api_end, [
					'json' => [ 'data' => $data ]
				] );

				return json_encode( $result, JSON_PRETTY_PRINT );
			}
			return false;
		}

		/**
		 * delete model data
		 *
		 * @param string $api_end
		 * @return string|bool
		 * @throws APIError
		 */
		public function deleteData($api_end){
			$client = $this->getAdminClient();
			if (!$client) return false;
			if($access_token = $this->get_option('onecrm_p_access_token')) {
				$result = $client->delete( $api_end );

				return json_encode( $result, JSON_PRETTY_PRINT );
			}
			return false;
		}

		/**
		 * filter/search data from model
		 * @param $model_name
		 * @param array $data
		 * @return array|bool
		 * @throws APIError
		 */
		public function filterData($model_name, $data){
			$client = $this->getAdminClient();
			if (!$client) return false;
			if($access_token = $this->get_option('onecrm_p_access_token')){
				$model = $client->model($model_name);
				$result = $model->getList(['fields' => ['primary_account_id', 'first_name', 'last_name', 'email1', 'company_name'], 'filters' => $data], 0, null);
				return ['status' => 'success', 'count' => $result->totalResults(), 'result' => $result->getRecords()];
			}
			return false;
		}

		/**
		 * add/update settings
		 * @param string $option_name
		 * @param mixed $new_value
		 */
		public function addOrUpdateOption($option_name, $new_value) {
			if ($this->options) {
				$this->options[$option_name] = $new_value;
				return;
			}
			if ( get_option( $option_name ) !== false ) {
				update_option( $option_name, $new_value );
			} else {
				$deprecated = null;
				$autoload = 'no';
				add_option( $option_name, $new_value, $deprecated, $autoload );
			}
		}

		/**
		 * @param string $option_name
		 * @return mixed
		 */
		public function get_option($option_name) {
			if ($this->options) {
				return onecrm_get_default($this->options, $option_name, false);
			}
			return get_option($option_name);
		}

		/**
		 * get contact /me data
		 *
		 * @param string $contact_id
		 * @return array
		 * @throws APIError
		 */
		public function getAuthenticatedUser($contact_id){
			$c = $this->getContactClient();
			if ($c) {
				return $c->me();
			} else {
				return [];
			}
		}
	}
}

