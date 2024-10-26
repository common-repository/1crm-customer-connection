<?php



require_once dirname(__FILE__) . '/options.php';
require_once dirname(__FILE__) . '/dashboard.php';
require_once dirname(__FILE__) . '/shortcodes.php';
require_once dirname(__FILE__) . '/booking.php';

add_action('admin_enqueue_scripts', 'onecrm_p_enqueue_scripts');

function onecrm_p_enqueue_scripts() {
	wp_enqueue_style('admin-styles', ONECRMP_PLUGIN_URL.'/css/admin.css');
	wp_enqueue_script('admin-scripts', ONECRMP_PLUGIN_URL.'/js/admin.js');
	wp_localize_script('admin-scripts', 'onecrm_p',
		['booking_sync_running' => __( 'The synchronization process is running. Please wait.', ONECRM_P_TEXTDOMAIN )]
	);
}

if ( ! function_exists( 'onecrm_kb_search' ) ) {
	function onecrm_kb_search() {
		echo SearchKBArticles(sanitize_text_field($_POST['search']));
	}
	add_action('wp_ajax_onecrm_kb_search',  'onecrm_kb_search');
	add_action('wp_ajax_nopriv_onecrm_kb_search',  'onecrm_kb_search');
}

if ( ! function_exists( 'SearchKBArticles' ) ) {
	$rows = [];
	function SearchKBArticles($search_string) {
		try {

			$model_name = 'KBArticle';
			$one_crm = \OneCRM\Portal\Auth\OneCrm::instance();
			$client = $one_crm->getAdminClient();
			$model = $client->model($model_name);
			
			$fields = ['name','slug','summary', 'category'];

			$opts = [
				'fields' => $fields,
				'filter_text' => $search_string,
			];
			$result = $model->getList($opts, 0, 200);
			$rows = $result->getRecords();

		} catch (APIError $e) {
		}
		header('Content-type: application/json');
		die(json_encode($rows));
	}
}

if ( ! function_exists( 'get_subcategories' ) ) {
	function get_subcategories() {
		try {
			$parent_id = sanitize_text_field($_POST['parent_id']);
			$model_name = 'KBCategory';
			$one_crm = \OneCRM\Portal\Auth\OneCrm::instance();
			$client = $one_crm->getAdminClient();
			$model = $client->model($model_name);
			$opts = [
				'fields' => ['name','slug','description','parent_id'],
				'filters' => ['parent_id' => $parent_id],
			];
			$result = $model->getList($opts, 0, 200);
			$rows = $result->getRecords();
			

			$html = '';

			foreach($rows as $row) {
				if($row['parent_id'] == $parent_id)
					$html .= '<div id="'.$row['id'].'">--<input type="checkbox" name="categories_searched" value="'.$row['id'].'" unchecked>'.$row['name'].'</div><br>';
			}
		} catch (APIError $e) {
			$html = __("Error loading articles list", ONECRM_P_TEXTDOMAIN);
		}
		echo $html;
	}
	add_action('wp_ajax_nopriv_get_subcategories',  'get_subcategories');
	add_action('wp_ajax_get_subcategories',  'get_subcategories');
}

