<?php
/*
Plugin Name: Friends
Plugin URI: http://premium.wpmudev.org/project/friends
Description: Lets your users 'friend' each other, display funky widgets with avatar mosaics of all their friends on the site and generally get all social!
Author: Paul Menard (Incsub)
Author URI: http://premium.wpmudev.org
Version: 1.3.1.1
Network: true
WDP ID: 62
Domain Path: languages

Copyright 2007-2012 Incsub (http://incsub.com)

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

if (!defined('WPMUDEV_FRIENDS_I18N_DOMAIN'))
	define('WPMUDEV_FRIENDS_I18N_DOMAIN', 'friends');

$FRIENDS_ALLOWED_CONTENT_TAGS = array(
	'a' 		=> 	array('href' => array(),'title' => array()),
  	'p'			=>	array(),
	'em'		=>	array(),
	'ul'		=>	array(),
	'ol'		=>	array(),
	'li'		=>	array(),
	'br'		=>	array(),
	'strong'	=>	array(),
	'img'		=>	array()
);

if ( !class_exists( "WPMUDev_Friends" ) ) {
	class WPMUDev_Friends {

		private $_config_data	= array();
		private $_settings		= array();	
		private $_messages		= array();	
		
		private $_admin_header_error;	// Set during processing will contain processing errors to display back to the user
		private $_admin_header_warning;	// Set during processing will contain processing warnings to display back to the user

		/**
		 * The PHP5 Class constructor. Used when an instance of this class is needed.
		 * Sets up the initial object environment and hooks into the various WordPress 
		 * actions and filters.
		 *
		 * @since 1.2.1
		 * @uses $this->_settings array of our settings
		 * @uses $this->_messages array of admin header message texts.
		 * @param none
		 * @return self
		 */
		function __construct() {

			$this->_settings['options_key'] 			= "friends-plugin-options";					
			$this->_settings['plugins'] 				= array();
			
			$this->_admin_header_error 					= "";		
			$this->_admin_header_warning 				= "";
			
			// Add support for new WPMUDEV Dashboard Notices
			global $wpmudev_notices;
			$wpmudev_notices[] = array( 'id'=> 62,'name'=> 'Friends', 'screens' => array( 'toplevel_page_friend-settings-network', 'toplevel_page_friends', 'friends_page_find-friends', 'friends_page_friend-requests' ) );
			include_once( dirname(__FILE__) . '/lib/dash-notices/wpmudev-dash-notification.php' );
			
			/* Setup the tetdomain for i18n language handling see http://codex.wordpress.org/Function_Reference/load_plugin_textdomain */
	        

			if (preg_match('/mu\-plugin/', PLUGINDIR) > 0) {
				load_muplugin_textdomain( WPMUDEV_FRIENDS_I18N_DOMAIN, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
			} else {
				load_plugin_textdomain( WPMUDEV_FRIENDS_I18N_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
			}

			register_activation_hook( __FILE__, 	array(&$this, 'friends_global_install' ) );
			add_action( 'admin_notices', 			array(&$this, 'friends_admin_notices') );			
			add_action( 'wpabar_menuitems', 		array(&$this, 'friends_admin_bar' ) );
			add_action( 'admin_menu', 				array(&$this, 'friends_admin_menu' ) );
			add_action( 'network_admin_menu', 		array(&$this, 'friends_admin_menu') );						
			add_action( 'admin_init', 				array(&$this, 'friends_admin_init' ) );
			
			// Called when User form is shown for edit. 
			add_action( 'show_user_profile', 		array(&$this, 'friends_user_edit' ) );
			add_action( 'edit_user_profile', 		array(&$this, 'friends_user_edit' ) );
			add_action( 'personal_options_update', 	array(&$this, 'friends_user_save' ) );
			add_action( 'edit_user_profile_update', array(&$this, 'friends_user_save' ) );
		}
	
		/**
		 * The old-style PHP Class constructor. Used when an instance of this class 
	 	 * is needed. If used (PHP4) this function calls the PHP5 version of the constructor.
		 *
		 * @since 1.2.1
		 * @param none
		 * @return self
		 */
	    function WPMUDev_Friends() {
	        $this->__construct();
	    }	
		
		/**
		 * The activation hook process to setup the database. 
		 *
		 * @since 1.0.0
		 * @uses $wodb
		 * @param none
		 */
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
	
		/**
		 * Remove our reference from the WP Admin menubar. (Not sure why!)
		 *
		 * @since 1.0.0
		 * @param $menu - Passed from WordPress
		 */
		function friends_admin_bar( $menu ) {
			unset( $menu['friends.php'] );
			return $menu;
		}

		/**
		 * Main admin init hook. Here we check if the Messaging plugin is installed and active. We set a global setting used later
		 *
		 * @since 1.2.1
		 * @param none
		 */
		function friends_admin_init() {
			
			if ((is_plugin_active('messaging/messaging.php'))) {
				$this->_settings['plugins']['messaging'] = true;
			} else if ((is_multisite()) && (is_plugin_active_for_network('messaging/messaging.php'))) {
				$this->_settings['plugins']['messaging'] = true;
			} else {
				$this->_settings['plugins']['messaging'] = false;
			}

			if ( (is_plugin_active('wordpress-chat/wordpress-chat.php')) || (is_plugin_active('wordpress-chat/wordpress-chat.php')) ) {
				if (function_exists('wpmudev_chat_get_chat_status_label')) 
					$this->_settings['plugins']['chat'] = true;
			} else if ((is_multisite()) && (is_plugin_active_for_network('wordpress-chat/wordpress-chat.php'))) {
				if (function_exists('wpmudev_chat_get_chat_status_label')) 
					$this->_settings['plugins']['chat'] = true;
			} else {
				$this->_settings['plugins']['chat'] = false;
			}
			
			//echo "_settings<pre>"; print_r($this->_settings); echo "</pre>";
		}
		
		/**
		 * Called when a user profile form is being shown. Here we are adding a new section so the user can control the Email
		 * Notification from this plugin. This was formerly part of a menu option but was confusion because this appeared it would
		 * be global when actually it was setting a user meta variable. So moved this to the user profile. 
		 *
		 * @since 1.2.1
		 * @uses $user - User structure passed from WordPress
		 * @param none
		 */
		function friends_user_edit($user) {

			$tmp_friend_email_notification = get_user_meta( $user->ID, 'friend_email_notification', true ); 
			?>
			<h3><?php _e('Friends Notifications', WPMUDEV_FRIENDS_I18N_DOMAIN); ?></h3>

			<table class="form-table">
			<tr valign="top">
			    <th><label for="message_email_notification"><?php _e('Email Notification',
		 			WPMUDEV_FRIENDS_I18N_DOMAIN) ?></label>
				</th>
				<td>					
			        <select name="message_email_notification" id="message_email_notification">
			            <option value="yes" <?php 
							if ( $tmp_friend_email_notification == 'yes' ) 
								{ echo 'selected="selected"'; } ?> ><?php _e('Yes', WPMUDEV_FRIENDS_I18N_DOMAIN) ?></option>
			            <option value="no"  <?php 
							if ( $tmp_friend_email_notification == 'no' )  
							{ echo 'selected="selected"'; } ?> ><?php _e('No', WPMUDEV_FRIENDS_I18N_DOMAIN) ?></option>
			        </select> <span class="description"><?php 
						_e('Receive an email when someone adds you as a friend', WPMUDEV_FRIENDS_I18N_DOMAIN); ?></span>					
			    </td>
			</tr>
			</table>
			<?php            
		}
		
		/**
		 * Called when a user profile is saved. See previous function where we setup the additional section. This function save
		 * our new form variable is present. The variable is saved as a user meta variable. 
		 *
		 * @since 1.2.1
		 * @uses $user_id - ID of the user being saved
		 * @param none
		 */
		
		function friends_user_save($user_id) {

			if ( !current_user_can( 'edit_user', $user_id ) )
					return false;

			if (isset($_POST['message_email_notification'])) {
				if (sanitize_text_field($_POST['message_email_notification']) == "no")
					update_user_meta( $user_id, 'friend_email_notification', 'no' );
				else
					update_user_meta( $user_id, 'friend_email_notification', 'yes' );
			}
		}
		
	
		/**
		 * Display messages to the user from actions performed like adding, approving, rejecting friends. 
		 *
		 * @since 1.2.1
		 * @uses $this->_messages Set in form processing functions
		 *
		 * @param none
		 * @return none
		 */
		function friends_admin_notices() {

			// IF set during the processing logic setsp for add, edit, restore
			if (isset($_REQUEST['updatedmsg'])) {
				$updatedmsg_key = sanitize_text_field($_REQUEST['updatedmsg']);
				if (isset($this->_messages[$updatedmsg_key])) {
					?><div id='friends-warning' class='updated fade'><p><?php echo $this->_messages[$updatedmsg_key]; ?></p></div><?php
				}
			}

			// IF we set an error display in red box
			if (strlen($this->_admin_header_error)) {
				?><div id='friends-error' class='error'><p><?php echo $this->_admin_header_error; ?></p></div><?php
			}

			if (strlen($this->_admin_header_warning)) {
				?><div id='friends-warning' class='updated fade'><p><?php echo $this->_admin_header_warning; ?></p></div><?php
			}
		}
	
		/**
		 * Setup our Friends menu page
		 *
		 * @since 1.2.1
		 *
		 * @param none
		 * @return none
		 */
	
		function friends_admin_menu() {
			global $wpdb, $user_ID;

			// Are we viewing the Network 
			if (is_network_admin()) {
				$this->_pagehooks['friends-settings'] = add_menu_page( 	
								__('Friends Settings', WPMUDEV_FRIENDS_I18N_DOMAIN ), 
								__('Friends Settings', WPMUDEV_FRIENDS_I18N_DOMAIN ), 
								'manage_network_options', 
								'friend-settings', 
								array(&$this, 'friends_settings_output')
				);
				add_action('load-'. $this->_pagehooks['friends-settings'], 	array(&$this, 'friends_on_load_settings_panel'));							
			    
			} else {

				$count_output = '';
				$request_count = $this->friends_get_request_count();
				if ($request_count > 0) {
					$count_output = '&nbsp;<span class="update-plugins"><span class="updates-count count-' . $request_count . '">' 
						. $request_count . '</span></span>';				
				}

			    $this->_pagehooks['friends'] = add_menu_page( 	__('Friends', WPMUDEV_FRIENDS_I18N_DOMAIN ), 
								__('Friends', WPMUDEV_FRIENDS_I18N_DOMAIN ), 
								'read', 
								'friends', 
								array(&$this, 'friends_page_output')
				);
				
			    $this->_pagehooks['find-friends'] = add_submenu_page( 'friends', 
								__('Friends', WPMUDEV_FRIENDS_I18N_DOMAIN ), 
								__('Find Friends', WPMUDEV_FRIENDS_I18N_DOMAIN ), 
								'read', 
								'find-friends', 
								array(&$this, 'friends_find_output') 
				);
			
			    $this->_pagehooks['friend-requests'] = add_submenu_page( 'friends', 
								__( 'Friends', WPMUDEV_FRIENDS_I18N_DOMAIN ), 
								__( 'Friend Requests', WPMUDEV_FRIENDS_I18N_DOMAIN ) . $count_output, 
								'read', 
								'friend-requests', 
								array(&$this, 'friends_requests_output')
				);
				
				add_action('load-'. $this->_pagehooks['friends'], 			array(&$this, 'friends_on_load_panels'));
				add_action('load-'. $this->_pagehooks['find-friends'], 		array(&$this, 'friends_on_load_panels'));
				add_action('load-'. $this->_pagehooks['friend-requests'], 	array(&$this, 'friends_on_load_panels'));
				
				
				if (!is_multisite()) {
				    $this->_pagehooks['friends-settings'] = add_submenu_page( 'friends', 
									__( 'Friends Settings', WPMUDEV_FRIENDS_I18N_DOMAIN ), 
									__( 'Friends Settings', WPMUDEV_FRIENDS_I18N_DOMAIN ), 
									'manage_options', 
									'friend-settings', 
									array(&$this, 'friends_settings_output')
					);
					add_action('load-'. $this->_pagehooks['friends-settings'], 	array(&$this, 'friends_on_load_settings_panel'));					
				}				
			} 
		}	
		
		/**
		 * Called using the built-in hooks from WordPress. This function is called based on the add_action from the previous 
		 * function which allows us to load CSS, JS things just for our Friends pages. 
		 *
		 * @since 1.2.1
		 *
		 * @param none
		 * @return none
		 */
		
		function friends_on_load_panels() {
			
			if ( ! current_user_can( 'read' ) )
				wp_die( __( 'Cheatin&#8217; uh?' ) );

			//if (!is_multisite())
			//	return;

			$this->load_config();
			$this->friends_process_actions();
			$this->friends_admin_plugin_help();
			
			/* These messages are displayed as part of the admin header message see 'admin_notices' WordPress action */
			$this->_messages['success-remove'] 			= __( "Friend Removed", WPMUDEV_FRIENDS_I18N_DOMAIN );
			$this->_messages['success-add'] 			= __( "Friend will be added pending approval.", WPMUDEV_FRIENDS_I18N_DOMAIN );
			$this->_messages['friend-approved'] 		= __( "Friend Request Approved", WPMUDEV_FRIENDS_I18N_DOMAIN );
			$this->_messages['friend-rejected'] 		= __( "Friend Request Rejected", WPMUDEV_FRIENDS_I18N_DOMAIN );
			$this->_messages['message-send-success'] 	= __( "Quick Message Success.", WPMUDEV_FRIENDS_I18N_DOMAIN );
			$this->_messages['message-send-fail'] 		= __( "Quick Message Failed.", WPMUDEV_FRIENDS_I18N_DOMAIN );
						
			wp_enqueue_style( 'friends-admin-stylesheet', plugins_url('css/friends-admin.css', __FILE__), false);	
			
		}
				
		/**
		 * Called using the built-in hooks from WordPress. This function is called based on the add_action from the previous 
		 * function which allows us to load CSS, JS things just for our Friends pages. 
		 *
		 * @since 1.2.1
		 *
		 * @param none
		 * @return none
		 */
		function friends_on_load_settings_panel() {
			global $wpdb;
			
			// Call the main page load function to kick things off. Then the rest of this function is specific to just Settings page options like
			// the metaboxes. 
			
			$this->friends_on_load_panels();
			
			$this->_messages['settings-saved'] 			= __( "Settings saved", WPMUDEV_FRIENDS_I18N_DOMAIN );
			
			wp_enqueue_script('common');
			wp_enqueue_script('wp-lists');
			wp_enqueue_script('postbox');
			
			if (((is_multisite()) && (is_network_admin())) || (!is_multisite())) {
				add_meta_box('friends-display-panel-email-request', 
					__('Email Request Template', WPMUDEV_FRIENDS_I18N_DOMAIN), 
					array(&$this, 'friends_metabox_show_email_request'), 
					$this->_pagehooks['friends-settings'], 
					'normal', 'core');

				add_meta_box('friends-display-panel-email-approval', 
					__('Email Approval Template', WPMUDEV_FRIENDS_I18N_DOMAIN), 
					array(&$this, 'friends_metabox_show_email_approval'), 
					$this->_pagehooks['friends-settings'], 
					'normal', 'core');

				add_meta_box('friends-display-panel-email-rejection', 
					__('Email Rejection Template', WPMUDEV_FRIENDS_I18N_DOMAIN), 
					array(&$this, 'friends_metabox_show_email_rejection'), 
					$this->_pagehooks['friends-settings'], 
					'normal', 'core');

/*
				add_meta_box('friends-display-panel-messaging', 
					__('Messaging', WPMUDEV_FRIENDS_I18N_DOMAIN), 
					array(&$this, 'friends_metabox_show_messaging'), 
					$this->_pagehooks['friends-settings'], 
					'normal', 'core');
*/

			} else {

			}
		}
		
		/**
		 * Called from our class function friends_on_load_panels() to process any form post/get information. This is run before HTML starts. 
		 *
		 * @since 1.2.1
		 *
		 * @param none
		 * @return none
		 */

		function friends_process_actions() {

			global $wpdb, $user_ID, $FRIENDS_ALLOWED_CONTENT_TAGS;
			
			// Check that user posting form have appropriate user capabilities
			if (is_network_admin()) {
				if ( ! current_user_can( 'manage_network_options' ) )
					return;
					
			} else {
				if ( ! current_user_can( 'read' ) )
					return;				
			}
			
			if (isset($_REQUEST['action'])) {			

				switch(sanitize_text_field($_REQUEST['action'])) {

					case "add":
						$tmp_friend_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "friends WHERE friend_user_ID = %d AND user_ID = %d", $_GET['id'], $user_ID));
							
						if ( $user_ID != intval($_GET['id']) || $tmp_friend_count < 1 ) {
							$this->friends_add($user_ID, intval($_GET['id']), '0');
							$this->friends_add_notification(intval($_GET['id']), $user_ID);
						}
						
						$location = remove_query_arg(array('action', 'id'));
						$location = add_query_arg('updatedmsg', 'success-add', $location);
						if ($location) {
							wp_redirect($location);
							die();
						}
						
					break;

					case "process":
//echo "_POST<pre>"; print_r($_POST); echo "</pre>";
//die();
						if ((isset($_POST['friends_add_request_notification_subject'])) 
						 && (isset($_POST['friends_add_request_notification_content']))) {

							if ((isset($_POST['reset'])) && (sanitize_text_field($_POST['reset']) == "Reset")) {

								unset($this->_config_data['templates']['friends_add_request_notification_subject']);
								unset($this->_config_data['templates']['friends_add_request_notification_content']);

							} else {

								$this->_config_data['templates']['friends_add_request_notification_subject'] =
								 	stripslashes(sanitize_text_field($_POST['friends_add_request_notification_subject']));

								$this->_config_data['templates']['friends_add_request_notification_content'] =
								 	stripslashes(wp_kses($_POST['friends_add_request_notification_content'], $FRIENDS_ALLOWED_CONTENT_TAGS));
							}
						}
						
						
						if ((isset($_POST['friends_request_approval_notification_subject']))
						 && (isset($_POST['friends_request_approval_notification_content']))) {

							if ((isset($_POST['reset'])) && (sanitize_text_field($_POST['reset']) == "Reset")) {

								unset($this->_config_data['templates']['friends_request_approval_notification_subject']);
								unset($this->_config_data['templates']['friends_request_approval_notification_content']);

							} else {
								$this->_config_data['templates']['friends_request_approval_notification_subject'] =
								 	stripslashes(sanitize_text_field($_POST['friends_request_approval_notification_subject']));

								$this->_config_data['templates']['friends_request_approval_notification_content'] =
								 	stripslashes(wp_kses($_POST['friends_request_approval_notification_content'], $FRIENDS_ALLOWED_CONTENT_TAGS));
							}
						}


						if ((isset($_POST['friends_request_rejection_notification_subject']))
						 && (isset($_POST['friends_request_rejection_notification_content']))) {

							if ((isset($_POST['reset'])) && (sanitize_text_field($_POST['reset']) == "Reset")) {

								unset($this->_config_data['templates']['friends_request_rejection_notification_subject']);
								unset($this->_config_data['templates']['friends_request_rejection_notification_content']);

							} else {
								$this->_config_data['templates']['friends_request_rejection_notification_subject'] =
								 	stripslashes(sanitize_text_field($_POST['friends_request_rejection_notification_subject']));

								$this->_config_data['templates']['friends_request_rejection_notification_content'] =
								 	stripslashes(wp_kses($_POST['friends_request_rejection_notification_content'], $FRIENDS_ALLOWED_CONTENT_TAGS));
							}
						}

						$this->save_config();

						$location = add_query_arg('updatedmsg', 'settings-saved');
						if ($location) {
							wp_redirect($location);
							die();
						}

					break;


					case "remove":
						$query = $wpdb->prepare("SELECT * FROM " . $wpdb->base_prefix . "friends WHERE friend_ID ='%d' AND user_ID = %d", $_GET['fid'], $user_ID);
						$delete_friend = $wpdb->get_row( $query, ARRAY_A );

						//get second friend_ID
						$tmp_friend_ID = $wpdb->get_var($wpdb->prepare("SELECT friend_ID FROM " . $wpdb->base_prefix . "friends WHERE user_ID = %d AND  friend_user_ID = %d", $delete_friend['friend_user_ID'], $delete_friend['user_ID']));

						//delete from second friend
						if ( $tmp_friend_ID )
							$wpdb->query( $wpdb->prepare("DELETE FROM " . $wpdb->base_prefix . "friends WHERE friend_ID = %d", $tmp_friend_ID ));

						//delete from us
						$wpdb->query( $wpdb->prepare("DELETE FROM " . $wpdb->base_prefix . "friends WHERE friend_ID = %d AND user_ID = %d",  $_GET['fid'], $user_ID));

						$location = remove_query_arg(array('action', 'fid'));
						$location = add_query_arg('updatedmsg', 'success-remove', $location);
						if ($location) {
							wp_redirect($location);
							die();
						}
						break;

					case "approve":
						$tmp_requesting_user_id = $wpdb->get_var($wpdb->prepare("SELECT user_ID FROM " . $wpdb->base_prefix 
							. "friends WHERE friend_ID = %d", $_GET['fid']));

						if ($tmp_requesting_user_id > 0) {
							//$wpdb->query( "UPDATE " . $wpdb->base_prefix . "friends SET friend_approved = '1' WHERE friend_ID = '" 
							//	. $_GET['fid'] . "' AND friend_user_ID = '" . $user_ID . "'" );
							$wpdb->update($wpdb->base_prefix . "friends", 
								array(
									'friend_approved' 	=> '1'
								),
								array(
									'friend_ID'			=>	$_GET['fid'],
									'friend_user_ID'	=>	$user_ID
								), array('%d'), array('%d', '%d')
							);

							$query = $wpdb->prepare("SELECT * FROM " . $wpdb->base_prefix . "friends WHERE user_ID ='" . $user_ID . "' AND friend_user_ID = %d AND friend_approved = %d", $tmp_requesting_user_id, '0');
							$tmp_friend = $wpdb->get_row( $query, ARRAY_A );

							if ( is_array( $tmp_friend ) ) {
								//$wpdb->query( "UPDATE " . $wpdb->base_prefix . "friends SET friend_approved = '1' WHERE friend_ID = '" 
								//	. $tmp_friend['friend_ID'] . "'" );
								$wpdb->update($wpdb->base_prefix . "friends", 
									array(
										'friend_approved' 	=> '1'
									),
									array(
										'friend_ID'			=>	$_GET['fid'],
									), array('%d'), array('%d')
								);
								
							} else {
								//$wpdb->query( "INSERT INTO " . $wpdb->base_prefix . "friends (user_ID, friend_user_ID, friend_approved) VALUES ( '" 
								//	. $user_ID . "','" . $tmp_requesting_user_id . "','1' )" );
								$wpdb->insert($wpdb->base_prefix . "friends", array(
									'user_ID'			=>	$user_ID, 
									'friend_user_ID'	=>	$tmp_requesting_user_id, 
									'friend_approved'	=>	'1'),
									array('%d', '%d', '%d')
								);
							}
							$this->friends_request_approval_notification( $tmp_requesting_user_id, $user_ID );							

							$location = remove_query_arg(array('action', 'fid'));
							$location = add_query_arg('updatedmsg', 'friend-approved', $location);
							if ($location) {
								wp_redirect($location);
								die();
							}
						}
						break;

					case "reject":
						$tmp_requesting_user_id = $wpdb->get_var($wpdb->prepare("SELECT user_ID FROM " . $wpdb->base_prefix 
							. "friends WHERE friend_ID = %d", $_GET['fid']));
						if ($tmp_requesting_user_id) {

							$this->friends_request_rejection_notification($tmp_requesting_user_id, $user_ID);

							//get all data of second friend
							$query = $wpdb->prepare("SELECT * FROM " . $wpdb->base_prefix . "friends WHERE friend_ID =%d AND friend_user_ID = %d", $_GET['fid'], $user_ID);
							$reject_friend = $wpdb->get_row( $query, ARRAY_A );

							//get us friend_ID
							$tmp_friend_ID = $wpdb->get_var($wpdb->prepare("SELECT friend_ID FROM " . $wpdb->base_prefix . "friends WHERE user_ID = %d AND friend_user_ID = %d", $reject_friend['friend_user_ID'], $reject_friend['user_ID']));

							//delete from second friend
							if ( $tmp_friend_ID )
								$wpdb->query( $wpdb->prepare("DELETE FROM " . $wpdb->base_prefix . "friends WHERE friend_ID = %d", $tmp_friend_ID));

							//delete from us
							$wpdb->query( $wpdb->prepare("DELETE FROM " . $wpdb->base_prefix . "friends WHERE friend_ID = %d AND friend_user_ID = %d", $_GET['fid'], $user_ID));

							$location = remove_query_arg(array('action', 'fid'));
							$location = add_query_arg('updatedmsg', 'friend-rejected', $location);
							if ($location) {
								wp_redirect($location);
								die();
							}
						}
						break;

/*
					case 'send_message':
						if ((isset($_POST['message_to'])) && (strlen($_POST['message_to']))) {
							//echo "_REQUEST<pre>"; print_r($_REQUEST); echo "</pre>";
							//exit;							
							$this->send_quick_message();
						} if ($this->_settings['plugins']['messaging'] != true) {
							
							$this->_admin_header_warning = 
							__('For better messaging install the', WPMUDEV_FRIENDS_I18N_DOMAIN) . ' <a href="http://premium.wpmudev.org/project/messaging">WPMU Dev Messaging plugin</a>';
						}
						break;
*/					
					default:
						break;
				}
			}
		}
		
		/**
		 * Setup the context help instances for the user
		 *
		 * @since 1.2.1
		 * @uses $screen global screen instance
		 * @uses $screen->add_help_tab function to add the help sections
		 * @see $this->on_load_main_page where this function is referenced
		 *
		 * @param none
		 * @return none
		 */
		function friends_admin_plugin_help() {

			global $wp_version;

			$screen = get_current_screen();
			//echo "screen<pre>"; print_r($screen); echo "</pre>";

			$screen_help_text = array();
			$screen_help_text['friends-help-overview'] = '<p>' . __( "The Friends plugin lets users connect with other users within the WordPress Multisite environment. Once connected friends can send message to each other. Also available is a handy widget to display a user's friends listing", WPMUDEV_FRIENDS_I18N_DOMAIN ) . '</p>';

			$screen_help_text['friends-help-friends'] = '<p>'. __('The Friends listing page will show all user your have connections with. From the listing you can send messages to users. Also you can remove users who may have become unfriendly.', WPMUDEV_FRIENDS_I18N_DOMAIN ) .'</p><p>' . __("<strong>Friend</strong> - This is the user's display name.", WPMUDEV_FRIENDS_I18N_DOMAIN ) .'</p><p>' . __("<strong>Avatar</strong> - The user's avatar", WPMUDEV_FRIENDS_I18N_DOMAIN ) .'</p><p>' . __( '<strong>Send Message</strong> - Once you are connected to other friends you can send them messages.', WPMUDEV_FRIENDS_I18N_DOMAIN ) . '</p><p>' . __("<strong>View Blog</strong> - View the user's blog", WPMUDEV_FRIENDS_I18N_DOMAIN ) .'</p><p>' . __( '<strong>Remove</strong> - Allows you to remove the user as a friend.', WPMUDEV_FRIENDS_I18N_DOMAIN ) . '</p>';


			$screen_help_text['friend-help-find'] = '<p>' . __( 'Using the search on this page you can search for other users within this Multisite system. You can locate other user by searching for Name, Login or Email. Click the Search button and all matching users will be listed. Beside each user will be an Add link which will create a request to the user. The user will need to accept the request to complete the friend connection. Within the list if you see Pending instead of Add this means the friend request has already been sent to this user.', WPMUDEV_FRIENDS_I18N_DOMAIN ) .'</p>';

			$screen_help_text['friend-help-request'] = '<p>' . __( 'When another user requests you to be their friend they will be listed on this page. To complete the friend connection you must approve the request. You may also opt to reject the request.', WPMUDEV_FRIENDS_I18N_DOMAIN ) .'</p>';

			$screen_help_text['friend-help-settings'] = '<p>' . __( 'This page contains various metaboxes to let you control the email templates used during the friend connection processing.', WPMUDEV_FRIENDS_I18N_DOMAIN ) .'</p><p>' . __("<strong>Email Request Template</strong> - This template contains the Subject and Content used when one user requests to be friend's with another user. ", WPMUDEV_FRIENDS_I18N_DOMAIN ) . '</p><p>' . __("<strong>Email Approval Template</strong> - This template contains the Subject and Content used when one user approves a friend request from another user. ", WPMUDEV_FRIENDS_I18N_DOMAIN ) . "</p><p>" . __("<strong>Email Rejection Template</strong> - This template contains the Subject and Content used when one user rejects a friend request from another user. ", WPMUDEV_FRIENDS_I18N_DOMAIN ) . "</p>";

			if ( version_compare( $wp_version, '3.3.0', '>' ) ) {

				$screen->add_help_tab( array(
					'id'		=> 'friends-help-overview',
					'title'		=> __('Overview', WPMUDEV_FRIENDS_I18N_DOMAIN ),
					'content'	=> $screen_help_text['friends-help-overview']
		    		) 
				);		

				if ((isset($_REQUEST['page'])) && (sanitize_text_field($_REQUEST['page']) == "friends")) {

					$screen->add_help_tab( array(
						'id'		=> 'friends-help-friends',
						'title'		=> __('Friends', WPMUDEV_FRIENDS_I18N_DOMAIN ),
						'content'	=>  $screen_help_text['friends-help-friends']
				    	) 
					);
				}
				else if ((isset($_REQUEST['page'])) && (sanitize_text_field($_REQUEST['page']) == "find-friends")) {

					$screen->add_help_tab( array(
						'id'		=> 'friends-help-find',
						'title'		=> __('Find Friends', WPMUDEV_FRIENDS_I18N_DOMAIN ),
						'content'	=> $screen_help_text['friend-help-find']
					    ) 
					);
				} else if ((isset($_REQUEST['page'])) && (sanitize_text_field($_REQUEST['page']) == "friend-requests")) {

					$screen->add_help_tab( array(
						'id'		=> 'friends-help-requests',
						'title'		=> __('Friend Request', WPMUDEV_FRIENDS_I18N_DOMAIN ),
						'content'	=> $screen_help_text['friend-help-request']
					    ) 
					);
				} else if ((isset($_REQUEST['page'])) && (sanitize_text_field($_REQUEST['page']) == "friend-settings")) {

					$screen->add_help_tab( array(
						'id'		=> 'friends-help-settings',
						'title'		=> __('Friend Settings', WPMUDEV_FRIENDS_I18N_DOMAIN ),
						'content'	=> $screen_help_text['friend-help-settings']
					    ) 
					);
				} 
				
				
				
			} else {

				if ((isset($_REQUEST['page'])) && (sanitize_text_field($_REQUEST['page']) == "friends")) {

					add_contextual_help($screen, $screen_help_text['friends-help-overview']);
				}
				else if ((isset($_REQUEST['page'])) && (sanitize_text_field($_REQUEST['page']) == "friends")) {

					add_contextual_help($screen, $screen_help_text['friends-help-overview'] . $screen_help_text['friends-help-friends']);

				} else if ((isset($_REQUEST['page'])) && (sanitize_text_field($_REQUEST['page']) == "find-friends")) {

					add_contextual_help($screen, $screen_help_text['friends-help-overview'] . $screen_help_text['friend-help-find']);

				} else if ((isset($_REQUEST['page'])) && (sanitize_text_field($_REQUEST['page']) == "friend-requests")) {

					add_contextual_help($screen, $screen_help_text['friends-help-overview'] . $screen_help_text['friend-requests']);	
				} 

			}
		}
		
		
		
		/**
		 * This build the main Friends listing table output. 
		 *
		 * @since 1.0.0
		 *
		 * @param none
		 * @return none
		 */
		function friends_page_output() {
		    global $wpdb, $user_ID;

			$title = __( 'Friends', WPMUDEV_FRIENDS_I18N_DOMAIN );

		    $tmp_friends_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . $wpdb->base_prefix 
				. "friends WHERE user_ID = %d AND friend_approved = %d", $user_ID, '1'));

		    if ( $tmp_friends_count > 0 )
		        $title .= ' (' . $tmp_friends_count . ')';

		    $parent_file = 'friends.php';

		    $this->friends_list_output();
		}
		
		function friends_list_output() {
			global $wpdb, $wp_roles, $current_user, $user_ID, $messaging_current_version;
			
//			if ((isset($_GET['action'])) && ($_GET['action'] == "send_message")) {
//				$this->friends_send_message();
//			} else {
				?>
				<div class="wrap friends-wrap">
					<h2><?php _e('Friends', WPMUDEV_FRIENDS_I18N_DOMAIN) ?></h2>
					<?php					
						if ( !isset( $_GET[ 'start' ] )) {
							$start = 0;
						} else {
							$start = intval( $_GET[ 'start' ] );
						}

						if( isset( $_GET[ 'num' ] ) == false ) {
							$num = 30;
						} else {
							$num = intval( $_GET[ 'num' ] );
						}

						$query = $wpdb->prepare("SELECT * FROM " . $wpdb->base_prefix . "friends WHERE user_ID = %d AND friend_approved = %d LIMIT %d,%d", $user_ID, '1', $start, $num);
						$tmp_friends = $wpdb->get_results( $query );
						if( count( $tmp_friends ) < $num ) {
							$next = false;
						} else {
							$next = true;
						}
						if (count($tmp_friends) > 0) {
							$tmp_friend_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "friends WHERE user_ID = %d AND friend_approved = %d", $user_ID, '1'));
							
							if ($tmp_friend_count > $num) {
								?><table><tr><td><?php

								//$order_sort = "order=" . $_GET[ 'order' ] . "&sortby=" . $_GET[ 'sortby' ];

								if( $start == 0 ) {
									echo __('Previous Page', WPMUDEV_FRIENDS_I18N_DOMAIN);
								} elseif( $start <= $num ) {
									echo '<a href="admin.php?page=friends&start=0&' . $order_sort . ' ">' 
										. __('Previous Page', WPMUDEV_FRIENDS_I18N_DOMAIN) . '</a>';
								} else {
									echo '<a href="admin.php?page=friends&start=' . ( $start - $num ) . '&' 
										. $order_sort . '">' . __('Previous Page', WPMUDEV_FRIENDS_I18N_DOMAIN) . '</a>';
								}
								if ( $next ) {
									echo '&nbsp;||&nbsp;<a href="admin.php?page=friends&start=' . ( $start + $num ) . '&' 
										. $order_sort . '">' . __('Next Page', WPMUDEV_FRIENDS_I18N_DOMAIN) . '</a>';
								} else {
									echo '&nbsp;||&nbsp;' . __('Next Page', WPMUDEV_FRIENDS_I18N_DOMAIN);
								}
								?>
								</td></tr></table>
								<br style="clear:both;" />
								<?php
							}
							?>
							<table cellpadding='3' cellspacing='3' width='100%' class='widefat'>
							<thead><tr>
								<th scope='col'><?php _e('Friend', WPMUDEV_FRIENDS_I18N_DOMAIN) ?></th>
								<th scope='col'><?php _e('Avatar', WPMUDEV_FRIENDS_I18N_DOMAIN) ?></th>
								<th scope='col'><?php _e('Actions', WPMUDEV_FRIENDS_I18N_DOMAIN) ?></th>

								<?php
									if ($this->_settings['plugins']['messaging'] == true) {
										?><th scope='col'></th><?php
									}

									if ($this->_settings['plugins']['chat'] == true) {
										?><th scope='col'></th><?php
									}
								?>
								<th scope='col'></th>
							</tr></thead>
							<tbody id='the-list'>
							<?php
								foreach ($tmp_friends as $tmp_friend) {
									$class = ( isset( $class ) ) ? NULL : 'alternate';
	                        		echo "<tr class='" . $class . "'>";
	                        		$tmp_display_name = $wpdb->get_var($wpdb->prepare("SELECT display_name FROM " . $wpdb->base_prefix 
										. "users WHERE ID = %d", $tmp_friend->friend_user_ID));
	                        		$tmp_user_login = $wpdb->get_var($wpdb->prepare("SELECT user_login FROM " . $wpdb->base_prefix . "users WHERE ID = %d", $tmp_friend->friend_user_ID));

	                        		if ($tmp_display_name != $tmp_user_login) {
	                            		echo "<td valign='top'><strong>" . $tmp_display_name . " (" . $tmp_user_login . ")</strong></td>";
	                        		} else {
	                            		echo "<td valign='top'><strong>" . $tmp_display_name . "</strong></td>";
	                        		}
	                        		echo "<td valign='top'>" . get_avatar($tmp_friend->friend_user_ID,'32','') . "</td>";

	                        		$tmp_blog_ID = get_user_meta($tmp_friend->friend_user_ID, 'primary_blog', true);
									if (is_multisite())
	                        			$tmp_blog_url = get_blog_option($tmp_blog_ID, 'siteurl');
									else
										$tmp_blog_url = get_option('siteurl');
										
	                        		//$tmp_blog_path = $wpdb->get_var("SELECT path FROM " . $wpdb->base_prefix . "blogs WHERE blog_id = '" 
									//	. $tmp_blog_ID . "'");
									if ($this->_settings['plugins']['messaging'] == true) {

                            			echo "<td valign='top'><a href='admin.php?page=messaging_new&message_to=" 
											. $tmp_user_login . "' rel='permalink' class='edit'>" 
											. __('Send Message', WPMUDEV_FRIENDS_I18N_DOMAIN) . "</a></td>";
									} 

									if ($this->_settings['plugins']['chat'] == true) {

                        				echo "<td valign='top'>";
										if ($tmp_friend->friend_approved == 1) {
											echo wpmudev_chat_get_chat_status_label($user_ID, $tmp_friend->friend_user_ID);
										}

										echo "</td>";
									}


	                        		if ($tmp_blog_url != '') {
	                            		echo "<td valign='top'><a href='" . $tmp_blog_url . "' rel='permalink' class='edit'>" 
											. __('View Blog', WPMUDEV_FRIENDS_I18N_DOMAIN) . "</a></td>";
	                        		} else {
	                            		echo "<td valign='top'><a class='edit' style='color:#999999;text-decoration:none;border:0px;'>" 
											. __('View Blog', WPMUDEV_FRIENDS_I18N_DOMAIN) . "</a></td>";
	                        		}
	                        		echo "<td valign='top'><a href='admin.php?page=friends&action=remove&fid=" . $tmp_friend->friend_ID
										. "' rel='permalink' class='delete'>" . __('Remove', WPMUDEV_FRIENDS_I18N_DOMAIN) . "</a></td>";
	                        		echo "</tr>";
								}
							?></tbody></table><?php
							if ($this->_settings['plugins']['messaging'] != true) {
								echo "<p>" . __(sprintf('Install the %s plugin to allow sending messages to friends', 
									'<a target="_blank" href="http://premium.wpmudev.org/project/messaging">WPMU Dev Messaging </a>'), WPMUDEV_FRIENDS_I18N_DOMAIN) ."</p>";
								
							}
						} else {
							?><p><?php _e('Your friends list is currently empty', WPMUDEV_FRIENDS_I18N_DOMAIN) ?></p><?php
					}
				?></div><?php
