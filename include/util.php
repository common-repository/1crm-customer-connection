<?php
if (!defined('ONECRM_P_ALLOWABLE_TAGS')){
	define('ONECRM_P_ALLOWABLE_TAGS',
		'<img><br><hr><b><i><a><em><strong>' .
		'<div><pre><p><h1><h2><h3><h4><h5><h6><span>' .
		'<table><thead><tbody><tr><th><td><ul><ol><li>' .
		'<header><nav><section><article><aside><footer>' .
		'<details><figure><meter><mark><progress><time><wbr>'
	);
}
if (!defined ('ONECRM_HTML_SANITIZER_FAILURE_MESSAGE')){
	define('ONECRM_HTML_SANITIZER_FAILURE_MESSAGE', "********");
}


if (!function_exists('onecrm_get_default')) {
	function onecrm_get_default($arr, $key, $default = null) {
		if (!is_array($arr))
			return $default;
		return isset($arr[$key]) ? $arr[$key] : $default;
	}
}

if (!function_exists('onecrm_signed_request')) {
	function onecrm_signed_request($req, $token = null) {
		if (!$token)
			$token = wp_get_session_token();
		$value = http_build_query($req);
		$value = base64_encode($value);
		$signature = sha1($token . $value);
		return [$value, $signature];
	}
}

if (!function_exists('onecrm_html_sanitizer')) {
	/**
	 * @param string $html
	 * @param string $allowed_tags
	 * @return string
	 */
	function onecrm_html_sanitizer($html, $allowable_tags = ONECRM_P_ALLOWABLE_TAGS) {
		require_once (ONECRM_P_PATH.'/vendor/autoload.php');

		$safe = new HTML_Safe();
		for ($fail = 0; $fail < 1; $fail ++) {
			for ($count = 0; $count < 4; $count++) {
				$out = $safe->parse($html);
				$out = strip_tags($out, $allowable_tags);
				if ($html === $out) break 2;
				$html = $out;
			}
			return ONECRM_HTML_SANITIZER_FAILURE_MESSAGE;
		}
		return $out;
	}
}
if (!function_exists('onecrm_html_sanitizer_r')) {
	/**
	 * @param mixed $data
	 * @param string $allowed_tags
	 * @return string
	 */
	function onecrm_html_sanitizer_r($data, $allowable_tags = ONECRM_P_ALLOWABLE_TAGS){
		if (!(is_array($data) || is_object($data))){
			return onecrm_html_sanitizer($data, $allowable_tags);
		} else {
			foreach($data as $key => &$value) $value = onecrm_html_sanitizer_r($value);
		}
		return $data;
	}
}



