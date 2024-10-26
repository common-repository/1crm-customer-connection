<?php
/*
Plugin Name: 1CRM Connect
*/

use OneCRM\APIClient\Authentication;
use OneCRM\APIClient;
use OneCRM\APIClient\Error as APIError;

require_once __DIR__ . '/kb_css.php';

if ( ! function_exists( 'onecrm_kb_search' ) ) {
	function onecrm_kb_search() {
		echo 'test';
	}
	add_action('wp_ajax_onecrm_kb_search',  'onecrm_kb_search');
	add_action('wp_ajax_nopriv_onecrm_kb_search',  'onecrm_kb_search');
}

function onecrm_p_get_urls() {
	$did = (int)get_option( 'onecrm_help_detail_page' );
	$iid = (int)get_option( 'onecrm_help_index_page' );
	$detail_url = get_the_permalink($did);
	$index_url = get_the_permalink($iid);
	return compact('detail_url', 'index_url');
}

if ( ! function_exists( 'getCategoryContent' ) ) {
	function makeCategoryIcons($row) {
		$icon1 = $icon2 = '';
		if (!empty($row['__full_image_filename'])) {
			$icon1 = '<img src="' . $row['__full_image_filename'] . '">';
		} elseif (!empty($row['category_icon'])) {
			$icon2 = '<i class="' . $row['category_icon'] . '"></i>';
		}
		return [$icon1, $icon2];
	}
	function getCategoryContent($id) {
		try {
			$one_crm = \OneCRM\Portal\Auth\OneCrm::instance();

			extract(onecrm_p_get_urls());

			$num_articles = 0;
			$num_categories = 0;

			$model_name = 'KBCategory';
			$one_crm = \OneCRM\Portal\Auth\OneCrm::instance();
			$client = $one_crm->getAdminClient();
			if (!$client) {
				return 'Internal error';
			}
			$model = $client->model($model_name);
			$opts = [
				'fields' => ['name','description', 'image', 'category_icon', 'categories_count', 'articles_count', 'parent_id'],
				'order' => 'display_order, name',
			];
			if ($id) {
				$result = $model->getRelated($id, 'subcategories', $opts);
			} else {
				$opts['filters'] = ['toplevel' => true, 'portal_active' => 1];
				$result = $model->getList($opts);
			}

			if ($id) {
				$category_row = $model->get($id, ['category_icon', 'image', 'name', 'description', 'parent_id', 'image', 'category_icon']);
				$html = '<div class="onecrm-p-breadcrumbs">';
				$html .= '<a href="' . $index_url . '?">';
				$html .= __('Topics', ONECRM_P_TEXTDOMAIN);
				$html .= '</a>';
				$path = [];
				if (!empty($category_row['__path'])) {
					$path = @json_decode($category_row['__path'], true);
				}
				if (empty($path)) $path = [];
				$path[] = $category_row;
				foreach ($path as $entry) {
					list($icon1, $icon2) = makeCategoryIcons($entry);
					$html .= '&nbsp;&raquo;&nbsp;' . $icon1 . '<a href="' . $detail_url . '?model=KBCategory&id='.$entry['id'].'"> '. $icon2 . htmlspecialchars($entry['name']).'</a>';
				}
				$html .= '</div>';
			}

			$rows = $result->getRecords();

			if($rows) {
				if ($id) {
					$html .= '<h2>' . __('Topics', ONECRM_P_TEXTDOMAIN) . '</h2>';
				}
				$extra_class = $id ? "" : "lower";
				$html .= '<div class="kbwrapper ' . $extra_class . '" id="wrapperdiv">';
				foreach($rows as $row) {
					$num_articles = $row['articles_count'];
					$num_categories = $row['categories_count'];
					if ($num_categories + $num_articles == 0) {
						$counters  = __('No content', ONECRM_P_TEXTDOMAIN);
					} else {
						$counters = [];
						if ($num_categories == 1)
							$counters[] = __('1 Topic', ONECRM_P_TEXTDOMAIN);
						else if ($num_categories > 0)
							$counters[] = sprintf(__('%d Topics' /* translators: %d is the number of topics  */, ONECRM_P_TEXTDOMAIN), $num_categories);
						if ($num_articles == 1)
							$counters[] = __('1 Article', ONECRM_P_TEXTDOMAIN);
						else if ($num_articles > 0)
							$counters[] = sprintf(__('%d Articles' /* translators: %d is the number of articles */, ONECRM_P_TEXTDOMAIN), $num_articles);
						$counters = join(', ', $counters);
					}

					list($icon1, $icon2) = makeCategoryIcons($row);
					$html .= '<div class="kbbox onecrm-p-category" data-id="'.$row['id'].'">
								<div class="onecrm-kb-head-wrapper">' . $icon1 . '
								<h2>' . $icon2 . htmlspecialchars($row['name']) . '</h2></div>
								<div class="onecrm-p-summary-container">
									<p class="onecrm-p-summary">' . nl2br(htmlspecialchars($row['description'])) . '</p>
									<p class="onecrm-p-counter">' . $counters . '</p>
								</div>
							</div>';
				}

				$html .= '</div>';
			}

			if ($id) {
				$fields = [
					'fields' => ['name','summary', 'category_id'],
					'order' => 'display_order, name',
					'filters' => [
						'portal_active' => 1,
					],
				];
				$result = $model->getRelated($id, 'articles', $fields);
				$rows = $result->getRecords();

				if ($rows) {	
					$html .= '<h2>' . __('Articles', ONECRM_P_TEXTDOMAIN) . '</h2>

							<div class="kbwrapper" id="articlewrapperdiv">';

					foreach($rows as $row) {
						$html .= '<div class="kbbox onecrm-p-article" data-id="' .$row['id'] . '">
									<h2>' . htmlspecialchars($row['name']) . '</h2>
									<div class="onecrm-p-summary-container">
										<p class="onecrm-p-summary">' . nl2br(htmlspecialchars($row['summary'])) . '</p>
									</div>
								</div>';
					}

					$html .= '</div>';
				}
			}
			$totalEntries += count($rows);
			if (!$totalEntries) {
				$html .= '<div class="onecrm-p-no-content">' . __('No content in this category', ONECRM_P_TEXTDOMAIN) . '</div>';
			}
		} catch (APIError $e) {
			$html = __("Error loading articles list", ONECRM_P_TEXTDOMAIN);
		}
		return $html;
	}
}