//			}
		}

		/**
		 * As part of the main Friends listing on each row is an option to send a user a message. This is the displayed form for that action
		 *
		 * @since 1.0.0
		 *
		 * @param none
		 * @return none
		 */

		function friends_send_message() {
			global $wpdb;

			?>
			<div class="wrap friends-wrap">
			<?php
				if ((isset($_GET['fid'])) && (intval($_GET['fid']))) {
					$tmp_display_name = $wpdb->get_var($wpdb->prepare("SELECT display_name FROM " . $wpdb->base_prefix . "users WHERE ID = %d", $_GET['fid']));
					$tmp_user_login = $wpdb->get_var($wpdb->prepare("SELECT user_login FROM " . $wpdb->base_prefix . "users WHERE ID = %d", $_GET['fid']));
					if ($tmp_display_name != $tmp_user_login){
						$tmp_display_name = $tmp_display_name . ' (' . $tmp_user_login . ')';
					}
					?>
					<h2><?php _e('Send Message To', WPMUDEV_FRIENDS_I18N_DOMAIN) ?> <em><?php echo $tmp_display_name; ?></em></h2>
					<form name="new_message" method="post">
						<input type="hidden" name="message_to" value="<?php echo $tmp_user_login; ?>" />
						<input type="hidden" name="message_subject" value="<?php _e('Quick Message', WPMUDEV_FRIENDS_I18N_DOMAIN) ?>" />
						<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php _e('Message', WPMUDEV_FRIENDS_I18N_DOMAIN) ?></th>
							<td><input type="text" name="message_content" id="message_content" maxlength="200" value="" /></td>
						</tr>
						</table>
						<p class="submit"><input type="submit" class="button-primary" name="quick_message" value="<?php _e('Send', WPMUDEV_FRIENDS_I18N_DOMAIN) ?>" /></p>
					</form>
					<?php
				}
			?>
			</div>
			<?php
		}
		
		
		/**
		 * Display function for the Find Friends page
		 *
		 * @since 1.0.0
		 *
		 * @param none
		 * @return none
		 */
		function friends_find_output() {
			global $wpdb, $wp_roles, $current_user, $user_ID;

			?>
			<div class="wrap friends-wrap">
				<h2><?php _e('Find Friends', WPMUDEV_FRIENDS_I18N_DOMAIN) ?></h2>
				<form id="posts-filter" action="admin.php" method="get">
					<input type="hidden" name="page" value="find-friends" />
	                <p id="post-search">
						<label for="post-search-input"><?php _e("Search by friends display name, username or email address", 
							WPMUDEV_FRIENDS_I18N_DOMAIN) ?></label><br />
	                    <input id="post-search-input" name="search_terms" value="<?php 
							if (isset($_GET['search_terms'])) { echo stripslashes(sanitize_text_field($_GET['search_terms'])); } ?>" type="text"  /><br />

						<input type="checkbox" id="search_show_friends" name="search_show_friends" <?php if (isset($_GET['search_show_friends'])) { echo ' checked="checked" '; } ?> />&nbsp;<label for="search_show_friends"><?php _e('Show existing friends', WPMUDEV_FRIENDS_I18N_DOMAIN); ?></label><br />
							
	                    <input value="<?php _e('Search', WPMUDEV_FRIENDS_I18N_DOMAIN) ?>" class="button" type="submit" />
	                </p>
				</form>
				<?php
					
					$tmp_search_terms = ( isset( $_GET['search_terms'] ) ) ? stripslashes(sanitize_text_field($_GET['search_terms'])) : '';
					if ($tmp_search_terms != '') {
						$query = $wpdb->prepare("SELECT ID, display_name, user_login, user_email FROM " . $wpdb->base_prefix . "users
							WHERE (user_login LIKE %s
							OR user_nicename LIKE %s
							OR user_email LIKE %s
							OR display_name LIKE %s)
							AND ID != '%d'
							ORDER BY user_nicename ASC LIMIT 50", 
							"%%".$tmp_search_terms."%%", 
							"%%".$tmp_search_terms."%%", 
							"%%".$tmp_search_terms."%%", 
							"%%".$tmp_search_terms."%%", $user_ID);
						//echo "query=[".$query."]<br />";
						
						$tmp_search_results = $wpdb->get_results( $query, ARRAY_A );

						//echo "tmp_search_results<pre>"; print_r($tmp_search_results); echo "</pre>";


						if (count($tmp_search_results) > 0) {
							echo '<ul id="friend-results">';
							
							foreach ($tmp_search_results as $tmp_search_result) {

								$friend_output = '';
								
								//$sql_str = "SELECT COUNT(*) FROM " . $wpdb->base_prefix . "friends WHERE friend_user_ID = '" 
								//	. $tmp_search_result['ID'] . "' AND user_ID = '" . $user_ID . "' AND friend_approved = 0";
								
								$sql_str = $wpdb->prepare("SELECT 
									f_from.friend_ID f_friend_ID, 
									f_from.user_ID f_user_ID, 
									f_from.friend_user_ID f_friend_user_ID, 
									f_from.friend_approved f_approved, 
									f_to.friend_ID t_friend_ID, 
									f_to.user_ID t_user_ID, 
									f_to.friend_user_ID t_friend_user_ID, 
									f_to.friend_approved t_approved
									FROM " . $wpdb->base_prefix . "friends as f_from
									LEFT JOIN " . $wpdb->base_prefix . "friends as f_to 
									ON f_to.friend_user_ID = f_from.user_ID 
									AND f_to.friend_user_ID = %d AND f_to.user_ID = %d
									WHERE f_from.user_ID = %d AND f_from.friend_user_ID = %d", 
									$user_ID, $tmp_search_result['ID'], $user_ID, $tmp_search_result['ID']);
								//echo "sql_str=[". $sql_str ."]<br />";	
							
								$tmp_friend_results = $wpdb->get_row($sql_str);
								//echo "tmp_friend_results<pre>"; print_r($tmp_friend_results); echo "</pre>";
							
								if ((isset($tmp_friend_results->f_approved)) && ($tmp_friend_results->f_approved == 1)
								 && (isset($tmp_friend_results->t_approved)) && ($tmp_friend_results->t_approved == 1)) {

									if (isset($_GET['search_show_friends'])) {
										$friend_output .= '<span class="friend-status">(<a style="color:#999999;text-decoration:none;border:0px;">' 
											. __('Friends', WPMUDEV_FRIENDS_I18N_DOMAIN) . '</a>)</span>&nbsp;';
									} else {
										continue;
									}
								
								
								} else if ((isset($tmp_friend_results->f_approved)) && ($tmp_friend_results->f_approved == 0)
									 && (!isset($tmp_friend_results->t_approved)) ) {
								
									$friend_output .= '<span class="friend-status">(<a style="color:#999999;text-decoration:none;border:0px;">' 
										. __('Pending', WPMUDEV_FRIENDS_I18N_DOMAIN) . '</a>)</span>&nbsp;';
								} else {
									$friend_output .= '<span class="friend-status">(<a href="admin.php?page=find-friends&action=add&id=' 
										. $tmp_search_result['ID'] . '&search_terms=' 
										. rawurlencode($tmp_search_terms) . '">' . __('Add', WPMUDEV_FRIENDS_I18N_DOMAIN) . '</a>)</span>&nbsp;';
								}
							

								$friend_avatar = get_avatar($tmp_search_result['user_email'], '32', '');
								if ($friend_avatar)
									$friend_output .= '<span class="friend-avatar">'. $friend_avatar."</span>&nbsp;";
								
								if ($tmp_search_result['display_name'] != $tmp_search_result['user_login']){
									$friend_output .= '<span class="friend-name">'. $tmp_search_result['display_name'] 
										. ' (' . $tmp_search_result['user_login'] . ')</span>';
								} else {
									$friend_output .= '<span class="friend-name">'. $tmp_search_result['display_name'] .'</span>';
								}

								if (strlen($friend_output))
									echo '<li>'. $friend_output .'</li>';

							}
							echo '</ul>';
						} else {
							?>
							<p><?php _e('Nothing found', WPMUDEV_FRIENDS_I18N_DOMAIN) ?></p>
							<?php
						}
					}
				?>
			</div>
			<?php
		}
		
		/**
		 * Display function to show pending requests from other users. Here the user can approve or reject the friend requests.
		 *
		 * @since 1.0.0
		 *
		 * @param none
		 * @return none
		 */
		
		function friends_requests_output() {
			global $wpdb, $wp_roles, $current_user, $user_ID;

			?>
			<div class="wrap friends-wrap">
				<h2><?php _e('Friend Requests', WPMUDEV_FRIENDS_I18N_DOMAIN) ?></h2>
				<?php

					if ( isset( $_GET[ 'start' ] ) == false ) {
						$start = 0;
					} else {
						$start = intval( $_GET[ 'start' ] );
					}

					if ( isset( $_GET[ 'num' ] ) == false ) {
						$num = 30;
					} else {
						$num = intval( $_GET[ 'num' ] );
					}

					$query = $wpdb->prepare("SELECT * FROM " . $wpdb->base_prefix . "friends WHERE friend_user_ID = %d AND friend_approved = %d", $user_ID, '0');
					$query .= " LIMIT " . intval( $start ) . ", " . intval( $num );
					//echo "query=[". $query ."]<br />";
					$tmp_friends = $wpdb->get_results( $query, ARRAY_A );

					if ( count( $tmp_friends ) < $num ) {
						$next = false;
					} else {
						$next = true;
					}

					if (count($tmp_friends) > 0) {

						$tmp_friend_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "friends WHERE user_ID = %d", $user_ID));
						if ($tmp_friend_count > 30) {
							?><table><tr><td><?php

							//$order_sort = "order=" . $_GET[ 'order' ] . "&sortby=" . $_GET[ 'sortby' ];

							if( $start == 0 ) {

								echo __('Previous Page', WPMUDEV_FRIENDS_I18N_DOMAIN);

							} elseif( $start <= 30 ) {

								echo '<a href="admin.php?page=friend-requests&start=0&' . $order_sort . ' ">' 
									. __('Previous Page', WPMUDEV_FRIENDS_I18N_DOMAIN) . '</a>';

							} else {

								echo '<a href="admin.php?page=friend-requests&start=' . ( $start - $num ) . '&' . $order_sort . '">' 
									. __('Previous Page', WPMUDEV_FRIENDS_I18N_DOMAIN) . '</a>';

							}

							if ( $next ) {

								echo '&nbsp;||&nbsp;<a href="admin.php?page=friend-requests&start=' . ( $start + $num ) . '&' 
									. $order_sort . '">' . __('Next Page', WPMUDEV_FRIENDS_I18N_DOMAIN) . '</a>';

							} else {

								echo '&nbsp;||&nbsp;' . __('Next Page', WPMUDEV_FRIENDS_I18N_DOMAIN);

							}
							?>
							</td></tr></table>
							<br style="clear:both;" />
							<?php
						}
						?>
						<table cellpadding='3' cellspacing='3' width='100%' class='widefat'>
		                <thead>
		                    <tr>
		                        <th scope='col'><?php _e('ID', WPMUDEV_FRIENDS_I18N_DOMAIN) ?></th>
		                        <th scope='col'><?php _e('User', WPMUDEV_FRIENDS_I18N_DOMAIN) ?></th>
		                        <th scope='col'><?php _e('Avatar', WPMUDEV_FRIENDS_I18N_DOMAIN) ?></th>
		                        <th scope='col'><?php _e('Actions', WPMUDEV_FRIENDS_I18N_DOMAIN) ?></th>
		                        <th scope='col'></th>
		                        <th scope='col'></th>
		                    </tr>
		                </thead>
						<tbody id='the-list'>
						<?php
							foreach ( $tmp_friends as $tmp_friend ) {
		                        $class = ( isset( $class ) ) ? NULL : 'alternate';
		                        echo "<tr class='" . $class . "'>";
		                        echo "<td valign='top'><strong>" . $tmp_friend['friend_ID'] . "</strong></td>";
		                        $tmp_display_name = $wpdb->get_var($wpdb->prepare("SELECT display_name FROM " . $wpdb->base_prefix . "users WHERE ID = %d", $tmp_friend['user_ID']));
		                        $tmp_user_login = $wpdb->get_var($wpdb->prepare("SELECT user_login FROM " . $wpdb->base_prefix . "users WHERE ID = %d", $tmp_friend['user_ID']));

		                        if ($tmp_display_name != $tmp_user_login) {
		                            echo "<td valign='top'><strong>" . $tmp_display_name . " (" . $tmp_user_login . ")</strong></td>";
		                        } else {
		                            echo "<td valign='top'><strong>" . $tmp_display_name . "</strong></td>";
		                        }

		                        echo "<td valign='top'>" . get_avatar($tmp_friend['user_ID'],'32','') . "</td>";
		                        $tmp_blog_ID = get_user_meta( $tmp_friend['user_ID'], 'primary_blog', true );
								if (is_multisite())
		                        	$tmp_blog_url = get_blog_option($tmp_blog_ID, 'siteurl');
								else
									$tmp_blog_url = get_option('siteurl');
									
		                        //$tmp_blog_path = $wpdb->get_var("SELECT path FROM " . $wpdb->base_prefix . "blogs WHERE blog_id = '" 
								//	. $tmp_blog_ID . "'");

		                        if ($tmp_blog_url != ''){
		                            echo "<td valign='top'><a href='" . $tmp_blog_url . "' rel='permalink' class='edit'>" . __('View Blog',
		 								WPMUDEV_FRIENDS_I18N_DOMAIN) . "</a></td>";
		                        } else {
		                            echo "<td valign='top'><a class='edit' style='color:#999999;text-decoration:none;border:0px;'>" . __('View Blog',
		 								WPMUDEV_FRIENDS_I18N_DOMAIN) . "</a></td>";
		                        }

		                        echo "<td valign='top'><a href='admin.php?page=friend-requests&action=approve&fid=" . $tmp_friend['friend_ID'] 
									. "' rel='permalink' class='edit'>" . __('Approve', WPMUDEV_FRIENDS_I18N_DOMAIN) . "</a></td>";
		                        echo "<td valign='top'><a href='admin.php?page=friend-requests&action=reject&fid=" . $tmp_friend['friend_ID'] 
									. "' rel='permalink' class='delete'>" . __('Reject', WPMUDEV_FRIENDS_I18N_DOMAIN) . "</a></td>";
		                        echo "</tr>";
							}
						?>
						</tbody></table>
						<?php
					}else {
						?><p><?php _e('No pending Friend requests', WPMUDEV_FRIENDS_I18N_DOMAIN) ?></p><?php
					}
				?>
			</div>
			<?php
		}
		
		/**
		 * Display function to show the Settings page. Built using Metaboxes
		 *
		 * @since 1.2.1
		 *
		 * @param none
		 * @return none
		 */
		
		function friends_settings_output() {
			global $wpdb, $wp_roles, $current_user, $user_ID;

			?>
			<div id="friends-settings-metaboxes-general" class="wrap friends-wrap">
				<?php screen_icon('friends'); ?>
				<h2><?php _ex("Friends Settings", "Friends Plugin Page Title", WPMUDEV_FRIENDS_I18N_DOMAIN); ?></h2>
				<p><?php _ex("", 'Friends page description', WPMUDEV_FRIENDS_I18N_DOMAIN); ?></p>

				<div id="poststuff" class="metabox-holder">
					<div id="post-body" class="">
						<div id="post-body-content" class="snapshot-metabox-holder-main">
							<?php do_meta_boxes($this->_pagehooks['friends-settings'], 'normal', ''); ?>
						</div>
					</div>
				</div>	
			</div>
			<script type="text/javascript">
				//<![CDATA[
				jQuery(document).ready( function($) {
					// close postboxes that should be closed
					$('.if-js-closed').removeClass('if-js-closed').addClass('closed');

					// postboxes setup
					postboxes.add_postbox_toggles('<?php echo $this->_pagehooks['friend-settings']; ?>');
				});
				//]]>
			</script>
			<?php
		}
	
		/**
		 * Metabox display function to show the Friend Request Email elements
		 *
		 * @since 1.2.1
		 *
		 * @param none
		 * @return none
		 */
	
		function friends_metabox_show_email_request() {
			?>
			<p><?php _e('This Email template is used when one user requests to be friends with another user.', WPMUDEV_FRIENDS_I18N_DOMAIN); ?></p>
			
            <form method="post" action="admin.php?page=friend-settings">
				<input type="hidden" name="action" value="process" />
                <table class="form-table">
                <tr valign="top">
                    <td colspan="2">
						<label for="friends_add_request_notification_subject">Email Subject</label><br />
						<input name="friends_add_request_notification_subject" id="friends_add_request_notification_subject" type="text" 
						value="<?php echo $this->_config_data['templates']['friends_add_request_notification_subject']; ?>" /></td>
				</tr>
                <tr valign="top">
					<td style="width: 50%;">
						<label for="friends_add_request_notification_content">Email Body</label><br />
						<textarea name="friends_add_request_notification_content" cols="40" rows="10"><?php 
							echo $this->_config_data['templates']['friends_add_request_notification_content']; ?></textarea>
					</td>
					<td style="width: 50%;"><?php $this->show_metabox_instructions(); ?></td>
				</tr>
				</table>
                <input type="submit" class="button-primary" name="submit" 
					value="<?php _e('Save Changes', WPMUDEV_FRIENDS_I18N_DOMAIN) ?>" />
				<input type="submit" class="button-secondary" name="reset" 
					value="<?php _e('Reset to Default', WPMUDEV_FRIENDS_I18N_DOMAIN) ?>" />
			</form>
			<?php			
		}

		/**
		 * Metabox display function to show the Friend Request Approval Email elements
		 *
		 * @since 1.2.1
		 *
		 * @param none
		 * @return none
		 */

		function friends_metabox_show_email_approval() {
			?>
			<p><?php _e('This Email template is used when a user receives a request from another user to become friends. And the user approves the friend request.', WPMUDEV_FRIENDS_I18N_DOMAIN); ?></p>
            <form method="post" action="admin.php?page=friend-settings">
				<input type="hidden" name="action" value="process" />
                <table class="form-table">
                <tr valign="top">
                    <td colspan="2">
						<label for="friends_request_approval_notification_subject">Email Subject</label><br />
						<input name="friends_request_approval_notification_subject" id="friends_request_approval_notification_subject" type="text" 
						value="<?php echo $this->_config_data['templates']['friends_request_approval_notification_subject']; ?>" /></td>
				</tr>
                <tr valign="top">
					<td style="width: 50%;">
						<label for="friends_request_approval_notification_content">Email Body</label><br />
						<textarea name="friends_request_approval_notification_content" cols="40" rows="10"><?php 
							echo $this->_config_data['templates']['friends_request_approval_notification_content']; ?></textarea>
					</td>
					<td style="width: 50%;"><?php $this->show_metabox_instructions(); ?></td>
				</tr>
				</table>
                <input type="submit" class="button-primary" name="submit" 
					value="<?php _e('Save Changes', WPMUDEV_FRIENDS_I18N_DOMAIN) ?>" />
				<input type="submit" class="button-secondary" name="reset" 
					value="<?php _e('Reset to Default', WPMUDEV_FRIENDS_I18N_DOMAIN) ?>" />
			</form>
			<?php			
		}
	
		/**
		 * Metabox display function to show the Friend Request Rejection Email elements
		 *
		 * @since 1.2.1
		 *
		 * @param none
		 * @return none
		 */
	
		function friends_metabox_show_email_rejection() {
			?>
			<p><?php _e('This Email template is used when a user receives a request from another user to become friends. And the user reject the friend request.', WPMUDEV_FRIENDS_I18N_DOMAIN); ?></p>
			
            <form method="post" action="admin.php?page=friend-settings">
				<input type="hidden" name="action" value="process" />
                <table class="form-table">
                <tr valign="top">
                    <td colspan="2">
						<label for="friends_request_rejection_notification_subject">Email Subject</label><br />
						<input name="friends_request_rejection_notification_subject" id="friends_request_rejection_notification_subject" type="text" 
						value="<?php echo $this->_config_data['templates']['friends_request_rejection_notification_subject']; ?>" /></td>
				</tr>
                <tr valign="top">
					<td style="width: 50%;">
						<label for="friends_request_rejection_notification_content">Email Body</label><br />
						<textarea name="friends_request_rejection_notification_content" cols="40" rows="10"><?php 
							echo $this->_config_data['templates']['friends_request_rejection_notification_content']; ?></textarea>
					</td>
					<td style="width: 50%;"><?php $this->show_metabox_instructions(); ?></td>
				</tr>
				</table>
                <input type="submit" class="button-primary" name="submit" 
					value="<?php _e('Save Changes', WPMUDEV_FRIENDS_I18N_DOMAIN) ?>" />
				<input type="submit" class="button-secondary" name="reset" 
					value="<?php _e('Reset to Default', WPMUDEV_FRIENDS_I18N_DOMAIN) ?>" />
			</form>
			<?php			
		}
	
		/**
		 * Library function called from the Email metaboxes. 
		 *
		 * @since 1.0.0
		 *
		 * @param none
		 * @return none
		 */
		
		function show_metabox_instructions() {
			?>
			<p><?php _e('Instructions: Use the following replaceable tokens in your message. These will be replaced with the real information for user, site, content, etc.. Note all tokens are wrapped in square brackets.', WPMUDEV_FRIENDS_I18N_DOMAIN); ?></p>
			<ul class="friends-instructions">
				<li><span class="label">[SITE_NAME]</span> - <?php _e("The name of the sender's primary site", WPMUDEV_FRIENDS_I18N_DOMAIN); ?></li>
				<li><span class="label">[SITE_URL]</span> - <?php _e("The URL of the sender's primary site", WPMUDEV_FRIENDS_I18N_DOMAIN); ?></li>
				<li><span class="label">[FROM_USER]</span> - <?php _e("The user name requesting friendship.", WPMUDEV_FRIENDS_I18N_DOMAIN); ?></li>
				<li><span class="label">[TO_USER]</span> - <?php _e("The user name being friended.", WPMUDEV_FRIENDS_I18N_DOMAIN); ?></li>
				<li><span class="label">[FRIENDS_URL]</span> - <?php _e("The TO_USER primary site admin page for the Friend Request.", WPMUDEV_FRIENDS_I18N_DOMAIN); ?></li>
			</ul>
			<?php
		}	
	
		/**
		 * Processing function when a user adds another user as a friend. This is a friend request. 
		 *
		 * @since 1.0.0
		 *
		 * @param none
		 * @return none
		 */
	
		function friends_add_notification( $to_uid, $from_uid ) {

			if ( get_user_meta( $to_uid, 'friend_email_notification', true) != 'no' ) {
				
				$this->friends_filter_email_content($to_uid, $from_uid, 
						$this->_config_data['templates']['friends_add_request_notification_subject'],
						$this->_config_data['templates']['friends_add_request_notification_content']);
			}
		}

		/**
		 * Processing function when a user approves another user as a friend.
		 *
		 * @since 1.0.0
		 *
		 * @param none
		 * @return none
		 */
		function friends_request_approval_notification( $to_uid, $from_uid ) {
			
			if ( get_user_meta( $to_uid, 'friend_email_notification', true) != 'no' ) {
				
				$this->friends_filter_email_content($to_uid, $from_uid, 
						$this->_config_data['templates']['friends_request_approval_notification_subject'],
						$this->_config_data['templates']['friends_request_approval_notification_content']);
			}
		}

		/**
		 * Processing function when a user rejects another user as a friend
		 *
		 * @since 1.0.0
		 *
		 * @param none
		 * @return none
		 */

		function friends_request_rejection_notification( $to_uid, $from_uid ) {
			global $wpdb, $user_ID;

			if ( get_user_meta( $to_uid, 'friend_email_notification', true) != 'no' ) {
				
				$this->friends_filter_email_content($to_uid, $from_uid, 
						$this->_config_data['templates']['friends_request_rejection_notification_subject'],
						$this->_config_data['templates']['friends_request_rejection_notification_content']);
			}
		}

		/**
		 * Library function call from friends_request_rejection_notification(), friends_request_approval_notification().
		 * and friends_add_notification to parse the email subject and email content for tokens. And deliver the email
		 *
		 * @since 1.0.0
		 *
		 * @param none
		 * @return none
		 */
		
		function friends_filter_email_content($to_user_id, $from_user_id, $email_subject, $email_content) {
			global $wpdb, $user_ID;

			if ((!$email_subject) || (!strlen($email_subject)) || (!$email_content) || (!strlen($email_content)))
				return;
				
			$from_user_username 		= get_the_author_meta('login', 			$from_user_id);
			$from_user_displayname 		= get_the_author_meta('display_name', 	$from_user_id);				
			$from_user_email 			= get_the_author_meta('email', 			$from_user_id);
			$from_user_blog_id 			= get_the_author_meta('primary_blog', 	$from_user_id);

			if (is_multisite()) {
				$from_user_blog_info 		= get_blog_details($from_user_blog_id);
			} else {
				$from_user_blog_info = new stdClass;
				$from_user_blog_info->blogname 	= get_option('blogname');
				$from_user_blog_info->siteurl	= get_option('siteurl');
			}

			if ($from_user_username != $from_user_displayname) {
				$from_user_displayname .= ' (' . $from_user_username . ')';					
			}


			$to_user_username 			= get_the_author_meta('login', 			$to_user_id);
			$to_user_displayname 		= get_the_author_meta('display_name', 	$to_user_id);
			$to_user_email 				= get_the_author_meta('email', 			$to_user_id);
			$to_user_blog_id 			= get_the_author_meta('primary_blog', 	$to_user_id);

			if (is_multisite()) {
				$to_user_blog_info 			= get_blog_details($to_user_blog_id);
			} else {
				$to_user_blog_info = new stdClass;
				$to_user_blog_info->blogname 	= get_option('blogname');
				$to_user_blog_info->siteurl		= get_option('siteurl');				
			}

			if ($to_user_username != $to_user_displayname) {
				$to_user_displayname .= ' (' . $to_user_username . ')';					
			}
			
			// Put the email text items into an array. Makes it easier to work with later. 
			$email_elements = array(
				'subject'	=> 	$email_subject,
				'content'	=>	$email_content
			);
			
			foreach($email_elements as $email_key => $email_text) {
				$email_text = str_replace( "[SITE_NAME]", $from_user_blog_info->blogname, $email_text );
				$email_text = str_replace( "[SITE_URL]", trailingslashit($from_user_blog_info->siteurl), $email_text );
				$email_text = str_replace( "[TO_USER]", $to_user_displayname, $email_text );
				$email_text = str_replace( "[FROM_USER]", $from_user_displayname, $email_text );				
				$email_text = str_replace( "[FRIENDS_URL]", trailingslashit($from_user_blog_info->siteurl) 
					. 'wp-admin/admin.php?page=friend-requests', $email_text );

				$email_elements[$email_key] = $email_text;
			}
			
			$admin_email = get_site_option('admin_email');
			$from_email = $admin_email;

			$message_headers = "MIME-Version: 1.0\n" . "From: " . $from_user_blog_info->blogname .  " <". $from_user_email .">\n" . 
				"Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"\n";

			wp_mail($to_user_email, $email_elements['subject'], $email_elements['content'], $message_headers);
		}

		
		function friends_add( $tmp_uid, $tmp_friend_uid, $tmp_approved ) {
			global $wpdb;
			$tmp_friend_count =  $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . $wpdb->base_prefix 
				. "friends WHERE user_ID = %d AND friend_user_ID = %d", $tmp_uid, $tmp_friend_uid));
			if ( $tmp_friend_count > 0 ) {
				//let's not add this friend twice shall we
			} else {
				//$wpdb->query( "INSERT INTO " . $wpdb->base_prefix . "friends (user_ID,friend_user_ID,friend_approved) VALUES ( '" . $tmp_uid . "','" 
				//	. $tmp_friend_uid . "','" . $tmp_approved . "' )" );
				$wpdb->insert($wpdb->base_prefix . 'friends', array(
					'user_ID'			=>	$tmp_uid,
					'friend_user_ID'	=>	$tmp_friend_uid,
					'friend_approved'	=>	$tmp_approved
					), array('%d', '%d', '%d')
				);
			}
		}
		
		
		/**
		 * Utility function to determine is a user is friends with another user. 
		 *
		 * @since 1.2.3
		 *
		 * @param 
		 * tmp_uid - int - Current user ID
		 * tmp_friend_uid - int - Friend user ID
		 * @return returns the value of 'friend_approved' field. 1 - Approved, 0 - Pending, null - no status
		 */
		function friends_check_status($tmp_uid, $tmp_friend_uid) {
			global $wpdb;
			
			$sql = $wpdb->prepare("SELECT friend_approved FROM " . $wpdb->base_prefix 
				. "friends WHERE user_ID = %d AND friend_user_ID = %d", $tmp_uid, $tmp_friend_uid);
			//echo "sql=[". $sql ."]<br />";	
			return $wpdb->get_var($sql);
		}
		
		
		/**
		 * Utility function to get a list of friend user IDs
		 *
		 * @since 1.2.3
		 *
		 * @param 
		 * tmp_uid - int - Current user ID
		 * friend_status - int - Approved status of friends. Defaults to '1' for returning approved only. '0' for pending. 
		 * @return returns array of user ids
		 */
		function friends_get_list($tmp_uid, $friend_status=1) {
			global $wpdb;
			
			$friend_status = intval($friend_status);
			if (($friend_status != 1) && ($friend_status != 0))
				$friend_status = 1;
				
			if ( $friends_list = get_transient( 'wpmudev-friends-'. $tmp_uid .'-'. $friend_status ) ) {
				return $friends_list;
			}
			
			$query = $wpdb->prepare("SELECT friend_user_ID FROM " . $wpdb->base_prefix . "friends WHERE user_ID = %d AND friend_approved = %d", 
				$tmp_uid, $friend_status);
			//echo "query=[". $query ."]<br />";
			$friends_list = $wpdb->get_col($query);
			set_transient( 'wpmudev-friends-'. $tmp_uid .'-'. $friend_status, $friends_list, 60 );
			
			return $friends_list;
		}
		
		
		/**
		 * Called from the admin menu processing to show the number of friend requests on our menu item
		 *
		 * @since 1.0.0
		 *
		 * @param none
		 * @return none
		 */
		
		function friends_get_request_count($user_id = 0) {

			global $wpdb;

			if (!$user_id) {
				global $user_ID;
				$user_id = $user_ID;
			}
			
			$friend_requests_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . 
				$wpdb->base_prefix . "friends WHERE friend_user_ID = %s AND friend_approved = %d", $user_id, '0'));
			if ($friend_requests_count)
				return intval($friend_requests_count);
		}
		
		/**
		 * Loads the email templates from out options key. Note this is stored only on the primary site. 
		 *
		 * @since 1.0.0
		 *
		 * @param none
		 * @return none
		 */
		
		function load_config() {
			
			if (is_multisite())
				$this->_config_data = get_blog_option(1, $this->_settings['options_key']);
			else
				$this->_config_data = get_option($this->_settings['options_key']);
				
			if (!isset($this->_config_data['templates']))
				$this->_config_data['templates'] = array();

/*
			if (!isset($this->_config_data['templates']['friends_add_notification_subject'])) {
				$this->_config_data['templates']['friends_add_notification_subject'] = 
					__('[SITE_NAME] [FROM_USER] has added you as a friend', WPMUDEV_FRIENDS_I18N_DOMAIN);
			}
			if (!isset($this->_config_data['templates']['friends_add_notification_content'])) {
				$this->_config_data['templates']['friends_add_notification_content'] = 
					__('Dear [TO_USER],
					
We would like to inform you that [FROM_USER] has recently added you as a friend.

Thanks,
[SITE_NAME]', WPMUDEV_FRIENDS_I18N_DOMAIN);
			}
*/

			if (!isset($this->_config_data['templates']['friends_add_request_notification_subject'])) {
				$this->_config_data['templates']['friends_add_request_notification_subject'] = 
				'[SITE_NAME] [FROM_USER] has requested to add you as a friend';
			}

			if (!isset($this->_config_data['templates']['friends_add_request_notification_content'])) {
				$this->_config_data['templates']['friends_add_request_notification_content'] = 
					'Dear [TO_USER],

We would like to inform you that [FROM_USER] has requested to add you as a friend. Please login to your admin panel to approve or reject this request.

To review all your Friend Request visit the following link
[FRIENDS_URL]

Thanks,
[SITE_NAME]';
			}
			
			if (!isset($this->_config_data['templates']['friends_request_approval_notification_subject'])) {			
				$this->_config_data['templates']['friends_request_approval_notification_subject'] = 
					'[SITE_NAME] Friend Request Approved';
			}
			
			if (!isset($this->_config_data['templates']['friends_request_approval_notification_content'])) {			
				$this->_config_data['templates']['friends_request_approval_notification_content'] = 
					'Dear [TO_USER],

We would like to inform you that [FROM_USER] has approved your request to add them as a friend.

Thanks,
[SITE_NAME]';
			}

			if (!isset($this->_config_data['templates']['friends_request_rejection_notification_subject'])) {			
				$this->_config_data['templates']['friends_request_rejection_notification_subject'] = 
					'[SITE_NAME] Friend Request Denied';
			}
			if (!isset($this->_config_data['templates']['friends_request_rejection_notification_content'])) {			
				$this->_config_data['templates']['friends_request_rejection_notification_content'] = 
					'Dear [TO_USER],

We would like to inform you that [FROM_USER] has denied your request to add them as a friend.

Thanks,
[SITE_NAME]';
				
			}			
		}
		
		/**
		 * Save out email template data. Note this is save to the primary site only. All blogs use the same templates. 
		 *
		 * @since 1.0.0
		 *
		 * @param none
		 * @return none
		 */
		function save_config() {

			if (is_multisite()) {
				delete_blog_option(1, $this->_settings['options_key']);			
				update_blog_option( 1, $this->_settings['options_key'], $this->_config_data);		
			} else {
				update_option($this->_settings['options_key'], $this->_config_data);
			}
		}
	}
}

