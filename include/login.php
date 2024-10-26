<?php

function onecrm_p_redirect_to_home($redirect_to, $requested_redirect_to, $user) {
	if ($user instanceof WP_User) {
		if (!in_array('administrator',  $user->roles)) {
			return get_home_url();
		}
	}
	return $redirect_to;
}

add_filter( 'login_redirect', 'onecrm_p_redirect_to_home', 999999, 3);

