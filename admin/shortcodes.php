<?php
function onecrm_p_shortcodes_menu() {
	add_submenu_page(
		'onecrm_p',
		esc_html__('1CRM Customer Connection Usage Guide', ONECRM_P_TEXTDOMAIN),
		esc_html__('Usage Guide', ONECRM_P_TEXTDOMAIN),
		'manage_options', 
		'onecrm_p_shortcodes',
		'onecrm_p_shortcodes' );
	wp_enqueue_script('postbox');
}

add_action( 'admin_menu', 'onecrm_p_shortcodes_menu' );
add_action( 'current_screen', 'onecrm_p_save_dashboard' );

function onecrm_p_shortcodes() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
<div style="font-size:130%; max-width: 700px; text-align: justify">
		<p>
<?php esc_html_e('The 1CRM Customer Connection plugin provides a number of shortcodes - special tags that you insert into your pages to display information. The complete set of shortcodes and their descriptions are listed below.', ONECRM_P_TEXTDOMAIN)?>
</p>

<h2><?php esc_html_e('Help System', ONECRM_P_TEXTDOMAIN)?></h2>
<p>
<?php esc_html_e('The 1CRM Customer Connection plugin allows you to display topics and articles from the 1CRM Knowledge Base module. There are two possible ways to to organize your help system:', ONECRM_P_TEXTDOMAIN)?>
</p>

<h3 style="font-size: 1em;"><?php esc_html_e('One-page layout', ONECRM_P_TEXTDOMAIN)?></h3>
<p>
<?php printf(__('All pages in your Help System will use the same layout. In <a href="%s">Dashboard Settings</a>, set both Index Page and Detail Page to "None". Create a page that will display the Help System content. Normally you will want to add that page to your site\'s main menu. On that page, place the [onecrm_kb_articles] shortcode where you want the content appear. To add a search bar, insert the [onecrm_kb_search] shortcode where appropriate.' /* translators: %s will be replaced with an URL */, ONECRM_P_TEXTDOMAIN), "?page=onecrm_p_dashboard")?>
</p>

