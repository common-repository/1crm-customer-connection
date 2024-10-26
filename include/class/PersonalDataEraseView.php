<?php

namespace OneCRM\Portal;

use OneCRM\Portal\Renderer\DetailView;

// Custom HTML Renderer for Personal Data Erase template
class PersonalDataEraseView extends DetailView {

	const COLUMNS_NUM = 2;

	/*
	 * renders a model to HTML
	 * 
	 * @param array $data [ "row"=>model, "fields"=>["field","names"]]
	 */
	public function render($data) {
		$match = ["row" => 1, "fields" => 1, "fields_meta" => 1];
		$other = (array_diff_key($data, $match));
		$this->field_options = array_diff_key($other, $this->detail_options) + $this->field_options;
		$this->detail_options = array_intersect_key($other, $this->detail_options) + $this->detail_options;

		$html = [];

		$html[] = '<h4>' . __('Check fields you want to erase', ONECRM_P_TEXTDOMAIN) . '</h4>';

		foreach ($data["fields"] as $field) {
			// skip fields we can't render:
			if (!(isset($data["fields_meta"]["fields"][$field]) && (isset($data["fields_meta"]["fields"][$field]["vname"]) ))) {
				continue;
			}
			$fdef = $data["fields_meta"]["fields"][$field];
			$cell_cls = '';
			if (isset($fdef['type']))
				$cell_cls .= 'cell-' . $fdef['type'];
			if ($this->check_format_input($field, $data["fields_meta"])){
				if (!empty($fdef['required'])) {
					$cell_cls .= ' required';
				}
			}

			$html[] = '<div class="cell ' . $cell_cls . '">' .
				'<label>'.
				'<input name="erase['.$fdef['name'].']" type="checkbox" checked="checked" value="1">&nbsp;' .
				$fdef["vname"] . 
				'</label>' .
				'</div>';

			$html[] = '<div class="cell ' . $cell_cls . '">' .
				$this->render_field($field, $data["row"], $data["fields_meta"]) .
				'</div>';

		}

		$post = get_post();

		return $this->create_hidden_input('ret', $post->ID).
			"<div class=\"detailview {$this->column_class_name[self::COLUMNS_NUM]}\"><div>" .
			implode($html) . '</div></div>';
	}


}
