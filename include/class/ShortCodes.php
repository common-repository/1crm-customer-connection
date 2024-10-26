<?php

namespace OneCRM\Portal;

/*
 *  Some sample shortcodes
 */

use OneCRM\APIClient\Error as APIError;
use OneCRM\Portal\Renderer;

class ShortCodes
{
	var $mode = "contact";

	/**
	 * ShortCodes constructor.
	 * @param string $mode      : "admin" | "contact"
	 */
	public function __construct($mode='')
	{
		if (array_search($mode,['admin','contact'])!==false) $this->mode = $mode;
	}

	public function get_client($throw = false) {
		return Auth\OneCrm::instance()->getContactClient($throw);
	}

	public function register()
	{
		$config_options = get_option('onecrm_p_options');
		Locale::Instance($config_options["locale"]);

		// register the codes we'll service:
		add_shortcode('onecrm_p_dashboard',		[$this, 'dashboard_shortcode']);
		add_shortcode('onecrm_p_plan_selection',[$this, 'plan_selection_shortcode']);
		add_shortcode('onecrm_p_payments_register', [$this, 'payments_register_shortcode']);
		add_shortcode('onecrm_p_payments_choose', [$this, 'payments_choose_shortcode']);
		add_shortcode('onecrm_p_payments_verify', [$this, 'payments_verify_shortcode']);

		$this->enqueue_application_scripts();

		Subscription::register(); // delegate subscription-related shortcodes
	}

	/**
	 * Presents / selects available Plans / Addons / OptionSets
	 * @param $attr
	 * @return string|bool
	 */
	public function plan_selection_shortcode($attr){
		if (!$client = Auth\OneCrm::instance()->getAdminClient(false)) return false;
		ob_start();
		$attr['processor'] = 'ChargeBee'; // hard-coded for now.
		if (! $attr['plans']??null) {
			return __("No Plans Selected", ONECRM_P_TEXTDOMAIN);
		}
		$pp_plan_ids = explode(',', $attr['plans']);
		$filter_coupons=[];
		$filter_addons=[];
		$filter_options=[];
		try {
			$modelFilters = [
				'Coupon' => [
					['field' => 'active', 'value' => 'Active'],
					['operator' => 'OR', 'multiple' => [
						['field' => 'pp_coupon_id', 'operator' => 'IN', 'value' => & $filter_coupons],
						['field' => 'addon_mode', 'value' => 'All Addons'],
						['field' => 'plan_mode', 'value' => 'All Plans'],
					]]
				],
				'Addon' => [
					['field' => 'active', 'value' => 'Active',],
					['operator' => 'OR', 'multiple' => [
						['field' => 'pp_addon_id', 'operator' => 'IN', 'value' => & $filter_addons],
						['field' => 'global', 'value' => '1'],
					]]
				],
				'Option' => [
					['field' => 'option_number', 'operator' => 'IN', 'value' => & $filter_options],
				],
			];

			if ($attr['plans']??null) $modelFilters['Plan'] = [
				[ 'field' => 'active', 'value' => 'Active'],
				[ 'field'=>'pp_plan_id', 'operator'=> 'IN', 'value' => $pp_plan_ids],
			];
			foreach(['pp_plan_id'=>'Plan', 'pp_addon_id'=>'Addon', 'pp_coupon_id'=>'Coupon', 'option_number'=>'Option'] as $key=> $model){
				$modelClient = $client->subscription($model,$attr['processor']);

				if (!empty(@$modelFilters[$model])) {
					$filters = ['_lq'=>$modelFilters[$model]];
				} else {
					$filters = ['active' => 'Active'];
				}
				$result = $modelClient->getlist([
					'fields' => [true], // all allowed fields
					'filters' => $filters,
				]);
				$list=$result->getRecords();
				foreach (['meta', 'pricing_tiers', 'addons', 'coupons', 'options'] as $field){
					if (!array_key_exists($field, current($list))) continue;
					if ($model == "Option" && $field == "options") continue;
					foreach($list as &$row){
						$row[$field] = json_decode($row[$field],true);
						if ($field == 'meta' && is_array($row['meta'])){
							$row += $row['meta'];
							unset($row['meta']);
						}
						if ($row[$field]==[]) {
							$row[$field] = null;
						} elseif ($field=='addons'){
							$filter_addons += $row['addons'];
						} elseif ($field == 'coupons'){
							$filter_coupons += $row['coupons'];
						} elseif ($field == 'options'){
							$filter_options = array_unique(array_merge($filter_options, $row[$field]));
						}
					}
				}
				$lists[$model] = array_combine(array_column($list, $key), $list);
			}

			$processor_id = $modelClient->get_processor_id();

			/* debug dump:
			echo onecrm_debug_var("JSON", $lists);/**/

		} catch (APIError $e){
			echo stripslashes( onecrm_debug_var(['value'=>$e,'args'=>1, ]));
		}
		if (count($lists['Plan']) > 0){
			// present them in the order set in the shortcode:
			$tmp=[];
			foreach($lists['Plan'] as $plan) {
				$tmp[strtolower($plan['pp_plan_id'])] = $plan;
			}
			$lists['Plan'] = $tmp;
		} else return __("No Plans Selected", ONECRM_P_TEXTDOMAIN);

		// inject the template and plan data:
		echo('<div class="onecrm-p-a">');
		$template = preg_replace('/[^a-z0-9._-]/i','',$attr['template']??'plan_selection');
		echo file_get_contents(ONECRM_P_PATH."/templates/html/$template.html");
		$this->enqueue_page_data('window.OneCRM.Portal.App.replace',$lists);
		echo "</div>"; // end app widget:

		// if there's a master plan then wrap it as the list:
		$source = ($attr['master']??null) ? "[R.Plan[\"{$attr['master']}\"]]": "R.Plan";
		// inject the trigger to populate the selection widget:
		$this->enqueue_page_scripts('plan_widget_init', /** @lang JavaScript */
<<<JS
		OneCRM.Portal.App.multiData('plan_selection','plan_selection_widget','{list:$source,shared:[R.plan_wrappers,{payment_processor_id:"$processor_id"}]}');
JS
		);
		return ob_get_clean();
	}

	/**
	 * Directs the Customer to the PaymentProcessor to register a payment method
	 * @param $attr
	 * @return string
	 */
	public function payments_register_shortcode($attr){
		return "<h4>TODO: Payments Register Shortcode</h4>";

	}

	/**
	 * Selects a payment method to finalize the purchase/subscription
	 * @param $attr
	 * @return string
	 */
	public function payments_choose_shortcode($attr){
		return "<h4>TODO: Payments Choose Shortcode</h4>";

	}

