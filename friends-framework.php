<?php
/*
Plugin Name: Friends
Plugin URI: 
Description:
Author: Andrew Billits
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

$friends_current_version = '1.1.3';
//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//
$friends_enable_approval = 0; //Either 0 or 1
$friends_add_notification_subject = __('[SITE_NAME] FROM_USER has added you as a friend');
$friends_add_notification_content = __('Dear TO_USER,

We would like to inform you that FROM_USER has recently added you as a friend.

Thanks,
SITE_NAME');

if ( $friends_enable_approval == 1 ) {
	$friends_add_notification_subject = __('[SITE_NAME] FROM_USER has requested to add you as a friend');
	$friends_add_notification_content = __('Dear TO_USER,
	
	We would like to inform you that FROM_USER has requested to add you as a friend. Please login to your admin panel to approve or reject this request.
	
	Thanks,
	SITE_NAME');

	$friends_request_approval_notification_subject = __('[SITE_NAME] Friend Request Approved');
	$friends_request_approval_notification_content = __('Dear TO_USER,
	
	We would like to inform you that REQUESTED_USER has approved your request to add them as a friend.
	
	Thanks,
	SITE_NAME');
	
	$friends_request_rejection_notification_subject = __('[SITE_NAME] Friend Request Denied');
	$friends_request_rejection_notification_content = __('Dear TO_USER,
	
	We would like to inform you that REQUESTED_USER has denied your request to add them as a friend.
	
	Thanks,
	SITE_NAME');
}
//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//
//check for activating
if ($_GET['key'] == '' || $_GET['key'] === ''){
	add_action('admin_head', 'friends_make_current');
}

add_action('admin_menu', 'friends_plug_pages');
add_action('wpabar_menuitems', 'friends_admin_bar');
//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//
function friends_make_current() {
	global $wpdb, $friends_current_version;
	if (get_site_option( "friends_version" ) == '') {
		add_site_option( 'friends_version', '0.0.0' );
	}
	
	if (get_site_option( "friends_version" ) == $friends_current_version) {
		// do nothing
	} else {
		//up to current version
		update_site_option( "friends_installed", "no" );
		update_site_option( "friends_version", $friends_current_version );
	}
	friends_global_install();
	//--------------------------------------------------//
	if (get_option( "friends_version" ) == '') {
		add_option( 'friends_version', '0.0.0' );
	}
	
	if (get_option( "friends_version" ) == $friends_current_version) {
		// do nothing
	} else {
		//up to current version
		update_option( "friends_version", $friends_current_version );
		friends_blog_install();
	}
}

function friends_blog_install() {
	global $wpdb, $friends_current_version;
	//$friends_table1 = "";
	//$wpdb->query( $friends_table1 );
}

function friends_global_install() {
	global $wpdb, $friends_current_version;
	if (get_site_option( "friends_installed" ) == '') {
		add_site_option( 'friends_installed', 'no' );
	}
	
	if (get_site_option( "friends_installed" ) == "yes") {
		// do nothing
	} else {
	
		$friends_table1 = "CREATE TABLE IF NOT EXISTS `" . $wpdb->base_prefix . "friends` (
  `friend_ID` bigint(20) unsigned NOT NULL auto_increment,
  `user_ID` int(11) NOT NULL default '0',
  `friend_user_ID` int(11) NOT NULL default '0',
  `friend_approved` int(1) NOT NULL default '1',
  PRIMARY KEY  (`friend_ID`)
) ENGINE=MyISAM;";
		$friends_table2 = "";
		$friends_table3 = "";
		$friends_table4 = "";
		$friends_table5 = "";

		$wpdb->query( $friends_table1 );
		//$wpdb->query( $friends_table2 );
		//$wpdb->query( $friends_table3 );
		//$wpdb->query( $friends_table4 );
		//$wpdb->query( $friends_table5 );
		update_site_option( "friends_installed", "yes" );
	}
}

function friends_plug_pages() {
	global $wpdb, $user_ID, $friends_enable_approval;
	
	add_menu_page(__('Friends'), __('Friends'), 0, 'friends.php');
	add_submenu_page('friends.php', __('Friends'), __('Find Friends'), '0', 'find-friends', 'friends_find_output' );
	if ( $friends_enable_approval == 1 ) {
		$tmp_friend_requests_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "friends WHERE friend_user_ID = '" . $user_ID . "' AND friend_approved = '0'");
		add_submenu_page('friends.php', __('Friends'), __('Friend Requests') . ' (' . $tmp_friend_requests_count . ')', '0', 'friend-requests', 'friends_requests_output' );
	}
	add_submenu_page('friends.php', __('Friends'), __('Notifications'), '0', 'friend-notifications', 'friends_notifications_output' );
}

function friends_admin_bar( $menu ) {
	unset( $menu['friends.php'] );
	return $menu;
}

function friends_add_notification($tmp_to_uid,$tmp_from_uid) {
	global $wpdb, $current_site, $user_ID, $friends_add_notification_subject, $friends_add_notification_content;
	if (get_usermeta($tmp_to_uid,'friend_email_notification') != 'no'){
		$tmp_to_username =  $wpdb->get_var("SELECT user_login FROM " . $wpdb->users . " WHERE ID = '" . $tmp_to_uid . "'");
		$tmp_to_email =  $wpdb->get_var("SELECT user_email FROM " . $wpdb->users . " WHERE ID = '" . $tmp_to_uid . "'");
		$tmp_from_username =  $wpdb->get_var("SELECT user_login FROM " . $wpdb->users . " WHERE ID = '" . $tmp_from_uid . "'");
		$tmp_from_displayname =  $wpdb->get_var("SELECT display_name FROM " . $wpdb->users . " WHERE ID = '" . $tmp_from_uid . "'");
		
		$message_content = $friends_add_notification_content;
		$message_content = str_replace( "SITE_NAME", $current_site->site_name, $message_content );
		$message_content = str_replace( "SITE_URL", 'http://' . $current_site->domain . '', $message_content );

		$message_content = str_replace( "TO_USER", $tmp_to_username, $message_content );
		if ($tmp_from_displayname != $tmp_from_username){
			$message_content = str_replace( "FROM_USER", $tmp_from_displayname . ' (' . $tmp_from_username . ')', $message_content );
		} else {
			$message_content = str_replace( "FROM_USER", $tmp_from_displayname, $message_content );
		}
		$message_content = str_replace( "\'", "'", $message_content );
		
		$subject_content = $friends_add_notification_subject;
		$subject_content = str_replace( "SITE_NAME", $current_site->site_name, $subject_content );
		if ($tmp_from_displayname != $tmp_from_username){
			$subject_content = str_replace( "FROM_USER", $tmp_from_displayname . ' (' . $tmp_from_username . ')', $subject_content );
		} else {
			$subject_content = str_replace( "FROM_USER", $tmp_from_displayname, $subject_content );
		}
		
		$admin_email = get_site_option('admin_email');
		if ($admin_email == ''){
			$admin_email = 'admin@' . $current_site->domain;
		}
		$from_email = $admin_email;
		
		$message_headers = "MIME-Version: 1.0\n" . "From: " . $current_site->site_name .  " <{$from_email}>\n" . "Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"\n";
		wp_mail($tmp_to_email, $subject_content, $message_content, $message_headers);
	}
}

function friends_request_approval_notification($tmp_requesting_uid,$tmp_requested_uid) {
	global $wpdb, $current_site, $user_ID, $friends_request_approval_notification_subject, $friends_request_approval_notification_content;
	if (get_usermeta($tmp_to_uid,'friend_email_notification') != 'no'){
		$tmp_requesting_username =  $wpdb->get_var("SELECT user_login FROM " . $wpdb->users . " WHERE ID = '" . $tmp_requesting_uid . "'");
		$tmp_requesting_email =  $wpdb->get_var("SELECT user_email FROM " . $wpdb->users . " WHERE ID = '" . $tmp_requesting_uid . "'");
		$tmp_requested_username =  $wpdb->get_var("SELECT user_login FROM " . $wpdb->users . " WHERE ID = '" . $tmp_requested_uid . "'");
		$tmp_requested_displayname =  $wpdb->get_var("SELECT display_name FROM " . $wpdb->users . " WHERE ID = '" . $tmp_requested_uid . "'");
		
		$message_content = $friends_request_approval_notification_content;
		$message_content = str_replace( "SITE_NAME", $current_site->site_name, $message_content );
		$message_content = str_replace( "SITE_URL", 'http://' . $current_site->domain . '', $message_content );

		$message_content = str_replace( "REQUESTING_USER", $tmp_requesting_username, $message_content );
		if ($tmp_requested_displayname != $tmp_requested_username){
			$message_content = str_replace( "REQUESTED_USER", $tmp_requested_displayname . ' (' . $tmp_requested_username . ')', $message_content );
		} else {
			$message_content = str_replace( "REQUESTED_USER", $tmp_requested_displayname, $message_content );
		}
		$message_content = str_replace( "\'", "'", $message_content );
		
		$subject_content = $friends_request_approval_notification_subject;
		$subject_content = str_replace( "SITE_NAME", $current_site->site_name, $subject_content );
		if ($tmp_requested_displayname != $tmp_requested_username){
			$subject_content = str_replace( "REQUESTED_USER", $tmp_requested_displayname . ' (' . $tmp_requested_username . ')', $subject_content );
		} else {
			$subject_content = str_replace( "REQUESTED_USER", $tmp_requested_displayname, $subject_content );
		}
		
		$admin_email = get_site_option('admin_email');
		if ($admin_email == ''){
			$admin_email = 'admin@' . $current_site->domain;
		}
		$from_email = $admin_email;
		
		$message_headers = "MIME-Version: 1.0\n" . "From: " . $current_site->site_name .  " <{$from_email}>\n" . "Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"\n";
		wp_mail($tmp_requesting_email, $subject_content, $message_content, $message_headers);
	}
}

function friends_request_rejection_notification($tmp_requesting_uid,$tmp_requested_uid) {
	global $wpdb, $current_site, $user_ID, $friends_request_rejection_notification_subject, $friends_request_rejection_notification_content;
	if (get_usermeta($tmp_to_uid,'friend_email_notification') != 'no'){
		$tmp_requesting_username =  $wpdb->get_var("SELECT user_login FROM " . $wpdb->users . " WHERE ID = '" . $tmp_requesting_uid . "'");
		$tmp_requesting_email =  $wpdb->get_var("SELECT user_email FROM " . $wpdb->users . " WHERE ID = '" . $tmp_requesting_uid . "'");
		$tmp_requested_username =  $wpdb->get_var("SELECT user_login FROM " . $wpdb->users . " WHERE ID = '" . $tmp_requested_uid . "'");
		$tmp_requested_displayname =  $wpdb->get_var("SELECT display_name FROM " . $wpdb->users . " WHERE ID = '" . $tmp_requested_uid . "'");
		
		$message_content = $friends_request_rejection_notification_content;
		$message_content = str_replace( "SITE_NAME", $current_site->site_name, $message_content );
		$message_content = str_replace( "SITE_URL", 'http://' . $current_site->domain . '', $message_content );

		$message_content = str_replace( "REQUESTING_USER", $tmp_requesting_username, $message_content );
		if ($tmp_requested_displayname != $tmp_requested_username){
			$message_content = str_replace( "REQUESTED_USER", $tmp_requested_displayname . ' (' . $tmp_requested_username . ')', $message_content );
		} else {
			$message_content = str_replace( "REQUESTED_USER", $tmp_requested_displayname, $message_content );
		}
		$message_content = str_replace( "\'", "'", $message_content );
		
		$subject_content = $friends_request_rejection_notification_subject;
		$subject_content = str_replace( "SITE_NAME", $current_site->site_name, $subject_content );
		if ($tmp_requested_displayname != $tmp_requested_username){
			$subject_content = str_replace( "REQUESTED_USER", $tmp_requested_displayname . ' (' . $tmp_requested_username . ')', $subject_content );
		} else {
			$subject_content = str_replace( "REQUESTED_USER", $tmp_requested_displayname, $subject_content );
		}
		
		$admin_email = get_site_option('admin_email');
		if ($admin_email == ''){
			$admin_email = 'admin@' . $current_site->domain;
		}
		$from_email = $admin_email;
		
		$message_headers = "MIME-Version: 1.0\n" . "From: " . $current_site->site_name .  " <{$from_email}>\n" . "Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"\n";
		wp_mail($tmp_requesting_email, $subject_content, $message_content, $message_headers);
	}
}

function friends_add($tmp_uid, $tmp_friend_uid, $tmp_approved) {
	global $wpdb;
	$tmp_friend_count =  $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "friends WHERE user_ID = '" . $tmp_uid . "' AND friend_user_ID = '" . $tmp_friend_uid . "'");
	if ( $tmp_friend_count > 0 ) {
		//let's not add this friend twice shall we
	} else {
		$wpdb->query( "INSERT INTO " . $wpdb->base_prefix . "friends (user_ID,friend_user_ID,friend_approved) VALUES ( '" . $tmp_uid . "','" . $tmp_friend_uid . "','" . $tmp_approved . "' )" );
	}
}

//------------------------------------------------------------------------//
//---Output Functions-----------------------------------------------------//
//------------------------------------------------------------------------//

//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//

function friends_list_output() {
	global $wpdb, $wp_roles, $current_user, $user_ID, $current_site, $messaging_current_version;

	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e('' . urldecode($_GET['updatedmsg']) . '') ?></p></div><?php
	}
	echo '<div class="wrap">';
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
			?>
            <h2><?php _e('Friends') ?></h2>
            <?php
			if( isset( $_GET[ 'start' ] ) == false ) {
				$start = 0;
			} else {
				$start = intval( $_GET[ 'start' ] );
			}
			if( isset( $_GET[ 'num' ] ) == false ) {
				$num = 30;
			} else {
				$num = intval( $_GET[ 'num' ] );
			}
			$query = "SELECT * FROM " . $wpdb->base_prefix . "friends WHERE user_ID = '" . $user_ID . "' AND friend_approved = '1'";
			$query .= " LIMIT " . intval( $start ) . ", " . intval( $num );
			$tmp_friends = $wpdb->get_results( $query, ARRAY_A );
			if( count( $tmp_friends ) < $num ) {
				$next = false;
			} else {
				$next = true;
			}
			if (count($tmp_friends) > 0){
				$tmp_friend_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "friends WHERE user_ID = '" . $user_ID . "' AND friend_approved = '1'");
				if ($tmp_friend_count > 30){
					?>
					<table><td>
					<fieldset>
					<?php 
					
					//$order_sort = "order=" . $_GET[ 'order' ] . "&sortby=" . $_GET[ 'sortby' ];
					
					if( $start == 0 ) { 
						echo __('Previous Page');
					} elseif( $start <= 30 ) { 
						echo '<a href="friends.php?start=0&' . $order_sort . ' ">' . __('Previous Page') . '</a>';
					} else {
						echo '<a href="friends.php?start=' . ( $start - $num ) . '&' . $order_sort . '">' . __('Previous Page') . '</a>';
					} 
					if ( $next ) {
						echo '&nbsp;||&nbsp;<a href="friends.php?start=' . ( $start + $num ) . '&' . $order_sort . '">' . __('Next Page') . '</a>';
					} else {
						echo '&nbsp;||&nbsp;' . __('Next Page');
					}
					?>
					</fieldset>
					</td></table>
					<br style="clear:both;" />
					<?php
				}
				if ($messaging_current_version != ''){
					echo "
					<table cellpadding='3' cellspacing='3' width='100%' class='widefat'> 
					<thead><tr>
					<th scope='col'>" . __('Friend') . "</th>
					<th scope='col'>" . __('Avatar') . "</th>
					<th scope='col'>" . __('Actions') . "</th>
					<th scope='col'></th>
					<th scope='col'></th>
					</tr></thead>
					<tbody id='the-list'>
					";
				} else {
					echo "
					<table cellpadding='3' cellspacing='3' width='100%' class='widefat'> 
					<thead><tr>
					<th scope='col'>" . __('Friend') . "</th>
					<th scope='col'>" . __('Avatar') . "</th>
					<th scope='col'>" . __('Actions') . "</th>
					<th scope='col'></th>
					</tr></thead>
					<tbody id='the-list'>
					";
				}
				//=========================================================//
					$class = ('alternate' == $class) ? '' : 'alternate';
					foreach ($tmp_friends as $tmp_friend){
					//=========================================================//
					echo "<tr class='" . $class . "'>";
					$tmp_display_name = $wpdb->get_var("SELECT display_name FROM " . $wpdb->base_prefix . "users WHERE ID = '" . $tmp_friend['friend_user_ID'] . "'");
					$tmp_user_login = $wpdb->get_var("SELECT user_login FROM " . $wpdb->base_prefix . "users WHERE ID = '" . $tmp_friend['friend_user_ID'] . "'");
					if ($tmp_display_name != $tmp_user_login){
						echo "<td valign='top'><strong>" . $tmp_display_name . " (" . $tmp_user_login . ")</strong></td>";				
					} else {
						echo "<td valign='top'><strong>" . $tmp_display_name . "</strong></td>";
					}
					echo "<td valign='top'>" . get_avatar($tmp_friend['friend_user_ID'],'32','') . "</td>";
					$tmp_blog_ID = get_usermeta($tmp_friend['friend_user_ID'], 'primary_blog');
					$tmp_blog_url = get_blog_option($tmp_blog_ID, 'siteurl');
					$tmp_blog_path = $wpdb->get_var("SELECT path FROM " . $wpdb->base_prefix . "blogs WHERE blog_id = '" . $tmp_blog_id . "'");
					if ($messaging_current_version != ''){
						echo "<td valign='top'><a href='friends.php?action=send_message&fid=" . $tmp_friend['friend_user_ID'] . "' rel='permalink' class='edit'>" . __('Send Message') . "</a></td>";
					}
					if ($tmp_blog_url != ''){
						echo "<td valign='top'><a href='" . $tmp_blog_url . "' rel='permalink' class='edit'>" . __('View Blog') . "</a></td>";
					} else {
						echo "<td valign='top'><a class='edit' style='color:#999999;text-decoration:none;border:0px;'>" . __('View Blog') . "</a></td>";
					}
					echo "<td valign='top'><a href='friends.php?action=remove&fid=" . $tmp_friend['friend_ID'] . "' rel='permalink' class='delete'>" . __('Remove') . "</a></td>";
					echo "</tr>";
					$class = ('alternate' == $class) ? '' : 'alternate';
					//=========================================================//
					}
				//=========================================================//
				?>
				</tbody></table>
				<?php
			} else {
				?>
	            <p><?php _e('Your friends list is currently empty') ?></p>
                <?php
			}
		break;
		//---------------------------------------------------//
		case "remove":
			$wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "friends WHERE friend_ID = '" . $_GET['fid'] . "' AND user_ID = '" . $user_ID . "'" );
			echo "
			<SCRIPT LANGUAGE='JavaScript'>
			window.location='friends.php?updated=true&updatedmsg=" . urlencode(__('Friend Removed!')) . "';
			</script>
			";
		break;
		//---------------------------------------------------//
		case "send_message":
			$tmp_display_name = $wpdb->get_var("SELECT display_name FROM " . $wpdb->base_prefix . "users WHERE ID = '" . $_GET['fid'] . "'");
			$tmp_user_login = $wpdb->get_var("SELECT user_login FROM " . $wpdb->base_prefix . "users WHERE ID = '" . $_GET['fid'] . "'");
			if ($tmp_display_name != $tmp_user_login){
				$tmp_display_name = $tmp_display_name . ' (' . $tmp_user_login . ')';
			}
			?>
			<h2><?php _e('Send Message To') ?> <em><?php echo $tmp_display_name; ?></em></h2>
				<form name="new_message" method="POST" action="inbox.php?page=new&action=process">
                <input type="hidden" name="message_to" value="<?php echo $tmp_user_login; ?>" />
                <input type="hidden" name="message_subject" value="<?php _e('Quick Message') ?>" />
					<table class="form-table">
					<tr valign="top">
					<th scope="row"><?php _e('Message') ?></th>
					<td><input type="text" name="message_content" id="message_content" style="width: 95%" tabindex='2' maxlength="200" value="" />
					<br />
					<?php //_e('Required') ?></td> 
					</tr>
					</table>
				<p class="submit">
				<input type="submit" name="Submit" value="<?php _e('Send') ?>" />
				</p>
				</form>
            <?php
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

function friends_find_output() {
	global $wpdb, $wp_roles, $current_user, $user_ID, $current_site, $friends_enable_approval;

	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e('' . urldecode($_GET['updatedmsg']) . '') ?></p></div><?php
	}
	echo '<div class="wrap">';
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
			$tmp_search_terms = $_POST['search_terms'];
			if ($tmp_search_terms == ''){
				$tmp_search_terms = rawurldecode($_GET['search_terms']);
			}
			?>
            <form id="posts-filter" action="friends.php?page=find-friends" method="post">
            <h2><?php _e('Find Friends') ?>&nbsp;&nbsp;<em style="font-size:14px;"><?php _e("Search by friends display name, username or email address") ?></em></h2>
            <p id="post-search">
                <input id="post-search-input" name="search_terms" value="<?php echo $tmp_search_terms; ?>" type="text">
                <input value="<?php _e('Search') ?>" class="button" type="submit">
            </p>
            </form>
            <?php
			if ($tmp_search_terms != ''){
				$query = "SELECT ID, display_name, user_login FROM " . $wpdb->base_prefix . "users 
					WHERE (user_login LIKE '%" . $tmp_search_terms . "%'
					OR user_nicename LIKE '%" . $tmp_search_terms . "%'
					OR user_email LIKE '%" . $tmp_search_terms . "%'
					OR display_name LIKE '%" . $tmp_search_terms . "%')
					AND ID != '" . $user_ID . "'
					ORDER BY user_nicename ASC LIMIT 50";
				$tmp_search_results = $wpdb->get_results( $query, ARRAY_A );
				
				if (count($tmp_search_results) > 0){
					echo '<ul id="friend_results">';
					foreach ($tmp_search_results as $tmp_search_result){
					//=========================================================//
					echo '<li>';
					$tmp_friend_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "friends WHERE friend_user_ID = '" . $tmp_search_result['ID'] . "' AND user_ID = '" . $user_ID . "'");
					if ($tmp_friend_count > 0){
						echo '(<a style="color:#999999;text-decoration:none;border:0px;">' . __('Add') . '</a>) ';
					} else {
						echo '(<a href="friends.php?page=find-friends&action=add&id=' . $tmp_search_result['ID'] . '&search_terms=' . rawurlencode($tmp_search_terms) . '">' . __('Add') . '</a>) ';
					}
					if ($tmp_search_result['display_name'] != $tmp_search_result['user_login']){
						echo $tmp_search_result['display_name'] . ' (' . $tmp_search_result['user_login'] . ')';			
					} else {
						echo $tmp_search_result['display_name'];
					}
					echo '</li>';
					//=========================================================//
					}
					echo '</ul>';
				} else {
					?>
					<p><?php _e('Nothing found') ?></p>
					<?php
				}
			}
		break;
		//---------------------------------------------------//
		case "add":
			$tmp_friend_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "friends WHERE friend_user_ID = '" . $_GET['id'] . "' AND user_ID = '" . $user_ID . "'");
			if ( $user_ID != $_GET['id'] || $tmp_friend_count < 1 ) {
				if ( $friends_enable_approval == '1' ) {
					friends_add($user_ID, $_GET['id'], '0');
					friends_add_notification($_GET['id'],$user_ID);
					echo "
					<SCRIPT LANGUAGE='JavaScript'>
					window.location='friends.php?page=find-friends&search_terms=" . $_GET['search_terms'] . "&updated=true&updatedmsg=" . urlencode(__('Friend will be added pending approval.')) . "';
					</script>";
				} else {
					friends_add($user_ID, $_GET['id'], '1');
					friends_add_notification($_GET['id'],$user_ID);
					echo "
					<SCRIPT LANGUAGE='JavaScript'>
					window.location='friends.php?page=find-friends&search_terms=" . $_GET['search_terms'] . "&updated=true&updatedmsg=" . urlencode(__('Friend Added!')) . "';
					</script>";

				}
			}
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

function friends_requests_output() {
	global $wpdb, $wp_roles, $current_user, $user_ID, $current_site;

	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e('' . urldecode($_GET['updatedmsg']) . '') ?></p></div><?php
	}
	echo '<div class="wrap">';
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
			?>
            <h2><?php _e('Friend Requests') ?></h2>
            <?php
			if( isset( $_GET[ 'start' ] ) == false ) {
				$start = 0;
			} else {
				$start = intval( $_GET[ 'start' ] );
			}
			if( isset( $_GET[ 'num' ] ) == false ) {
				$num = 30;
			} else {
				$num = intval( $_GET[ 'num' ] );
			}
			$query = "SELECT * FROM " . $wpdb->base_prefix . "friends WHERE friend_user_ID = '" . $user_ID . "' AND friend_approved = '0'";
			$query .= " LIMIT " . intval( $start ) . ", " . intval( $num );
			$tmp_friends = $wpdb->get_results( $query, ARRAY_A );
			if( count( $tmp_friends ) < $num ) {
				$next = false;
			} else {
				$next = true;
			}
			if (count($tmp_friends) > 0){
				$tmp_friend_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "friends WHERE user_ID = '" . $user_ID . "'");
				if ($tmp_friend_count > 30){
					?>
					<table><td>
					<fieldset>
					<?php 
					
					//$order_sort = "order=" . $_GET[ 'order' ] . "&sortby=" . $_GET[ 'sortby' ];
					
					if( $start == 0 ) { 
						echo __('Previous Page');
					} elseif( $start <= 30 ) { 
						echo '<a href="friends.php?page=friend-requests&start=0&' . $order_sort . ' ">' . __('Previous Page') . '</a>';
					} else {
						echo '<a href="friends.php?page=friend-requests&start=' . ( $start - $num ) . '&' . $order_sort . '">' . __('Previous Page') . '</a>';
					} 
					if ( $next ) {
						echo '&nbsp;||&nbsp;<a href="friends.php?page=friend-requests&start=' . ( $start + $num ) . '&' . $order_sort . '">' . __('Next Page') . '</a>';
					} else {
						echo '&nbsp;||&nbsp;' . __('Next Page');
					}
					?>
					</fieldset>
					</td></table>
					<br style="clear:both;" />
					<?php
				}
				echo "
				<table cellpadding='3' cellspacing='3' width='100%' class='widefat'> 
				<thead><tr>
				<th scope='col'>" . __('ID') . "</th>
				<th scope='col'>" . __('User') . "</th>
				<th scope='col'>" . __('Avatar') . "</th>
				<th scope='col'>" . __('Actions') . "</th>
				<th scope='col'></th>
				<th scope='col'></th>
				</tr></thead>
				<tbody id='the-list'>
				";
				//=========================================================//
					$class = ('alternate' == $class) ? '' : 'alternate';
					foreach ($tmp_friends as $tmp_friend){
					//=========================================================//
					echo "<tr class='" . $class . "'>";
					echo "<td valign='top'><strong>" . $tmp_friend['friend_ID'] . "</strong></td>";
					$tmp_display_name = $wpdb->get_var("SELECT display_name FROM " . $wpdb->base_prefix . "users WHERE ID = '" . $tmp_friend['user_ID'] . "'");
					$tmp_user_login = $wpdb->get_var("SELECT user_login FROM " . $wpdb->base_prefix . "users WHERE ID = '" . $tmp_friend['user_ID'] . "'");
					if ($tmp_display_name != $tmp_user_login){
						echo "<td valign='top'><strong>" . $tmp_display_name . " (" . $tmp_user_login . ")</strong></td>";				
					} else {
						echo "<td valign='top'><strong>" . $tmp_display_name . "</strong></td>";
					}
					echo "<td valign='top'>" . get_avatar($tmp_friend['user_ID'],'32','') . "</td>";
					$tmp_blog_ID = get_usermeta($tmp_friend['user_ID'], 'primary_blog');
					$tmp_blog_url = get_blog_option($tmp_blog_ID, 'siteurl');
					$tmp_blog_path = $wpdb->get_var("SELECT path FROM " . $wpdb->base_prefix . "blogs WHERE blog_id = '" . $tmp_blog_id . "'");
					if ($tmp_blog_url != ''){
						echo "<td valign='top'><a href='" . $tmp_blog_url . "' rel='permalink' class='edit'>" . __('View Blog') . "</a></td>";
					} else {
						echo "<td valign='top'><a class='edit' style='color:#999999;text-decoration:none;border:0px;'>" . __('View Blog') . "</a></td>";
					}
					echo "<td valign='top'><a href='friends.php?page=friend-requests&action=approve&fid=" . $tmp_friend['friend_ID'] . "' rel='permalink' class='edit'>" . __('Approve') . "</a></td>";
					echo "<td valign='top'><a href='friends.php?page=friend-requests&action=reject&fid=" . $tmp_friend['friend_ID'] . "' rel='permalink' class='delete'>" . __('Reject') . "</a></td>";
					echo "</tr>";
					$class = ('alternate' == $class) ? '' : 'alternate';
					//=========================================================//
					}
				//=========================================================//
				?>
				</tbody></table>
				<?php
			} else {
				?>
	            <p><?php _e('No items in queue') ?></p>
                <?php
			}
		break;
		//---------------------------------------------------//
		case "approve":
			$tmp_requesting_user_id = $wpdb->get_var("SELECT user_ID FROM " . $wpdb->base_prefix . "friends WHERE friend_ID = '" . $_GET['fid'] . "'");

			$wpdb->query( "UPDATE " . $wpdb->base_prefix . "friends SET friend_approved = '1' WHERE friend_ID = '" . $_GET['fid'] . "' AND friend_user_ID = '" . $user_ID . "'" );

			friends_request_approval_notification($tmp_requesting_user_id,$user_ID);

			echo "
			<SCRIPT LANGUAGE='JavaScript'>
			window.location='friends.php?updated=true&updatedmsg=" . urlencode(__('Friend Request Approved!')) . "';
			</script>
			";
		break;
		//---------------------------------------------------//
		case "reject":
			$tmp_requesting_user_id = $wpdb->get_var("SELECT user_ID FROM " . $wpdb->base_prefix . "friends WHERE friend_ID = '" . $_GET['fid'] . "'");

			$wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "friends WHERE friend_ID = '" . $_GET['fid'] . "' AND friend_user_ID = '" . $user_ID . "'" );

			friends_request_rejection_notification($tmp_requesting_user_id,$user_ID);

			echo "
			<SCRIPT LANGUAGE='JavaScript'>
			window.location='friends.php?updated=true&updatedmsg=" . urlencode(__('Friend Request Rejected!')) . "';
			</script>
			";
		break;
	}
	echo '</div>';
}

function friends_notifications_output() {
	global $wpdb, $wp_roles, $current_user, $user_ID, $current_site;

	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e('' . urldecode($_GET['updatedmsg']) . '') ?></p></div><?php
	}
	echo '<div class="wrap">';
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
			$tmp_friend_email_notification = get_usermeta($user_ID,'friend_email_notification');
			?>
			<h2><?php _e('Notification Settings') ?></h2>
                <form method="post" action="friends.php?page=friend-notifications&action=process"> 
	            <table class="form-table">
                <tr valign="top"> 
                <th scope="row"><?php _e('Receive an email when someone adds you as a friend') ?></th> 
                <td>
                <select name="message_email_notification" id="message_email_notification">
                <option value="yes" <?php if ($tmp_friend_email_notification == 'yes'){ echo 'selected="selected"'; } ?> ><?php _e('Yes') ?></option>
                <option value="no" <?php if ($tmp_friend_email_notification == 'no'){ echo 'selected="selected"'; } ?> ><?php _e('No') ?></option>
                </select>
                </td> 
                </tr> 
                </table>
                <p class="submit">
                <input type="submit" name="Submit" value="<?php _e('Save Changes') ?>" />
                </p>
                </form>
            <?php
		break;
		//---------------------------------------------------//
		case "process":
			update_usermeta($user_ID,'friend_email_notification',$_POST['friend_email_notification']);
			echo "
			<SCRIPT LANGUAGE='JavaScript'>
			window.location='friends.php?page=friend-notifications&updated=true&updatedmsg=" . urlencode('Settings saved.') . "';
			</script>
			";
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

//------------------------------------------------------------------------//
//---Support Functions----------------------------------------------------//
//------------------------------------------------------------------------//

?>