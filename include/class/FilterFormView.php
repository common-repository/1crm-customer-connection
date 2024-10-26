<?php

namespace OneCRM\Portal;

use OneCRM\Portal\Renderer\DetailView;

// Custom HTML Renderer for List Filter Form template
class FilterFormView extends DetailView {

	static $filters_counter;

	public function __construct($options) {
		parent::__construct($options);
		self::$filters_counter = 0;
	}

	/*
	 * renders a model to HTML
	 * 
	 * @param array $data [
	 *  "filters"=>model filters
	 *  "input"=>user input,
	 *  "def"=>filters definition
	 *  "model"=>model name
	 *  "page_url" => current list page url
	 * ]
	 */
	public function render($data) {
		$input = isset($data['input']['filters']) ? $data['input']['filters'] : [];
		$current_url = $data['page_url'];

		list($req, $signature) = onecrm_signed_request([
			'model' => $data['model'],
			'id' => 'filter_form',
			'return' => $current_url,
		]);

		$html[] = "<div id='list-filters' class='list-filters'><form id='list-filter-form' class='list-filter-form' method='post' action='{$current_url}'>";
		$html[] = $this->create_hidden_input('request', $req);
		$html[] = $this->create_hidden_input('token', $signature);

		foreach ($data['filters'] as $item) {
			if (! isset($data['def'][$item]))
				continue;

			$def = $data['def'][$item];

			if ($def['type'] != 'date')
				static::$filters_counter ++;

			if ($this->is_verified_request($data['input'])) {
				$value = isset($input[$item]) ? $input[$item] : null;
			} else {
				$value = null;
			}

			if ($def['type'] == 'unified_search') {
				$def['type'] = 'text';
				$html[] = $this->render_text($item, $def['vname'], $value);
			} elseif ($def['type'] == 'flag') {
				$html[] = $this->render_flag($item, $def['vname'], (bool)$value);
			} elseif ($def['type'] == 'date') {
				$start = isset($input[$item .'-start']) ? $input[$item .'-start'] : null;
				$end = isset($input[$item .'-end']) ? $input[$item .'-end'] : null;
				$html[] = $this->render_date_range($item, $def['vname'], $start, $end);
			} else {
				$html[] = $this->render_text($item, $def['vname'], $value);
			}
		}

		$filter_button_style = "";


		if (! empty($data['create_button'])) {
			$html[] = $data['create_button'];
			$filter_button_style = "style='margin-right: 5px;'";
		}

		if (self::$filters_counter > 0)
			$html[] = "<button class='onecrm-p-create button' {$filter_button_style} onclick=\"return OneCRM.Portal.App.FilterForm.submit(true);\">" . __('Filter', ONECRM_P_TEXTDOMAIN) . "</button>";

		$html[] = "</form></div>";

		return implode($html);
	}

	private function render_date_range($name, $label, $start, $end) {
		$value1 = Locale::format_date($start,'UTC');
		$value2 = Locale::format_date($end,'UTC');

		$result = "<div>";
		$result .= '<input name="filters['.$name.'-start]" type="text" class="date" value="'.$value1.'" placeholder="'.$label.' From">';
		$result .= '<input name="filters['.$name.'-end]" type="text" class="date" style="margin-left: 10px;" value="'.$value2.'" placeholder="'.$label.' To">';
		$result .= "</div>";

		return $result;
	}

	private function render_text($name, $label, $value) {
		$html = '<input type="text" class="text" name="filters['.$name.']" '.$this->get_filter_style().' value="'.$value.'" placeholder="'.$label.'">';

		return $html;
	}

	private function render_flag($name, $label, $checked) {
		$check = '';
		if ($checked)
			$check = 'checked="checked"';

		$html = '<label for="'.$name.'" '.$this->get_filter_style().'><input name="filters['.$name.']" type="checkbox" '.$check.' value="1">';
		$html .= $label . '</label>';

		return $html;
	}

	private function is_verified_request($input) {
		$token = wp_get_session_token();
		return sha1($token . $input['request']) == $input['token'];
	}

	private function get_filter_style() {
		$style = 'style="margin-top: 5px;';

		if (self::$filters_counter > 1)
			$style .= ' margin-left: 10px;';

		$style .= '"';

		return $style;
	}
}
