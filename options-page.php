<?php
	if(isset($_POST['save'])) {
		$post_values = $_POST['status_change_notifier'];
		$save_values = array(
			"notify_users" => !empty($_POST['status_change_notifier']['notify_users']) ? $_POST['status_change_notifier']['notify_users'] : array(),
			"notify_approved" => isset($_POST['status_change_notifier']['notify_approved']) ? $_POST['status_change_notifier']['notify_approved'] : false,
			"notify_declined" => isset($_POST['status_change_notifier']['notify_declined']) ? $_POST['status_change_notifier']['notify_declined'] : false
		);
		update_option("status_change_notifier", $save_values);
		echo "<div id='message' class='updated fade'><p>".__("Settings saved","wpscn")."</p></div>";
	}
	$option = get_option("status_change_notifier");
	$roles = $this->get_roles_with_privileges();
?>
<div class="wrap">
	<h2><?php _e("Post Status Change Notifications","wpscn") ?></h2>
	<form name="site" action="" method="post" id="notifier">
	
		<div id="review">
			<?php if(!empty($roles)): ?>
			
			<fieldset id="pendingdiv">
				<h3><?php _e("Send pending notifications to selected users","wpscn") ?></h3>
				<div>
					<?php foreach($roles as $role => $role_name): ?>
						<?php
							$users_query = new WP_User_Query( array(
								'fields' => 'all_with_meta',
								'role' => $role,
								'orderby' => 'display_name'
							));
							$results = $users_query->get_results();
							
							if(!empty($results)){
								echo '<p>' . $role_name . ':</p>';
								foreach($results as $user){
									$input = '<input type="checkbox" %s value="%s" name="status_change_notifier[notify_users][]" /> %s <br />';
									printf($input, checked(true, in_array($user->ID, (array)$option['notify_users']), false), $user->ID, $user->user_login);
								}
							}
						?>
					<?php endforeach; ?>
				</div>
			</fieldset>
			<br />
			<?php endif; ?>
		
			<fieldset id="reviewdiv">
				<h3><?php _e("Post Review Notifications","wpscn") ?></h3>
				<div>
					<label class="selectit">
							<input type="checkbox" name="status_change_notifier[notify_approved]" value="1" <?php echo checked(1, $option['notify_approved']) ?> /> <?php _e("Notify contributor when their post is approved","wpscn") ?>
					</label>
					<br />
					<label class="selectit">
							<input type="checkbox" name="status_change_notifier[notify_declined]" value="1" <?php echo checked(1, $option['notify_declined']) ?> /> <?php _e("Notify contributor when their post is declined (sent back to drafts)","wpscn") ?>
					</label>
				</div>
			</fieldset>
			<br />
			<p class="submit">
				<input name="save" type="submit" value="<?php _e("Save Settings","wpscn") ?>" />
			</p>
		</div>
	</form>
</div>