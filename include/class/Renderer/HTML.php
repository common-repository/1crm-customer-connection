<?php

/**
 * @package  OneCRMPortalRenderer
 *
 * TODO:
 *	1 https://codepen.io/vkjgr/pen/Bfcae for "required" fields
 *	2 client-side validation (even just simple match regex)
 */

namespace OneCRM\Portal\Renderer;
use OneCRM\Portal\Locale;

class HTML {

	public $field_options = [
		'link_template' => '/model/{MODEL}/{ID}',
		'link_template_data' => NULL, // callable f($model,$id,$row)
		'link_template_enclose' => array('{', '}'),
		'new_tab' => False,
		'model' => NULL, // missing in metadata, needed for callbacks
		'detail_available' => false, // true|false|callable f($model,$row){ return true|false; }
		'download_url' => false, // false|callable f($model_name, $id){ return string|null; }
		'format_input' => [], // format these fields as inputs
		'format_input_prefix' => '', // prepended to input name attribute ie 'Model.'
		'format_input_postfix' => '', // appended to input name attribute ie '[]'
		'allowed_tags'=> '<br><hr><p><b><i><a><img><em><strong><span><ul><ol><li><pre>',
	];

	/*
	 * This maps the meta $type to a render method and a display class
	 * 'class' provides the styles/icons for progress, links, phone etc.
	 */

	protected $LUT = [
		'varchar' => ['format' => 'varchar', 'class' => 'text'],
		'name' => ['format' => 'name', 'class' => 'link'], // handled by ref indirectly
		'phone' => ['format' => 'varchar', 'class' => 'phone'],
		'fax' => ['format' => 'varchar', 'class' => 'phone'],
		'url' => ['format' => 'url', 'class' => 'link'],
		'int' => ['format' => 'varchar', 'class' => 'number'],
		'number' => ['format' => 'varchar', 'class' => 'number'],
		'decimal' => ['format' => 'varchar', 'class' => 'number'],
		'date' => ['format' => 'date', 'class' => 'date'],
		'datetime' => ['format' => 'datetime', 'class' => 'datetime'],
		'bool' => ['format' => 'bool', 'class' => 'bool'],
		'currency' => ['format' => 'currency', 'class' => 'currency'],
		'base_currency' => ['format' => 'currency', 'class' => 'currency'],
		'raw_currency' => ['format' => 'currency', 'class' => 'currency'],
		'double' => ['format' => 'float', 'class' => 'float'],
		'text' => ['format' => 'text', 'class' => 'text'],
		'email' => ['format' => 'email', 'class' => 'mailto'],
		'progress' => ['format' => 'percent', 'class' => 'progress'],
		'percent' => ['format' => 'percent', 'class' => 'percent'],
		'enum' => ['format' => 'enum', 'class' => 'enum'],
		'status' => ['format' => 'status', 'class' => 'status'],
		'multienum' => ['format' => 'multienum', 'class' => 'enum'],
		'ref' => ['format' => 'ref', 'class' => 'link'],
		'html' => ['format' => 'html', 'class' => 'html'],
		// for type id, render the 'for_ref' instead:
		'id' => ['format' => 'id', 'class' => 'link'],
		// for these, render as varchar for now:
		'sequence_number' => ['format' => 'varchar', 'class' => 'number'],
		'item_number' => ['format' => 'varchar', 'class' => 'number'],
		'age' => ['format' => 'datetime', 'class' => 'datetime'],
		'status_color' => ['format' => 'varchar', 'class' => 'text'],
		'reminder' => ['format' => 'enum', 'class' => 'enum'],
		'file_ref' => ['format' => 'file_ref', 'class' => 'text'],
		// TODO: unhandled types mapped to varchar:
		'image' => ['format' => 'varchar', 'class' => 'image'],
		'star_rating' => ['format' => 'varchar', 'class' => 'text'],
		'duration' => ['format' => 'varchar', 'class' => 'text'],
		'formula' => ['format' => 'varchar', 'class' => 'text'],
		'_custom' => ['format' => '_custom', 'class' => 'link'],
	];

