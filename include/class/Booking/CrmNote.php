<?php

namespace OneCRM\Portal\Booking;


class CrmNote extends CrmEntry {

	/**
	 * CrmNote constructor.
	 *
	 * @param \OneCRM\Portal\API\Client $client
	 */
	public function __construct(\OneCRM\Portal\API\Client $client) {
		parent::__construct($client);

		$this->model_name = 'Note';
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
			'name' => $original_data['note_name'],
			'description' => $original_data['remark'],
			'parent_type' => 'Meetings',
			'parent_id' => $original_data['appointment_id'],
			'account_id' => $original_data['account_id'],
			'contact_id' => $original_data['contact_id']
		];
	}
}