if (!function_exists('onecrm_format_traceback')) {
	/**
	 * args by name supported. To produce a full dump for an Exception:
	 * onecrm_format_traceback(['trace'=>$exception,'args'=>true]);
	 *
	 * @param string $message of the error
	 * @param int $code of the error
	 * @param Exception|array $trace to dump
	 * @param true|false|string $args - see onecrm_debug_var()
	 * @param string $class name
	 * @param bool|array $replace shorten path strings or pass an assoc array of replacements, or false to skip
	 * @param bool $html format for browser
	 * @param bool $numbers add line numbers to the backtrace
	 * @param bool $return
	 * @param array|null $skip_files
	 * @return null|string
	 */
	function onecrm_format_traceback($message = null, $code = null, $trace = null, $args = false, $class = null, $replace = true, $html = true, $numbers = true, $return = false, $skip_files = null, $show_class_file = false)
	{
		if (!$skip_files) $skip_files = [
			'wp-includes/shortcodes.php' => 15,
		];

		if (is_array($message)) {
			extract($message, EXTR_OVERWRITE);
			if (is_array($message)) $message = NULL;
		}
		$out = "<pre class='error'>";
		if (is_subclass_of($trace, 'Exception')) {
			/** @var Exception $trace */
			$message = $trace->getMessage();
			$code = $trace->getCode();
			$class = get_class($trace);
			$frame = [ // fix for missing top frame:
				'file' => $trace->getFile(),
				'line' => $trace->getLine(),
				'function' => '',
				'args' => [$message, $code],
				'class' => $class,
			];
			$trace = array_merge([$frame], $trace->getTrace());
		} elseif (!$trace) {
			$trace = debug_backtrace();
			echo "<p>$class $code $message</p>";
		}

		if (strtolower($args) === 'false') $args = false;
		if ($args && $args < 24) $args = true;
		$caller = [];
		$skip = 0;
		foreach ($trace as $lineNo => $call) {
			if ($skip && $skip--) continue;
			foreach ($skip_files as $skip_file => $skip_lines) {
				if (substr($call['file'], -strlen($skip_file)) === $skip_file) {
					$skip = $skip_lines;
					continue;
				}
			}
			$f = $call['file'];
			if ($args && count((array)$call['args']))
				$append = '(' . htmlspecialchars(onecrm_debug_var(['format' => 'ARGS', 'args' => $args, 'value' => $call['args'], 'replace' => false, 'html' => false])) . ')';
			else $append = '';
			if (isset($call['class'])) {
				$f = $show_class_file ? ($f.':') : '';
				$f .= '<b>'.$call['line'].':'.$call['class'].'</b>';
				$caller[$lineNo] = "$f{$call['type']}{$call['function']}$append";
			} else {
				if (in_array($call['function'], array('do_action', 'apply_filters'))) {
					$caller[$lineNo] = "$f:{$call['line']}:{$call['function']}('{$call['args'][0]}')";
				} elseif (in_array($call['function'], array('include', 'include_once', 'require', 'require_once'))) {
					$caller[$lineNo] = "$f:{$call['line']}:{$call['function']} ↖";
				} else {
					$caller[$lineNo] = "$f:{$call['line']}:{$call['function']}$append";
				}
			}
			if ($numbers) $caller[$lineNo] = "#$lineNo " . $caller[$lineNo];
		}
		$out .= implode(PHP_EOL, $caller) . "</pre>\n";
		if ($replace) $out = onecrm_debug_var(['format' => 'STRING', 'value' => $out, 'replace' => true, 'html' => false]);

		if (!$html) $out = htmlspecialchars_decode(strip_tags($out));

		if ($return) {
			return $out;
		} else {
			echo $out;
			return null;
		}
	}
}