if ( ! function_exists( 'getSingleKBArticle' ) ) {
	function getSingleKBArticle($id) {
		try {
			$model_name = 'KBArticle';
			$one_crm = \OneCRM\Portal\Auth\OneCrm::instance();
			$client = $one_crm->getAdminClient();
			if (!$client) {
				return 'Internal error';
			}
			$model = $client->model($model_name);
			$fields = ['name', 'category', 'description', 'category_id', 'summary'];
			$row = $model->get($id, $fields);

			extract(onecrm_p_get_urls());

			$html = '<div class="onecrm-p-breadcrumbs">';

			$html .= '<a href="' . $index_url . '?">';
			$html .= __('Topics', ONECRM_P_TEXTDOMAIN);
			$path = [];
			if (!empty($row['__path'])) {
				$path = @json_decode($row['__path'], true);
			}
			if (empty($path)) $path = [];
			foreach ($path as $entry) {
				list($icon1, $icon2) = makeCategoryIcons($entry);
				$html .= '&nbsp;&raquo;&nbsp;' . $icon1 . '<a href="' . $detail_url . '?model=KBCategory&id='.$entry['id'].'"> '. $icon2 . htmlspecialchars($entry['name']).'</a>';
			}

			$html .= '</a>&nbsp;&raquo;&nbsp;<a href="' . $detail_url . '?model=KBArticle&id='.$row['id'].'">' . htmlspecialchars($row['name']) . '</a>';
			$html .= '</div>';

			$value = '<h2>' . htmlspecialchars($row['name']) . '</h2>';
			if (strlen(trim($row['summary']))) {
				$value .= '<p class="onecrm-article-summary-container"><span style="text-decoration: underline"><b>Summary:</b></span>';
				$value .= '<span class="onecrm-article-summary-content">' . nl2br(htmlspecialchars($row['summary'])) .'</span></p>';
			}

			$value .= str_replace("onecrm:","onecrm_",$row['description']);

			if($value != '') {
				$doc = new DOMDocument();
				libxml_use_internal_errors(true);
				$doc->loadHTML('<?xml encoding="utf-8" ?>' . $value);

				$result = $doc->getElementsByTagName('onecrm_article');
				$nodes = [];
				foreach ($result as $node) {
					$nodes[] = $node;
				}
				foreach($nodes as $node) {
					$aid = $node->getAttribute('id');
					$newNode = new DOMElement('a');
					$node->parentNode->replaceChild($newNode, $node);
					$newNode->setAttribute('class', 'onecrm-p-internal');
					$newNode->setAttribute('href', $detail_url . "?model=KBArticle&id=$id");
					foreach ($node->childNodes as $c) {
						$newNode->appendChild($c);
					}
				}
				$result = $doc->getElementsByTagName('onecrm_category');
				$nodes = [];
				foreach ($result as $node) {
					$nodes[] = $node;
				}
				foreach($nodes as $node) {
					$aid = $node->getAttribute('id');
					$newNode = new DOMElement('a');
					$node->parentNode->replaceChild($newNode, $node);
					$newNode->setAttribute('class', 'onecrm-p-internal');
					$newNode->setAttribute('href', $detail_url . "?model=KBCategory&id=$id");
					foreach ($node->childNodes as $c) {
						$newNode->appendChild($c);
					}
				}
				$value = $doc->saveHTML();
			}

			$html .= '<div class="onecrm-p-article-content">'.$value.'</div>';
		} catch (APIError $e) {
			$html = __("Error loading article", ONECRM_P_TEXTDOMAIN);
		}
		return $html;
	}
}

