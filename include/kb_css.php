<?php

function onecrm_p_generate_kb_css() {
	$css_options = [
		'onecrm_help_theme_col',
		'onecrm_help_css_borders',
		'onecrm_help_css_shadows',
		'onecrm_help_css_borders_round',
		'onecrm_help_css_color',
		'onecrm_help_css_head_font_col',
		'onecrm_help_css_p_font_col',
		'onecrm_help_css_p_counter_col',
		'onecrm_help_css_border_color',
		'onecrm_help_css_p_icon_col_active',
		'onecrm_help_css_p_icon_col_inactive',
		'onecrm_help_css_p_icon_border',
	];
	foreach ($css_options as $opt) {
		$$opt = get_option($opt);
		if ($$opt === false)
			$$opt = '""';
	}
	$borders = $onecrm_help_css_borders ? "solid 1px $onecrm_help_css_border_color;" : "none; ";
	$borders .= "border-radius: " . ($onecrm_help_css_borders_round ? '5px;' : '0;');
	$shadows = $onecrm_help_css_shadows ? 'box-shadow: 1px 1px 2px 3px #efeeee;' : '';
	$icon_border = $onecrm_help_css_p_icon_border ? "border: solid 1px $onecrm_help_css_p_icon_col_active; border-radius: 4px" : "";
	$rules = [
		'.kbbox h2' => "color: $onecrm_help_css_head_font_col;",
		'.kbbox' => "background-color: $onecrm_help_css_color; color: $onecrm_help_css_p_font_col; border: $borders; $shadows",
		'.onecrm-p-counter' => "color: $onecrm_help_css_p_counter_col;",
		'.onecrm-p-view-grid' => "background: $onecrm_help_css_p_icon_col_inactive;",
		'.onecrm-p-view-list' => "background: $onecrm_help_css_p_icon_col_inactive;",
		'.onecrm-p-view-icons.view-list .onecrm-p-view-list' => "background: $onecrm_help_css_p_icon_col_active; ",
		'.onecrm-p-view-icons.view-grid .onecrm-p-view-grid' => "background: $onecrm_help_css_p_icon_col_active; ",
		'.onecrm-p-view-icons.view-list .onecrm-p-view-list-outer' => "$icon_border",
		'.onecrm-p-view-icons.view-grid .onecrm-p-view-grid-outer' => "$icon_border;",
	];
	

	$css = '';
	foreach ($rules as $selector => $r) {
		$css .= "div.onecrmhelp.onecrm-p-themed $selector { $r }\n";
	}
	$css .= get_option("onecrm_help_custom_css");
	return $css;
}
