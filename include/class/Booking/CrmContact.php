<?php

namespace OneCRM\Portal\Booking;


class CrmContact extends CrmEntry {

	/**
	 * CrmContact constructor.
	 *
	 * @param \OneCRM\Portal\API\Client $client
	 */
	public function __construct(\OneCRM\Portal\API\Client $client) {
		parent::__construct($client);

		$this->model_name = 'Contact';
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
		return [
			'first_name' => $original_data['name'],
			'last_name' => $original_data['secondname'],
			'email1' => $original_data['email'],
			'phone_home' => $original_data['_all_fields_']['phone'],
			'primary_account_id' => $original_data['account_id'],
			'description' => $original_data['_all_fields_']['details']
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