	/**
	 * Waits for payment notification from PP and shows thankyou or rejection page
	 * @param $attr
	 * @return string
	 */
	public function payments_verify_shortcode($attr){
		return "<h4>TODO: Payments Verify Shortcode</h4>";

	}


	/**
	 * called to add the datetime picker support resources
	 */
	public function enqueue_datetime_scripts(){
		static $once=false;

		if (! $once) {
			add_action('wp_footer',function() { // load this as late as possible:
				wp_enqueue_style('jquery-ui-css', ONECRMP_PLUGIN_URL . '/css/jquery-ui.min.css');
				wp_enqueue_style('jquery-ui-timepicker-addon', ONECRMP_PLUGIN_URL . '/css/jquery-ui-timepicker-addon.min.css');
				wp_enqueue_script('jquery-ui-timepicker-addon', ONECRMP_PLUGIN_URL. '/js/jquery-ui-timepicker-addon.min.js', array('jquery','jquery-ui-core','jquery-ui-datepicker'));
				wp_enqueue_script('jquery-ui-sliderAccess', ONECRMP_PLUGIN_URL. '/js/jquery-ui-sliderAccess.js');
				wp_enqueue_script('onecrm-p-editor-events', ONECRMP_PLUGIN_URL . '/js/editor-events.js');
			});
			$once=true;
		}
	}


	/**
	 * Aggregates jQuery.extend(target,data) in script blocks, ordered by id and call order
	 * Use only for page-specific data.
	 * @param string $target ie 'window.OneCRM.App.Portal.replace'
	 * @param array $data
	 * @param string $id - id of the script tag to aggregate in
	 * @return bool false if $name isn't string or $data isn't object
	 */
	public function enqueue_page_data($target, $data, $id = "onecrmPortalApp"){
		if (!(is_array($data)|| is_object($data))) return false;

		// ~=20ms for 10 plans with about 10 shared coupons/addons
		$data = onecrm_html_sanitizer_r($data);

		$data = json_encode($data, JSON_PRETTY_PRINT);

		return $this->enqueue_page_scripts($id, "$.extend(true,$target, $data);",'ready');
	}

	/**
	 * Ordered by template, script, ready; then name, order called
	 * Use only for page-specific content, or short activation scripts.
	 * @param string $id of the injected script tag.  multiple calls aggregate
	 * @param string $content
	 * @param string $type
	 * @param int $priority >=11, set on the first call only
	 * @return bool
	 */
	public function enqueue_page_scripts($id, $content, $type = 'ready', $priority = 20){
		static $app_scripts=['template'=>[],'script'=>[],'ready'=>[]];
		static $registered = false;

		if (!(is_string($content) && is_string($id))) return false;
		$this->enqueue_application_scripts();
		$app_scripts[$type][$id][]=$content;
		if (!$registered){
			$registered = true;
			add_action('wp_print_footer_scripts',function () use (&$app_scripts) {
				foreach ($app_scripts as $type => &$ids){
					ksort($ids);
					foreach ($ids as $id => $contents){
						$attr = ($type=='template') ? ' type="text/template"' : '';
						$attr .= ' id="'.htmlspecialchars($id).'"';
						echo "<script$attr>".PHP_EOL;
						if ($type==="ready") {
							echo 'jQuery(function($){';
						} elseif ($type==="data" || $type=== "script") {
							echo '(function($){';
						}
						echo implode(PHP_EOL,$contents);
						if ($type==="ready") {
							echo "});";
						} elseif ($type === "data" || $type === "script") {
							echo '})(jQuery);';
						}
						echo '</script>'.PHP_EOL;
					}
				}
				unset($names);
			}, $priority);
		}
		return true;
	}
	public function enqueue_application_scripts(){
		static $once=false;
		if (! $once) {
			add_action('wp_footer', function () { // load this as late as possible:
				wp_enqueue_style('onecrm-portal-app', ONECRMP_PLUGIN_URL . '/css/OneCRM.Portal.css');
				wp_enqueue_script('onecrm-portal-app', ONECRMP_PLUGIN_URL . '/js/OneCRM.Portal.js');
			});
			$once = true;
		}
	}

	/*
	 * Sample implementation of Content wrapper
	 */
	public function onecrm_start_shortcode($attr)
	{
		$style = htmlspecialchars($attr['style']);
		return "<div class=\"onecrm\" style=\"$style\">";
	}

	public function onecrm_end_shortcode($attr)
	{
		return "</div>";
	}

	/*
	 * Sample implementation of Editor Form wrapper
	 * submit action sends the form and dumps the server response to the console
	 * if no action is given it will be directed to an echo facility for testing
	 *
	 *  @param $attr[action] = ajax action (onecrm_ajax_echo)
	 */

	public function onecrm_editor_start_shortcode($attr)
	{
		// default action:
		$attr = (array)$attr;
		$URL = admin_url('admin-ajax.php') . '?action=' . $attr['action'];

		$html = '<div id="onecrm-p-errors"></div>';
		$html .= '<form id="onecrm-p-editor-form" action="' . $URL . '" accept-charset="UTF-8">';
		return $html;
	}

	/*
	 * the script should be enqueued from a file, but this is just WIP debug code...
	 */
	public function onecrm_editor_end_shortcode($attr)
	{
		$content = '</form>';
		if (@$attr["submit"]) {
			$content = '<button type="submit" class="onecrm-p-save button">' .
				htmlspecialchars($attr["submit"]) . '</button>' . $content;
		}
		return $content;
	}

	/* dump a model as an HTML fieldset, as part of an Editor Form:
	 *
	 * a proper implementation will take the Model and ID from GET args
	 * and also offer a way to restrict and order the fields rendered.
	 *
	 * @param $attr[model]		1CRM API model name ie Contact
	 * @param $attr[id]			model GUID to render a form for
	 * @param $attr[fields]		list of field names to render
	 * @param $attr[classes]	hash of field names to class names to add to the field cells
	 */