	/*
	 * Constructor
	 *
	 * @param array $options [ link_template, link_template_data ]
	 * options include these and possible more:
	 * link_template, string, ie. "/model/[MODEL]/[ID]"
	 * link_template_data, callable(field,model,meta) returns array of variables
	 * new_tab, boolean, if true link will have target=”_blank”
	 * detail_available, callable(model), if true ref is a link otherwise plaintext.
	 */

	public function __construct($options) {
		$this->field_options = (array) $options + $this->field_options;
	}

	/*
	 * renders a field from a model to HTML
	 *
	 * @param $field name from the model to render as HTML
	 * @param $model row returned from 1CRM API
	 * @param $meta  metadata returned from 1CRM API
	 */

	public function render_field($field, $model, $meta) {
		if (!isset($meta["fields"][$field])) return NULL;
		$type = @$meta["fields"][$field]["type"];
		if(($field == 'date_due' || $field == 'date_start') && $type == 'datetime') {
			$type = 'date';
		}
		if (!isset($this->LUT[$type])) $type = "varchar";
		$format = $this->LUT[$type]["format"];
		$class = $this->LUT[$type]["class"];
		if (isset($meta["fields"][$field]["class"])) $class.=' '.$meta["fields"][$field]["class"];
		return call_user_func_array([$this, "render_field_$format"], [$field, $model, $meta, $class]);
	}

	/* used to create unique IDs to map labels to inputs */
	private static final function input_uid(){
		static $unique=0;
		return 'u'.($unique++);
	}

	/*
	 * The model field related rendering methods:
	 *
	 * @param $field: the field name/index
	 * @param $model: hash of field names to values
	 * @param $meta: model metadata
	 * @param $class: classname for the top-level tag
	 *
	 * returns HTML markup
	 */

	protected function render_field_varchar($field, $model, $meta, $class) {
		$value = htmlspecialchars($model[$field]);

		if ($this->check_format_input($field,$meta)){
			$name = $this->create_input_name($field);
			return "<input name=\"$name\" type=\"text\" class=\"$class\" value=\"$value\">";
		} else {
			return "<span class=\"$class\">$value</span>";
		}
	}

	protected function render_field_file_ref($field, $model, $meta, $class) {
		if ($this->check_format_input($field,$meta)){
			$name = $this->create_input_name($field);
			return "<input name=\"$name\" type=\"file\" class=\"$class\">";
		} else {
			$value = htmlspecialchars(basename($model[$field]));
			if (!empty($this->field_options["model"])) {
				if (is_callable($this->field_options['download_url'])) {
					$url = call_user_func($this->field_options['download_url'], $this->field_options["model"], $model['id']);
					return "<a target=\"_blank\" href=\"$url\" class=\"$class\">$value</a>";
				}
			}
			return "<span class=\"$class\">$value</span>";
		}
	}

	protected function render_field__custom($field, $model, $meta, $class) {
		if (empty($this->field_options['custom_render']) || !is_callable($this->field_options['custom_render'])) return '';
		$render = $this->field_options['custom_render'];
		return $render($field, $model, $meta);
	}

	protected function render_field_url($field, $model, $meta, $class) {
		$text = $link = htmlspecialchars($model[$field]);
		if ($this->check_format_input($field,$meta)){
			$name = $this->create_input_name($field);
			return "<input name=\"$name\" type=\"URL\" class=\"$class\" value=\"$text\">";
		} else
			switch (substr($link, -4)) {
				case ".png":
				case ".jpg":
				case ".gif":
					$text = '<img src="' . $link . '">';
					$class = 'url-image';
				DEFAULT: break;
			}
		return "<a href=\"$link\" class=\"$class\">$text</a>";
	}

