<?php
require_once('admin.php');
$tmp_friends_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "friends WHERE user_ID = '" . $user_ID . "' AND friend_approved = '1'");
if ($tmp_friends_count > 0){
	$title = __('Friends') . ' (' . $tmp_friends_count . ')';
} else {
	$title = __('Friends');
}
$parent_file = 'friends.php';
require_once('admin-header.php');

friends_list_output();

include('admin-footer.php');
?>