<?php

namespace OneCRM\Portal\Booking;


use OneCRM\APIClient\Error;

class CrmPayment extends CrmEntry {

	/**
	 * CrmInvoice constructor.
	 *
	 * @param \OneCRM\Portal\API\Client $client
	 */
	public function __construct(\OneCRM\Portal\API\Client $client) {
		parent::__construct($client);

		$this->model_name = 'Payment';
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
		$payment_id = parent::save($data, $id, $wp_id);

		if ($payment_id) {
			$link_data = [
				'payment_id' => $payment_id,
				'invoice_id' => $data['invoice_id'],
				'amount' => $data['cost']
			];

			if (! empty($data['currency'])) {
				$link_data['currency_id'] = $data['currency']['id'];
				$link_data['exchange_rate'] = $data['currency']['exchange_rate'];
			}

			try {
				$link_model = $this->client->model('invoices_payments');
				$link_model->create($link_data);
			} catch (Error $e) {
				error_log($e->getMessage());
			}

		}

		return $payment_id;
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
		$now = new \DateTime();

		$result = [
			'account_id' => $original_data['account_id'],
			'direction' => 'incoming',
			'payment_date' => $now->format('Y-m-d'),
			'payment_type' => 'Credit Card',
			'amount' => $original_data['cost'],
			'total_amount' => $original_data['cost'],

			'related_invoice_id' => $original_data['invoice_id'],
			'applied_amount' => $original_data['cost']
		];

		if (! empty($original_data['currency'])) {
			$result['currency_id'] = $original_data['currency']['id'];
			$result['exchange_rate'] = $original_data['currency']['exchange_rate'];
			$result['applied_currency_id'] = $original_data['currency']['id'];
			$result['applied_rate'] = $original_data['currency']['exchange_rate'];
		}

		return $result;
	}
}