	public function onecrm_fieldset_shortcode($attr)
	{
		if (!$client = $this->get_client()) return false;
		try {

			$attr = (array)$attr;
			if (empty($attr['columns']))
				$attr['columns'] = 3;
			$model_name = @$attr['model'];
			$id = @$attr['id'];

			if (!$model_name) {
				return false;
			}

			$model = $client->model($model_name);
			$fields_meta = $model->metadata();

			if (!@$attr['fields']) {
				// start with all possible fields:
				$fields = array_keys($fields_meta["fields"]);
				asort($fields);
				$fields = array_values(["name"] + $fields);
			} else {
				$fields = $attr['fields'];
				if (!is_array($fields))
				$fields = explode(',', $fields);
			}

			// shortcode classes arg can specify additional class names for some fields: */
			$field_classes = [];
			parse_str(@$attr["classes"], $field_classes);
			foreach ($field_classes as $key => $value) {
				$fields_meta["fields"][$key]["class"] = $value;
			}
			if (!empty($attr['id']))
				$row = $model->get(@$attr["id"], $fields);
			else
				$row = [];

		} catch (APIError $e) {
			return '<div id="onecrm-p-errors" class="active"><div class="onecrm-p-error">' 
				. __("Unable to load record", ONECRM_P_TEXTDOMAIN)
				. '</div></div>'
				;
		}

		$this->enqueue_datetime_scripts();
		ob_start();

		$EditView = new Renderer\EditView(['detail_available' => null, 'model' => $model_name, 'columns' => $attr['columns'], 'format_input' => $fields, 'format_input_prefix' => $model_name . '[', 'format_input_postfix' => ']']);

		$post = get_post();
		echo $EditView->render(compact('row', 'fields', 'fields_meta'));
		echo $EditView->create_hidden_input($model_name . '[id]', $id);
		echo $EditView->create_hidden_input('model', $model_name);
		echo $EditView->create_hidden_input('ret', $post->ID);
		return ob_get_clean();
	}

	/*
	 * Sample implementation of DetailView as shortcode
	 * Not intended for production.
	 */

	public function detailview_shortcode($attr, $show_empty = true)
	{
		if (!$client = $this->get_client(true)) return false;
		try {

			$attr = (array)$attr;

			$link_template_data = isset($attr['link_template_data']) ? $attr['link_template_data'] : null;
			$link_template = isset($attr['link_template']) ? $attr['link_template'] : '?model={MODEL}&id={ID}';
			$columns = isset($attr["columns"])?$attr['columns']:3;
			$detail_available = !empty($attr["detail_available"]);
			$model_name = $attr["model"];

			$model = $client->model($model_name);
			$fields_meta = $client->get("/meta/fields/$model_name");

			if ($model_name == 'Invoice')
				$this->add_cutom_field_metadata($fields_meta, 'paypal_button');

			$fields = array_keys($fields_meta["fields"]);
			if (@$attr['fields']) {
				$f = $attr['fields'];
				if (!is_array($f))
					$f = explode(',', $f);
				$fields = array_values(array_intersect($f, $fields));
			}
			$filter = compact("fields");

			if (@$attr["start"]) {
				$rows = $model->getList($filter, $attr["start"], 1)->getRecords();
				$row = $rows[0];
			} elseif (@$attr["id"]) {
				$row = $model->get(@$attr["id"], $fields);
			} else {
				return false;
			}
		} catch (APIError $e) {
			return '<div id="onecrm-p-errors" class="active"><div class="onecrm-p-error">' 
				. __("Unable to load record", ONECRM_P_TEXTDOMAIN)
				. '</div></div>'
				;
		}

		$this->add_custom_fields($model_name, $fields, $fields_meta, $row);

		if (! $show_empty) {
			$row = array_filter($row);

			$fields = array_filter(
				$fields,
				function($el) use ($row) { return isset($row[$el]); }
			);
		}

		// test of default link template and default link data from meta/row, and customer columns
		$renderer_options = [
			"detail_available" => $detail_available,
			"model" => $model_name,
			"id" => $row['id'],
			"columns" => $columns,
			'link_template_data' => $link_template_data,
			'link_template' => $link_template,
		];

		$title = isset($attr['title']) ? $attr['title'] : $model_name;
		$title = !empty($attr['title_escaped']) ? $title : htmlspecialchars($title);
		ob_start();
		echo "<h3>$title</h3>";

		// order the fields by field name:
		$render_type = isset($attr['render_type']) ? $attr['render_type'] : '';
		$DetailView = $this->get_detail_renderer($renderer_options, $render_type);

		echo $DetailView->render(compact("row", "fields", "fields_meta"));

		return ob_get_clean();
	}

	/**
	 * Get DetailView Renderer by type
	 *
	 * @param array $options
	 * @param string $type: renderer type - 'erase_personal' or '' by default
	 *
	 * @return PersonalDataEraseView|Renderer\DetailView
	 */
	private function get_detail_renderer($options, $type = '') {
		if ($type == 'erase_personal') {
			$this->enqueue_datetime_scripts();
			return new PersonalDataEraseView($options);
		} else {
			return new Renderer\DetailView($options);
		}
	}

	/*
	 * Sample implementation of ListView as shortcode
	 * Not intended for production.
	 */

