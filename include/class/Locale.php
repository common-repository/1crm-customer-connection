<?php

namespace OneCRM\Portal;

final class Locale {

	private static $locale_data;

	/**
	 * @param $locale_data  : OneCRM\APIClient\Client->get('/meta/locale')
	 * @return Locale       : Singleton instance of Locale
	 */
	public static function Instance($locale_data) {

		static $single = null;

		if (is_null($single)) {
			$single = new Locale($locale_data);
		}
		return $single;
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public static function get($key) {
		return Locale::$locale_data[$key];
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 */
	public static function set($key, $value) {
		Locale::$locale_data[$key] = $value;
	}

	/**
	 * @param $number
	 * @return string
	 */
	public static function format_number($number) {
		return number_format(
			$number, Locale::$locale_data["number_format"]["significant_digits"], Locale::$locale_data["number_format"]["dec_sep"], Locale::$locale_data["number_format"]["grp_sep"]
		);
	}

	/**
	 * NOTE: we're not using NumberFormatter::FormatCurrency using ISO4217 Code
	 *		 together with Locale::setDefault using BCP47 Country Code
	 *		 Instead we follow user preferences set in 1CRM app
	 *
	 * @param mixed $amt :  numerical value
	 * @param string $sym :  "name", "iso4217", "symbol", or "none"
	 * @param boolean $dash :  if true, zero amount will be rendered as em-dash entity *only*
	 * @param string $tpl :  may contain any of following template variables:
	 *				  {amount} {name} {iso4217} {symbol}
	 * @return string
	 */

	public static function format_currency($amt, $sym = "symbol", $dash = true, $tpl = "") {
		if ($amt == 0 && $dash) {
			return "&mdash;";
		}
		$amount = number_format(
			$amt, Locale::$locale_data["base_currency"]["significant_digits"], Locale::$locale_data["number_format"]["dec_sep"], Locale::$locale_data["number_format"]["grp_sep"]
		);
		if ($tpl) {
			return str_replace(
				["{amount}", "{name}", "{iso4217}", "{symbol}"], [
					$amount,
					Locale::$locale_data["base_currency"]["name"],
					Locale::$locale_data["base_currency"]["iso4217"],
					Locale::$locale_data["base_currency"]["symbol"],
				], $tpl
			);
		} elseif (Locale::$locale_data["base_currency"]["symbol_place_after"]) {
			return $amount . Locale::$locale_data["base_currency"][$sym];
		} else {
			return Locale::$locale_data["base_currency"][$sym] . $amount;
		}
	}

	/**
	 * @param $expression       : parseable by strtotime()
	 * @param string $format    : time format string  parseable by date() or 'SQL' for MySQL format
	 * @param string $tz_in     : ie GMT or America/vancouver
	 * @param string $tz_out    : empty/null for local time.
	 * @return string		   : formatted time string or empty string on error.
	 */

	public static function normalize_datetime($expression, $format='C',$tz_in='',$tz_out=''){
		if ($expression=='') return '';
		if ($format=='SQL') {
			$format="Y-m-d H:i:s";
			$tz_out='GMT';
		}
		if (!$tz_out)
			$tz_out = @self::$locale_data['timezone'];
		try {
			$dateTime = new \DateTime($expression,$tz_in? new \DateTimeZone($tz_in):null);
			if ($tz_out) $dateTime->setTimezone(new \DateTimeZone($tz_out));
			return $dateTime->format($format);
		} catch (\Exception $e) {
			return '';
		}
	}

	/**
	 * @param string $expression    : see normalize_datetime()
	 * @param string $join		  : string to join date with time, ie " " or "<br>"
	 * @param string $tz_in
	 * @param string $tz_out
	 * @return string
	 */
	public static function format_datetime($expression = "now", $join = " ", $tz_in = '', $tz_out = '') {
		return self::normalize_datetime($expression,
			Locale::$locale_data["date_format"] . $join .
			Locale::$locale_data["time_format"], $tz_in, $tz_out
		);
	}

	/**
	 * @param string $expression    : see normalize_datetime()
	 * @param string $tz_in
	 * @param string $tz_out
	 * @return string
	 */
	public static function format_date($expression = "now", $tz_in = '', $tz_out = '') {
		return self::normalize_datetime($expression,
			Locale::$locale_data["date_format"], $tz_in, $tz_out
		);
	}

	/**
	 * @param string $expression    : see normalize_datetime()
	 * @param string $tz_in
	 * @param string $tz_out
	 * @return string
	 */
	public static function format_time($expression = "now", $tz_in = '', $tz_out = '') {
		return self::normalize_datetime($expression,
			Locale::$locale_data["time_format"], $tz_in, $tz_out
		);
	}

	/**
	 * Locale constructor.
	 * @param $locale_data
	 */
	private function __construct($locale_data) {
		Locale::$locale_data = $locale_data;
	}

	private function __clone() {
		
	}

	private function __sleep() {
		
	}

	private function __wakeup() {
		
	}

}