if (!function_exists('onecrm_debug_var')) {
	/**
	 * formats values for display and shortens common path strings to keep it readable
	 * Exceptions generate a traceback, objects and non-uniform arrays produce JSON
	 * 2D array with uniform keys on first 2 rows will be formatted as table with first row keys as header
	 *
	 * if format=ARGS and $args is numeric then 24..$args chars of JSON-encoded args will be shown
	 * if format=ARGS and $args not numeric a tidy version of var_export() will be used for args
	 *
	 * @param string $format in AUTO | STRING | TABLE | EXCEPTION | JSON | PHP | ARGS
	 * @param mixed $value
	 * @param bool|array $replace shortens common path strings
	 * @param bool $html adds pre tags to STRING, JSON, PHP
	 * @param string $class property of TABLE
	 * @param bool $index true adds root index as left heading value in 2D table
	 * @param bool $args EXCEPTION shows caller args
	 * @return string
	 */
	function onecrm_debug_var($format = 'AUTO', $value = null, $replace = true, $html = true, $class = 'onecrm_format_var', $index = false, $args = false, $show_class_file=false)
	{
		if (is_array($format)) {
			extract($format, EXTR_OVERWRITE);
		}
		if (is_array($format) || ! $format) $format = 'AUTO';

		$defaults = [
			ONECRM_P_PATH . '/vendor/onecrm/api/src/' => '<b>/P/V/1/</b>',
			ONECRM_P_PATH . '/' => '<b>/P/</b>',
			WP_CONTENT_DIR . '/themes/twentyseventeen/' => '<b>/W/C/T/17/</b>',
			WP_CONTENT_DIR . '/themes/twentysixteen/' => '<b>/W/C/T/16/</b>',
			WP_CONTENT_DIR . '/themes/twentyfifteen/' => '<b>/W/C/T/15/</b>',
			WP_CONTENT_DIR . '/themes/' => '<b>/W/C/T/</b>',
			WP_CONTENT_DIR . '/' => '<b>/W/C/</b>',
			ABSPATH . 'wp-includes/' => '<b>/W/I/</b>',
			ABSPATH => '<b>/W/</b>',
		];

		$format = strtoupper($format);
		if (is_array($value)) {
			$keys = array_keys($value);
			if (count($keys) > 1 && is_array($value[$keys[0]]) && is_array($value[$keys[1]])
				&& ($head_keys = array_keys($value[$keys[0]])) && $head_keys == array_keys($value[$keys[1]])) {
				$heading = true;
			}
		}
		if ($format === "AUTO") {
			if (is_object($value) && is_subclass_of($value, 'Exception')) {
				$format = "EXCEPTION";
			} elseif (is_array($value) && @$heading) {
				$format = "TABLE";
			} elseif (is_string($value) || is_numeric($value)) {
				$format = 'STRING';
			} else {
				$format = 'PHP';
			}
		}

		switch (strtoupper($format)) {
			case "EXCEPTION":
				return onecrm_format_traceback(['trace' => $value, 'args' => $args,
					'return' => true, 'show_class_file' => $show_class_file]);
			case "TABLE":
				$table = ["<table class=\"$class\">"];
				if (@$heading) {
					$value = ['​​​' => $head_keys] + $value; // utf-8 &#8203; x2
					$table[0] .= '<thead>';
				} else {
					$heading = false;
					$index = true;
					$table[0] .= '<tbody>';
				}
				$rowNum = 0;
				foreach ($value as $k => $v) {
					$row = array_values((array)$v);
					if (@$index) $row = [-1 => $k] + $row;
					$colNum = 0;
					$tableRow = "<tr>";
					foreach ($row as $cell) {
						$tableRow .= (($index && $colNum == 0) || ($heading && $rowNum == 0)) ? '<th>' : '<td>';
						$tableRow .= htmlspecialchars($cell);
						$colNum++;
					}
					$rowNum++;
					$table[] = $tableRow;
				}
				if ($heading) $table[1] .= '</thead><tbody>';
				$value = implode(PHP_EOL, $table) . '</tbody></table>';
				break;
			case "PHP":
				$value = var_export($value, true);
				break;
			case "ARGS": // used for backtrace args
				if (is_numeric($args)) {
					if ($args<24) $args = 24;
					$cooked = [];
					foreach ($value as $raw) {
						$tmp = substr(json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, $args + 1);
						if (strlen($tmp) == ($args + 1)) $tmp = substr($tmp, 0, ($args - 3)) . '...';
						$cooked[] = $tmp;
					}
					$value = implode(', ', $cooked);
				} else {
					$value = substr(var_export($value, true), 8, -3);
					$value = preg_replace("/=> \n *array [(]/s", '=> [', $value);
					$value = preg_replace("/,\n *[)],\n/s", "],\n", $value);
				}
				break;
			case "JSON":
				$value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
				break;
			DEFAULT: // STRING
				break;
		}
		if ($format !== 'TABLE' && $html) {
			$value = '<pre>' . htmlspecialchars($value) . '</pre>';
		}
		if ($replace) {
			if (is_array($replace)) {
				$overlap = array_intersect_key($replace, $defaults);
				$replace = $overlap + $defaults + $replace;
			} else {
				$replace = $defaults;
			}
			$value = str_replace(array_keys($replace), $replace, $value);
		}
		return $value;
	}
}

if (!function_exists('onecrm_get_dashboard_permalink')) {

	/**
	 * Get Dashboard page permalink
	 *
	 * @return string
	 */
	function onecrm_get_dashboard_permalink(){
		global $wpdb;
		$query = "SELECT * FROM ".$wpdb->posts." WHERE `post_content` LIKE '%[onecrm_p_dashboard]%' AND `post_status` = 'publish'";
		$page = $wpdb->get_results ($query);
		$result = '';

		if ($page && isset($page[0]) && property_exists($page[0],'ID')) {
			$result = get_permalink($page[0]->ID);
		}

		return $result;
	}
}