	public function listview_shortcode($attr, $content = null, $shortcode = null)
	{
		try {
			!$client = $this->get_client(true);
			if (!$client) return false;
		} catch (Auth\ConfigError $e) {
			return $this->displayAuthErrors($e->getErrors());
		}
		$link_template_data = isset($attr['link_template_data']) ? $attr['link_template_data'] : true;
		$link_template = isset($attr['link_template']) ? $attr['link_template'] : '?model={MODEL}&id={ID}';

		$model_name = $attr["model"];
		$link_name = @$attr["link"];
		$parent_model = @$attr["parent_model"];
		$parent_id = @$attr["parent_id"];
		$no_restrictions = @$attr['no_restrictions'];
		
		$start = (int)@$attr["start"];
		if ($start < 0) $start = 0;
		$count = (int)@$attr["count"];
		if ($count < 1) $count = 20;

		try {
			$title = isset($attr['title']) ? $attr['title'] : $model_name;
			$title = !empty($attr['title_escaped']) ? $title : htmlspecialchars($title);
			$detail_available = isset($attr['detail_available']) && is_callable($attr['detail_available']) ? $attr['detail_available'] : true;

			if ($no_restrictions) {
				$client = Auth\OneCrm::instance()->getAdminClient();
				if (!$client) return false;
			}
			$model = $client->model($model_name);
			$fields_meta = $client->get("/meta/fields/$model_name");
			$filter = ['fields' => array_keys($fields_meta['fields'])];
			$fields = isset($attr['fields']) ? $attr['fields'] : null;
			$order = isset($attr['order']) ? $attr['order'] : null;
			if (is_null($fields)) {
				$fields = array_keys($fields_meta['fields']);
			}
			if (is_string($fields)) {
				$fields = explode(',', $fields);
			}
			if (isset($attr['filters']) && is_array($attr['filters'])) {
				$filter['filters'] = $attr['filters'];
			}

			//Add filters dat from user's input
			$this->add_input_filters($filter, $attr['model_list_filters'], $fields_meta['filters']);

			$filter['order'] = $order;
			if ($link_name) {
				$related_model = $client->model($parent_model);
		   		$list = $related_model->getRelated($parent_id, $link_name, $filter, $start, $count);
			} else {
				$list = $model->getList($filter, $start, $count);
			}
			$rows = $list->getRecords();
			$totalRows = $list->totalResults();
		} catch  (APIError $e) {
			return __("Unable to load list for", ONECRM_P_TEXTDOMAIN).' '.$model_name;
		}

		if (!empty($attr['paginate'])) {
			$curPage = (int)($start / $count) + 1;
			$pages = new Renderer\Pagination($curPage, $count, $totalRows, '?full_list=' . $model_name . '&page_number=%s');
		}

		ob_start();
		echo "<h3>$title</h3>";

		if ($attr['limit_model'] && ! empty($attr['model_list_filters'])) {
			$this->enqueue_datetime_scripts();
			$FilterFormView = new FilterFormView([]);

			echo $FilterFormView->render(
				[
					'filters' => $attr['model_list_filters'],
					'input' => onecrm_p_sanitize_postdata_multiline($_POST),
					'def' => $fields_meta['filters'],
					'model' => $model_name,
					'create_button' => @$attr['create_button'],
					'page_url' => $_SERVER['REQUEST_URI']
				]
			);
		}

		if (!empty($attr['paginate']))
			echo $pages->render();

		// test of custom link template and link_template_data callback:

		$ListView = new Renderer\ListView([
			"detail_available" => $detail_available, 
			"model" => $model_name,
			"link_template" => $link_template,
			"download_url" => @$attr['download_url'],
			"link_template_data" => $link_template_data
		]);

		$this->add_custom_fields($model_name, $fields, $fields_meta, $rows);

		echo $ListView->render(compact("rows", "fields", "fields_meta"));
		if (!empty($attr['paginate']))
			echo $pages->render();

		return ob_get_clean();
	}

	public function dashboard_shortcode($attr, $content = null, $shortcode = null) {
		if (is_null($this->is_partner)) {
			$this->query_partner_status();
		}
		$model = onecrm_get_default($attr, 'model');
		$full_list = sanitize_text_field(onecrm_get_default($_GET, 'full_list'));
		if ($full_list && $model) $full_list = $model;
		$detailview = sanitize_text_field(onecrm_get_default($_GET, 'detailview'));
		if ($detailview && $model) $detailview = $model;
		$create = sanitize_text_field(onecrm_get_default($_GET, 'create'));
		//if ($create && $model) $create = $model;
		$edit = sanitize_text_field(onecrm_get_default($_GET, 'edit'));
		//if ($edit && $model) $edit = $model;
		$personal_data = sanitize_text_field(onecrm_get_default($_GET, 'personal_data'));
		//if ($personal_data && $model) $edit = $model;

		if (!$create && !$edit && !$detailview && ! $personal_data && $model) $full_list = $model;

		$html = $this->onecrm_start_shortcode([]);

		if ($personal_data) {
			$html .= $this->dashboard_personal_data($personal_data, sanitize_key($_GET['record']), sanitize_key($_GET['format']), !!$model);
		} elseif ($create) {
			$html .= $this->dashboard_create($create, !!$model);
		} elseif ($edit) {
			$html .= $this->dashboard_edit($edit, sanitize_key($_GET['record']), !!$model);
		} elseif ($full_list) {
			$html .= $this->dashboard_index($full_list, !!$model);
		} elseif ($detailview) {
			$html .= $this->dashboard_detail($detailview, sanitize_key($_GET['record']), !!$model);
		} else {
			$html .= $this->dashboard_index();
		}
		$html .= $this->onecrm_end_shortcode([]);
		return $html;
	}

	public function dashboard_index($limit_model = null, $no_breadcrumbs = false) {
		$all = onecrm_p_get_all_modules();
		$saved = get_option('onecrm_p_dashboard_config');
		if ($limit_model) {
			$limit_module = onecrm_p_module_for_model($limit_model);
		} else {
			$limit_module = null;
		}
		$modules = array_filter($saved, function($m) use($limit_model, $limit_module) {
			return $m['enabled'] && (!$limit_model || $limit_module === $m['module']);
		});
		$html = '';
		
		$detail_available = function($m) use($all) {
			return count(array_filter($all, function($d) use($m) {
				return $d['model'] === $m;
			}));
		};
		
		$link_template_data = function($model = "", $id = "") {
			return ["MODEL" => $model, "RECORD" => $id];
		};

		foreach ($modules as $m) {
			$fields = array_map(function($f) {return $f['field'];},
				array_filter($m['list'], function($f) { return $f['enabled'];})
			);
			$defs = array_filter($all, function($d) use ($m) { return $d['module'] === $m['module'];});
			if (!$defs) continue;
			$def = current($defs);
			if (!empty($def['partners_only']) && !$this->is_partner) {
				continue;
			}
			if ($limit_model && !$no_breadcrumbs) {
				$title = '<a href="?">' . __("Dashboard", ONECRM_P_TEXTDOMAIN) . '</a> &raquo; ' . htmlspecialchars($def['plural']);
			} else {
				$title = '<a class="link" href="?full_list=' . $def['model'] . '">' . htmlspecialchars($def['plural']) . '</a>';
			}

			$attrs = [
				'model' => $def['model'],
				'title' => $title,
				'title_escaped' => true,
				'fields' => join(',', $fields),
				'detail_available' => $detail_available,
				'link_template_data' => $link_template_data,
				'link_template' => '?detailview={MODEL}&record={ID}',
				'limit_model' => $limit_model
			];

			if (!empty($def['add_filters']))
				$attrs['model_list_filters'] = $def['add_filters'];

			if (!empty($def['can_create'])) {
				$create_url = '?create=' . $def['model'];
				$create_button = '&nbsp;<button onclick="window.location.href=\'' . $create_url
					. '\'; return false;" class="onecrm-p-create button">' . __('Create New', ONECRM_P_TEXTDOMAIN) . '</button>';

				//If model has list filters, add create button in the FilterFormView
				//otherwise add create button to title
				if (!empty($def['add_filters'])) {
					$attrs['create_button'] = $create_button;
				} else {
					$attrs['title'] .= $create_button;
				}
			}

			if ($limit_model) {
				$page = (int)sanitize_key($_GET['page_number']);
				if ($page < 1)
					$page = 1;
				$perPage = 20;
				$attrs['count'] = $perPage;
				$attrs['start'] = ($page - 1) * $perPage;
				$attrs['paginate'] = true;
			}
			$lv = $this->listview_shortcode($attrs);
			if (strpos($lv, 'onecrm-p-exception') !== false) {
				return $lv;
			}
			$html .= $lv;
		}

		return $html;
	}
	
