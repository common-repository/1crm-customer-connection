<?php

namespace OneCRM\Portal\API;

use OneCRM\APIClient\ListResult;
use OneCRM\APIClient\Error as APIError;

/**
 * Used to work with 1CRM Subscription-related data for Contact Client
 * NOTE: this is largely cut&paste OneCRM\APIClient\Client.php so maybe merge them?
 *       see 1CRM /modules/Subscriptions/API/Subscription/Data.php for allowed Models and Fields
 *       getList() is limited to the class's PaymentProcessor id.  Processors cannot be mixed.
 *
 * handles these endpoints:
 * /subscription/data/{object_name}
 * /subscription/data/{object_name}/{id}
 * /subscription/data/{object_name}/{id}/{link_name} (get only)
 * /subscription/meta/fields/{object_name}
 */
class Subscription
{

static $processors = null;
	static $without_processor = ['PaymentProcessor', 'Contact'];
	protected $client;
		protected $object_name; // [name=>id]
	protected $processor_id;

	/**
	 * Subscription constructor.
	 * @param Client $client
	 * @param $object_name
	 * @param $processor_id : id or name.
	 * @returns $this
	 * @throws APIError
	 */
	public function __construct(Client $client, $object_name, $processor_id)
	{
		$this->client = $client;
		$this->object_name = $object_name;
		if (!self::$processors) self::$processors = @get_option('onecrm_p_options')['processors'];
		if (array_search($processor_id, self::$processors)) {
			$this->processor_id = $processor_id;
		} else {
			$this->processor_id = @self::$processors[$processor_id];
			if (!($object_name === 'PaymentProcessor' || $this->processor_id))
				throw new APIError('Unknown processor: ' . $processor_id);
		}
		return $this;
	}

	/**
	 * @return string payment processor GUID
	 */
	public function get_processor_id(){
		return $this->processor_id;
	}

	/**
	 * @param array $options Request options
	 *      * `fields`: optional array with fields you want returned
	 *      * `filters`: optional associative array with filters. Keys are filter names, values are filter values
	 *      *  NOTE: `filter` will automatically have payment_processor_id unless $object_name in $without_processor
	 *      * `order`: optional sort order
	 *      * `query_favorite`: optional boolean, if true, results will include `is_favorite` flag
	 *      * `filter_text`: optional filter text, used for generic text search
	 * @param int $offset Starting offset
	 * @param int $limit Maximum number of records to return
	 * @return ListResult
	 * @throws APIError
	 */
	public function getList($options = [], $offset = 0, $limit = 0)
	{
		$endpoint = "/subscription/data/$this->object_name";
		$query = [];
		if (isset($options['fields']) && is_array($options['fields']))
			$query['fields'] = $options['fields'];
		if (!array_search($this->object_name, self::$without_processor)) {
			if (isset($options['filters']['_lq'])) {
				// redirected listQuery filters:
				$options['filters']['_lq'] = [['operator'=>'AND', 'multiple' => array_merge(
					[['field' => 'payment_processor_id', 'value' => $this->processor_id]],
					$options['filters']['_lq']
				)]];
			} else {
				// normal filters:
				$options['filters']['payment_processor_id'] = $this->processor_id;
			}
		}
		if (isset($options['filters']) && is_array($options['filters']))
			$query['filters'] = $options['filters'];
		if (isset($options['order']) && is_string($options['order']))
			$query['order'] = $options['order'];
		if (isset($options['filter_text']))
			$query['filter_text'] = $options['filter_text'];
		$query['offset'] = $offset;
		if ($limit > 0)
			$query['limit'] = $limit;
		$result = $this->client->get($endpoint, $query);
		return new ListResult($this->client, $endpoint, $query, $result);
	}

	/**
	 * Get list of related records.
	 *
	 * @param string $id ID of parent record
	 * @param string $link Link name
	 * @param array $options array with request options
	 *      * `fields`: optional array with fields you want returned
	 *      * `filters`: optional associative array with filters. Keys are filter names, values are filter values
	 *      * `order`: optional sort order
	 *      * `filter_text`: optional filter text, used for generic text search
	 * @param int $offset Starting offset
	 * @param int $limit Maximum number of records to return
	 * @return ListResult
	 * @throws APIError
	 */
	public function getRelated($id, $link, $options = [], $offset = 0, $limit = 0)
	{
		$endpoint = "/subscription/data/$this->object_name/$id/$link";
		$query = [];
		if (isset($options['fields']) && is_array($options['fields']))
			$query['fields'] = $options['fields'];
		if (isset($options['filters']) && is_array($options['filters']))
			$query['filters'] = $options['filters'];
		if (isset($options['order']) && is_string($options['order']))
			$query['order'] = $options['order'];
		if (isset($options['filter_text']))
			$query['filter_text'] = $options['filter_text'];
		$query['offset'] = $offset;
		if ($limit > 0)
			$query['limit'] = $limit;
		$result = $this->client->get($endpoint, $query);
		return new ListResult($this->client, $endpoint, $query, $result);
	}

	/**
	 * Retrieves single record with specified ID
	 *
	 * @param string $id Record ID
	 * @param array $fields List of fields to fetch
	 * @return mixed
	 * @throws APIError
	 */
	public function get($id, array $fields = [])
	{
		$endpoint = "/subscription/data/$this->object_name/$id";
		$query = ['fields' => $fields];
		$result = $this->client->get($endpoint, $query);
		return $result['record'];
	}

	/**
	 * Creates a new record
	 *
	 * @param array $data Associative array with record data. Keys are field names, values are field values.
	 *
	 * NOTE: If a unique record pre-exists, returns that ID without committing the changes
	 *
	 * @return string|null New record ID
	 * @throws APIError
	 */
	public function create($data, $sync = true)
	{
		if (isset($data['payment_processor_id']) && ($this->processor_id !== $data['payment_processor_id'])) {
			throw new APIError('payment processor mismatch');
		} else {
			$data['payment_processor_id'] = $this->processor_id;
		}
		$endpoint = "/subscription/data/$this->object_name";
		$body = ['data' => $data, 'sync'=> $sync];
		try {
			$result = $this->client->post($endpoint, $body);
		} catch (APIError $e) {
			$message = $e->getMessage();
			$parts = explode(' exists: ', $message);
			if ($parts[0] == $this->object_name && $e->getCode() == 400) {
				// Record pre-existed.  throw json-encoded ID[s] as message with 'Found' code.
				throw new APIError($parts[1], 302);
			}
			throw $e;
		}
		return $result['id'];
	}

	/**
	 * Updates a record
	 *
	 * @param string $id Record ID
	 * @param array $data Associative array with record data. Keys are field names, values are field values.
	 * @param bool $create If true, the record will be created if it does not exist
	 *
	 * @return true Always
	 * @throws APIError
	 */
	public function update($id, $data, $create = false)
	{
		$endpoint = "/subscription/data/$this->object_name/$id";
		$body = ['data' => $data, 'create' => $create];
		$result = $this->client->patch($endpoint, $body);
		return $result['result'];
	}

	/**
	 * Deletes a record
	 *
	 * @param string $id Record ID
	 *
	 * @return bool true if record was deleted
	 * @throws APIError
	 */
	public function delete($id)
	{
		$endpoint = "/subscription/data/$this->object_name/$id";
		$result = $this->client->delete($endpoint);
		return $result['result'];
	}

	/**
	 * Retrieves fields and filters metadata
	 *
	 * @return array Metadata
	 * @throws APIError
	 */
	public function metadata()
	{
		$endpoint = "/subscription/meta/fields/$this->object_name";
		$result = $this->client->get($endpoint);
		return $result;
	}

}
