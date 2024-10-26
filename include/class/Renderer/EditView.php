<?php

/**
 * @package  OneCRMPortalRenderer
 */

namespace OneCRM\Portal\Renderer;

/*
 * Extends DetailView with form creation/validation features
 * Individual fields can be enabled for editing
 * see /readme.md for examples
 */

class EditView extends DetailView {

	/*
	 * Constructor
	 * 
	 * @param array $options [ 'columns'=>int (2), 'labels_on_top'=>boole ]
	 * options can also contain persistent args for render_field()
	 */

	public function __construct($options = null, $format_input = null) {
		if ($format_input) {
			$options['format_input'] = $format_input;
		}
		return parent::__construct($options);
	}

	/*
	 * renders a model to HTML
	 * 
	 * NOTE: this is an adapter for DetailView->render()
	 * the magic actually happens in HTML->render_field()
	 * 
	 * @param $data [ 
	 * 	'row'=>model, 
	 * 	'fields'=>['field','names'], 
	 * 	'fields_meta'=>metadata,
	 * 	'format_input'=>['fields','to_format_as','inputs'],
	 * 	'format_input_prefix'=>'',
	 *	'format_input_postfix=>'',
	 * ]
	 * options can also contain persistent args for render_field()
	 * format_input_prefix should probably be 'Model.' to prevent field name clashes.
	 */

	public function render($data, $format_input = NULL) {
		if ($format_input)
			$data['format_input'] = $format_input;
		return parent::render($data);
	}

	/* $meta_types mimics 1CRM API /meta/fields/{model} 
	 * fields are indexed by type
	 * unknown types will be rendered as varchar and cannot be edited
	 * adding a type requires a validation callback to be editable
	 * 
	 * 	fields[type]:
	 * 		type: the type to display and validate as
	 * 		vname: Cell label but inputs with muliple values use Options as labels
	 * 		placeholder: will be displayed if the input is empty (hint to user)
	 * 		len: character length of string(ish) inputs
	 * 		editable: allowed to be rendered as an input, not masked from writeback
	 * 		required: must be present and non-empty in create/edit forms
	 * 		options: [['label'=>'i10n label','value'=>'value'],...] for the type
	 * 		class: default CSS classname given to the type
	 * 	filters[type]:
	 * 		options: ['value'=>'i10n label'...] used to validate enum(ish) form responses
	 * 	
	 * 	validate[type]: function($value,$field,$fields_meta) true if the value is valid
	 * 
	 * 	NOTE: 
	 * 		if ONE of fields[$type][options] or filters[$type][options] is null
	 * 		then the missing one will be generated from the other's contents
	 */