	public function dashboard_detail($model, $id, $no_breadcrumbs = false) {
		$html = '';
		$all = onecrm_p_get_all_modules();
		$saved = get_option('onecrm_p_dashboard_config');
		$module = onecrm_p_module_for_model($model);
		$modules = array_filter($saved, function($m) use($module) {
			return $m['enabled'] && $module === $m['module'];
		});
		if (empty($modules)) {
			return $html;
		}
		$module = current($modules);
		
		$defs = array_filter($all, function($d) use ($module) { return $d['module'] === $module['module'];});
		$def = current($defs);
		if (!empty($def['partners_only']) && !$this->is_partner) {
			return $html;
		}
		
		$detail_available = function($m) use($all) {
			return count(array_filter($all, function($d) use($m) {
				return $d['model'] === $m;
			}));
		};
		
		$link_template_data = function($model = "", $id = "") {
			return ["MODEL" => $model, "RECORD" => $id];
		};
		
		$fields = array_map(function($f) {return $f['field'];}, 
			array_filter($module['detail'], function($f) { return $f['enabled'];})
		);

		$title = '';
		if (!$no_breadcrumbs) {
			$title .= '<a href="?">' . __("Dashboard", ONECRM_P_TEXTDOMAIN) . '</a> &raquo; ';
		}
		$title .= '<a href="?full_list=' . $model . '">' . htmlspecialchars($def['plural']) . '</a>';

		if (! empty($def['pdf_print']))
			$title .= $this->render_pdf_link($model, $id);

		$attrs = [
			'model' => $model,
			'id' => $id,
			'fields' => $fields,
			'title_escaped' => true,
			'title' => $title,
			'detail_available' => $detail_available,
			'link_template_data' => $link_template_data,
			'link_template' => '?detailview={MODEL}&record={ID}',
			'columns' => 2,
		];
		$dv = $this->detailview_shortcode($attrs);
		if ($dv === false) {
			return '<div id="onecrm-p-errors" class="active"><div class="onecrm-p-error">' 
				. __('Customer Connection is not properly configured', ONECRM_P_TEXTDOMAIN)
				. '</div></div>'
				;
		}

		if (!empty($def['items']))
			$dv .= $this->render_items($model, $id);

		if (!empty($def['display_contacts']))
			$dv .= $this->render_contacts($model, $id);

		if (!empty($def['display_notes'])) {
			$dv .= $this->render_notes($model, $id, !empty($def['add_notes']));

			if (!empty($def['add_notes'])) {
				$dv .= '<div class="onecrm-p-add-note" id="add-note-container">';
				$dv .= '<br><form method="POST" enctype="multipart/form-data">';
				$ev = new Renderer\EditView([
					'detail_available' => null, 
					'model' => 'Note', 
					'columns' => 2,
					'format_input' => $fields,
				]);
				$dv .= $ev->render([
					'row' => [],
					'fields' => ['name', 'file', 'description'],
					'format_input' => ['file', 'name', 'description'],
					'fields_meta' => [
						'fields' => [
							'file' => [
								'name' => 'file',
								'type' => 'file_ref',
								'vname' => __('Attachment (optional)', ONECRM_P_TEXTDOMAIN),
							],
							'name' => [
								'name' => 'name',
								'type' => 'varchar',
								'len' => 40,
								'vname' => __('Note title', ONECRM_P_TEXTDOMAIN),
								'required' => true,
							],
							'description' => [
								'name' => 'description',
								'type' => 'text',
								'vname' => __('Details', ONECRM_P_TEXTDOMAIN),
							],
						],
					],
				]);
				$dv .= '<br><button class="button onecrm-p-create">' . __('Save Note', ONECRM_P_TEXTDOMAIN) . '</button><br>';
				list ($req, $signature) = onecrm_signed_request([
					'module' => $module['module'],
					'id' => $id,
					'return' => $_SERVER['REQUEST_URI'],
				]);
				$dv .= $ev->create_hidden_input('request', $req);
				$dv .= $ev->create_hidden_input('token', $signature);
				$dv .= $ev->create_hidden_input('onecrm_post_action', 'create_note');
				$dv .= '</form></div>';
			}
		}

		if ($model == 'Project')
			$dv .= $this->render_project_tasks($model, $id);

		if ($model == 'Invoice')
			$dv .= $this->render_payments($model, $id);

		return $dv;
	}

	private function render_pdf_link($model, $id, $personal_data = false) {
		$pdf_url = '?onecrm_pdf_generate='.$id.'&amp;model='.$model.'&amp;token=' . sha1(wp_get_session_token() . $id);
		if ($personal_data)
			$pdf_url .= '&amp;personal_data=1';

		$link = '<button onclick="window.open(\'' . $pdf_url . '\', \'_blank\');" class="onecrm-p-create button">' . __('Download PDF', ONECRM_P_TEXTDOMAIN) . '</button>';

		return $link;
	}

