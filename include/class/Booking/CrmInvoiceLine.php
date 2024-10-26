<?php

namespace OneCRM\Portal\Booking;


class CrmInvoiceLine extends CrmEntry {

	/**
	 * CrmInvoice constructor.
	 *
	 * @param \OneCRM\Portal\API\Client $client
	 */
	public function __construct(\OneCRM\Portal\API\Client $client) {
		parent::__construct($client);

		$this->model_name = 'InvoiceLine';
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
		$name = __( 'Booking Appointment', 'onecrm_p' ) .': '. $original_data['resource']['title'];

		$result = [
			'invoice_id' => $original_data['invoice_id'],
			'line_group_id' => $original_data['group_id'],
			'name' => $name,
			'position' => 1,
			'quantity' => 1,
			'ext_quantity' => 1,
			'unit_price' => $original_data['cost'],
			'std_unit_price' => $original_data['cost'],
		];
		
		return $result;
	}
}