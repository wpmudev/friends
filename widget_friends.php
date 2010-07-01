<?php
/*
Plugin Name: Friends Widget
Description:
Author: Incsub
Version: 1.1.3
Author URI:
*/

/* 
Copyright 2007-2009 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

function widget_friends_init() {
	global $wpdb, $user_ID, $friends_enable_approval;
		
	// Check for the required API functions
	if ( !function_exists('register_sidebar_widget') || !function_exists('register_widget_control') )
		return;

	// This saves options and prints the widget's config form.
	function widget_friends_control() {
		global $wpdb, $user_ID, $friends_enable_approval;
		$options = $newoptions = get_option('widget_friends');
		if ( $_POST['friends-submit'] ) {
			$newoptions['friends-display'] = $_POST['friends-display'];
			$newoptions['freinds-uid'] = $_POST['freinds-uid'];
		}
		if ( $options != $newoptions ) {
			$options = $newoptions;
			update_option('widget_friends', $options);
		}
	?>
				<div style="text-align:right">
                <?php
				$tmp_blog_users_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->usermeta . " WHERE meta_key = '" . $wpdb->base_prefix . $wpdb->blogid . "_capabilities'");
				if ($tmp_blog_users_count > 1){
							$tmp_username = $wpdb->get_var("SELECT user_login FROM " . $wpdb->users . " WHERE ID = '" . $tmp_sent_message['sent_message_from_user_ID'] . "'");
					?>
					<label for="freinds-uid" style="line-height:35px;display:block;"><?php _e('User', 'widgets'); ?>:
					<select name="freinds-uid" id="freinds-uid" style="width:65%;">
					<?php
					$query = "SELECT user_id FROM " . $wpdb->usermeta . " WHERE meta_key = '" . $wpdb->base_prefix . $wpdb->blogid . "_capabilities'";
					$tmp_users = $wpdb->get_results( $query, ARRAY_A );
					if (count($tmp_users) > 0){
						foreach ($tmp_users as $tmp_user){
							$tmp_username = $wpdb->get_var("SELECT user_login FROM " . $wpdb->users . " WHERE ID = '" . $tmp_user['user_id'] . "'");
							?>
							<option value="<?php echo $tmp_user['user_id']; ?>" <?php if ($options['freinds-uid'] == $tmp_user['user_id']){ echo 'selected="selected"'; } ?> ><?php echo $tmp_username; ?></option>
                            <?php
						}
					}
					?>
					</select>
					</label>
					<?php
				} else {
					if ($tmp_blog_users_count == 1){
						$tmp_friends_uid = $wpdb->get_var("SELECT user_id FROM " . $wpdb->usermeta . " WHERE meta_key = '" . $wpdb->base_prefix . $wpdb->blogid . "_capabilities'");
					} else {
						$tmp_friends_uid = $user_ID;
					}
					?>
					<input type="hidden" name="freinds-uid" value="<?php echo $tmp_friends_uid; ?>" />
					<?php
				}
				?>
				<label for="friends-display" style="line-height:35px;display:block;"><?php _e('Display', 'widgets'); ?>:
                <select name="friends-display" id="friends-display-type" style="width:65%;">
                <option value="mosaic" <?php if ($options['friends-display'] == 'mosaic'){ echo 'selected="selected"'; } ?> ><?php _e('Mosaic'); ?></option>
                <option value="list" <?php if ($options['friends-display'] == 'list'){ echo 'selected="selected"'; } ?> ><?php _e('List'); ?></option>
                </select>
                </label>
				<input type="hidden" name="friends-submit" id="friends-submit" value="1" />
				</div>
	<?php
	}
// This prints the widget
	function widget_friends($args) {
		global $wpdb, $user_ID, $current_site, $friends_enable_approval, $messaging_current_version;
		extract($args);
		$defaults = array('count' => 10, 'username' => 'wordpress');
		$options = (array) get_option('widget_friends');

		foreach ( $defaults as $key => $value )
			if ( !isset($options[$key]) )
				$options[$key] = $defaults[$key];

		?>
		<?php echo $before_widget; ?>
			<?php echo $before_title . __('Friends') . $after_title; ?>
            <br />
            <?php
				//=================================================//
				if ( $friends_enable_approval == '1' ) {
					$query = "SELECT * FROM " . $wpdb->base_prefix . "friends WHERE user_ID = '" . $options['freinds-uid'] . "' AND friend_approved = '1'";
				} else {
					$query = "SELECT * FROM " . $wpdb->base_prefix . "friends WHERE user_ID = '" . $options['freinds-uid'] . "'";
				}
				$tmp_friends = $wpdb->get_results( $query, ARRAY_A );
				if (count($tmp_friends) > 0){
					if ($options['friends-display'] == 'list'){
						echo '<ul>';
						foreach ($tmp_friends as $tmp_friend){
							echo '<li>';
							$tmp_blog_ID = get_usermeta($tmp_friend['friend_user_ID'], 'primary_blog');
							$tmp_blog_url = get_blog_option($tmp_blog_ID, 'siteurl');
							$tmp_user_display_name = $wpdb->get_var("SELECT display_name FROM " . $wpdb->users . " WHERE ID = '" . $tmp_friend['friend_user_ID'] . "'");
							if ($tmp_user_display_name == ''){
								$tmp_user_display_name = $wpdb->get_var("SELECT user_login FROM " . $wpdb->users . " WHERE ID = '" . $tmp_friend['friend_user_ID'] . "'");
							}
							if ($tmp_blog_url != ''){
								echo '<a href="' . $tmp_blog_url . '">' . $tmp_user_display_name . '</a>';
							} else {
								echo $tmp_user_display_name;
							}
							echo '</li>';
						}
						echo '</ul>';
					} else {
						foreach ($tmp_friends as $tmp_friend){
							$tmp_blog_ID = get_usermeta($tmp_friend['friend_user_ID'], 'primary_blog');
							$tmp_blog_url = get_blog_option($tmp_blog_ID, 'siteurl');
							$tmp_user_display_name = $wpdb->get_var("SELECT display_name FROM " . $wpdb->users . " WHERE ID = '" . $tmp_friend['friend_user_ID'] . "'");
							if ($tmp_user_display_name == ''){
								$tmp_user_display_name = $wpdb->get_var("SELECT user_login FROM " . $wpdb->users . " WHERE ID = '" . $tmp_friend['friend_user_ID'] . "'");
							}
							if ($tmp_blog_url != ''){
								?>
								<a href="<?php echo $tmp_blog_url; ?>" style="text-decoration:none;border:0px;"><img src="http://<?php echo $current_site->domain . $current_site->path . 'avatar/user-' . $tmp_friend['friend_user_ID'] . '-32.png'; ?>" alt="<?php echo $tmp_user_display_name; ?>" title="<?php echo $tmp_user_display_name; ?>" /></a>
								<?php
							} else {
								?>
								<img src="http://<?php echo $current_site->domain . $current_site->path . 'avatar/user-' . $tmp_friend['friend_user_ID'] . '-32.png'; ?>" alt="<?php echo $tmp_user_display_name; ?>" title="<?php echo $tmp_user_display_name; ?>" />
								<?php
							}
						}
					}
				}

				//=================================================//
			?>
		<?php echo $after_widget; ?>
<?php
	}
	// Tell Dynamic Sidebar about our new widget and its control
	register_sidebar_widget(array(__('Friends'), 'widgets'), 'widget_friends');
	register_widget_control(array(__('Friends'), 'widgets'), 'widget_friends_control');

}

add_action('widgets_init', 'widget_friends_init');

?>