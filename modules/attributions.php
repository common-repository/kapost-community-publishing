<?php
// See below (kapost_post_meta) for the reason why we need this variable.
static $kapost_xmlrpc_post_id;

// Search and massage all the required "Custom Fields".
function kapost_attribution_meta($id)
{
	$meta = array(); 
	$meta_fields = array(	"kapost_author"=>"name",
							"kapost_author_email"=>"email",
							"kapost_author_profile"=>"profile",
							"kapost_author_avatar"=>"avatar",
							"kapost_post_timestamp"=>"created_at" );

	foreach($meta_fields as $field=>$f)
	{
		$tmp = get_post_meta($id, $field, true);
		if(empty($tmp)) return false;

		$meta[$f] = $tmp;
	}

	return $meta;
}

// Creates a user for a given post based on the
// included "Custom Fields", if an existing user
// is found with the given email address then
// it is used without any changes.
function kapost_create_user_for_post($id)
{
	$post = get_post($id);
	if(!is_object($post)) return false;

	$meta = kapost_attribution_meta($id);
	if($meta === false) return false;

	require_once(ABSPATH . WPINC . '/registration.php');

	$uid = email_exists($meta['email']);
	if(!$uid)
	{
		$c = 0;
		$user_name = $user_login = str_replace(" ","",strtolower($meta['name']));

		// FIXME: find a better way to do this
		// Assuming 1000 collisions is safe enough for now, but there must be
		// a better way to achieve this; the request will time out before
		// reaching 1000 anyway ...
		while(username_exists($user_name))
		{
			$user_name = "$user_login-$c";
			if(++$c == 1000) return false;
		}

		$uid = wp_insert_user(array(
			'user_login'=>esc_sql($user_name),
			'user_pass'=>wp_generate_password(12,false),
			'user_email'=>esc_sql($meta['email']),
			'user_url'=>esc_sql($meta['profile']),
			'display_name'=>esc_sql($meta['name']),
			'role'=>'contributor'
		));

		// Should never really happen
		if(!$uid) return false;
	}

	// Override our community user with original author ...
	$post->post_author = $uid;
	return $post;
}

// We don't do anything here, see kapost_post_meta() 
// for more information regarding this issue.
function kapost_xmlrpc_publish_post($id)
{
	global $kapost_xmlrpc_post_id;
	$kapost_xmlrpc_post_id = $id;
}

// We still need this in order to handle the case when a post
// has been submitted as draft and then published `manually`.
// This is harmless if the metadata is not present anyway.
function kapost_publish_post($id)
{
	if(!kapost_can_create_user_for_attr()) return;

	static $avoidRecursion;
	if($avoidRecursion === true) return;

	// Create the user for the post and update it if necessary
	// and possible
	if(($post = kapost_create_user_for_post($id))!==false)
	{
		$avoidRecursion = true;
		wp_update_post((array) $post);
	}
}

// FIXME: this is very very bad and counter-intuitive but we must do this
// because WordPress is not consistent when it comes to adding the custom
// fields and therefore they are not available in the xmlrpc_publish_post
// at the time the hook it is called (see xmlrpc.php for more information)
function kapost_post_meta($iid=false,$id=false,$key=false,$value=false)
{
	global $kapost_xmlrpc_post_id;
	if(!$kapost_xmlrpc_post_id) return;

	kapost_publish_post($kapost_xmlrpc_post_id);
}

add_action('publish_post','kapost_publish_post');
add_action('xmlrpc_publish_post','kapost_xmlrpc_publish_post');
add_action('added_postmeta','kapost_post_meta');

?>
