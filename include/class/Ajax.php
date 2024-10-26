<?php

namespace OneCRM\Portal;
use OneCRM\Portal\Auth\OneCrm;

class Ajax
{

	/**
	 * XHR Save a Model
	 */
	public static function model_save($create=false)
	{
		ob_clean();
		$model_name = @sanitize_text_field($_POST['model']);
		$data = $_POST[$model_name]; // sanitize_* applied later
		$id = @sanitize_text_field($data['id']);

		$account_id = get_user_meta(get_current_user_id(), 'onecrm_p_account_id');
		if ($account_id)
			$account_id = $account_id[0];
		$contact_id = get_user_meta(get_current_user_id(), 'onecrm_p_contact_id');
		if ($contact_id)
			$contact_id = $contact_id[0];

		if (!$model_name || !($id || $create)) {
			self::bad_request_repsonse();
		}
		$all = onecrm_p_get_all_modules(true);
		$defs = array_filter($all, function($d) use ($model_name) { return $d['model'] === $model_name;});
		$def = current($defs);
		if (empty($def['can_create'])) {
			self::unauthorized_repsonse();
		}

		$client = OneCrm::instance()->getAdminClient();
		if (!$client) {
			self::unauthorized_repsonse();
		}

		$errors = [];
		try {

			$model = $client->model($model_name);
			$meta = $model->metadata();
			$fields_meta = $meta['fields'];

			foreach ($data as $key=>&$value) {
				$value = sanitize_textarea_field($value);
				if (is_array($value)) {
					// join the multiple values if any:
					$value = implode('^,^', array_filter($value, 'strlen'));
				}
				$value = trim($value);
				if ($key != 'id' && !empty($fields_meta[$key]['required']) && !strlen($value)) {
					$errors[] = sprintf(__('Missing value for required field %s' /* translators: %s will bre placed with field name */, ONECRM_P_TEXTDOMAIN),  $fields_meta[$key]['vname']);
				}
			}
			unset($value);
			if (!empty($def['create']['account_fields'])) {
				$acc_fields = $def['create']['account_fields'];
				if (!empty($acc_fields['account_id']))
					$data[$acc_fields['account_id']] = $account_id;
				if (!empty($acc_fields['contact_id']))
					$data[$acc_fields['contact_id']] = $contact_id;
			}
			if (!empty($errors)) {
				self::error_response(200, $errors);
			}

			if ($create) {
				$id = $model->create($data);
			} else {
				$model->update($id, $data, false);
			}

			if (! empty($def['detail'])) {
				$res = [
					'redirect' =>  empty($_POST['return_to']) ? 
						add_query_arg(
							[
								'detailview' => $model_name,
								'record' => $id,
							],
							get_permalink(sanitize_key($_POST['ret']))
						)
						:
						wp_unslash($_POST['return_to']),
				];
			} else {
				$res = [
					'redirect' =>  empty($_POST['return_to']) ? '?' : wp_unslash($_POST['return_to'])
				];
			}

			self::success_response($res);

		} catch (\OneCRM\ApiClient\Error $e) {
			self::error_response($e->getCode(), $e->getMessage());
		}
	}

	/**
	 * XHR Erase Personal Data
	 */
	public static function erase_personal_data()
	{
		ob_clean();
		$model_name = 'Contact';
		$erase = isset($_POST['erase']) 
			? (is_array($_POST['erase']) 
				? array_map('sanitize_key', array_keys($_POST['erase']))
				: null) 
			: null;

		$contact_id = get_user_meta(get_current_user_id(), 'onecrm_p_contact_id');
		if ($contact_id)
			$contact_id = $contact_id[0];

		if (!$model_name || !$contact_id) {
			self::bad_request_repsonse();
		}
		if (empty($erase)) {
			self::error_response(
				200,
				__('Please select at least one field', ONECRM_P_TEXTDOMAIN)
			);
		}

		$client = OneCrm::instance()->getAdminClient();
		if (!$client)
			self::unauthorized_repsonse();

		try {
			$fields = implode(',', $erase);
			$endpoint = '/data/erase_personal/' . $model_name . '/' . $contact_id;
			$body = ['data' => ['fields' => $fields]];

			$client->patch($endpoint, $body);

			$res = [
				'redirect' =>  empty($_POST['return_to']) ?
					add_query_arg(
						[
							'personal_data' => $model_name,
							'format' => 'view',
							'record' => $contact_id
						],
						get_permalink(sanitize_key($_POST['ret']))
					)
					: wp_unslash($_POST['return_to']),
			];

			self::success_response($res);

		} catch (\OneCRM\ApiClient\Error $e) {
			self::error_response($e->getCode(), $e->getMessage());
		}
	}

	public static function model_create()
	{
		self::model_save(true);
	}

	public static function run_booking_calendar_sync()
	{
		ob_clean();

		$manager = new \OneCRM\Portal\Booking\AppointmentsManager();

		if ($manager->is_sync_running()) {
			self::error_response('400', __( 'The synchronization process is running already. Please try later.', ONECRM_P_TEXTDOMAIN ));
		} else {
			$manager->check_ytd_updates();
			$res = __( 'The synchronization process was completed.', ONECRM_P_TEXTDOMAIN );
			self::success_response($res);
		}
	}

	public static function bad_request_repsonse($body=''){
		self::error_response(400, $body);
	}
	public static function unauthorized_repsonse($body=''){
		self::error_response(401, $body);
	}
	public static function error_response($code = '400', $body = '')
	{
		$body = (array)$body;
		$body = [
			'status' => 'error',
			'errors' => $body,
		];
		self::response($body, $code);
	}
	public static function success_response($body = '')
	{
		$body = [
			'status' => 'success',
			'result' => $body,
		];
		self::response($body);
	}

	public static function response($body, $code = '200')
	{
		ob_clean();
		http_response_code($code);
		if (is_string($body)) {
			die($body);
		} else {
			die(json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		}
	}

}