	private function render_items($model, $id) {
		if (!$client = $this->get_client()) return false;
		
		$all = onecrm_p_get_all_modules();
		$saved = get_option('onecrm_p_dashboard_config');
		$module = onecrm_p_module_for_model($model);
		$modules = array_filter($saved, function($m) use($module) {
			return $m['enabled'] && $module === $m['module'];
		});
		if (empty($modules)) {
			return "";
		}
		$module = current($modules);
		$fields = array_map(function($f) {return $f['field'];}, 
			array_filter($module['items'], function($f) { return $f['enabled'];})
		);
	
		$ret = '';
		try {
			$tally = $client->get("/tally/{$model}/{$id}");
			$multigroup = count($tally['groups']) > 1;
			$gnum = 1;
			foreach ($tally['groups'] as $g) {

				if (!strlen($g['name'])) {
					$g['name'] = sprintf(
						__(
							'Group %d' 
							/* translators: This is default quote/invoice group name. If a group name is empty, default name will be generated. %d is the group namber */
							, ONECRM_P_TEXTDOMAIN
						), 
					$gnum);
				}
				$gnum++;

				$gname = $multigroup ? $g['name'] : '';
				$ret .= '<h4 class="onecrm-p-tally-group-title">' . htmlspecialchars($gname) . '</h4>';


				$items = array_reduce($g['line_items'], function($carry, $item) {
					$carry[] = $item;
					if (!empty($item['sum_of_components'])) {
						foreach ($item['components'] as $comp) {
							$comp['name'] = '    ' . $comp['name'];
							$carry[] = $comp;
						}
					}
					return $carry;
				}, []);
				
				foreach ($items as &$li) {
					if (empty($li['is_comment'])) {
						$li['unit_price'] = Locale::format_currency($li['unit_price'], "symbol", false, '{amount}');
						$li['ext_price'] = Locale::format_currency($li['ext_price'], "symbol", false, '{amount}');
						$li['quantity'] = Locale::format_number($li['quantity']);
					}
				}

				$ListView = new Renderer\ListView([
					"detail_available" => false, 
				]);
				$ret .= $ListView->render([
					'rows' => $items,
					'fields' => $fields, 
					'fields_meta' => ['fields' => onecrm_p_get_items_fields()],
				]);

				if ($multigroup) {
					$ret .= '<h5 class="onecrm-p-tally-totals-line">';
					$ret .= sprintf(__('Subtotal: %0.2f' /* translators: %f will be replaced with group subtotal */, ONECRM_P_TEXTDOMAIN), $g['subtotal']);
					$ret .= '</h5>';
					foreach ($g['adjusts'] as $adj) {
						switch ($adj['type']) {
						case 'StandardTax':
							if ($adj['value'] >= 0.01) {
								$ret .= '<h5 class="onecrm-p-tally-totals-line">';
								$ret .= htmlspecialchars(sprintf(__(
									'%1$s (%2$0.2f%%): %3$0.2f'
									/*
									translators: 
									3 arguments: %1$s will be replaced with tax name, %2$0.2f will be replaced with tax rate, second %3$0.2f will be replaced with tax amount. Note % after first %f!
									 */
									, ONECRM_P_TEXTDOMAIN), $adj['name'], $adj['rate'], $adj['value']));
								$ret .= '</h5>';
							}
							break;
						case 'TaxedShipping':
						case 'UntaxedShipping':
							if ($adj['value'] >= 0.01) {
								$ret .= '<h5 class="onecrm-p-tally-totals-line">';
								$ret .= htmlspecialchars(sprintf(
									__(
										'Shipping: %0.2f'
										/* translators: %0.2f will be replaced with shipping amount */
										, ONECRM_P_TEXTDOMAIN
									), $adj['value']));
								$ret .= '</h5>';
							}
							break;

						}
					}
					$ret .= '<h5 class="onecrm-p-tally-totals-line">';
					$ret .= sprintf(__('Total: %0.2f' /* translators: %0.2f will be replaced with quote/invoice total amount */ , ONECRM_P_TEXTDOMAIN), $g['total']);
					$ret .= '</h5>';
				}
				
			}

			if ($multigroup) {
				$ret .= '<hr>';
			}
			$ret .= '<h4 class="onecrm-p-tally-totals-header">' . __('Totals', ONECRM_P_TEXTDOMAIN) . '</h4>';

			$ret .= '<h5 class="onecrm-p-tally-totals-line">';
			$ret .= sprintf(__('Subtotal: %0.2f', ONECRM_P_TEXTDOMAIN), $tally['totals']['subtotal']);
			$ret .= '</h5>';

			if ($tally['totals']['discount'] >= 0.01) {
				$ret .= '<h5 class="onecrm-p-tally-totals-line">';
				$ret .= sprintf(__('Discount: %0.2f' /* translators: %0.2f will be replaced with discount amount */, ONECRM_P_TEXTDOMAIN), $tally['totals']['discount']);
				$ret .= '</h5>';
			}

			foreach ([true, false] as $t) {
				if ($tally['totals']['shipping_taxed'] == $t &&  $tally['totals']['shipping'] >= 0.01) {
					$ret .= '<h5 class="onecrm-p-tally-totals-line">';
					$ret .= sprintf(__('Shipping: %0.2f', ONECRM_P_TEXTDOMAIN), $tally['totals']['shipping']);
					$ret .= '</h5>';
				}
				if ($tally['totals']['shipping_taxed'] != $t) {
					foreach ($tally['totals']['taxes'] as $tax) {
						if ($tax['amount'] >= 0.01) {
							$ret .= '<h5 class="onecrm-p-tally-totals-line">';
							$ret .= htmlspecialchars(sprintf(__('%1$s (%2$0.2f%%): %3$0.2f', ONECRM_P_TEXTDOMAIN), $tax['name'], $tax['rate'], $tax['amount']));
							$ret .= '</h5>';
						}
					}
				}
			}
			$ret .= '<h5 class="onecrm-p-tally-totals-line">';
			$ret .= sprintf(__('Total: %0.2f', ONECRM_P_TEXTDOMAIN), $tally['totals']['total']);
			$ret .= '</h5>';

		} catch (APIError $e) {
			return $e->getMessage();
		}
		return $ret;
	}

	private function render_contacts($parent_model, $parent_id) {
		$result = '<br>';

		$result .= $this->listview_shortcode([
			'model' => 'Contact',
			'parent_model' => $parent_model,
			'link' => 'contacts',
			'parent_id' => $parent_id,
			'no_restrictions' => true,
			'title' => __('Contacts', ONECRM_P_TEXTDOMAIN),
			'fields' => ['name','email1', 'phone_work', 'primary_account'],
			'detail_available' => function() {return false;},
			'count' => 200,
		]);

		return $result;
	}

	private function render_notes($parent_model, $parent_id, $add_note = false) {
		$result = '<br>';

		$title = __('Notes', ONECRM_P_TEXTDOMAIN);
		if ($add_note)
			$title .= '<button class="onecrm-p-create button" id="expand-noteform">' . __('Add Note', ONECRM_P_TEXTDOMAIN) . '</button>';

		$result .= $this->listview_shortcode([
			'model' => 'Note',
			'parent_model' => $parent_model,
			'link' => 'notes',
			'parent_id' => $parent_id,
			'no_restrictions' => true,
			'title' => $title,
			'title_escaped' => true,
			'fields' => ['name', 'description', 'filename'],
			'detail_available' => function() {return false;},
			'count' => 200,
			'download_url' => function($model, $id) {
				$token = wp_get_session_token();
				return '?onecrm_file_download=' . $id . '&amp;token=' . sha1($token . $id);
			},
			'filters' => [
				'portal_flag' => 1,
			],
			'order' => 'date_entered',
		]);

		return $result;
	}

