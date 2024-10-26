<?php

namespace OneCRM\Portal\Booking;


use OneCRM\APIClient\Error;

class CrmAppointment extends CrmEntry {

	/**
	 * CrmAppointment constructor.
	 *
	 * @param \OneCRM\Portal\API\Client $client
	 */
	public function __construct(\OneCRM\Portal\API\Client $client) {
		parent::__construct($client);

		$this->model_name = 'Meeting';
		$this->search_field = 'booking_app_wp_id';
	}

	/**
	 * Create/Update entry in CRM
	 *
	 * @param array $data
	 * @param string|null $id - entry's CRM ID
	 * @param int|null $wp_id - WordPress ID
	 *
	 * @return bool|string
	 */
	public function save($data, $id = null, $wp_id = null) {
		$result = parent::save($data, $id, $wp_id);
		$is_new = ! $id;
		$app_id = ! is_null($id) ? $id : $result;

		if (is_bool($result) && $result === true)
			$result = $app_id;

		//Add Contact and resource
		try {

			$this->get_model()->addRelated($app_id, 'contacts', [$data['contact_id']]);
			$this->get_model()->addRelated($app_id, 'resources', [$data['resource_id']]);

		} catch (\OneCRM\APIClient\Error $e) {
			error_log($e->getMessage());
		}

		//Add note (by remark field)
		$this->create_note($app_id, $wp_id, $data);

		//Create Invoice and Payment if needed
		$this->add_payment_items($app_id, $data, $is_new);

		return $result;
	}

	/**
	 * Adjust POST data for store in CRM
	 *
	 * @param array $original_data
	 * @param null|int $wp_id
	 *
	 * @return array
	 */
	protected function adjust_data($original_data, $wp_id = null) {
		$dates = $original_data['dates'];
		$booking_status = $dates[0]->approved == 1 ? 'Approved' : 'Pending';

		$date_start = new \DateTime($dates[0]->booking_date);
		$date_end_idx = sizeof($dates) - 1;
		$date_end = new \DateTime($dates[$date_end_idx]->booking_date);

		$duration = (int) (($date_end->getTimestamp() - $date_start->getTimestamp()) / 60);

		$result =  [
			'name' => __( 'Booking Appointment', 'onecrm_p' ) .': '. $original_data['resource']['title'],
			'status' => 'Planned',
			'date_start' => get_gmt_from_date($date_start->format('Y-m-d H:i:s')),
			'date_end' => $date_end->format('Y-m-d'),
			'duration' => $duration,
			'description' => html_entity_decode($original_data['form_data']['_all_fields_']['details'], ENT_QUOTES, 'UTF-8'),
			'account_id' => $original_data['account_id'],

			'booking_app' => 1,
			'booking_app_wp_id' => $wp_id,
			'booking_app_status' => $booking_status,
			'booking_app_payment_status' => $original_data['pay_status'],
			'booking_app_cost' => $original_data['cost'],
			'booking_app_resource_id' => $original_data['resource_id']
		];

		if (! empty($original_data['currency'])) {
			$result['booking_app_currency_id'] = $original_data['currency']['id'];
			$result['booking_app_exchange_rate'] = $original_data['currency']['exchange_rate'];
		}

		return $result;
	}

	/**
	 * @param string $app_id - CRM appointment ID
	 * @param array $app_data
	 * @param bool $is_new
	 */
	private function add_payment_items($app_id, $app_data, $is_new) {
		if ( ! empty($app_data['cost']) && (float)$app_data['cost'] > 0 ) {
			$invoice_id = null;
			$link = false;

			if ($is_new) {
				$invoice_id = $this->create_invoice($app_data);
				$link = true;
			} else {
				$is_linked = $this->is_linked_with_invoice($app_id);

				if (! $is_linked) {
					$invoice_id = $this->create_invoice($app_data);
					$link = true;
				} else {
					$invoice_id = $is_linked;
				}
			}

			if ($invoice_id) {
				if ($link)
					$this->link_with_invoice($app_id, $invoice_id);

				$this->add_payment($app_data, $invoice_id);
			}
		}
	}

	/**
	 * @param mixed $value - filter value
	 *
	 * @return array
	 */
	protected function get_identification_filter($value) {
		$filter = parent::get_identification_filter($value);
		$filter['filters'][$this->search_field .'-operator'] = 'eq';

		return $filter;
	}

	/**
	 * @param string $app_id - CRM appointment ID
	 * @param int $app_wp_id - WordPress appointment ID
	 * @param array $data
	 */
	private function create_note($app_id, $app_wp_id, $data) {
		$note = new CrmNote($this->client);

		if ($app_id && !empty($data['remark'])) {
			$name = __( 'Booking Appointment', 'onecrm_p' ) .': '. $data['resource']['title'] .' ('.$app_wp_id.')';

			$is_exists = $note->is_exist($name);
			$id = $is_exists ? $is_exists : null;

			$data['appointment_id'] = $app_id;
			$data['note_name'] = $name;

			$note->save($data, $id);
		}

	}

	/**
	 * @param array $data
	 *
	 * @return bool|string
	 */
	private function create_invoice($data) {
		$invoice = new CrmInvoice($this->client);

		return $invoice->save($data);
	}

	/**
	 * Link appointment with created invoice
	 *
	 * @param string $appointment_id
	 * @param string $invoice_id
	 */
	private function link_with_invoice($appointment_id, $invoice_id) {
		try {
			$this->get_model()->update(
				$appointment_id,
				[
					'parent_type' => 'Invoice',
					'parent_id' => $invoice_id
				]
			);
		} catch (Error $e) {
			error_log($e->getMessage());
		}
	}

	/**
	 * Is appointment already linked with invoice or not
	 *
	 * @param string $appointment_id
	 *
	 * @return bool|string - false if there isn't linked invoice or linked invoice's ID otherwise
	 */
	private function is_linked_with_invoice($appointment_id) {
		$result = false;
		$app = [];

		try {
			$app = $this->get_model()->get($appointment_id, ['parent_type', 'parent_id']);
		} catch (Error $e) {
			error_log($e->getMessage());
		}

		if (! empty($app) && ($app['parent_type'] == 'Invoice' && ! empty($app['parent_id']))) {
			$result = $app['parent_id'];
		}

		return $result;
	}

	/**
	 * Add Payment for Invoice
	 *
	 * @param array $data
	 * @param string $invoice_id
	 *
	 * @return bool
	 */
	private function add_payment($data, $invoice_id) {
		if ($data['pay_status'] != 'Completed')
			return false;

		$invoice = new CrmInvoice($this->client);
		$amount_due = $invoice->get_amount_due($invoice_id);

		if (! empty($amount_due)) {
			$payment = new CrmPayment($this->client);
			$data['invoice_id'] = $invoice_id;
			$payment_id = $payment->save($data);

			if ($payment_id)
				$invoice->reset_amount_due($invoice_id);
		}

		return true;
	}
}