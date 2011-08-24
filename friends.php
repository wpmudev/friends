<?php
/*
Plugin Name: Friends
Plugin URI: http://premium.wpmudev.org/project/friends
Description: Lets your users 'friend' each other, display funky widgets with avatar mosaics of all their friends on the site and generally get all social!
Author: Ivan Shaovchev & Andrew Billits, Andrey Shipilov (Incsub)
Author URI: http://premium.wpmudev.org
Version: 1.1.6
Network: true
WDP ID: 62
*/

/*
Copyright 2007-2011 Incsub (http://incsub.com)

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

//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//

$friends_enable_approval = true; // true || false
$friends_add_notification_subject = __('[SITE_NAME] FROM_USER has added you as a friend');
$friends_add_notification_content = __('Dear TO_USER,

We would like to inform you that FROM_USER has recently added you as a friend.

Thanks,
SITE_NAME');

if ( $friends_enable_approval ) {
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

register_activation_hook( __FILE__, 'friends_global_install' );

add_action('wpabar_menuitems', 'friends_admin_bar');
add_action('admin_menu', 'friends_plug_pages');

//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//

function friends_global_install() {
	global $wpdb;

    $friends_table1 = "CREATE TABLE IF NOT EXISTS `" . $wpdb->base_prefix . "friends` (
      `friend_ID` bigint(20) unsigned NOT NULL auto_increment,
      `user_ID` int(11) NOT NULL default '0',
      `friend_user_ID` int(11) NOT NULL default '0',
      `friend_approved` int(1) NOT NULL default '1',
      PRIMARY KEY  (`friend_ID`)
    ) ENGINE=MyISAM;";

    $wpdb->query( $friends_table1 );
}

function friends_plug_pages() {
	global $wpdb, $user_ID, $friends_enable_approval;

    add_menu_page( __('Friends', 'friends' ), __('Friends', 'friends' ), 'read', 'friends', 'friends_output' );
    add_submenu_page( 'friends', __('Friends', 'friends' ), __('Find Friends', 'friends' ), 'read', 'find-friends', 'friends_find_output' );

	if ( $friends_enable_approval ) {
		$tmp_friend_requests_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "friends WHERE friend_user_ID = '" . $user_ID . "' AND friend_approved = '0'");
		add_submenu_page( 'friends', __( 'Friends', 'friends' ), __( 'Friend Requests', 'friends' ) . ' (' . $tmp_friend_requests_count . ')', 'read', 'friend-requests', 'friends_requests_output' );
	}
	add_submenu_page( 'friends', __( 'Friends', 'friends' ), __( 'Notifications', 'friends' ), 'read', 'friend-notifications', 'friends_notifications_output' );
}

function friends_output() {
    global $wpdb, $user_ID;
    $tmp_friends_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "friends WHERE user_ID = '" . $user_ID . "' AND friend_approved = '1'");
    if ( $tmp_friends_count > 0 ) {
        $title = __( 'Friends', 'friends' ) . ' (' . $tmp_friends_count . ')';
    } else {
        $title = __( 'Friends', 'friends' );
    }
    $parent_file = 'friends.php';

    friends_list_output();
}

function friends_admin_bar( $menu ) {
	unset( $menu['friends.php'] );
	return $menu;
}

function friends_add_notification( $tmp_to_uid, $tmp_from_uid ) {
	global $wpdb, $current_site, $user_ID, $friends_add_notification_subject, $friends_add_notification_content;
	if ( get_user_meta( $tmp_to_uid,'friend_email_notification') != 'no' ) {
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

function friends_request_approval_notification( $tmp_requesting_uid, $tmp_requested_uid ) {
	global $wpdb, $current_site, $user_ID, $friends_request_approval_notification_subject, $friends_request_approval_notification_content;
	if ( get_user_meta( $tmp_requesting_uid, 'friend_email_notification' ) != 'no' ) {
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

function friends_request_rejection_notification( $tmp_requesting_uid, $tmp_requested_uid ) {
	global $wpdb, $current_site, $user_ID, $friends_request_rejection_notification_subject, $friends_request_rejection_notification_content;
	if ( get_user_meta( $tmp_requesting_uid ,'friend_email_notification' ) != 'no' ) {
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

function friends_add( $tmp_uid, $tmp_friend_uid, $tmp_approved ) {
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

	if ( isset( $_GET['updated'] ) ) {
		?><div id="message" class="updated fade"><p><?php _e('' . urldecode( $_GET['updatedmsg'] ) . '') ?></p></div><?php
	}
	echo '<div class="wrap">';
    $action = ( isset( $_GET['action'] ) ) ? $_GET['action'] : NULL;
	switch( $action ) {
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
					foreach ($tmp_friends as $tmp_friend) {
                        //=========================================================//
                        $class = ( isset( $class ) ) ? NULL : 'alternate';
                        echo "<tr class='" . $class . "'>";
                        $tmp_display_name = $wpdb->get_var("SELECT display_name FROM " . $wpdb->base_prefix . "users WHERE ID = '" . $tmp_friend['friend_user_ID'] . "'");
                        $tmp_user_login = $wpdb->get_var("SELECT user_login FROM " . $wpdb->base_prefix . "users WHERE ID = '" . $tmp_friend['friend_user_ID'] . "'");
                        if ($tmp_display_name != $tmp_user_login){
                            echo "<td valign='top'><strong>" . $tmp_display_name . " (" . $tmp_user_login . ")</strong></td>";
                        } else {
                            echo "<td valign='top'><strong>" . $tmp_display_name . "</strong></td>";
                        }
                        echo "<td valign='top'>" . get_avatar($tmp_friend['friend_user_ID'],'16','') . "</td>";
                        $tmp_blog_ID = get_user_meta($tmp_friend['friend_user_ID'], 'primary_blog', true);
                        $tmp_blog_url = get_blog_option($tmp_blog_ID, 'siteurl');
                        $tmp_blog_path = $wpdb->get_var("SELECT path FROM " . $wpdb->base_prefix . "blogs WHERE blog_id = '" . $tmp_blog_ID . "'");
                        if ($messaging_current_version != ''){
                            echo "<td valign='top'><a href='admin.php?page=friends&action=send_message&fid=" . $tmp_friend['friend_user_ID'] . "' rel='permalink' class='edit'>" . __('Send Message') . "</a></td>";
                        }
                        if ($tmp_blog_url != ''){
                            echo "<td valign='top'><a href='" . $tmp_blog_url . "' rel='permalink' class='edit'>" . __('View Blog') . "</a></td>";
                        } else {
                            echo "<td valign='top'><a class='edit' style='color:#999999;text-decoration:none;border:0px;'>" . __('View Blog') . "</a></td>";
                        }
                        echo "<td valign='top'><a href='admin.php?page=friends&action=remove&fid=" . $tmp_friend['friend_ID'] . "' rel='permalink' class='delete'>" . __('Remove') . "</a></td>";
                        echo "</tr>";
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
            //get all data of friend (us)
            $query = "SELECT * FROM " . $wpdb->base_prefix . "friends WHERE friend_ID ='" .  $_GET['fid'] . "' AND user_ID = '" . $user_ID . "'";
            $delete_friend = $wpdb->get_row( $query, ARRAY_A );

            //get second friend_ID
            $tmp_friend_ID = $wpdb->get_var("SELECT friend_ID FROM " . $wpdb->base_prefix . "friends WHERE user_ID = '" . $delete_friend['friend_user_ID'] . "' AND  friend_user_ID = '" . $delete_friend['user_ID'] . "'");

            //delete from second friend
            if ( $tmp_friend_ID )
                $wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "friends WHERE friend_ID = '" . $tmp_friend_ID . "'" );

            //delete from us
            $wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "friends WHERE friend_ID = '" . $_GET['fid'] . "' AND user_ID = '" . $user_ID . "'" );

			echo "
			<SCRIPT LANGUAGE='JavaScript'>
                window.location='admin.php?page=friends&updated=true&updatedmsg=" . urlencode(__('Friend Removed!')) . "';
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

	if ( isset( $_GET['updated'] ) ) {
		?><div id="message" class="updated fade"><p><?php _e('' . urldecode($_GET['updatedmsg']) . '') ?></p></div><?php
	}
	echo '<div class="wrap">';
    $action = ( isset( $_GET['action'] ) ) ? $_GET['action'] : NULL;
	switch( $action ) {
		//---------------------------------------------------//
		default:
			$tmp_search_terms = ( isset( $_POST['search_terms'] ) ) ? $_POST['search_terms'] : NULL;
			if ( empty( $tmp_search_terms ) ){
				$tmp_search_terms = ( isset( $_GET['search_terms'] ) ) ? rawurldecode( $_GET['search_terms'] ) : NULL;
			}
			?>
            <form id="posts-filter" action="" method="post">
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
						echo '(<a href="admin.php?page=find-friends&action=add&id=' . $tmp_search_result['ID'] . '&search_terms=' . rawurlencode($tmp_search_terms) . '">' . __('Add') . '</a>) ';
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
				if ( $friends_enable_approval ) {
					friends_add($user_ID, $_GET['id'], '0');
					friends_add_notification($_GET['id'],$user_ID);
					echo "
					<SCRIPT LANGUAGE='JavaScript'>
                        window.location='admin.php?page=find-friends&search_terms=" . $_GET['search_terms'] . "&updated=true&updatedmsg=" . urlencode(__('Friend will be added pending approval.')) . "';
					</script>";
				} else {
					friends_add($user_ID, $_GET['id'], '1');
					friends_add_notification($_GET['id'],$user_ID);
					echo "
					<SCRIPT LANGUAGE='JavaScript'>
                        window.location='admin.php?page=find-friends&search_terms=" . $_GET['search_terms'] . "&updated=true&updatedmsg=" . urlencode(__('Friend Added!')) . "';
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
    $action = ( isset( $_GET['action'] ) ) ? $_GET['action'] : NULL;
	switch( $action ) {
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
						echo '<a href="admin.php?page=friend-requests&start=0&' . $order_sort . ' ">' . __('Previous Page') . '</a>';
					} else {
						echo '<a href="admin.php?page=friend-requests&start=' . ( $start - $num ) . '&' . $order_sort . '">' . __('Previous Page') . '</a>';
					}
					if ( $next ) {
						echo '&nbsp;||&nbsp;<a href="admin.php?page=friend-requests&start=' . ( $start + $num ) . '&' . $order_sort . '">' . __('Next Page') . '</a>';
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
                <thead>
                    <tr>
                        <th scope='col'>" . __('ID') . "</th>
                        <th scope='col'>" . __('User') . "</th>
                        <th scope='col'>" . __('Avatar') . "</th>
                        <th scope='col'>" . __('Actions') . "</th>
                        <th scope='col'></th>
                        <th scope='col'></th>
                    </tr>
                </thead>
				<tbody id='the-list'>
				";
				//=========================================================//
					foreach ( $tmp_friends as $tmp_friend ) {
                        //=========================================================//
                        $class = ( isset( $class ) ) ? NULL : 'alternate';
                        echo "<tr class='" . $class . "'>";
                        echo "<td valign='top'><strong>" . $tmp_friend['friend_ID'] . "</strong></td>";
                        $tmp_display_name = $wpdb->get_var("SELECT display_name FROM " . $wpdb->base_prefix . "users WHERE ID = '" . $tmp_friend['user_ID'] . "'");
                        $tmp_user_login = $wpdb->get_var("SELECT user_login FROM " . $wpdb->base_prefix . "users WHERE ID = '" . $tmp_friend['user_ID'] . "'");
                        if ($tmp_display_name != $tmp_user_login){
                            echo "<td valign='top'><strong>" . $tmp_display_name . " (" . $tmp_user_login . ")</strong></td>";
                        } else {
                            echo "<td valign='top'><strong>" . $tmp_display_name . "</strong></td>";
                        }
                        echo "<td valign='top'>" . get_avatar($tmp_friend['user_ID'],'16','') . "</td>";
                        $tmp_blog_ID = get_user_meta( $tmp_friend['user_ID'], 'primary_blog', true );
                        $tmp_blog_url = get_blog_option($tmp_blog_ID, 'siteurl');
                        $tmp_blog_path = $wpdb->get_var("SELECT path FROM " . $wpdb->base_prefix . "blogs WHERE blog_id = '" . $tmp_blog_ID . "'");
                        if ($tmp_blog_url != ''){
                            echo "<td valign='top'><a href='" . $tmp_blog_url . "' rel='permalink' class='edit'>" . __('View Blog') . "</a></td>";
                        } else {
                            echo "<td valign='top'><a class='edit' style='color:#999999;text-decoration:none;border:0px;'>" . __('View Blog') . "</a></td>";
                        }
                        echo "<td valign='top'><a href='admin.php?page=friend-requests&action=approve&fid=" . $tmp_friend['friend_ID'] . "' rel='permalink' class='edit'>" . __('Approve') . "</a></td>";
                        echo "<td valign='top'><a href='admin.php?page=friend-requests&action=reject&fid=" . $tmp_friend['friend_ID'] . "' rel='permalink' class='delete'>" . __('Reject') . "</a></td>";
                        echo "</tr>";
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


            $query = "SELECT * FROM " . $wpdb->base_prefix . "friends WHERE user_ID ='" . $user_ID . "' AND friend_user_ID = '" . $tmp_requesting_user_id . "' AND friend_approved = '0'";
            $tmp_friend = $wpdb->get_row( $query, ARRAY_A );

            if ( is_array( $tmp_friend ) ) {
                $wpdb->query( "UPDATE " . $wpdb->base_prefix . "friends SET friend_approved = '1' WHERE friend_ID = '" . $tmp_friend['friend_ID'] . "'" );
            } else {
                $wpdb->query( "INSERT INTO " . $wpdb->base_prefix . "friends (user_ID, friend_user_ID, friend_approved) VALUES ( '" . $user_ID . "','" . $tmp_requesting_user_id . "','1' )" );
            }


			friends_request_approval_notification( $tmp_requesting_user_id, $user_ID );

			echo "
			<SCRIPT LANGUAGE='JavaScript'>
                window.location='admin.php?page=friend-requests&updated=true&updatedmsg=" . urlencode(__('Friend Request Approved!')) . "';
			</script>
			";
		break;
		//---------------------------------------------------//
		case "reject":
			$tmp_requesting_user_id = $wpdb->get_var("SELECT user_ID FROM " . $wpdb->base_prefix . "friends WHERE friend_ID = '" . $_GET['fid'] . "'");


            //get all data of second friend
            $query = "SELECT * FROM " . $wpdb->base_prefix . "friends WHERE friend_ID ='" .  $_GET['fid'] . "' AND friend_user_ID = '" . $user_ID . "'";
            $reject_friend = $wpdb->get_row( $query, ARRAY_A );

            //get us friend_ID
            $tmp_friend_ID = $wpdb->get_var("SELECT friend_ID FROM " . $wpdb->base_prefix . "friends WHERE user_ID = '" . $reject_friend['friend_user_ID'] . "' AND  friend_user_ID = '" . $reject_friend['user_ID'] . "'");

            //delete from second friend
            if ( $tmp_friend_ID )
                $wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "friends WHERE friend_ID = '" . $tmp_friend_ID . "'" );

            //delete from us
            $wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "friends WHERE friend_ID = '" . $_GET['fid'] . "' AND friend_user_ID = '" . $user_ID . "'" );


			friends_request_rejection_notification($tmp_requesting_user_id,$user_ID);

			echo "
			<SCRIPT LANGUAGE='JavaScript'>
                window.location='admin.php?page=friend-requests&updated=true&updatedmsg=" . urlencode(__('Friend Request Rejected!')) . "';
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
    $action = ( isset( $_GET['action'] ) ) ? $_GET['action'] : NULL;
	switch( $action ) {
		//---------------------------------------------------//
		default:
			$tmp_friend_email_notification = get_user_meta( $user_ID,'friend_email_notification', true );
			?>
			<h2><?php _e('Notification Settings') ?></h2>
            <form method="post" action="admin.php?page=friend-notifications&action=process">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php _e('Receive an email when someone adds you as a friend') ?></th>
                        <td>
                            <select name="message_email_notification" id="message_email_notification">
                                <option value="yes" <?php if ( $tmp_friend_email_notification == 'yes' ) { echo 'selected="selected"'; } ?> ><?php _e('Yes') ?></option>
                                <option value="no"  <?php if ( $tmp_friend_email_notification == 'no' )  { echo 'selected="selected"'; } ?> ><?php _e('No') ?></option>
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
			update_user_meta( $user_ID,'friend_email_notification', $_POST['message_email_notification'] );
			echo "
			<SCRIPT LANGUAGE='JavaScript'>
                window.location='admin.php?page=friend-notifications&updated=true&updatedmsg=" . urlencode('Settings saved.') . "';
			</script>
			";
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}


function widget_friends_init() {
	global $wpdb, $user_ID, $friends_enable_approval;

	// This saves options and prints the widget's config form.
	function widget_friends_control() {
		global $wpdb, $user_ID, $friends_enable_approval;
		$options = $newoptions = get_option('widget_friends');
		if ( isset( $_POST['friends-submit'] ) ) {
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

		foreach ( $defaults as $key => $value ) {
			if ( !isset($options[$key]) )
				$options[$key] = $defaults[$key];
        }
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
				if ( count( $tmp_friends ) > 0 ) {
					if ( $options['friends-display'] == 'list' ){
						echo '<ul>';
						foreach ( $tmp_friends as $tmp_friend ){
							echo '<li>';
							$tmp_blog_ID = get_user_meta( $tmp_friend['friend_user_ID'], 'primary_blog', true );
							$tmp_blog_url = get_blog_option( $tmp_blog_ID, 'siteurl' );
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
							$tmp_blog_ID = get_user_meta($tmp_friend['friend_user_ID'], 'primary_blog', true);
							$tmp_blog_url = get_blog_option($tmp_blog_ID, 'siteurl');
							$tmp_user_display_name = $wpdb->get_var("SELECT display_name FROM " . $wpdb->users . " WHERE ID = '" . $tmp_friend['friend_user_ID'] . "'");
							if ($tmp_user_display_name == ''){
								$tmp_user_display_name = $wpdb->get_var("SELECT user_login FROM " . $wpdb->users . " WHERE ID = '" . $tmp_friend['friend_user_ID'] . "'");
							}
							if ( $tmp_blog_url != '' ){
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
    wp_register_sidebar_widget( 'widget_friends', __( 'Friends', 'friends' ), 'widget_friends' );
	wp_register_widget_control( 'widget_friends', __( 'Friends', 'friends' ), 'widget_friends_control' );

}
add_action('widgets_init', 'widget_friends_init');

//------------------------------------------------------------------------//
//---Support Functions----------------------------------------------------//
//------------------------------------------------------------------------//

/* Update Notifications Notice */
if ( !function_exists( 'wdp_un_check' ) ):
function wdp_un_check() {
    if ( !class_exists('WPMUDEV_Update_Notifications') && current_user_can('edit_users') )
        echo '<div class="error fade"><p>' . __('Please install the latest version of <a href="http://premium.wpmudev.org/project/update-notifications/" title="Download Now &raquo;">our free Update Notifications plugin</a> which helps you stay up-to-date with the most stable, secure versions of WPMU DEV themes and plugins. <a href="http://premium.wpmudev.org/wpmu-dev/update-notifications-plugin-information/">More information &raquo;</a>', 'wpmudev') . '</a></p></div>';
}
add_action( 'admin_notices', 'wdp_un_check', 5 );
add_action( 'network_admin_notices', 'wdp_un_check', 5 );
endif;

?>