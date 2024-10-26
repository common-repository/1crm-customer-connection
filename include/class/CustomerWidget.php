<?php

namespace OneCRM\Portal;

class CustomerWidget extends \WP_Widget {

	/**
     * Link of base page for user info and personal data forms
     *
	 * @var string
	 */
    private $base_page_link;

	/**
	 * @var int
	 */
    private $user_id;

	public function __construct() {
		parent::__construct(
			'onecrm_customer_widget', // Base ID
			esc_html__( 'Customer Connection Customer Info', ONECRM_P_TEXTDOMAIN ),
			[
				'description' => esc_html__( 'Displays infomation about Customer Connection user', ONECRM_P_TEXTDOMAIN ),
			]
		);

		$this->base_page_link = onecrm_get_dashboard_permalink();
		$this->user_id = get_current_user_id();
	}

	public function widget( $args, $instance ) {
		echo $args['before_widget'];
		echo '<div class="onecrm-customer-widget">';

		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . $instance['title'] . $args['after_title'];
		}

		if ($this->user_id) {

			if (!empty($instance['show_name']))
			    echo $this->get_customer_info_html();

			if (!empty($instance['show_logout']))
                echo $this->get_customer_menu_html();

		} else {

			if (!empty($instance['show_name'])) {
				echo esc_html__('You are not logged in', ONECRM_P_TEXTDOMAIN);
			}

			if (!empty($instance['show_login']) || !empty($instance['show_login'])) {
				echo '<ul style="margin: 0;padding: 0;list-style: none">';
				if (!empty($instance['show_login'])) {
					$url = wp_login_url();
					printf(
						'<li><a href="%s">%s</a></li>',
						esc_attr($url),
						esc_html__('Login as an Existing User', ONECRM_P_TEXTDOMAIN)
					);
				}
				if (!empty($instance['show_register'])) {
					$url = wp_registration_url();
					printf(
						'<li><a href="%s">%s</a></li>',
						esc_attr($url),
						esc_html__('Register as a New User', ONECRM_P_TEXTDOMAIN)
					);
				}
				echo '</ul>';
			}

		}
		echo '</div>';
		echo $args['after_widget'];
	}

	public function form( $instance ) {
		$title = isset( $instance['title']) ? $instance['title'] : esc_html__( 'New title', ONECRM_P_TEXTDOMAIN );
		?>
		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_attr_e( 'Title:', ONECRM_P_TEXTDOMAIN ); ?></label> 
		<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
<?php
		$this->display_checkbox($instance, 'show_name', esc_attr( 'Display customer name', ONECRM_P_TEXTDOMAIN ));
		$this->display_checkbox($instance, 'show_login', esc_attr( 'Display "Login" link', ONECRM_P_TEXTDOMAIN ));
		$this->display_checkbox($instance, 'show_logout', esc_attr( 'Display "Logout" link', ONECRM_P_TEXTDOMAIN ));
		$this->display_checkbox($instance, 'show_register', esc_attr( 'Display "Register" link', ONECRM_P_TEXTDOMAIN ));
	}

	public function update( $new_instance, $old_instance ) {
		$instance = [
			'title' => empty($new_instance['title']) ? '' : sanitize_text_field($new_instance['title']),
		];
		$checkboxes = ['show_name', 'show_login', 'show_logout', 'show_register'];
		foreach ($checkboxes as $cb) {
			$instance[$cb] = empty($new_instance[$cb]) ? 0 : 1;
		}
		return $instance;
	}

	public static function shortcode($attr) {
		$w = new self;
		$w->widget(
			[
				'before_title' => '<h4 class="widget-title">',
				'after_title' => '</h4>',
			], 
			[
				'title' => isset($attr['title']) ? $attr['title'] : '',
				'show_name' => !empty($attr['show_name']),
				'show_logout' => !empty($attr['show_logout']),
				'show_login' => !empty($attr['show_login']),
				'show_register' => !empty($attr['show_register']),
			]
		);
	}

	private function get_customer_info_html() {
		$first_name = get_user_meta($this->user_id, 'first_name', true);
		$last_name = get_user_meta($this->user_id, 'last_name', true);
		$account_name = get_user_meta($this->user_id, 'onecrm_p_company_name', true);

		$html = '<h5>' . esc_html__('Name', ONECRM_P_TEXTDOMAIN) . '</h5>';
		$html .= esc_html($first_name . ' ' . $last_name);
		$html .= '<h5>' . esc_html__('Company', ONECRM_P_TEXTDOMAIN) . '</h5>';
		$html .= esc_html($account_name);

		return $html;
    }

	private function get_customer_menu_html() {
		$urls = $this->get_customer_menu_urls();

		$html = '<ul style="margin: 0;padding: 0;list-style: none">';

		foreach ($urls as $item) {
			$html .= '<li><a href="'.esc_attr($item['url']).'">';
			$html .= esc_html__($item['label'], ONECRM_P_TEXTDOMAIN);
			$html .= '</a></li>';
		}

		$html .= '</ul>';

		return $html;
	}

	private function get_customer_menu_urls() {
		global $wp;

		$contact_id = get_user_meta($this->user_id, 'onecrm_p_contact_id', true);
		$current_page = add_query_arg( $wp->query_vars, home_url( $wp->request ) );
		$return_to =  '&return_to=' . urlencode($current_page);

		$edit_info_url = '?edit=Contact&record='.$contact_id . $return_to;
		$personal_data_url = '?personal_data=Contact&format=view&record='.$contact_id . $return_to;
		$personal_data_erase_url = '?personal_data=Contact&format=erase&record='.$contact_id . $return_to;

		$urls = [
			['url' => $edit_info_url, 'label' => 'Edit my Info'],
			['url' => $personal_data_url, 'label' => 'View Personal Data'],
			['url' => $personal_data_erase_url, 'label' => 'Erase Personal Data']
		];

		if ($this->base_page_link) {
			foreach ($urls as $index => $item) {
				$urls[$index]['url'] = $this->base_page_link . $item['url'];
			}
		}

		$urls[] = ['url' => wp_logout_url(), 'label' => 'Logout'];

		return $urls;
	}

	private function display_checkbox($instance, $name, $label) {
		$checked = empty($instance[$name]) ? '' : 'checked="checked"';
?>
<p>
    <input id="<?php echo esc_attr( $this->get_field_id( $name ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $name ) ); ?>" type="checkbox" value="1" <?php echo $checked;?>>
    <label for="<?php echo esc_attr( $this->get_field_id( $name ) ); ?>"><?php echo $label ?></label>
</p>
<?php
	}
}
