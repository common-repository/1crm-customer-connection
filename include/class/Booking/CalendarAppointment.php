<?php
/**
 * Get Booking Calendar Data
 */

namespace OneCRM\Portal\Booking;


class CalendarAppointment {

	/**
	 * @param string $date_from - modification date from (format: Y-m-d H:i:s)
	 * @param string $date_to - modification date to (format: Y-m-d H:i:s)
	 *
	 * @return array
	 */
	public function get_list($date_from, $date_to) {
		$params = [
			'wh_booking_type' => '',
			'wh_approved' => '',
			'wh_booking_id' => '',
			'wh_is_new' => '',
			'wh_pay_status' => 'all',
			'wh_keyword' => '',
			'wh_booking_date' => '3', //3 - all
			//'wh_booking_date2' => '',
			'wh_modification_date' => $date_from,
			'wh_modification_date2' => $date_to,
			'wh_cost' => '',
			'wh_cost2' => '',
			'or_sort' => get_bk_option('booking_sort_order'),
			'page_num' => '1',
			'wh_trash' => 'any', // '' | trash | any
			//'limit_hours' => '0,24',
			'only_booked_resources' => 0,
			'page_items_count' => '100000'
		];

		$list = wpbc_api_get_bookings_arr($params);
		$result = [];

		if ($list && $list['bookings_count'] > 0)
			$result = $list['bookings'];

		return $result;
	}

	/**
	 * Return booking plugin currency
	 *
	 * @return string
	 */
	public function get_currency() {
		return wpbc_get_currency();
	}

	/**
	 * Convert stored Payment status to presentation value
	 *
	 * @param string $status - appointment object status
	 *
	 * @return string
	 */
	public function get_payment_status_title($status) {
		if (function_exists('wpdev_bk_get_payment_status_simple')) {
			return wpdev_bk_get_payment_status_simple($status);
		} else{
			if (is_numeric($status) || $status == '') {
				return __('Unknown' ,'booking');
			} else {
				return ucwords($status);
			}
		}
	}
}