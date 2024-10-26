<?php

namespace OneCRM\Portal\Booking;


use OneCRM\APIClient\Model;
use OneCRM\APIClient\Error;
use OneCRM\Portal\API\Client;

abstract class CrmEntry {

	/**
	 * @var string
	 */
	protected $model_name;

	/**
	 * @var Model
	 */
	private $model;

	/**
	 * @var string
	 */
	protected $search_field;

	/**
	 * @var \OneCRM\Portal\API\Client
	 */
	protected $client;

	/**
	 * CrmEntry constructor.
	 *
	 * @param \OneCRM\Portal\API\Client $client
	 */
	public function __construct(Client $client) {
		$this->model_name = '';
		$this->model = null;
		$this->search_field = '';
		$this->client = $client;
	}

	/**
	 * @return Model|null
	 */
	protected function get_model() {
		if (! $this->model) {
			$this->model = $this->client->model($this->model_name);
		}

		return $this->model;
	}

	/**
	 * @param mixed $search_value
	 *
	 * @return mixed - false if entry isn't exists in CRM
	 * or string entry's ID otherwise
	 */
	public function is_exist($search_value) {

		try {
			$result = $this->get_model()->getList($this->get_identification_filter($search_value), 0, 1);
		} catch (Error $e) {
			error_log($e->getMessage());
			return false;
		}

		if ($result->totalResults() > 0) {
			return $result->getRecords()[0]['id'];
		} else {
			return false;
		}

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
		$adjusted_data = $this->adjust_data($data, $wp_id);
		$result = false;

		if ($id) {

			try {
				$result = $this->get_model()->update($id, $adjusted_data);
			} catch (Error $e) {
				error_log($e->getMessage());
				return $result;
			}

		} else {

			try {
				$result = $this->get_model()->create($adjusted_data);
			} catch (Error $e) {
				error_log($e->getMessage());
				return $result;
			}

		}

		return $result;
	}

	/**
	 * @param string $id
	 *
	 * @return bool
	 */
	public function delete($id) {
		try {
			$this->model->delete($id);
		} catch (Error $e) {
			error_log($e->getMessage());
			return false;
		}

		return true;
	}

	/**
	 * Adjust POST data for store in CRM
	 *
	 * @param array $original_data
	 * @param null|int $wp_id
	 *
	 * @return array
	 */
	protected abstract function adjust_data($original_data, $wp_id = null);

	/**
	 * @param mixed $value - filter value
	 *
	 * @return array
	 */
	protected function get_identification_filter($value) {
		$filter = [
			'fields' => [$this->search_field],
			'filters' => [$this->search_field => $value]
		];

		return $filter;
	}
}