	protected function render_field_id($field, $model, $meta, $class) {
		if (@$for_ref = $meta["fields"][$field]["for_ref"]) {
			// pass off to type ref if possible:
			return $this->render_field_ref($for_ref, $model, $meta, $class);
		} else {
			// otherwise don't show it:
			return false;
		}
	}

	protected function render_field_name($field, $model, $meta, $class) {
		if ($this->check_format_input($field,$meta)){
			$value = htmlspecialchars($model[$field]);
			$name = $this->create_input_name($field);
			return "<input name=\"$name\" type=\"text\" class=\"$class\" value=\"$value\">";
		} else {
			// make the "name" type render as its ref link:
			if (!@$this->field_options["model"])
				return $this->render_field_varchar($field, $model, $meta, $class);
			$meta["fields"][$field]+=["bean_name" => $this->field_options["model"]];
			$model["name_id"] = $model["id"];
			// pass off type name to type ref:
			return $this->render_field_ref($field, $model, $meta, $class);
		}
	}

	protected function render_field_ref($field, $model, $meta, $class) {
		$value = htmlspecialchars($model[$field]);
		if (@$meta["fields"][$field]["detail_link"] && (
		    $this->field_options['detail_available']===true || (
		        is_callable($this->field_options["detail_available"]) &&
		call_user_func($this->field_options["detail_available"], @$meta["fields"][$field]["bean_name"],$model))))
		{
			for (;;) {
				$data = ["MODEL" => @$meta["fields"][$field]["bean_name"]];
				// try to guess it:
				if (isset($model[$field . "_id"])) {
					$data["ID"] = $model[$field . "_id"];
				} else {
					// brute-force lookup:
					foreach (array_keys($meta["fields"]) as $key) {
						if (@$meta["fields"][$key]["for_ref"] === $field) {
							$data["ID"] = $model[$key];
							break;
						}
					}
				}
				if (!empty($this->field_options["model"]) && !empty($this->field_options["id"])) {
					if ($this->field_options["model"] == $data['MODEL'] && $this->field_options["id"] == $data['ID'])
						break;
				}
				if (is_callable(@$this->field_options["link_template_data"])) {
					$data = call_user_func($this->field_options["link_template_data"], @$data["MODEL"], @$data["ID"],$model) + $data;
				}
				$URL = $this->render_link_template($this->field_options["link_template"], $data);
				return "<a href=\"$URL\" class=\"$class\">$value</a>";
			}
		}
		return "<span class=\"$class\">$value</span>";
	}

	protected function render_field_email($field, $model, $meta, $class) {
		$value = htmlspecialchars($model[$field]);
		if ($this->check_format_input($field,$meta)){
			$name = $this->create_input_name($field);
			return "<input name=\"$name\" type=\"email\" class=\"$class\" value=\"$value\">";
		} else {
			return "<a href=\"mailto:$value\" class=\"$class\">$value</a>";
		}
	}

	protected function render_field_date($field, $model, $meta, $class) {
		$value = Locale::format_date($model[$field],'UTC');
		if ($this->check_format_input($field,$meta)){
			$name = $this->create_input_name($field);
			return "<input name=\"$name\" type=\"text\" class=\"$class\" value=\"$value\">";
		} else {
			return "<span class=\"$class\">$value</span>";
		}
	}

	protected function render_field_datetime($field, $model, $meta, $class) {
		$value = Locale::format_datetime($model[$field], ' ', "UTC");
		if ($this->check_format_input($field,$meta)){
			$name = $this->create_input_name($field);
			return "<input name=\"$name\" type=\"text\" class=\"$class\" value=\"$value\">";
		} else {
			return "<span class=\"$class\">$value</span>";
		}
	}