if (!isset($wpmudev_friends))
	$wpmudev_friends = new WPMUDev_Friends();


function friends_add($tmp_uid, $tmp_friend_uid, $tmp_approved) {
	global $wpmudev_friends;
	
	return $wpmudev_friends->friends_add($tmp_uid, $tmp_friend_uid, $tmp_approved);
}

/**
 * Interface function for friends class function. See class function for details.
 *
 * @since 1.2.3
 *
 */
function friends_check_status($tmp_uid, $tmp_friend_uid) {
	global $wpmudev_friends;

	return $wpmudev_friends->friends_check_status($tmp_uid, $tmp_friend_uid);
}

/**
 * Interface function for friends class function. See class function for details.
 *
 * @since 1.2.3
 *
 */
function friends_get_list($tmp_uid, $friend_status=1) {
	global $wpmudev_friends;

	return $wpmudev_friends->friends_get_list($tmp_uid, $friend_status);
	
}


function friends_add_notification( $to_uid, $from_uid ) {
	global $wpmudev_friends;

	return $wpmudev_friends->friends_add_notification( $to_uid, $from_uid );
}

function widget_friends_init() {
	global $wpdb, $user_ID;

	// This saves options and prints the widget's config form.
	function widget_friends_control() {
		global $wpdb, $user_ID;
		$options = $newoptions = get_option('widget_friends');
		if ( isset( $_POST['friends-submit'] ) ) {
			$newoptions['friends-display'] = sanitize_text_field($_POST['friends-display']);
			$newoptions['freinds-uid'] = intval($_POST['freinds-uid']);
		}
		if ( $options != $newoptions ) {
			$options = $newoptions;
			update_option('widget_friends', $options);
		}
        ?>

        <div style="text-align:right">
            <?php
            $tmp_blog_users_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->usermeta . " WHERE meta_key = '" . $wpdb->base_prefix 
				. $wpdb->blogid . "_capabilities'");
				
            if ($tmp_blog_users_count > 1) {
                        $tmp_username = $wpdb->get_var($wpdb->prepare("SELECT user_login FROM " . $wpdb->users . " WHERE ID = %d", $tmp_sent_message['sent_message_from_user_ID']));
                ?>
                <label for="freinds-uid" style="line-height:35px;display:block;"><?php _e('User', WPMUDEV_FRIENDS_I18N_DOMAIN); ?>:
                <select name="freinds-uid" id="freinds-uid" style="width:65%;">
                <?php
                $query = "SELECT user_id FROM " . $wpdb->usermeta . " WHERE meta_key = '" . $wpdb->base_prefix . $wpdb->blogid . "_capabilities'";
                $tmp_users = $wpdb->get_results( $query, ARRAY_A );
                if (count($tmp_users) > 0){
                    foreach ($tmp_users as $tmp_user){
                        $tmp_username = $wpdb->get_var($wpdb->prepare("SELECT user_login FROM " . $wpdb->users . " WHERE ID = %d", $tmp_user['user_id']));
                        ?>
                        <option value="<?php echo $tmp_user['user_id']; ?>" <?php 
						if ($options['freinds-uid'] == $tmp_user['user_id']){ 
							echo 'selected="selected"'; } ?> ><?php echo $tmp_username; ?></option>
                        <?php
                    }
                }
                ?>
                </select>
                </label>
                <?php
            } else {
                if ($tmp_blog_users_count == 1){
                    $tmp_friends_uid = $wpdb->get_var("SELECT user_id FROM " . $wpdb->usermeta . " WHERE meta_key = '" . $wpdb->base_prefix 
						. $wpdb->blogid . "_capabilities'");
                } else {
                    $tmp_friends_uid = $user_ID;
                }
                ?>
                <input type="hidden" name="freinds-uid" value="<?php echo $tmp_friends_uid; ?>" />
                <?php
            }
            ?>
            <label for="friends-display" style="line-height:35px;display:block;"><?php _e('Display', WPMUDEV_FRIENDS_I18N_DOMAIN); ?>:
                <select name="friends-display" id="friends-display-type" style="width:65%;">
                    <option value="mosaic" <?php if ($options['friends-display'] == 'mosaic'){ echo 'selected="selected"'; } ?> ><?php _e('Mosaic', WPMUDEV_FRIENDS_I18N_DOMAIN); ?></option>
                    <option value="list" <?php if ($options['friends-display'] == 'list'){ echo 'selected="selected"'; } ?> ><?php _e('List', WPMUDEV_FRIENDS_I18N_DOMAIN); ?></option>
                </select>
            </label>
            <input type="hidden" name="friends-submit" id="friends-submit" value="1" />
        </div>
        <?php
	}

    // This prints the widget
	function widget_friends($args) {
		global $wpdb, $user_ID, $messaging_current_version;
		extract($args);
		$defaults = array('count' => 10, 'username' => 'wordpress');
		$options = (array) get_option('widget_friends');

		foreach ( $defaults as $key => $value ) {
			if ( !isset($options[$key]) )
				$options[$key] = $defaults[$key];
        }
		?>
		<?php echo $before_widget; ?>
			<?php echo $before_title . __('Friends', WPMUDEV_FRIENDS_I18N_DOMAIN) . $after_title; ?>
            <br />
            <?php
				//=================================================//
				$query = $wpdb->prepare("SELECT * FROM " . $wpdb->base_prefix . "friends WHERE user_ID = %d AND friend_approved = %d", $options['freinds-uid'], '1');
				$tmp_friends = $wpdb->get_results( $query, ARRAY_A );
				if ( count( $tmp_friends ) > 0 ) {
					if ( $options['friends-display'] == 'list' ){
						echo '<ul>';
						foreach ( $tmp_friends as $tmp_friend ){
							echo '<li>';
							$tmp_blog_ID = get_user_meta( $tmp_friend['friend_user_ID'], 'primary_blog', true );
							if (is_multisite())
								$tmp_blog_url = get_blog_option( $tmp_blog_ID, 'siteurl' );
							else
								$tmp_blog_url = get_option( 'siteurl' );
								
							$tmp_user_display_name = $wpdb->get_var($wpdb->prepare("SELECT display_name FROM " . $wpdb->users . " WHERE ID = %d", $tmp_friend['friend_user_ID']));
							if ($tmp_user_display_name == ''){
								$tmp_user_display_name = $wpdb->get_var($wpdb->prepare("SELECT user_login FROM " . $wpdb->users . " WHERE ID = %d", $tmp_friend['friend_user_ID']));
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
							if (is_multisite())
								$tmp_blog_url = get_blog_option($tmp_blog_ID, 'siteurl');
							else
								$tmp_blog_url = get_option('siteurl');
								
							$tmp_user_display_name = $wpdb->get_var($wpdb->prepare("SELECT display_name FROM " . $wpdb->users . " WHERE ID = %d", $tmp_friend['friend_user_ID']));
							if ($tmp_user_display_name == ''){
								$tmp_user_display_name = $wpdb->get_var($wpdb->prepare("SELECT user_login FROM " . $wpdb->users . " WHERE ID = %s", $tmp_friend['friend_user_ID']));
							}

							$friend_avatar = get_avatar($tmp_friend['friend_user_ID'], '32', '', $tmp_user_display_name);

							if ( $tmp_blog_url != '' ){

								?>
								<a href="<?php echo $tmp_blog_url; ?>" style="text-decoration:none;border:0px;" title="<?php 
									echo $tmp_user_display_name; ?>"><?php echo $friend_avatar ?></a>
								<?php
							} else {
								?>
								<?php echo $friend_avatar ?>
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
    wp_register_sidebar_widget( 'widget_friends', __( 'Friends', WPMUDEV_FRIENDS_I18N_DOMAIN ), 'widget_friends' );
	wp_register_widget_control( 'widget_friends', __( 'Friends', WPMUDEV_FRIENDS_I18N_DOMAIN ), 'widget_friends_control' );

}
add_action('widgets_init', 'widget_friends_init');
