<?php

namespace OneCRM\Portal\Booking;

use OneCRM\Portal\API\Client;
use OneCRM\Portal\Auth;

class AppointmentsManager {

	const BOOKING_CALENDAR_PLUGIN_MAIN_SCRIPT = 'wpdev-booking.php';

	const BOOKING_SYNC_CONF_OPTION = 'onecrm_p_booking_app_sync';

	const BOOKING_ACCOUNT_ID_OPTION = 'onecrm_p_booking_account_id';

	/**
	 * @var CalendarAppointment
	 */
	private $appointment;

	/**
	 * AppointmentsManager constructor.
	 */
	public function __construct() {
		$this->appointment = new CalendarAppointment();
	}

	/**
	 * Sync appointments for year to date date range
	 *
	 */
	public function check_ytd_updates() {
		$real_date = strtotime('-1 year');
		$updated_from = date_i18n("Y-m-d H:i:s", $real_date);

		$real_date = strtotime('now');
		$updated_to = date_i18n("Y-m-d H:i:s", $real_date);

		$this->check_updates($updated_from, $updated_to);
	}

	/**
	 * Sync appointments by date range
	 *
	 * @param string $updated_from - date string in Y-m-d H:i:s format
	 * @param string $updated_to - date string in Y-m-d H:i:s format
	 *
	 * @return bool
	 */
	public function check_updates($updated_from, $updated_to) {
		if (! $this->is_sync_enabled())
			return false;

		if ($this->is_sync_running())
			return false;

		try {
			$client = $this->get_client();
		} catch (Auth\ConfigError $e) {
			return false;
		}

		//Start synchronization process
		$this->run_sync();

		$currency = $this->get_currency($client);
		$updated = $this->appointment->get_list($updated_from, $updated_to);

		if (empty($updated))
			return false;

		$appointment = new CrmAppointment($client);
		$contact = new CrmContact($client);
		$resource = new CrmResource($client);

		foreach ($updated as $wp_id => $item) {
			$form_data = $item->form_data;

			if (isset($form_data['_all_fields_']['resource_title'])) {
				$resource_data = (array)$form_data['_all_fields_']['resource_title'];
			} else {
				$resource_data = null;
			}

			$is_app_exists = $appointment->is_exist($wp_id);
			$is_contact_exists = $contact->is_exist($form_data['email']);
			$is_resource_exists = false;

			if ($resource_data)
				$is_resource_exists = $resource->is_exist($resource_data['title']);

			$app_id = $is_app_exists ? $is_app_exists : null;

			//Deleted item
			if ($item->trash) {

				if ($is_app_exists) {
					$appointment->delete($app_id);
				} else {
					continue;
				}

			} else {
				$contact_id = $is_contact_exists ? $is_contact_exists : null;
				$resource_id = $is_resource_exists ? $is_resource_exists : null;

				//Add CRM account ID
				$form_data['account_id'] = $this->get_account_id($client, $form_data);

				$contact_save_res = $contact->save($form_data, $contact_id);
				$resource_save_res = false;

				if ($resource_data)
					$resource_save_res = $resource->save($resource_data, $resource_id);

				if (! $contact_id)
					$contact_id = $contact_save_res;

				if (! $resource_id)
					$resource_id = $resource_save_res;

				$item_data = $this->prepare_app_item_data(
					$item,
					$form_data,
					$resource_data,
					$contact_id,
					$resource_id,
					$currency
				);

				$appointment->save($item_data, $app_id, $wp_id);
			}
		}

		//Stop synchronization process
		$this->stop_sync();

		return true;
	}

	/**
	 * @return bool
	 */
	public function is_sync_running() {
		$value = get_option('onecrm_p_bc_sync_running');

		return (bool)$value;
	}

	/**
	 * Set run flag to True
	 */
	public function run_sync() {
		update_option('onecrm_p_bc_sync_running', 1);
	}

	/**
	 * Set run flag to False
	 */
	public function stop_sync() {
		update_option('onecrm_p_bc_sync_running', 0);
	}

	/**
	 * Get CRM API Client
	 *
	 * @return bool|null|\OneCRM\Portal\API\Client
	 * @throws Auth\ConfigError
	 */
	private function get_client() {
		return Auth\OneCrm::instance()->getAdminClient(false);
	}

	/**
	 * Get CRM Account ID (if account doesn't exist create new one)
	 *
	 * @param Client $client
	 * @param array $data - appointment data
	 *
	 * @return string|null
	 */
	private function get_account_id(Client $client, $data) {
		if ( ! function_exists( 'get_user_by' ) )
			require_once( ABSPATH . "/wp-includes/pluggable.php" );

		$user = get_user_by('email', $data['email']);
		$result = null;

		if ($user) {
			$data['company'] = get_user_meta($user->ID, 'billing_company', true);
			$result = get_user_meta($user->ID, 'onecrm_p_account_id', true);

			if (! $result) {
				$result = $this->create_account($client, $data);
				update_user_meta($user->ID, 'onecrm_p_account_id', $result);
			}

		} else {
			$result = $this->create_account($client, $data);
		}

		return $result;
	}

