<?php

// WARN: will be called for both logged-in and non-logged-in users!
add_action('wp_ajax_onecrm_p_model_create', function(){\OneCRM\Portal\Ajax::model_create();});
add_action('wp_ajax_onecrm_p_model_save', function(){\OneCRM\Portal\Ajax::model_save();});
add_action('wp_ajax_onecrm_p_personal_data_erase', function(){\OneCRM\Portal\Ajax::erase_personal_data();});