if ( ! function_exists( 'getKBArticleDocs' ) ) {
	function getKBArticleDocs($id) {
		try {
			$model_name = 'KBArticle';
			$one_crm = \OneCRM\Portal\Auth\OneCrm::instance();
			$client = $one_crm->getAdminClient();
			if (!$client) {
				return 'Internal error';
			}
			$model = $client->model($model_name);
			$result = $model->getRelated($id, 'documents', ['fields' => ['id']]);

			$doc_ids = $result->getRecords();
			$doc_model = $client->model('Document');
			$doc_rev_model = $client->model('DocumentRevision');
			$html = '';
			if (!empty($doc_ids)) {
				$html .= '<div class="onecrm-p-article-docs"><span style="text-decoration: underline"><b>Documents:</b></span><br>';
				$wp_token = wp_get_session_token();
				foreach($doc_ids as $doc_id) {
					$doc_rev_id = $doc_model->get($doc_id['id'], ['document_revision_id']);
					$doc = $doc_rev_model->get($doc_rev_id['document_revision_id']);
					$html .= '<a target="_blank" href="?onecrm_file_download=' . $doc_id['id'] . '&token=' . sha1($wp_token . $doc_id['id']) . '&doc=1&type=' . $doc['file_mime_type'] . '">' . $doc['filename'] . '</a> (Revision: ' . $doc['revision']. ')<br>';
				}
				$html .= '</div>';
			}
		} catch (APIError $e) {
			$html = __("Error loading article documents", ONECRM_P_TEXTDOMAIN);
		}
		return $html;
	}
}

if ( ! function_exists( 'getKBArticleNotes' ) ) {
	function getKBArticleNotes($id) {
		try {
			$model_name = 'KBArticle';
			$one_crm = \OneCRM\Portal\Auth\OneCrm::instance();
			$client = $one_crm->getAdminClient();
			if (!$client) {
				return 'Internal error';
			}
			$model = $client->model($model_name);
			$result = $model->getRelated($id, 'notes', []);
			$note_ids = $result->getRecords();
			$note_model = $client->model('Note');
			$html = '';
			if (!empty($note_ids)) {
				$html .= '<div class="onecrm-p-article-docs"><span style="text-decoration: underline"><b>Notes:</b></span><br>';
				$wp_token = wp_get_session_token();
				foreach($note_ids as $note_id) {
					$note = $note_model->get($note_id['id']);
					$html .= '<span style="text-decoration: underline">' . htmlspecialchars($note['name']) . ':</span> <span class="onecrm-article-summary-content">' . nl2br(htmlspecialchars($note['description'])) .'</span>';
					if ($note['filename']) {
						$html .= '<br>Attachment: <a target="_blank" href="?onecrm_file_download=' . $note['id'] . '&token=' . sha1($wp_token . $note['id']) . '">' . $note['filename'] . '</a><br>';
					}
				}
				$html .= '</div>';
			}
		} catch (APIError $e) {
			$html = __("Error loading article notes", ONECRM_P_TEXTDOMAIN);
		}
		return $html;
	}
}