	/**
	 * Create Account in CRM
	 *
	 * @param Client $client
	 * @param array $data
	 *
	 * @return bool|string
	 */
	private function create_account(Client $client, $data) {
		$account = new CrmAccount($client);
		$is_account_exist = $account->is_exist($data['email']);

		if ($is_account_exist) {
			$result = $is_account_exist;
		} else {
			$result = $account->save($data);
		}

		return $result;
	}

	/**
	 * Get currency data by ISO 4217 code
	 *
	 * @param \OneCRM\Portal\API\Client $client
	 *
	 * @return array|null
	 */
	private function get_currency(\OneCRM\Portal\API\Client $client) {
		$model = $client->model('Currency');

		$filter = [
			'fields' => ['id', 'conversion_rate'],
			'filters' => [
				'filter_text' => $this->appointment->get_currency()
			]
		];

		try {
			$result = $model->getList($filter, 0, 1);
		} catch(\OneCRM\APIClient\Error $e) {
			error_log($e->getMessage());
			return null;
		}

		if ($result->totalResults() > 0) {
			return [
				'id' => $result->getRecords()[0]['id'],
				'exchange_rate' => $result->getRecords()[0]['conversion_rate']
			];
		} else {
			return null;
		}
	}

	/**
	 * Prepare item data for store in CRM
	 *
	 * @param \stdClass $item
	 * @param array $form_data
	 * @param array $resource_data
	 * @param string $contact_id - CRM contact ID
	 * @param string $resource_id - CRM resource ID
	 * @param array $currency
	 *
	 * @return array
	 */
	private function prepare_app_item_data(\stdClass $item, $form_data, $resource_data, $contact_id, $resource_id, $currency) {
		$item_data = (array)$item;

		$item_data['account_id'] = $form_data['account_id'];
		$item_data['contact_id'] = $contact_id;
		$item_data['resource'] = $resource_data;
		$item_data['resource_id'] = $resource_id;
		$item_data['currency'] = $currency;

		$item_data['pay_status'] = $this->appointment->get_payment_status_title(
			$item_data['pay_status']
		);

		return $item_data;
	}

	/**
	 * Add cron job for booking appointments synchronization
	 *
	 * @return bool
	 */
	public static function add_updater_cron_job() {
		add_filter('cron_schedules', [__CLASS__, 'register_five_minutes_interval']);
		add_action('init', [__CLASS__, 'cron_activation']);
		register_deactivation_hook(__FILE__, [__CLASS__, 'cron_deactivation']);
		add_action(ONECRM_P_BOOKING_CRON_NAME, [__CLASS__, 'scheduler']);

		return true;
	}

	/**
	 * Register new cron job
	 */
	public static function cron_activation() {
		if ( ! wp_next_scheduled(ONECRM_P_BOOKING_CRON_NAME))
			wp_schedule_event(time(), 'every_five_minutes', ONECRM_P_BOOKING_CRON_NAME);
	}

	/**
	 * Deactivate cron job after plugin deactivation
	 */
	public static function cron_deactivation() {
		$timestamp = wp_next_scheduled(ONECRM_P_BOOKING_CRON_NAME);
		wp_unschedule_event($timestamp, ONECRM_P_BOOKING_CRON_NAME);
	}

	/**
	 * Cron job updater function
	 */
	public static function scheduler() {
		$real_date = strtotime('-20 minute');
		$updated_from = date_i18n("Y-m-d H:i:s", $real_date);

		$real_date = strtotime('now');
		$updated_to = date_i18n("Y-m-d H:i:s", $real_date);

		$manager = new AppointmentsManager();
		$manager->check_updates($updated_from, $updated_to);

		$manager->stop_sync();
	}

	/**
	 * Register custom 5 minutes cron interval
	 *
	 * @return array
	 */
	public static function register_five_minutes_interval() {
		$schedules['every_five_minutes'] = [
			'interval' => 300,
			'display' => __('Every Five Minutes')
		];

		return $schedules;
	}

	/**
	 * Is Booking Calendar synchronization enabled or not
	 *
	 * @return bool
	 */
	private function is_sync_enabled() {
		$plugins = get_option('active_plugins', []);
		$sync_option = get_option(self::BOOKING_SYNC_CONF_OPTION);
		$is_plugin_active = false;

		foreach ($plugins as $name) {
			if (strpos($name, self::BOOKING_CALENDAR_PLUGIN_MAIN_SCRIPT) !== false) {
				$is_plugin_active = true;
				break;
			}
		}

		if ( ($is_plugin_active && function_exists('wpbc_get_currency')) && $sync_option) {
			return true;
		} else {
			return false;
		}
	}
}