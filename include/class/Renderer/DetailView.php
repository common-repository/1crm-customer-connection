<?php

/**
 * @package  OneCRMPortalRenderer
 */

namespace OneCRM\Portal\Renderer;

// generates simple HTML matrix view of model data with label in each cell
class DetailView extends HTML {

	public $detail_options = ["columns" => 2, "labels_on_top" => True];
	public $column_class_name = [ // used to look up class name from $options["columns"]
		"", "one_column", "two_column", "three_column", "four_column", "five_column", "six_column"
	];

	/*
	 * Constructor
	 * 
	 * @param array $options [ "columns"=>int (2), "labels_on_top"=>boole ]
	 * options can also contain persistent args for render_field()
	 */

	public function __construct($options) {
		$this->detail_options = array_intersect_key($options, $this->detail_options) + $this->detail_options;
		parent::__construct(array_diff_key($options, $this->detail_options));
	}

	/*
	 * renders a model to HTML
	 * 
	 * @param array $data [ "row"=>model, "fields"=>["field","names"], "fields_meta"=>metadata ]
	 * options can also contain persistent args for render_field()
	 */

	public function render($data) {
		$match = ["row" => 1, "fields" => 1, "fields_meta" => 1];
		$other = (array_diff_key($data, $match));
		$this->field_options = array_diff_key($other, $this->detail_options) + $this->field_options;
		$this->detail_options = array_intersect_key($other, $this->detail_options) + $this->detail_options;

		$html = [];
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
				'<label>' . $fdef["vname"] . '</label>' .
				$this->render_field($field, $data["row"], $data["fields_meta"]) .
				'</div>';
		}

		return "<div class=\"detailview {$this->column_class_name[$this->detail_options["columns"]]}\"><div>" .
			implode($html) . '</div></div>';
	}

}