	private function render_project_tasks($parent_model, $parent_id) {
		$result = '<br>';

		$result .= $this->listview_shortcode([
			'model' => 'ProjectTask',
			'parent_model' => $parent_model,
			'link' => 'project_tasks',
			'parent_id' => $parent_id,
			'no_restrictions' => true,
			'title' => __('Tasks', ONECRM_P_TEXTDOMAIN),
			'fields' => ['name', 'date_start', 'date_due', 'status', 'percent_complete'],
			'detail_available' => function() {return false;},
			'count' => 200,
		]);

		return $result;
	}

	private function render_payments($parent_model, $parent_id) {
		$result = '<br>';

		$result .= $this->listview_shortcode([
			'model' => 'Payment',
			'parent_model' => $parent_model,
			'link' => 'payments',
			'parent_id' => $parent_id,
			'no_restrictions' => true,
			'title' => __('Payments', ONECRM_P_TEXTDOMAIN),
			'fields' => ['full_number', 'payment_type', 'applied_amount', 'payment_date'],
			'detail_available' => function() {return false;},
			'count' => 200,
		]);

		return $result;
	}

	public function dashboard_create($model, $no_breadcrumbs = false) {
		$html = '';
		$all = onecrm_p_get_all_modules();
		$saved = get_option('onecrm_p_dashboard_config');
		$module = onecrm_p_module_for_model($model);
		$modules = array_filter($saved, function($m) use($module) {
			return $m['enabled'] && $module === $m['module'];
		});
		if (empty($modules)) {
			return $html;
		}
		$module = current($modules);

		$defs = array_filter($all, function($d) use ($module) { return $d['module'] === $module['module'];});
		$def = current($defs);
		if (empty($def['can_create'])) {
			return $html;
		}
		if (!empty($def['partners_only']) && !$this->is_partner) {
			return $html;
		}

		$fields = array_map(function($f) {return $f['field'];}, 
			array_filter($module['create'], function($f) { return $f['enabled'];})
		);

		$attr = [
			'model' => $model,
			'fields' => $fields,
			'columns' => 2,
		];
		$title = '';
		if (!$no_breadcrumbs) {
			$title = '<a href="?">' . __("Dashboard", ONECRM_P_TEXTDOMAIN) . '</a> &raquo; ';
		}
		$title .= '<a href="?full_list=' . $model . '">' . htmlspecialchars($def['plural']) . '</a>';
		return 
			"<h3>{$title}</h3>" .
			$this->onecrm_editor_start_shortcode(['action' => 'onecrm_p_model_create']) .
		   	$this->onecrm_fieldset_shortcode($attr) .
			$this->onecrm_editor_end_shortcode(['submit' => 'Save']) 
			;

	}

	public function dashboard_edit($model, $id, $no_breadcrumbs = false) {
		$html = '';
		$all = onecrm_p_get_all_modules(true);
		$saved = get_option('onecrm_p_dashboard_config');

		if ($model == 'Contact') {
			$module = onecrm_p_get_contacts_data();
			$modules = [$module];
			$no_list = true;
		} else {
			$module = onecrm_p_module_for_model($model);
			$modules = array_filter($saved, function($m) use($module) {
				return $m['enabled'] && $module === $m['module'];
			});
			$no_list = false;
		}

		if (empty($modules)) {
			return $html;
		}
		$module = current($modules);

		$defs = array_filter($all, function($d) use ($module) { return $d['module'] === $module['module'];});
		$def = current($defs);
		if (empty($def['can_create'])) {
			return $html;
		}
		if (!empty($def['partners_only']) && !$this->is_partner) {
			return $html;
		}


		$fields = array_map(function($f) {return $f['field'];},
			array_filter($module['create'], function($f) { return $f['enabled'];})
		);

		if (empty($fields))
			$fields = $module['create']['fixed_fields'];

		$attr = [
			'id' => $id,
			'model' => $model,
			'fields' => $fields,
			'columns' => 2,
		];

		$title = '';
		if (!$no_breadcrumbs) {
			$title = '<a href="?">' . __("Dashboard", ONECRM_P_TEXTDOMAIN) . '</a> &raquo; ';
		}
		if (! $no_list) {
			$title .= '<a href="?full_list=' . $model . '">' . htmlspecialchars($def['plural']) . '</a>';
		} else {
			$title .= htmlspecialchars($def['plural']);
		}

		$hidden = '';
		if (!empty($_GET['return_to'])) {
			$hidden .= '<input type="hidden" name="return_to" value="' . htmlspecialchars(sanitize_text_field($_GET['return_to'])) . '">';
		}

		return
			"<h3>{$title}</h3>" .
			$this->onecrm_editor_start_shortcode(['action' => 'onecrm_p_model_save']) .
			$this->onecrm_fieldset_shortcode($attr) .
			$hidden .
			$this->onecrm_editor_end_shortcode(['submit' => 'Save'])
			;
	}

	public function dashboard_personal_data($model, $id, $format, $no_breadcrumbs = false) {
		$html = '';
		$all = onecrm_p_get_all_modules(true);
		$saved = get_option('onecrm_p_dashboard_config');

		if ($model == 'Contact') {
			$module = onecrm_p_get_contacts_data();
			$modules = [$module];
		} else {
			$module = onecrm_p_module_for_model($model);
			$modules = array_filter($saved, function($m) use($module) {
				return $m['enabled'] && $module === $m['module'];
			});
		}

		if (empty($modules))
			return $html;

		$module = current($modules);

		$defs = array_filter($all, function($d) use ($module) { return $d['module'] === $module['module'];});
		$def = current($defs);

		if (empty($def['personal_data']))
			return $html;

		$title = '';
		if (!$no_breadcrumbs) {
			$title .= '<a href="?">' . __("Dashboard", ONECRM_P_TEXTDOMAIN) . '</a> &raquo; ';
		}

		if($format == 'erase') {
			$label = 'Erase Personal Data';
			$pdf = '';
		} else {
			$label = 'Personal Data';
			$pdf = $this->render_pdf_link($model, $id, true);
		}

		$title .= htmlspecialchars($def['plural']) .' &raquo; '. __($label, ONECRM_P_TEXTDOMAIN);
		$title .= $pdf;

		$attrs = [
			'model' => $model,
			'id' => $id,
			'fields' => $this->get_personal_data_fields($model),
			'title_escaped' => true,
			'title' => $title,
			'detail_available' => false,
			'columns' => 2,
		];

		if ($format == 'erase') {

			$attrs['render_type'] = 'erase_personal';

			return
				$this->onecrm_editor_start_shortcode(['action' => 'onecrm_p_personal_data_erase']) .
				$this->detailview_shortcode($attrs, false) .
				$this->onecrm_editor_end_shortcode(['submit' => 'Erase'])
				;

		} else {
			$dv = $this->detailview_shortcode($attrs, false);
			if ($dv === false) {
				return '<div id="onecrm-p-errors" class="active"><div class="onecrm-p-error">'
					. __('Customer Connection is not properly configured', ONECRM_P_TEXTDOMAIN)
					. '</div></div>'
					;
			}

			return $dv;
		}
	}

