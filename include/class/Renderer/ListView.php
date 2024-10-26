<?php

/**
 * @package  OneCRMPortalRenderer
 */

namespace OneCRM\Portal\Renderer;

// Renders multiple models into a list of rows without labels.
// ie <div class=listview><div class=listview-row> ... </div> ... </div>
// see assets/admin.css listview section
class ListView extends HTML {

	/*
	 * Renders an array of models to HTML
	 * 
	 * @param $data [ "rows"=>[model,...], "fields"=>["field","names"], "fields_meta"=>metadata ]
	 * 
	 * can also contain args for render_field()
	 * 
	 * @return string HTML markup
	 */

	public function render($data) {
		$match = ["rows" => 1, "fields" => 1, "fields_meta" => 1];
		$this->field_options = (array_diff_key($data, $match)) + $this->field_options;
		$html = $header = [];
		$fields = $data["fields"];
		// produce the header data:
		foreach ($fields as $key => $value) {
			if (is_numeric($key)) {
				$key = $value;
			}
			if ($title = $this->get_vname($key, $data["rows"][0], $data["fields_meta"])) {
				$header[$key] = $title;
			}
		}
		// filter out fields we can't render:
		$fields = array_intersect_key($fields, $header);
		if (!count($fields)) {
			$fields = array_keys($header);
		}

		// iterate over rows:
		foreach (array_keys($data["rows"]) as $row) {
			// iterate over columns:
			foreach ($fields as $cell) {
				$cls = "";
				if (!is_array($cell)) {
					$cls = "col-" . $cell;
				}
				@$html[$row] .= "<td class=\"$cls\">";
				// check for multiple fields in a cell:
				if (is_array($cell)) {
					$content = [];
					foreach ($cell as $subcell_key => $subcell) {
						// check for multiple fields in a sub-cell:
						if (is_array($subcell)) {
							// combine multiple fields into a single sub-cell:
							$join = [];
							$with = ", ";
							if (isset($subcell["join"])) {
								$with = $subcell["join"];
								unset($subcell["join"]);
							}
							foreach ($subcell as $field) {
								$join[] = $data["rows"][$row][$field];
							}
							// patch $row[$subcell_key] then render it using its type:
							$content[] = $this->render_field(
								$subcell_key, [$subcell_key => implode($with, $join)] + $data["rows"][$row], $data["fields_meta"]
							);
						} else {
							// render a sub-cell type:
							$content[] = $this->render_field($subcell, $data["rows"][$row], $data["fields_meta"]);
						}
					}
					@$html[$row].=implode('<br>', $content);
				} else {
					// render a type as full cell content:
					@$html[$row].=$this->render_field($cell, $data["rows"][$row], $data["fields_meta"]);
				}
				@$html[$row] .= "</td>";
			}
		}

		foreach ($header as $key=>&$value){
			$type = @$data["fields_meta"]["fields"][$key]["type"];
			$value="<th class=\"col-$key col-type-$type\"><div>$value</div></th>";
		}
		unset($value);

		if (count($html)) {
			$result = '<table class="listview '.$this->field_options['model'].'"><thead><tr>' .
				implode(PHP_EOL, $header) .
				'</tbody><tbody><tr>' .
				implode(PHP_EOL . '<tr>', $html) .
				'</table>' . PHP_EOL;
		} else {
			$result = '<p>' . __('No records to display', ONECRM_P_TEXTDOMAIN) . '</p>';
		}
		return $result;
	}

	/*
	 * Picks localized display label for a column from the model
	 * 
	 * @param $field  field name to look up the localized display label
	 * @param array $row    model data
	 * @param array $meta   metadata for the model
	 * 
	 * returns false if the field doesn't exist in the row
	 * returns null if the field exists in the row but no label exists in the metadata
	 */

	protected function get_vname($field, $row, $meta) {
		if (!is_array($row)) return false;
		if (!(isset($row[$field]) || array_key_exists($field, $row)))
			return false;
		return onecrm_get_default($meta["fields"][$field], "vname_list", onecrm_get_default($meta["fields"][$field], "vname"));
	}

}
