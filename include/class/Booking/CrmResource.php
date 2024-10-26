<?php

namespace OneCRM\Portal\Booking;


class CrmResource extends CrmEntry {

	/**
	 * CrmResource constructor.
	 *
	 * @param \OneCRM\Portal\API\Client $client
	 */
	public function __construct(\OneCRM\Portal\API\Client $client) {
		parent::__construct($client);

		$this->model_name = 'Resource';
		$this->search_field = 'filter_text';
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
		return [
			'name' => $original_data['title'],
			'type' => 'Booking Appointment'
		];
	}
}