	private function get_personal_data_fields($model) {
		$fields = [];
		if (!$client = $this->get_client()) return $fields;

		$model_obj = $client->model($model);

		try {
			$fields_meta = $model_obj->metadata();
		} catch (\Exception $e) {
			return $fields;
		}

		if (empty($fields_meta['fields']))
			return $fields;

		foreach ($fields_meta['fields'] as $field) {
			if (! empty($field['personal_info']))
				$fields[] = $field['name'];
		}

		return $fields;
	}

	private function add_custom_fields($model_name, $fields, &$fields_meta, &$rows) {
		$single_row = false;

		if ($model_name == 'Invoice') {
			$paypal_link = get_option('onecrm_paypal_link');

			if (in_array('paypal_button', $fields) && ! empty($paypal_link)) {

				if (! isset($fields_meta['paypal_button']))
					$this->add_cutom_field_metadata($fields_meta, 'paypal_button');

				if (! isset($rows[0])) {
					$rows = [$rows];
					$single_row = true;
				}

				foreach ($rows as &$row) {
					if (! isset($row['amount_due']) || $row['amount_due'] <=0)
						continue;

					$item_name = $row['full_number'] .'-'. $row['name'] .' ('. $row['currency'] . $row['amount_due'] .')';
					$item_name = urlencode($item_name);

					$link = $paypal_link .
						'&amount=' . $row['amount_due'] .
						'&item_name=' . $item_name;

					$row['paypal_button'] = '<a href="'.$link.'" target="_blank" title="PayPal"><img border="0" width="84" height="26" src="'.ONECRMP_PLUGIN_URL.'/css/images/paypal_button.png" alt="PayPal"></a>';
				}

				if ($single_row && isset($rows[0]))
					$rows = $rows[0];
			}
		}
	}

	private function add_cutom_field_metadata(&$fields_meta, $field) {
		if ($field == 'paypal_button' && !isset($fields_meta['fields']['paypal_button'])) {
			$fields_meta['fields']['paypal_button'] = [
				'vname' => __('PayPal', ONECRM_P_TEXTDOMAIN),
				'type' => 'html',
				'name' => 'paypal_button'
			];
		}
	}

	function displayAuthErrors($errors) {
		if (in_array('login', $errors)) {
			$ret = '<p>';
			$ret .= __('You need to login in order to access this area', ONECRM_P_TEXTDOMAIN);
			$ret .= '</p>';
			$ret .= '<div style="display:none;" class="onecrm-p-exception">';
			return $ret;
		}
		if (in_array('account', $errors) || in_array('contact', $errors)) {
			$ret = '<p>';
			$ret .= __('Please provide your contact details to access this area', ONECRM_P_TEXTDOMAIN);
			$ret .= '</p>';
			$ret .= '<form method="POST">';
			$ev = new Renderer\EditView([
				'detail_available' => null, 
				'model' => 'Note',
				'columns' => 2,
			]);
			$user_id = get_current_user_id();
			$company_name = get_user_meta( $user_id, 'onecrm_p_company_name', true);
			$first_name = get_user_meta( $user_id, 'first_name', true);
			$last_name = get_user_meta( $user_id, 'last_name', true);

			$ret .= $ev->render([
				'row' => [
					'company_name' => $company_name,
					'first_name' => $first_name,
					'last_name' => $last_name,
				],
				'columns' => 1,
				'fields' => ['first_name', 'last_name', 'company_name'],
				'format_input' => ['company_name', 'first_name', 'last_name'],
				'fields_meta' => [
					'fields' => [
						'first_name' => [
							'name' => 'first_name',
							'type' => 'varchar',
							'vname' => __('First name', ONECRM_P_TEXTDOMAIN),
						],
						'last_name' => [
							'name' => 'last_name',
							'type' => 'varchar',
							'vname' => __('Last name', ONECRM_P_TEXTDOMAIN),
						],
						'company_name' => [
							'name' => 'company_name',
							'type' => 'varchar',
							'vname' => __('Company name', ONECRM_P_TEXTDOMAIN),
						],
					],
				],
			]);
			$ret .= '<br><button class="onecrm-p-create button">' . __('Send', ONECRM_P_TEXTDOMAIN) . '</button><br>';
			list ($req, $signature) = onecrm_signed_request([
				'return' => $_SERVER['REQUEST_URI'],
			]);
			$ret .= $ev->create_hidden_input('request', $req);
			$ret .= $ev->create_hidden_input('token', $signature);
			$ret .= $ev->create_hidden_input('onecrm_post_action', 'set_account_name');
			$ret .= '</form></div>';
			$ret .= '<div style="display:none;" class="onecrm-p-exception">';
			return $ret;
		}
	}

	private function query_partner_status() {
		try {
			$client = $this->get_client(true);
			if (!$client) {
				$this->is_partner = false;
				return;
			}
			$user = $client->me();
			$this->is_partner = !empty($user['is_partner']);
		} catch (\Exception $e) {
			$this->is_partner = false;
		}
	}

	private function add_input_filters(&$filter, $model_filters, $def) {
		$token = wp_get_session_token();
		if (sha1($token . sanitize_text_field($_POST['request'])) !== sanitize_key($_POST['token']))
			return false;

		if (isset($_POST['filters']) && is_array($_POST['filters'])) {
			$result = [];
			$input = array_map('onecrm_p_sanitize_postdata', $_POST['filters']);

			foreach ($model_filters as $item) {
				if (! isset($def[$item]))
					continue;

				if ($def[$item]['type'] == 'date') {
					if (isset($input[$item . '-start']) && isset($input[$item . '-end'])) {
						$result[$item] = $input[$item . '-start'];
						$result[$item . '-end'] = $input[$item . '-end'];
						$result[$item . '-period'] = 'week';
						$result[$item . '-operator'] = 'between_dates';
					}
				} else {
					if (isset($input[$item]))
						$result[$item] = $input[$item];
				}
			}

			if (is_array($filter['filters'])) {
				$filter['filters'] = array_merge($filter['filters'], $result);
			} else {
				$filter['filters'] = $result;
			}
		}

		return true;
	}
}