<h3 style="font-size: 1em;"><?php esc_html_e('Two-page layout', ONECRM_P_TEXTDOMAIN)?></h3>
<p>
<?php echo __('With this option, you create two pages. One of them will serve as the <b>index page</b> that displays top-level Help topics. On that page, place the [onecrm_kb_articles] shortcode where you want the content to appear. To add a search bar, insert the [onecrm_kb_search] shortcode where appropriate.', ONECRM_P_TEXTDOMAIN)?>
</p>
<p>
<?php echo __('Then create another page that will serve as your <b>detail page</b> - it will display sub-topics and articles. On that page, insert the [onecrm_kb_articles] shortcode. Do not insert the [onecrm_kb_search] shortcode on this page, because the detail page will display its own search input.
', ONECRM_P_TEXTDOMAIN)?>
</p>

<p>
<?php printf(__('In <a href="%s">Dashboard Settings</a>, set Index Page and Detail Page to the pages you created in the steps above.' /* translators: %s will be replaced with an URL */, ONECRM_P_TEXTDOMAIN), "?page=onecrm_p_dashboard")?>
</p>


<hr>

<h2><?php esc_html_e('Dashboard: Information from 1CRM modules', ONECRM_P_TEXTDOMAIN)?></h2>

<p>
<?php printf(__('The 1CRM Customer Connection plugin can display information from various 1CRM modules to customers. Currently, the following modules are supported: Projects, Cases, Bugs, Quotes, and Invoices. To display a list of of records from all the supported modules, simply insert the [onecrm_p_dashboard] shortcode into a page. You can enable or disable modules in <a href="%s">Dashboard Settings</a>. You can also display records from a single module by specifying the "model" parameter in the shortcode. Use one of the following:' /* translators: %s will be replaced with an URL */, ONECRM_P_TEXTDOMAIN), "?page=onecrm_p_dashboard")?>
<ul style="list-style: initial; font-size: 77%; padding-left: 2em;">
<li>[onecrm_p_dashboard model="aCase"]</li>
<li>[onecrm_p_dashboard model="Bug"]</li>
<li>[onecrm_p_dashboard model="Project"]</li>
<li>[onecrm_p_dashboard model="Quote"]</li>
<li>[onecrm_p_dashboard model="Invoice"]</li>
</ul>
</p>
<p><b><?php esc_html_e('Note:', ONECRM_P_TEXTDOMAIN)?></b> <?php esc_html_e('Do not insert more than one [onecrm_p_dashboard] shortcode into a page', ONECRM_P_TEXTDOMAIN)?></p>

<hr>

<h2><?php esc_html_e('Subscriptions', ONECRM_P_TEXTDOMAIN)?></h2>

<p>
	<?php esc_html_e('The 1CRM Customer Connection plugin provides two shortcodes for subscriptions. Insert the [onecrm_p_signup] shortcode to display a "Signup" form that will be used by your site visitors to create a subscription. Or use the [onecrm_subscriptions] shortcode to render a list of the current user\'s subscriptions.', ONECRM_P_TEXTDOMAIN); ?>
</p>
<p>
	<?php esc_html_e('After creating pages which use the [onecrm_p_signup] and [onecrm_subscriptions] shortcodes, be sure to open the dashboard settings of 1CRM Customer Connection and set "Subscription management page" to the page with the [onecrm_subscriptions] shortcode.'  , ONECRM_P_TEXTDOMAIN); ?>
</p>

<h2><?php esc_html_e('Customer Details', ONECRM_P_TEXTDOMAIN)?></h2>

<p>
	<?php esc_html_e('Within different areas of your site you can display information about the customer who has logged in. Customer information is available in two forms: as a widget and as a shortcode.',  ONECRM_P_TEXTDOMAIN); ?>
</p>

<h4><?php esc_html_e('Widget', ONECRM_P_TEXTDOMAIN)?></h4>

<p>
	<?php esc_html_e('1CRM Customer Connection provides a "Customer Connection Customer Info" widget that you can place in any of the widget areas of your site. Available widget areas depend on the Wordpress theme in use. ', ONECRM_P_TEXTDOMAIN); ?>
	<?php esc_html_e('The widget can be configured to display customer name, account name, and/or login/logout/register links. ', ONECRM_P_TEXTDOMAIN); ?>
	<?php esc_html_e('Login and Register links, if enabled, will be displayed to site visitors who are not logged in. The Logout link, if enabled, will be displayed to logged-in users.', ONECRM_P_TEXTDOMAIN); ?>
</p>

<p>
	<?php esc_html_e('You can use multiple instances of the widget in different areas. For example, you may want to display customer name in the footer, and login links in the sidebar.', ONECRM_P_TEXTDOMAIN); ?>
</p>

<h4><?php esc_html_e('Shortcode', ONECRM_P_TEXTDOMAIN)?></h4>

<p>
	<?php esc_html_e('To display customer information inside a page, add the [onecrm_p_customer_info] shortcode to your page content. The shortcode accepts parameters that control the information displayed by the shortcode:'  , ONECRM_P_TEXTDOMAIN); ?>
</p>
<ul style="list-style: initial; font-size: 77%; padding-left: 2em;">
	<li>
	[onecrm_p_customer_info title=&quot;Customer Details&quot;] — <?php esc_html_e('displays a title', ONECRM_P_TEXTDOMAIN)?>
	</li>
	<li>
	[onecrm_p_customer_info show_name=&quot;1&quot;] — <?php esc_html_e('displays customer name and company name', ONECRM_P_TEXTDOMAIN)?>
	</li>
	<li>
	[onecrm_p_customer_info show_login=&quot;1&quot;] — <?php esc_html_e('displays login link', ONECRM_P_TEXTDOMAIN)?>
	</li>
	<li>
	[onecrm_p_customer_info show_logout=&quot;1&quot;] — <?php esc_html_e('displays logout link', ONECRM_P_TEXTDOMAIN)?>
	</li>
	<li>
	[onecrm_p_customer_info show_register=&quot;1&quot;] — <?php esc_html_e('displays register link', ONECRM_P_TEXTDOMAIN)?>
	</li>
</ul>
<p>
<?php esc_html_e('You can combine multiple parameters in one shortcode:', ONECRM_P_TEXTDOMAIN)?>
</p>
<ul style="list-style: initial; font-size: 77%; padding-left: 2em;">
	<li>
	[onecrm_p_customer_info title=&quot;Customer Details&quot; show_name=&quot;1&quot;] — <?php esc_html_e('displays customer name with a title', ONECRM_P_TEXTDOMAIN)?>
	</li>
</ul>

	</div>
	</div>
<?php
}



