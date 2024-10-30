<?php
// Fields used as login data
$KAPOST_USER_LOGIN_FIELDS = array
(
	'username',
	'password',
	'profile_name',
	'profile_url',
	'token',
	'newsroom_domain',
	'newsroom_subdomain',
	'newsroom_token'
);
// Fetch posts which have our Kapost metadata attached to them.
function kapost_get_posts($limit=10)
{
	global $wpdb;

	$query ="SELECT wposts.* FROM $wpdb->posts wposts, $wpdb->postmeta wpostmeta  
				WHERE wposts.ID = wpostmeta.post_id 
				AND wpostmeta.meta_key = 'kapost_author' 
				AND wposts.post_status = 'publish' 
				AND wposts.post_type = 'post' 
				ORDER BY wposts.post_date DESC LIMIT $limit";

	return $wpdb->get_results($query, ARRAY_A);
}
// Returns the URL to our Kapost Settings Page
function kapost_settings_url()
{
	return admin_url('options-general.php?page=kapost_settings');
}
// Construct a new native kapost error
function kapost_error($code, $msg)
{
	return (object) array('error'=>(object) array('code'=>$code,'message'=>$msg));
}
// Format a native kapost error into a human-readable format
function kapost_format_error($error, $errcode=false)
{
	$msg = $error->error->message;
	if($errcode) $msg .= " [Error Code: " . $user->error->code . "]";
  	return $msg;
}
// Determine if the given object is a native kapost error
function kapost_is_error($res)
{
	return (@is_object($res) && 
			@is_object($res->error) && 
			!empty($res->error->code) &&
			!empty($res->error->message));
}
// Login a user (an editor) to a given newsroom (if possible)
function kapost_login_user($url, $username, $password)
{
	if(empty($url)) return kapost_error(1, "A valid newsroom URL is required.");
	if(empty($username)) return kapost_error(2, "A valid username is required.");
	if(empty($password)) return kapost_error(3, "A valid password is required.");

	// Get the subdomain and use it as the slug
	$subdomain = kapost_newsroom_subdomain($url);
	// Verify the credentials by trying to access the specific newsroom information
	$req = kapost_request_json($url.'newsrooms/'.$subdomain.'.json','GET',array(),$username,$password);

	// Return any errors immediately ...
	if(kapost_is_error($req)) return $req;

	// Our REST API should respond with `Hello` so check it...
	if(@empty($req->body->domain)) return kapost_error(4,"There is no valid newsroom at the given URL. Doesn't exist?");

	// Create the User Object with the details we have so far
	$user = array(); 
	$user['username'] = $username;
	$user['password'] = $password;
	$user['newsroom_domain'] = !empty($req->body->custom_domain) ? $req->body->custom_domain :  $req->body->domain;
	$user['newsroom_subdomain'] = $req->body->subdomain;
	$user['newsroom_token'] = $req->body->api_key;

	// Save the newsroom name just in case we need it for the error message later ...
	$newsroom_name = $req->body->name;

	// Verify if the given user is an editor of the newsroom
	$req = kapost_request_json($url.'users.json?filter=editors&email='.strtolower($username),'GET',array(),$username,$password);

	// Return any errors immediately ...
	if(kapost_is_error($req))
	{
		$req->error->message .= " (User Search)";
		return $req;
	}

	// Exactly one Editor should match our query ...
	if(count($req->body)!==1 || !is_object($req->body[0]))
		return kapost_error(401, $username." is not an editor of the ".$newsroom_name." newsroom.");

	$user_info = $req->body[0];

	// Figure out the proper username and slug
	$name = !empty($user_info->name) ? $user_info->name : $user_info->email;
	$slug = !empty($user_info->slug) ? $user_info->slug : $user_ifno->_id;

	// Additional User Information
	$user['profile_name'] = $name;
	$user['profile_url'] = $url."users/".$slug;
	$user['token'] = $user_info->authentication_token;

	return (object) $user;
}
// Clears all fields used for login
function kapost_clear_user_login($instance)
{
	global $KAPOST_USER_LOGIN_FIELDS;
	foreach($KAPOST_USER_LOGIN_FIELDS as $field)
		unset($instance[$field]);

	return $instance;
}
// Is the user logged in? 
function kapost_user_logged_in($instance=null)
{
	if(!is_array($instance)) $instance = kapost_settings();

	global $KAPOST_USER_LOGIN_FIELDS;
	foreach($KAPOST_USER_LOGIN_FIELDS as $field)
		if(empty($instance[$field])) return false;

	return true;
}
// Creates a User or Not for native Attributions
function kapost_can_create_user_for_attr()
{
	$settings = kapost_settings();
	return (kapost_user_logged_in($settings) && $settings['attr_create_user'] == 'on');
}
// Append Trailing Slash if necessary
function kapost_clean_url($url)
{
	if(substr($url,-1) != '/') $url .= '/';
	return clean_url($url);
}
// Update the newsroom's settings
function kapost_update_newsroom($url, $username, $password, $data)
{
	// Get the subdomain and use it as the slug
	$subdomain = kapost_newsroom_subdomain($url);
	// Update the newsroom's settings by performing a PUT request with the necessary information
	return kapost_request_json($url.'newsrooms/'.$subdomain.'.json','PUT',$data,$username,$password);
}
// Extract the newsroom's subdomain
function kapost_newsroom_subdomain($url)
{
	$url = explode(".",trim(str_replace("http://", "", $url)));
	return strtolower($url[0]);
}
// Get Kapost Settings
function kapost_settings()
{
	global $KAPOST_DEFAULT_SETTINGS;
	return wp_parse_args((array) get_option('kapost_settings'), $KAPOST_DEFAULT_SETTINGS);
}
// Intelligent behind the scenes request wrapper
function kapost_request($url, $type = 'GET', $data=array(), $username=false, $password=false)
{
	$req_url = $url;

	if(!is_array($data))
		$data = array();

	if($type == 'GET')
	{
		$req_url .= (!strpos($req_url,"?")) ? "?wp=".KAPOST_WP_VERSION : "&wp=".KAPOST_WP_VERSION;
		$req_url .= http_build_query($data);

		$data = array();
	}
	else
	{
		$data['wp'] = KAPOST_WP_VERSION;
		if($type == 'PUT') $data['_method'] = 'PUT';
	}

	$args = array();
	$args['headers']['User-Agent'] = 'Kapost WordPress Plugin v'.KAPOST_VERSION;
	if($username && $password && function_exists('base64_encode'))
	{
		$args['headers']['Authorization'] = 'Basic '. base64_encode($username.':'.$password);
	}
	$args['body'] = $data;

	$req = (($type=='GET') ? wp_remote_get($req_url, $args) : wp_remote_post($req_url, $args));

	$res = array();
	$res['code'] = wp_remote_retrieve_response_code($req);
	$res['body'] = wp_remote_retrieve_body($req);

	return (object) $res;
}
// A convenience wrapper for fetching JSON (this is what we use in the plugin everywhere!)
function kapost_request_json($url, $type='GET', $data=array(), $username=false, $password=false)
{
	$req = kapost_request($url, $type, $data, $username, $password);

	if(!empty($req->body) && is_string($req->body)) $req->body = json_decode($req->body);

	if(!@is_object($req->body) && !@is_array($req->body))
		return kapost_error($req->code,'Unexpected error, the response body is empty.');

	if($req->code != 200) 
		return kapost_error($req->code, ($req->body->error) ? $req->body->error : 'Unexpected error, got no error message.');

	return $req;
}
// Widget Helper for Checkbox Ouput
function kapost_form_checkbox($cls, $name, $title, $instance)
{
	$id = $cls->get_field_id($name);
	$nm = $cls->get_field_name($name);

	$checked = ($instance[$name] == 'on') ? ' checked="checked"' : '';

	echo '<p><label for="'.$id.'">';
	echo '<input type="checkbox" id="'.$id.'" name="'.$nm.'"'.$checked.'/> '.$title;
	echo '</label></p>';
}
// Widget Helper for Input Ouput
function kapost_form_input($cls, $name, $title, $instance)
{
	$id = $cls->get_field_id($name);
	$nm = $cls->get_field_name($name);

	echo '<p><label for="'.$id.'">'.$title.' ';
	echo '<input class="widefat" id="'.$id.'" name="'.$nm.'" type="text" value="'.esc_attr($instance[$name]).'" />';
	echo '</label></p>';
}
// Widget Helper to Output a contribute button
function kapost_contribute_button($url, $instance, $default)
{
	$text = $instance['contribute_text'];
	if(empty($text)) $text = $default;

	/*$id = "Kp_".md5(time());
	echo '<script type="text/javascript" src="'.$url.'javascripts/kapost_widget.js"></script>';
	echo '<script type="text/javascript">';
	echo "var $id = new KapostWidget({\"path\":\"$url\"});"; 
	echo '</script>';*/
	$js = "try { window.location = '" . $url . "posts/new'; } catch(err){alert(err);} return false;";

	$settings = kapost_settings();
	$c = $settings['contribute'];

	if($c['custom'] == 1)
	{
		$align = "";
		switch($c['align'])
		{
			case "left":
				$align = ' style="text-align:left"';
				break;
			case "center":
				$align = ' style="text-align:center"';
				break;
			case "right":
				$align = ' style="text-align:right"';
				break;
		}

		$style = "padding:5px;";

		if(is_numeric($c['width']) && $c['width'] > 0)
			$style .= "width:".$c['width']."px;";
		else
			$style .= "width:auto;";

		$style .= "font-family:'".$c['ffamily']."';";

		if(is_numeric($c['fsize']) && $c['fsize'] >= 8)
			$style .= "font-size:".$c['fsize']."px;";

		if($c['fbold'] == 'on')	$style .= "font-weight:bold;";
		if($c['fitalic'] == 'on') $style .= "font-style:italic;";
		
		$style .= "background-color:".$c['bgcolor'].";";
		$style .= "color:".$c['fgcolor'].";";
		$style .= "border: 1px solid ".$c['brcolor'].";";

		echo "<div$align><p>";
		echo '<input type="submit" class="submit" style="'.$style.'" onclick="'.$js.'" value="'.$text.'"/>';
		echo "</p></div>";
	}
	else
	{
		echo '<div><p><input type="submit" class="submit" style="width:auto;padding:5px;" onclick="'.$js.'" value="'.$text.'"/></p></div>';
	}
}
// Custom Trim
function kapost_trim($text, $max)
{
	if(strlen($text)>$max&&$max>3)
		return substr($text, 0, $max-3)."...";

	return $text;
}
// Time since function taken from WordPress.com
function kapost_time_since($original, $do_more=0) 
{
	// array of time period chunks
	$chunks = array(
						array(60 * 60 * 24 * 365 , 'year'),
						array(60 * 60 * 24 * 30 , 'month'),
						array(60 * 60 * 24 * 7, 'week'),
						array(60 * 60 * 24 , 'day'),
						array(60 * 60 , 'hour'),
						array(60 , 'minute'),
						array(1, 'second')
					);

	$today = time();
	$since = $today - $original;

    for($i = 0, $j = count($chunks); $i < $j; $i++) 
	{
     	$seconds = $chunks[$i][0];
        $name = $chunks[$i][1];

        if(($count = floor($since / $seconds)) != 0)
			break;
	}

	$print = ($count == 1) ? '1 '.$name : "$count {$name}s";

	if($i + 1 < $j) 
	{
		$seconds2 = $chunks[$i + 1][0];
		$name2 = $chunks[$i + 1][1];

		// add second item if it's greater than 0
		if ((($count2 = floor(($since - ($seconds * $count)) / $seconds2)) != 0) && $do_more)
			$print .= ($count2 == 1) ? ', 1 '.$name2 : ", $count2 {$name2}s";
	}

	return $print;
}
// Create our 'Community' user on-demand
function kapost_create_user($user_name, &$user_pass)
{
	// Reset User Password Here
	$user_pass = '';

	require_once(ABSPATH . WPINC . '/registration.php');
	$uid = username_exists($user_name);
	if(!$uid) 
	{
		$user_pass 	= wp_generate_password(12,false);
		update_option("kapost_community_password", base64_encode($user_pass));


		$user_login = esc_sql($user_name);
		$role = "editor";
		$user_data = compact('user_login','user_pass','role');

		$uid = wp_insert_user($user_data);
	}
	else
	{
		$user_pass = base64_decode(get_option("kapost_community_password",''));
	}

	return $uid;
}
// Set Persistent Cache (name, data)
function kapost_cache_set($name, $data, $expire=5)
{
	if(!$expire)
		$expire = 5;

	$cache = array('data'=>$data, 
				   'timestamp'=>time(),
				   'expire'=>$expire * 60);

	update_option($name, $cache);
}
// Get Persistent Cached Data by Name
function kapost_cache_get($name, &$expired)
{
	$cache = get_option($name);
	if(!$cache) { $expired = true; return null; }

	$timestamp = time();

	if($timestamp - $cache['timestamp'] >= $cache['expire'])
		$expired = true;

	return $cache['data'];
}
// Delete Persistent Cached Data by Name
function kapost_cache_delete($name)
{
	delete_option($name);
}
// Flush Caches Hook
function kapost_cache_flush()
{
	do_action('kapost_cache_flush');
}
?>