	static $meta_types = [
		'fields' => [
			'varchar' => ['type' => 'varchar', 'required' => true, 'vname' => 'VARCHAR', 'editable' => true],
			'name' => ['type' => 'name', 'required' => true, 'vname' => 'NAME', 'editable' => true],
			'phone' => ['type' => 'phone', 'required' => true, 'vname' => 'PHONE', 'editable' => true],
			'fax' => ['type' => 'phone', 'required' => true, 'vname' => 'FAX', 'editable' => true],
			'url' => ['type' => 'url', 'required' => true, 'vname' => 'URL', 'editable' => true],
			'int' => ['type' => 'int', 'required' => true, 'vname' => 'INT', 'editable' => true],
			'number' => ['type' => 'number', 'required' => true, 'vname' => 'NUMBER', 'editable' => true],
			'decimal' => ['type' => 'decimal', 'required' => true, 'vname' => 'DECIMAL', 'editable' => true],
			'date' => ['type' => 'date', 'required' => true, 'vname' => 'DATE', 'editable' => true],
			'datetime' => ['type' => 'datetime', 'required' => true, 'vname' => 'DATETIME', 'editable' => true],
			'bool' => ['type' => 'bool', 'required' => true, 'vname' => 'BOOL', 'editable' => true],
			'currency' => ['type' => 'currency', 'required' => true, 'vname' => 'CURRENCY', 'editable' => true],
			'base_currency' => ['type' => 'currency', 'required' => true, 'vname' => 'BASE_CURRENCY', 'editable' => true],
			'raw_currency' => ['type' => 'currency', 'required' => true, 'vname' => 'RAW_CURRENCY', 'editable' => true],
			'double' => ['type' => 'float', 'required' => true, 'vname' => 'DOUBLE', 'editable' => true],
			'text' => ['type' => 'text', 'required' => true, 'vname' => 'TEXT', 'editable' => true],
			'email' => ['type' => 'email', 'required' => true, 'vname' => 'EMAIL', 'editable' => true],
			'progress' => ['type' => 'percent', 'required' => true, 'vname' => 'PROGRESS', 'editable' => true],
			'percent' => ['type' => 'percent', 'required' => true, 'vname' => 'PERCENT', 'editable' => true],
			'enum' => ['type' => 'enum', 'required' => true, 'vname' => 'ENUM', 'editable' => true],
			'status' => ['type' => 'status', 'required' => true, 'vname' => 'STATUS', 'editable' => true],
			'multienum' => ['type' => 'multienum', 'required' => true, 'vname' => 'MULTIENUM', 'editable' => true],
			'id' => ['type' => 'varchar', 'required' => true, 'vname' => 'ID', 'editable' => false],
			'sequence_number' => ['type' => 'varchar', 'required' => true, 'vname' => 'SEQUENCE_NUMBER', 'editable' => true],
			'item_number' => ['type' => 'varchar', 'required' => true, 'vname' => 'ITEM_NUMBER', 'editable' => true],
			'age' => ['type' => 'datetime', 'required' => true, 'vname' => 'AGE', 'editable' => true],
			'status_color' => ['type' => 'varchar', 'required' => true, 'vname' => 'STATUS_COLOR', 'editable' => true],
			'reminder' => ['type' => 'enum', 'required' => true, 'vname' => 'REMINDER', 'editable' => true],
			'image' => ['type' => 'varchar', 'required' => true, 'vname' => 'IMAGE', 'editable' => true],
			'star_rating' => ['type' => 'varchar', 'required' => true, 'vname' => 'STAR_RATING', 'editable' => true],
			'file_ref' => ['type' => 'varchar', 'required' => true, 'vname' => 'FILE_REF', 'editable' => true],
			'duration' => ['type' => 'varchar', 'required' => true, 'vname' => 'DURATION', 'editable' => true],
			'html' => ['type' => 'html', 'required' => true, 'vname' => 'HTML', 'editable' => true],
			'formula' => ['type' => 'varchar', 'required' => true, 'vname' => 'FORMULA', 'editable' => true],
		],
		'filters' => [],
		'validate' => [],
	];

	/*
	 * add or replace ::$meta_types
	 * @param $type			: type name
	 * @param $fields		: array of fields entries as described for ::$meta_types[fields]
	 * @param $filters		: array of filter entries as described for ::$meta_types[filters]
	 * @param $validate		: optional validation callback for ::meta_types[validate]
	 */

	static function set_meta_type($type, $fields, $filters = [], $validate = null) {
		self::$meta_types['fields'][$type] = $fields;
		self::$meta_types['filters'][$type] = $filters;
		self::$meta_types['validate'][$type] = $validate;
	}

	/*
	 * add or update ::$meta_types
	 * @param $type			: type name
	 * @param $fields		: array of fields entries as described for ::$meta_types[fields]
	 * @param $filters		: array of filter entries as described for ::$meta_types[filters]
	 * @param $validate		: optional validation callback for ::$meta_types[validate]
	 */

	static function modify_meta_type($type, $fields, $filters = [], $validate = null) {
		self::$meta_types['fields'][$type] = $fields + (array) @self::$meta_types['fields'][$type];
		self::$meta_types['filters'][$type] = $filters + (array) @self::$meta_types['filters'][$type];
		if (is_callable($validate))
			self::$meta_types['validate'][$type] = $validate;
	}

