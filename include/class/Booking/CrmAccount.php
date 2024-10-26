<?php

namespace OneCRM\Portal\Booking;


class CrmAccount extends CrmEntry {

	/**
	 * CrmAccount constructor.
	 *
	 * @param \OneCRM\Portal\API\Client $client
	 */
	public function __construct(\OneCRM\Portal\API\Client $client) {
		parent::__construct($client);

		$this->model_name = 'Account';
		$this->search_field = 'any_email';
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
		if (! empty($original_data['company'])) {
			$name = $original_data['company'];
		} else {
			$name = $original_data['secondname'] .', '. $original_data['name'];
		}

		return [
			'name' => $name,
			'email1' => $original_data['email'],
			'phone_office' => $original_data['_all_fields_']['phone']
		];
	}

	/**
	 * @param mixed $value - filter value
	 *
	 * @return array
	 */
	protected function get_identification_filter($value) {
		$filter = parent::get_identification_filter($value);
		$filter['filters'][$this->search_field .'-operator'] = 'like';

		return $filter;
	}

}