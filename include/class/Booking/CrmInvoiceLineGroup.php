<?php

namespace OneCRM\Portal\Booking;


class CrmInvoiceLineGroup extends CrmEntry {

	/**
	 * CrmInvoice constructor.
	 *
	 * @param \OneCRM\Portal\API\Client $client
	 */
	public function __construct(\OneCRM\Portal\API\Client $client) {
		parent::__construct($client);

		$this->model_name = 'InvoiceLineGroup';
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
		$result = [
			'parent_id' => $original_data['invoice_id'],
			'name' => '',
			'position' => 1,
			'status' => 'Closed Accepted',
			'cost' => $original_data['cost'],
			'subtotal' => $original_data['cost'],
			'total' => $original_data['cost'],
			'group_type' => 'products'
		];

		return $result;
	}
}