if ( ! function_exists( 'getKBSearch' ) ) {
	function getKBSearch() {
		extract(onecrm_p_get_urls());
		$html = '<script>var onecrm_p_ajaxurl = ' . json_encode(admin_url( 'admin-ajax.php', 'relative' )) . ';</script>';
		$html .= '<script>var onecrm_p_detail_url = ' . json_encode($detail_url) . ';</script>';
		$html .= '<script>var onecrm_p_index_url = ' . json_encode($index_url) . ';</script>';
		$html .= '<div class="onecrmhelp ui-front' . $themed . ' onecrm-p-searchbar">';
		$html .= '<input type="text" name="search-help" id="search-help" placeholder="Search..." />';
		$html .= '</div>';
		return $html;
	}
}

if ( ! function_exists( 'getKBSearchInline' ) ) {
	function getKBSearchInline() {
		$html = '<script>var onecrm_p_ajaxurl = ' . json_encode(admin_url( 'admin-ajax.php', 'relative' )) . ';</script>';
		$html .= '<input type="text" name="search-help" id="search-help" class="onecrm-p-search-inline" placeholder="Search..." />';
		return $html;
	}
}

if ( ! function_exists( 'getKBArticles' ) ) {
	function getKBArticles() {
		wp_enqueue_style('helpmod-styles', ONECRMP_PLUGIN_URL.'/css/kb_style.css');
		add_action('wp_footer',function() { // load this as late as possible:
			wp_enqueue_script('helpmod-scripts', ONECRMP_PLUGIN_URL . '/js/kbmodule.js', array('jquery', 'jquery-ui-autocomplete'));
		});

		$themed = get_option('onecrm_help_theme_col') ? 'onecrm-p-themed' : '';
		try {
			$model_name = 'KBCategory';
			$one_crm = \OneCRM\Portal\Auth\OneCrm::instance();
			$client = $one_crm->getAdminClient();
			if (!$client) {
				return 'Internal error';
			}
			$model = $client->model($model_name);
			$opts = [
				'fields' => ['name','description','parent_id', 'image', 'category_icon'],
				'order' => 'display_order, name',
			];
			$result = $model->getList($opts, 0, 200);
			$rows = $result->getRecords();

			$html = '<style>';
			$html .= onecrm_p_generate_kb_css();
			$html .= '</style>';

			extract(onecrm_p_get_urls());
			$post = get_post();
			$post_url = get_the_permalink($post);
			$in_detail = $post_url != $index_url || $detail_url == $index_url;
			$inline_class = $in_detail ? ' inline-search ' : '';
				
			$html .= '<script>var onecrm_p_ajaxurl = ' . json_encode(admin_url( 'admin-ajax.php', 'relative' )) . ';</script>';
			$html .= '<script>var onecrm_p_detail_url = ' . json_encode($detail_url) . ';</script>';
			$html .= '<script>var onecrm_p_index_url = ' . json_encode($index_url) . ';</script>';

			$html .= '<div class="onecrmhelp ' .  $inline_class . $themed . '" id="listArticles">';	

			$html .= '<div id="onecrm_article_listall" class="ui-front">';
			
			$html .= '<div class="onecrm-p-search-wrapper">';

			if ($in_detail) {
				$html .= getKBSearchInline();
			}
			$html .= '<div class="onecrm-p-view-icons">';
			$html .= '<div class="onecrm-p-view-grid-outer"><div class="onecrm-p-view-grid"></div></div>';
			$html .= '<div class="onecrm-p-view-list-outer"><div class="onecrm-p-view-list"></div></div>';
			$html .= '</div>';
			$html .= '</div>';

			$html .= '<script>var onecrm_p_ajaxurl = ' . json_encode(admin_url( 'admin-ajax.php', 'relative' )) . ';</script>';

			if(isset($_GET['model']) && isset($_GET['id'])) {
				$model = sanitize_text_field($_GET['model']);
				$id = sanitize_key($_GET['id']);
				if($model == 'KBCategory') {
					$html .= getCategoryContent($id);
				}
				else if($model == 'KBArticle') {
					$html .= getSingleKBArticle($id);
					$html .= getKBArticleDocs($id);
					$html .= getKBArticleNotes($id);
				}
			} else {
				$html .= getCategoryContent(0);
			}

			$html .= '</div></div>';

		}
		catch (APIError $e) {
			$html = __("Error loading article", ONECRM_P_TEXTDOMAIN);
		}

		return $html;
	}
	add_shortcode( 'onecrm_kb_articles', 'getKBArticles' );
	add_shortcode( 'onecrm_kb_search', 'getKBSearch' );
}