	protected function render_field_bool($field, $model, $meta, $class) {
		if ($this->check_format_input($field,$meta)){
		$name = $this->create_input_name($field);
		$checked=$model[$field]?'checked=checked':'';
		return "<input type=hidden name=\"$name\">".
		"<input name=\"$name\" type=\"checkbox\" $checked value='1'>";
		} else {
		$label_h = $model[$field] ? "&#x2714;" : "&#x2718;";
		return "<span class=\"$class\">$label_h</span>";
		}
	}

	protected function render_field_currency($field, $model, $meta, $class) {
		$value = Locale::format_currency($model[$field]);
		if ($this->check_format_input($field,$meta)){
			$name = $this->create_input_name($field);
			return "<input name=\"$name\" type=\"email\" class=\"$class\" value=\"$value\">";
		} else {
			return "<span class=\"$class\">$value</span>";
		}
	}

	protected function render_field_float($field, $model, $meta, $class) {
		$value = Locale::format_number($model[$field]);
		if ($this->check_format_input($field,$meta)){
			$name = $this->create_input_name($field);
			return "<input name=\"$name\" type=\"number\" class=\"$class\" value=\"$value\">";
		} else {
			return "<span class=\"$class\">$value</span>";
		}
	}

	protected function render_field_text($field, $model, $meta, $class) {
		$value = htmlspecialchars($model[$field]);
		if ($this->check_format_input($field,$meta)){
			$name = $this->create_input_name($field);
			return "<textarea name=\"$name\" class=\"$class\">$value</textarea>";
		} else {
			// TODO: confirm replacing linefeeds vs using CSS white-space
			// replace linefeeds with breaks:
			$value = str_replace(["\n\r", "\r\n", "\r", "\n"], "<br>", $value);
			return "<span class=\"$class\">$value</span>";
		}
	}

	protected function render_field_percent($field, $model, $meta, $class) {
		// TODO: make the progress 0..limit from meta instead of 0..100%
		if (is_double($model[$field])) {
			$value = Locale::format_number($model[$field]);
		} else {
			$value = $model[$field];
		}
		if ($this->check_format_input($field,$meta)){
			$name = $this->create_input_name($field);
			return "<input name=\"$name\" type=\"email\" class=\"$class\" value=\"$value\">";
		} else {
			// the nested divs are for the progress bar, the span for the numerical report
			return "<div class=\"$class\"><span>$value%</span><div><div><div style=\"width:$value%\"></div></div></div></div>";
		}
	}

	protected function render_field_status($field, $model, $meta, $class) {
		$parts = explode(' - ', $model[$field]);
		if (!$this->check_format_input($field,$meta)) switch ($parts[0]) {
			case "New": $class.= " badge state-draft";
				break;
			case "Recycled":
			case "Assigned": $class.= " badge state-success";
				break;
			case "In Progress": $class.= " badge state-success";
				break;
			case "Planned":
			case "Pending": $class.= " badge state-pending";
				break;
			case "Pending Input": $class.= " badge state-pending";
				break;
			case "In Process": $class.= " badge state-neutral";
				break;
			case "Converted":
			case "Held":
			case "Closed" : $class.= " badge state-closed";
				break;
			case "Dead": $class.= " badge state-dead";
				break;
			case "Not Started": $class.= " badge state-draft";
				break;
			case "Deferred": $class.= " badge state-deferred";
				break;
			case "Active" :
				switch ($parts[1]) {
					case "New": $class.= " badge state-draft";
						break;
					case "Starting Soon": $class.= " badge state-pending";
						break;
					DEFAULT: $class.= " badge state-success";
						break;
				}
				break;
			DEFAULT: $class.= " badge state-neutral";
				break;
		} else $class="enum";

		// fall through to enum type:
		return $this->render_field_enum($field, $model, $meta, $class);
	}

	protected function render_field_enum($field, $model, $meta, $class) {
		$value = $model[$field];
		if ($this->check_format_input($field,$meta)){
			$name = $this->create_input_name($field);
			return $this->create_list_control($name, $value, $meta["fields"][$field]["options"], false,$class." list");
		} else {
			$label = $this->find_option_label($value, $meta["fields"][$field]["options"]);
			if (!$label) {
				$label=$value;
			}
			$label = htmlspecialchars($label);
			return "<span class=\"$class\">$label</span>";
		}
	}

