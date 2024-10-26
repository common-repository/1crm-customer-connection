<div class="wrap">

<h2><?php
	echo esc_html( __( '1CRM Customer Connection General settings', ONECRM_P_TEXTDOMAIN) );
?></h2>

<br class="clear" />

<?php if ($error) : ?>
<div class="error"><p>
<?php echo $error; ?>
</p></div>
<?php endif; ?>

<?php if ($message) : ?>
<div class="updated"><p>
<?php echo $message; ?>
</p></div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( add_query_arg( array(), menu_page_url( 'ocrmcb_import', false ) ) ); ?>" id="ocrmcb-admin-form-element">
<div>
	<h3>1CRM URL (path only, no index.php)</h3>
	<input type="text" name="url" placeholder="ex. https://demo.1crmcloud.com/" size="40" value="<?php echo esc_attr(get_option('ocrmcb_import_url'))?>" />
	<h3>Admin Username</h3>
	<input type="text" name="username" size="40" value="<?php echo esc_attr(get_option('ocrmcb_import_username'))?>" />
	<h3>Admin Password</h3>
	<input type="password" name="password" size="40" />
	<h3>Coupon Code Creation</h3>
	<select name="create_coupons">
		<option value="0" selected="selected">Do Not Create Coupon Codes</option>
		<option value="1">Create Coupon Codes For New Partners</option>
		<option value="2">Create Coupon Codes For All Partners with Empty Coupon Code</option>
	</select>
</div>

<p>
	<input type="submit" class="button-primary" value="<?php echo esc_attr( __( 'Import', OCRMCB_TEXTDOMAIN)); ?>" />
</p>
<input type="hidden" name="_ocrmcb_import" value="1" />

</form>