	/*
	 * Simulate $fields_meta for HTML/EditView->render() and EditView::validate_fields()
	 * @param $fields: [
	 * 		field_name => 'type' OR
	 *		field_name => ['type'=>'type_name', ...] OR 
	 * 		field_name => [
	 * 			'fields'=> ['type'=>'type_name', ...],	// fields to set/override
	 * 			'filters' => [ ],						// etc
	 * 			'validate' => callable,					// optional, for custom types
	 * 		];
	 * @param $meta_types: optional alternate source for $meta_types
	 * @param $skip_class: don't use/inherit records data from ::$meta_types
	 * 
	 * NOTE: only one of fields[options] or filters[options] must be defined for a new type
	 * 		the other will be built automatically
	 * WARN: when overriding existing options BOTH fields and filters options MUST be passed
	 * 		otherwise the result is undefined.
	 */

	static function generate_fields_meta($fields, $meta_types = [], $skip_class = false) {
		$result = [];

		foreach ($fields as $field => $type) {
			// pass 1:  try to take records from the $fields row:
			if (is_array($type)) {
				if (@$type['type']) $type=['fields'=>$type];
				if (isset($type['fields'])) $result['fields'][$field]=$type['fields'];
				if (isset($type['filters'])) $result['filters'][$field]=$type['filters'];
				if (isset($type['validate'])) $result['validate'][$field]=$type['validate'];
				$type = $type['fields']['type'];
			}
			// pass 2:  populate records from $meta_types:
			if (isset($meta_types['fields'][$type])) {
				if (isset($meta_types['fields'][$type])) 
					$result['fields'][$field] = (array) @$result['fields'][$field] + (array) $meta_types['fields'][$type];
				if (isset($meta_types['filter'][$type])) 
					$result['filter'][$field] = (array) @$result['filter'][$field] + (array) $meta_types['filter'][$type];
				if (isset($meta_types['validate'][$type]) && !isset($result['validate'][$field])) 
					$result['validate'][$field] = $meta_types['validate'][$type];
				
			}
			// pass 3: populate records from the Class defaults:
			if (!$skip_class && isset(self::$meta_types['fields'][$type])) {
				if (isset(self::$meta_types['fields'][$type])) 
					$result['fields'][$field] = (array) @$result['fields'][$field] + (array) self::$meta_types['fields'][$type];
				if (isset(self::$meta_types['filters'][$type])) 
					$result['filters'][$field] = (array) @$result['filters'][$field] + (array) self::$meta_types['filters'][$type];
				if (isset(self::$meta_types['validate'][$type]) && (!isset($result['validate'][$field]))) 
					$result['validate'][$field] = self::$meta_types['validate'][$type];
			}
			// create missing filter options from fields options
			if (@count($result['fields'][$field]['options']) && !@count($result['filters'][$field]['options'])) {
				foreach ($result['fields'][$field]['options'] as $row) {
					$result['filters'][$field]['options'][$row['value']] = $row['label'];
				}
			}
			// create missing fields options from filters options
			if (!@count($result['fields'][$field]['options']) && @count($result['filters'][$field]['options'])) {
				foreach ($result['filters'][$field]['options'] as $value => $label) {
					$result['fields'][$field]['options'][] = compact('label', 'value');
				}
			}
		}
		return $result;
	}

	/*
	 * @param $data                 : submitted data row
	 * @param string $fields        : fields to be checked
	 * @param null $fields_meta     : Model metadata, or look-alike
	 * @param string $action        : how to deal with errors: test | crop | throw
	 * @return array|bool           : array of errors, true, or false
	 * @throws \Exception           : only if action=throw
	 *
	 * validate chosen fields of data against matching metadata
	 * returns true if when all checked fields pass validation
	 * 
	 * $fields can be:
	 * 		'*' : to check all fields 
	 * 		'required' : to check only 'required' fields
	 * 		comma,separated,fields_to_check
	 * 		an array of field names to check
	 * $fields_meta is a Model metadata description from the 1CRM API (or a look-alike)
	 * $action can be one of: 
	 * 		test : return index of first failed field, or TRUE if all succeed
	 * 		crop : like test but crop and pass over-length varchar and text
	 * 		throw : throw [$field => 'error_string'] on error
	 * Any field without a match in $data or $fields_meta will be treated as invalid.
	 * 
	 * TODO: maybe break this state machine up into per-type validation methods
	 * TODO: this probably belongs somewhere else... but where?
	 */