	protected function render_field_multienum($field, $model, $meta, $class) {
		$values = explode('^,^', $model[$field]);
		if ($this->check_format_input($field,$meta)){
			$name = $this->create_input_name($field);
			return $this->create_list_control($name, $values, $meta["fields"][$field]["options"], true,$class." list");
		} else {
			$set = [];
			foreach ($values as $key) {
				$value = $this->find_option_label($key, $meta["fields"][$field]["options"]);
				if (!$value) {
					$value = $key;
				}
				$set[] = $value;
			}
			$value = htmlspecialchars(implode(', ', $set));
			return "<span class=\"$class\">$value</span>";
		}
	}

	protected function render_field_html($field, $model, $meta, $class) {
		$value = onecrm_html_sanitizer($model[$field], $this->field_options['allowed_tags']);
		return "<span class=\"$class\">$value</span>";
	}

	/*
	 * replaces the values into the template string
	 *
	 * @param $link_template        string ie "/model/{MODEL}/{$ID}"
	 * @param $link_template_data   assoc array ie ["MODEL"=>"Product","ID"=>"a1b2c3-..."]
	 */

	protected function render_link_template($link_template, $link_template_data) {
		foreach (array_keys($link_template_data) as $key) {
			$keys[$key] = $this->field_options["link_template_enclose"][0] .
				$key .
				$this->field_options["link_template_enclose"][1];
		}
		return str_replace($keys, $link_template_data, $link_template);
	}

	/*
	 * returns the matching label for an enum(ish) value
	 */

	protected function find_option_label($value, $options) {
		foreach ($options as $option) {
			if ($option["value"] === $value)
				return $option["label"];
		}
	}

	/*
	 * returns the name attribute for an input field
	 */

	public function create_input_name($field){
		return $this->field_options['format_input_prefix'] .
			$field . $this->field_options['format_input_postfix'];
	}

	/* returns a hidden input with the given name/value pair */
	public function create_hidden_input($name,$value){
		$name = htmlspecialchars($name);
		$value = htmlspecialchars($value);
		return "<input type=hidden name=\"$name\" value=\"$value\">";
	}

	/*
	 * @param $name             : field name
	 * @param $values           : value / array values
	 * @param $options          : options for an enum/multienum/status etc
	 * @param bool $multiple    : true for multienum
	 * @param string $class     : class name(s) for the root tag
	 */
	public function create_list_control($name, $values, $options, $multiple=true, $class="enum list"){
		// allows passing a single value or an array:
		$values = is_array($values) ? array_flip($values) : [$values=>1];
		$type=($multiple===true)?'checkbox':'radio';
		$uid=self::input_uid();
		$result=[];
		$line=0;
		if ($multiple) $name.='[]';
		foreach($options as $value=>$label){
			if (is_array($label)){
				$value=$label["value"];
				$label=$label["label"];
			}
			$value_h=htmlspecialchars($value);
			$label_h=htmlspecialchars($label);
			$result[]="<input id=\"$uid-$line\" name=\"$name\" type=\"$type\" value=\"$value_h\"" .
				(isset($values[$value])?'checked="checked"':'') .
				"><label for=\"$uid-$line\">$label_h</label>" ;
			$line++;
		}
		return "<div class=\"$class\">".
		$this->create_hidden_input($name,null)."<ul><li>" .
			implode('</li><li>', $result) .
			"</li></ul></div>";
	}

	/*
	 * returns true if a field is editable:
	 */

	public function check_format_input($field,$meta){
		if (@$meta['fields'][$field]['editable']===false) return false;
		return array_search($field,$this->field_options['format_input']) !== false;
	}



}
