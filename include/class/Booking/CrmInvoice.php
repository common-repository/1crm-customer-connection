<?php

namespace OneCRM\Portal\Booking;


use OneCRM\Portal\Locale;
use OneCRM\APIClient\Error;

class CrmInvoice extends CrmEntry {

	/**
	 * CrmInvoice constructor.
	 *
	 * @param \OneCRM\Portal\API\Client $client
	 */
	public function __construct(\OneCRM\Portal\API\Client $client) {
		parent::__construct($client);

		$this->model_name = 'Invoice';
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
		$invoice_id = parent::save($data, $id, $wp_id);

		if ($invoice_id) {
			$group = new CrmInvoiceLineGroup($this->client);

			$data['invoice_id'] = $invoice_id;
			$group_id = $group->save($data);

			if ($group_id) {
				$line = new CrmInvoiceLine($this->client);

				$data['group_id'] = $group_id;
				$line->save($data);
			}
		}

		return $invoice_id;
	}

	/**
	 * @param string $id - invoice ID
	 *
	 * @return null|float
	 */
	public function get_amount_due($id) {
		try {
			$invoice = $this->get_model()->get($id, ['amount_due']);
		} catch (Error $e) {
			error_log($e->getMessage());
		}

		$result = null;

		if (! empty($invoice) && ! empty($invoice['amount_due']))
			$result = $invoice['amount_due'];

		return $result;
	}

	/**
	 * Make Amount Due value a zero
	 *
	 * @param string $invoice_id
	 */
	public function reset_amount_due($invoice_id) {
		try {
			$this->get_model()->update(
				$invoice_id,
				[
					'amount_due' => 0,
				]
			);
		} catch (Error $e) {
			error_log($e->getMessage());
		}
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
		$dates = $original_data['dates'];

		$name = __( 'Booking Appointment', 'onecrm_p' ) .': '. $original_data['resource']['title'];

		$result =  [
			'name' => $name,
			'shipping_stage' => 'None',
			'terms' => 'Due on Receipt',

			'description' => $this->build_description($name, $dates),

			'invoice_date' => $now->format('Y-m-d'),
			'due_date' => $now->format('Y-m-d'),

			'billing_contact_id' => $original_data['contact_id'],
			'billing_account_id' => $original_data['account_id'],

			'amount' => $original_data['cost'],
			'amount_due' => $original_data['cost']
		];

		if (! empty($original_data['currency'])) {
			$result['currency_id'] = $original_data['currency']['id'];
			$result['exchange_rate'] = $original_data['currency']['exchange_rate'];
		}

		return $result;
	}

	/**
	 * Build a description for the CRM appointment
	 *
	 * @param string $app_name - appointment name
	 * @param array $app_dates - the list of appointment dates
	 *
	 * @return string
	 */
	private function build_description($app_name, $app_dates) {
		$date_start = $this->extract_date_from_app_dates($app_dates, 'start');
		$date_end = $this->extract_date_from_app_dates($app_dates, 'end');
		$duration = $this->calc_duration($date_start, $date_end);

		$description = $app_name;
		$description .= '; '. __( 'Date Start', 'onecrm_p' ) .': '. $this->date_to_crm_format($date_start);
		$description .= '; '. __( 'Date End', 'onecrm_p' ) .': '. $this->date_to_crm_format($date_end);

		if (! empty($duration))
			$description .= '; '. __( 'Duration', 'onecrm_p' ) .': '. $duration;
		$description .= ' '. __( 'hours', 'onecrm_p' );

		return $description;
	}

	/**
	 * Take start/end date from the list of appointment dates and return it
	 *
	 * @param array $dates - the list of appointment dates
	 * @param string $type - 'start' or 'end'
	 *
	 * @return \DateTime
	 */
	private function extract_date_from_app_dates($dates, $type = 'start') {
		if (empty($dates) || ! is_array($dates))
			return new \DateTime();

		if ($type == 'end') {
			$idx = sizeof($dates) - 1;
		} else {
			$idx = 0;
		}

		return new \DateTime($dates[$idx]->booking_date);
	}

	/**
	 * @param \DateTime $date_start
	 * @param \DateTime $date_end
	 *
	 * @return float
	 */
	private function calc_duration(\DateTime $date_start, \DateTime $date_end) {
		return round( (($date_end->getTimestamp() - $date_start->getTimestamp()) / 60 / 60), 2 );
	}

	/**
	 * Convert appointment date from WordPress format to CRM
	 *
	 * @param \DateTime $date
	 *
	 * @return string
	 */
	private function date_to_crm_format(\DateTime $date) {
		$config_options = get_option('onecrm_p_options');
		Locale::Instance($config_options["locale"]);

		$formatted = Locale::format_datetime(
			get_gmt_from_date($date->format('Y-m-d H:i:s')),
			' ',
			'GMT'
		);

		return $formatted;
	}
}