	static function validate_data(&$data, $fields = '*', $fields_meta=null, $action = 'crop') {
		if ($fields === '*') {
			$fields = array_keys($data);
		} elseif (strtolower($fields) === 'required') {
			$fields = [];
			foreach (array_keys($data) as $key) {
				if (isset($fields_meta['fields'][$key]['required'])) {
					$fields[] = $key;
				}
			}
		} elseif (is_string($fields)) {
			$fields = explode(',', $fields);
		}
		$error = [];
		foreach ($fields as $field) {
			if (!isset($data[$field]) && !array_key_exists($field, $data)) {
				$error[$field] = 'missing data';
				break;
			}
			if (!isset($fields_meta['fields'][$field]['type'])) {
				$error[$field] = 'missing meta';
				break;
			}
			$type = $fields_meta['fields'][$field]['type'];
			$value = $data[$field];
			// handle custom type validation callback:
			if (is_callable(@$fields_meta['validate'][$field])) {
				if ($fields_meta['validate'][$field]($value, $field, $fields_meta))
					continue;
				$error[$field] = 'bad type';
				break;
			}
			// pass one: check the storage length:
			$len = @$fields_meta[$field]['len'];
			$length = strlen($value);
			// crop for text / varchar:
			if (($type === 'text' || $type === 'varchar') && $action === 'crop') {
				if ($len < 1)
					$len = 255;
				if ($length > $len)
					$data[$field] = substr($value, 0, $len);
				continue; // no more tests
			}
			if (($len > 0) && $length > $len) {
				$error[$field] = 'too long';
				break;
			}

			/* AD: this in fact does nothing, but generates warnings:
			 * "continue" targeting switch is equivalent to "break". Did you mean to use "continue 2"?
			switch ($type) {
				case 'html': // we only care that it fits: 
					continue;
					break;
				case 'name':
					if ($length > 0)
						continue;
					break;
				case 'phone':
				case 'fax':
					if ($length > 6)
						continue;
					break;
				case 'image': // image is an URL 
				case 'url':
					if (filter_var($value, FILTER_VALIDATE_URL))
						continue;
					break;
				case 'int': // just decimals
				case 'decimal':
					if (is_int($value))
						continue;
					break;
				case 'number': // int and float
				case 'currency':
				case 'base_currency':
				case 'raw_currency':
				case 'double':
					if (is_double($value))
						continue;
					break;
				case 'age':
				case 'date':
				case 'datetime':
				case 'duration':
					// if we can parse it we assume it is good:
					// Maybe we should normalize it here too?
					if (strtotime($value))
						continue;
					break;
				case 'boole':
					if (is_bool($value))
						continue;
					break;
				case 'email':
					if (filter_var($value, FILTER_VALIDATE_EMAIL))
						continue;
					break;
				case 'progress':
				case 'percent':
					// TODO: Progress type can have non-century range in theory
					if ($value > 0 && $value <= 100)
						continue;
					break;
				case 'enum':
				case 'status':
					// emulate a multienum
					$value = [$value];
				case 'multienum':
					foreach ($value as $key) {
						if (!isset($fields_meta['filters'][$field]['options'][$key])) {
							$error[$field] = 'bad option';
							break 2;
						}
					}
				case 'id': // TODO: these assorted types
				case 'sequence_number':
				case 'item_number':
				case 'status_color':
				case 'reminder':
				case 'star_rating':
				case 'file_ref':
				case 'formula':
					continue;
					break;
				DEFAULT:
			}
			 * */
		}
		if (count($error)) {
			if ($action === 'throw') {
				throw new \Exception($error);
			} else {
				return $error;
			}
		}
		return true;
	}

}
