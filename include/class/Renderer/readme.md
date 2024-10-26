# 1CRM ENGINEERING PROJECTS 1.2.2 Rendering Library Assignment

## Datatypes:

* varchar, name, phone, fax: a normal string
* url: external URL, rendered using <a> HTML tag
* int, number: integer
* date: date, string formatted as “2018-03-26”, displayed in WP Locale
* datetime: date and time, formatted as “2018-03-26 17:38:55”. Always GMT
* bool: a boolean value (can contain 0 or 1), (displayed as True/False?)
* currency, base_currency, raw_currency: currency value, double formatted with 2 decimals
* double
* text: string, line breaks should be converted to <br> tags when rendering
* email: email, should render as mailto: link
* progress, percent: percent value. Can be int or double, if double, limit to 2 decimals
* enum, status: an enumerated value. For display purposes, the value should be mapped using 
options provided in field metadata
* multienum: a set. For display purposes, the value should be split with “^,^”, and resulting 
array mapped using options provided in field metadata, and concatenated with “, “
* ref: linked record. Should be rendered as URL using provided template.

Any field with unexpected field type must be rendered as if it had varchar type.
Field labels are available in meta vname, or vname_list for ListView
When rendering ref fields, look for ID in other field that can be found in
metadata using for_ref key

## Meta fields for render:

* vname          = localizad field label to use in list views and detail views
* vname_list     = Similar to vname, but  for list view use only
* unified_search = Flag saying that the field is used for sytem-wide searches
* bean_name      = For ref fields, designates class/model the field refers to
* name           = storage / lookup  / column variable name
* options        = array of localized display values w/ numeric index for enum/set types 
* type           = validation type
* multiline      = text input vs textarea.  When type is text, multiline is implied
* dbType         = storage data type, can be ignored
* detail_link    = flag for link to view detail (type=ref), ignore for now. 
* reportable     = field can be used in reports. No use in portal render context
* for_ref        = field from which this field was inferred
* inferred       = Inferred means that this particular field is not present in original 
    metadata, and was inferred. For example, in Contacts there is primary_account field defined, 
    and processed metadata will include primary_account_id field. That ID field is not
    explicitly defined by module metadata, but is inferred instead
* lots more... see Renderer/HTML::$LUT for all the handled types. 

# Examples of usage:
## ListView and DetailView
NOTE: fields for these views are defined in the Posts/ShortCodes.php sample implementation
``` html
<h1>Various views...:</h1>
[onecrm_p_listview model=Account start=0 count=2]
[onecrm_p_listview model=Bug start=0 count=2]
[onecrm_p_listview model=Call start=0 count=2]
[onecrm_p_listview model=aCase start=0 count=2]
[onecrm_p_listview model=Contact start=0 count=2]
[onecrm_p_listview model=Lead start=0 count=2]
[onecrm_p_listview model=Meeting start=0 count=2]
[onecrm_p_listview model=Product start=0 count=2]
[onecrm_p_detailview model=Product start=0]
[onecrm_p_listview model=Project start=0 count=2]
[onecrm_p_detailview model=Project id=6324fa61-19f8-b87a-e6b7-5b907d219d43]
```

## ListView->render(['fields'=>...]) examples:
``` php
$listview_fields["Call"] = [
	// default behaviour: plain old list of field names
	"name", "direction", "account", "status", "date_start"
];
$listview_fields["Account"] = [
	// Stack 2 fields in a cell:
	"name" => ["name", "account_type"],
	// Stacks 2 fields; 2nd field joins 3 with ', ' (default)
	"website" => ["website", [
		"billing_address_city", 
		"billing_address_state", 
		"billing_address_country",
	]],
	"email1" => ["email1", "phone_office"],
	"balance" => "balance",
];
$listview_fields["Bug"] = [
	// Joins 'bug_number' and 'name' using ' : ' and name's behaviour (ref url)
	"bug_number" => ["name" => ["join" => ' : ', "bug_number", "name"]],
	"product" => "product",
	"priority" => ["priority", "type"],
	"status" => "status",
];
```
## Options for All Three Views (inherited from Renderer/HTML)
constructor $options
* link_template = `model/{MODEL}/{ID}`
* link_template_data = `callable f($model,$id,$row)`
* link_template_enclose = `['{', '}']`
* new_tab = `False` : open links in a new tab
* model = `NULL` : workaround for 'name' field type, missing in metadata
* detail_available = `callable f(){ true|false; }`
* format_input = `[]` : format these fields as inputs
* format_input_prefix = `''` : prepended to input name attribute ie 'Model.'
* format_input_postfix' = `''` : appended to input name attribute ie '[]'
* allowed_tags = `'<br><hr><p><b><i><a><em><strong><span><ul><ol><li><pre>'`

## options for DetailView:
* public $detail_options = `["columns" => 2, "labels_on_top" => True]`
* public $column_class_name = `["", "one_column", "two_column", "three_column", "four_column", "five_column", "six_column"`

## EditView
``` html
[onecrm_p_start style="font-size:0.7em;"]<h3>Editor Example with a single fieldset:</h3>
[onecrm_p_editor_start][onecrm_p_fieldset model=Contact id=339ded7d-ad6d-a9e4-b9fe-5ba0257d9ccb classes=categories=cols1+xxx&lead_source=cols1 fields=name,birthdate,department,categories,business_role,description]
[onecrm_p_editor_end submit="Save Changes"][onecrm_p_end]```
Watch the console when submitting.


## EditView->generate_fields_meta() examples:
``` php
$FakeModel = [
	// specify the type only:
	'name' => 'name',
	// short form overrides for 'fields' of existing type in CLASS defaults:
	'id' => ['type' => 'id', 'vname' => 'Fake ID'],
	// short form overrides for a custom type:
	'created_date' => ['type' => 'ro_time', 'vname' => 'Created'],
	// long form overrides for a custom type:
	'editable_date' => ['fields' => ['type' => 'datetime', 'vname' => 'Whenever']],
	// long form overrides, set filter options, fields options will be inferred.
	'optlist' => ['fields' => ['type' => 'enum', 'vname' => 'Options', 'class'=>'cols1'], 'filters' => ['options' => [
				'Value1' => 'Label1', 'Value2' => 'Label2', 'Value3' => 'Label3',
			]]],
	'custom'=>'custom',
];
// per-type re-usable descriptors:
$FakeTypes = [
	'fields' => [
		'ro_time' => ['type' => 'datetime', 'required' => 'true', 'vname' => 'DATETIME', 'editable' => false],
		'custom' => ['type' => 'custom', 'vname' => 'Custom', 'editable' => true],
	],
	// optional validation hooks:
	'validate' => [
		'custom' => function($value, $field, $fields_meta) {
			dump($value,'Custom Validation Hook for: '.$field);
			return true;
		},
	],
];
$FakeMeta = $EditView->generate_fields_meta($FakeModel, $FakeTypes);
```
Note: optlist demonstrates changing the style of an editor control.  It has class cols1 which lays it out in a single column.  2 columns is default, 3 is also supported

### Layout Columns / Column widths:
* for DetailView and EditView, call with `columns` arg to set number of display columns
* for ListView, each column header has class `col-name` for adjusting widths using CSS rules.

### TODO:
* ListView column priorities for auto-collapse on constrained displays?
* ListView take an array for explicit column widths?
* Change the ListView $data['fields'] structure like so:
```[field1, field2, ['stack'=>['join'=>[ 'with'=> ' & ', field3, field4]], field5], 'title'=>field3, 'type'=>field3]```
* Shortcodes integrated with Admin field order selection.