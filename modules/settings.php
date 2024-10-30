<?php
// Enqueue our Javascript and CSS files (the admin_head hook is way too late for these)
add_action('admin_init', 'kapost_admin_init');
function kapost_admin_init()
{
	$base_url =  WP_PLUGIN_URL."/".KAPOST_PLUGIN_DIRNAME;

	wp_register_script('kapost_colorpicker',$base_url.'/modules/colorpicker/colorpicker.js');
	wp_register_script('kapost_settings',$base_url.'/modules/settings.js');

	wp_enqueue_script('kapost_colorpicker');
	wp_enqueue_script('kapost_settings');

	wp_register_style('kapost_colorpicker',$base_url.'/modules/colorpicker/colorpicker.css');
	wp_register_style('kapost_settings',$base_url.'/modules/settings.css');

	wp_enqueue_style('kapost_colorpicker');
	wp_enqueue_style('kapost_settings');
}

// Kapost Settings (in Plugins)
add_action('admin_menu', 'kapost_settings_menu');
function kapost_settings_menu() 
{
	if(function_exists("add_submenu_page"))
	    add_submenu_page('options-general.php','Kapost', 'Kapost', 'manage_options', 'kapost_settings', 'kapost_settings_options');
}

// Add Settings Link to the Plugin Page
add_filter('plugin_action_links', 'kapost_plugin_page_settings_link', 10, 2);
function kapost_plugin_page_settings_link($links, $file) 
{
	if($file == KAPOST_PLUGIN_DIRNAME.'/kapost.php') 
	{
		$link = '<a href="'.kapost_settings_url().'">Settings</a>';
		array_unshift($links,$link); 
	}

	return $links;
}

function kapost_track_GA_pageview() 
{
	$var_utmac='UA-12328066-1'; //enter the new urchin code
	$var_utmhn='kapost.com'; //enter your domain
	$var_utmn=rand(1000000000,9999999999);//random request number
	$var_cookie=rand(10000000,99999999);//random cookie number
	$var_random=rand(1000000000,2147483647); //number under 2147483647
	$var_today=time(); //today
	$var_referer = '';
	if(array_key_exists('HTTP_REFERER', $_SERVER)) 
		$var_referer=$_SERVER['HTTP_REFERER']; //referer url
	$var_uservar='-'; //enter your own user defined variable
	$var_utmp='/plugin_settings'; // fake site to track pageview
	$urchinUrl='http://www.google-analytics.com/__utm.gif?utmwv=1&utmn='.$var_utmn.'&utmsr=-&utmsc=-&utmul=-&utmje=0&utmfl=-&utmdt=-&utmhn='.$var_utmhn.'&utmr='.$var_referer.'&utmp='.$var_utmp.'&utmac='.$var_utmac.'&utmcc=__utma%3D'.$var_cookie.'.'.$var_random.'.'.$var_today.'.'.$var_today.'.'.$var_today.'.2%3B%2B__utmb%3D'.$var_cookie.'%3B%2B__utmc%3D'.$var_cookie.'%3B%2B__utmz%3D'.$var_cookie.'.'.$var_today.'.2.2.utmccn%3D(direct)%7Cutmcsr%3D(direct)%7Cutmcmd%3D(none)%3B%2B__utmv%3D'.$var_cookie.'.'.$var_uservar.'%3B';

	$handle = fopen ($urchinUrl, "r");
	$test = fgets($handle);
	fclose($handle);
}

function kapost_settings_form($instance)
{
	$create = '<tr><td><input class="button-primary" type="submit" onclick="window.open(\''.KAPOST_START_URL.'\');return false;" value="Create Account"/></td></tr>';
	$create_text = "If you don't have an account create one:";

	if(!isset($instance['username']))
		$instance['username'] = '';

	if(!isset($instance['url']))
		$instance['url'] = '';

	$login='
	<tr>
		<td><strong>Kapost Email:</strong></td>
		<td><input type="text" style="width:175px" value="'.$instance['username'].'" class="regular-text" name="kapost_login[username]"/></td>
		<td></td>
	</tr>
	<tr>
		<td><strong>Kapost Password:</strong></td>
		<td><input type="password" style="width:175px" value="" class="regular-text" name="kapost_login[password]"/></td>
		<td></td>
	</tr>
	<tr>
		<td><strong>Newsroom URL:</strong></td>
		<td>http://<input type="text" value="'.str_replace("http://","",$instance['url']).'" class="regular-text" name="kapost_login[url]"/></td>
		<td></td>
	</tr>
	<tr>
		<td></td>
		<td style="font-size:10px;font-style:italic">Example: http://sample.kapost.com</td>
		<td></td>
	</tr>
	<tr>
		<td></td>
		<td><input type="submit" name="submit" class="button-primary" value="Login"/></td>
		<td></td>
	</tr>';

	$logged_in = kapost_user_logged_in($instance);
    if($logged_in)
	{
		$profile_url = kapost_auth_url($instance, $instance['profile_url'], true);

		$login = 'Logged in as, <a href="'.$profile_url.'" target="_blank">'.$instance['profile_name'].'</a>';
		$login.= '<br/><a href="'.$_SERVER['REQUEST_URI'].'&kapost_logout=true">Logout</a>';

		$site_url = kapost_auth_url($instance, '', true);
		$create = '<tr><td><a href="'.$site_url.'" target="_blank">'.$instance['url'].'</a></td></tr>';
		$create_text = 'Your community site is located at:';
	}
	else
	{
		kapost_track_GA_pageview();
	}

	echo
	'<div>
	<h3>Account Information</h3>
	<blockquote>
	<div style="float:left;margin-right:200px;">
	<p>If you have an account, login below:</p>
	<blockquote>
	<form action="" method="post">
	<table>
	'.$login.'
	</table>
	</form>
	</blockquote>
	</div>
	<div style="float:left;">
	<p>'.$create_text.'</p>
	<blockquote>
	<form action="" method="post">	
	<table style="float:left">'.$create.'</table>
	</form>
	</blockquote>
	</div>
	<div style="clear:both;"></div>
	</blockquote>';

	if(!$logged_in)	return;

	$btn_algn = array
	(
		'left'=>'Left',
		'center'=>'Center',
		'right'=>'Right'
	);
	$fnt_fml = array('Arial','Courier','Georgia','Impact','Times New Roman','Trebuchet','Verdana');
	$fnt_sze = array('8','10','11','12','13','14','16','18','23','24','30','36','42');
	$btn_algn_op = '';
	$fnt_fml_op = '';
	$fnt_sze_op = '';

	foreach($btn_algn as $name=>$title)
	{
		$selected = ($name == $instance['contribute']['align']) ? ' selected="selected"' : '';
	   	$btn_algn_op .= '<option value="'.$name.'"'.$selected.'>'.$title.'</option>';
	}
	foreach($fnt_fml as $name)
	{
		$selected = ($name == $instance['contribute']['ffamily']) ? ' selected="selected"' : '';
	   	$fnt_fml_op .= '<option value="'.$name.'"'.$selected.'>'.$name.'</option>';
	}
	foreach($fnt_sze as $name)
	{
		$selected = ($name == $instance['contribute']['fsize']) ? ' selected="selected"' : '';
	   	$fnt_sze_op .= '<option value="'.$name.'"'.$selected.'>'.$name.'</option>';
	}
	$fnt_bold = ($instance['contribute']['fbold'] == 'on')?' checked="checked"':'';
	$fnt_italic = ($instance['contribute']['fitalic'] == 'on')?' checked="checked"':'';

	if($instance['contribute']['custom']) $instance['contribute']['custom'] = 0;

	$options = '
	<div class="kapost-column header first">Font Color</div>
	<div class="kapost-column"><input type="text" autocomplete="off" id="contribute_fgcolor" name="kapost_settings[contribute][fgcolor]" value="'.$instance['contribute']['fgcolor'].'"/></div>
	<div class="kapost-column header first">Font Style</div>
	<div class="kapost-column wide">
		<select name="kapost_settings[contribute][ffamily]">'.$fnt_fml_op.'</select>
		<select name="kapost_settings[contribute][fsize]">'.$fnt_sze_op.'</select>
		<input type="checkbox" name="kapost_settings[contribute][fbold]"'.$fnt_bold.'/>Bold
		<input type="checkbox" name="kapost_settings[contribute][fitalic]"'.$fnt_italic.'/>Italic
	</div>
	<div class="kapost-column header first">Border Color</div>
	<div class="kapost-column"><input type="text" autocomplete="off" id="contribute_brcolor" name="kapost_settings[contribute][brcolor]" value="'.$instance['contribute']['brcolor'].'"/></div>
	<div class="kapost-column header first">Background Color</div>
	<div class="kapost-column"><input type="text" autocomplete="off" id="contribute_bgcolor" name="kapost_settings[contribute][bgcolor]" value="'.$instance['contribute']['bgcolor'].'"/></div>
	<div class="kapost-column header first">Button Aligment</div>
	<div class="kapost-column"><select name="kapost_settings[contribute][align]">'.$btn_algn_op.'</select></div>
	<div class="kapost-column header first">Width of Button</div>
	<div class="kapost-column wide"><input type="text" autocomplete="off" name="kapost_settings[contribute][width]" value="'.$instance['contribute']['width'].'"/>&nbsp;px</div>
	<input type="hidden" id="contribute_custom" name="kapost_settings[contribute][custom]" value="'.$instance['contribute']['custom'].'"/>
	';

	$button_options = '<h3>Advanced Widget Options</h3>
			<blockquote>
			<p><h4>Button Options&nbsp;<a href="javascript:void(0)" id="toggle-button-options">Click here to view</a></h4>
			<div id="button-options">
			<blockquote>
				'.$options.'
				<div style="clear:both"></div>
				<div><a href="javascript:void(0);" id="contribute_restore">Restore default style</a></div>
			</blockquote>
			</div>
			</blockquote>';

	$sso_checked = ($instance['sso'] == 'on') ?' checked="checked"':'';
	$sso_options = '<h3>Single Sign-On</h3>
		<blockquote>
			<input type="checkbox" name="kapost_settings[sso]"'.$sso_checked.'/> Enable Single Sign-On
		</blockquote>';

	$attr_checked = ($instance['attr_create_user'] == 'on') ?' checked="checked"':'';
	$attr_options = '<h3>Attribution Options</h3>
						<blockquote>
							<input type="checkbox" name="kapost_settings[attr_create_user]"'.$attr_checked.'/> Create a new WordPress user for each promoted user unless their account (based on email) already exists. 
						</blockquote>';

	echo '
		<form action="" method="post" autocomplete="off" id="options_form">
				'.$attr_options.'
				'.$button_options.'
				'.$sso_options.'
			<p class="submit">
				<input type="submit" value="Update Settings" id="submit" class="button-primary" name="submit"/>
			</p>
		</form>
	</div>';
}

// Handshake with Kapost ... cheese!
function kapost_settings_form_register($instance)
{
	// Verify the existence of the Community user and create it if necessary
	$pass= '';
	$uid = kapost_create_user(KAPOST_COMMUNITY_USER, $pass);
	if(empty($pass)) return kapost_error(-1, 'Community user exists. Delete it and try again ...');

	// Login the user to Kapost returning the necessary user data
	$user = kapost_login_user($instance['url'], $instance['username'], $instance['password']);

	// Return any errors immediately ... 
	if(kapost_is_error($user)) return $user;

	// Update the external site in the newsroom's settings
	$external_site = array
	(
		'newsroom[external_site_attributes][url]'=>get_bloginfo('url'),
		'newsroom[external_site_attributes][platform]'=>'Wordpress',
		'newsroom[external_site_attributes][wordpress_username]'=>KAPOST_COMMUNITY_USER,
		'newsroom[external_site_attributes][wordpress_password]'=>$pass
	);

	$req = kapost_update_newsroom(	$instance['url'], 
									$instance['username'], 
									$instance['password'], 
									$external_site );

	// Return any errors immediately
	if(kapost_is_error($req)&&$req->error->code!=200) return $req;

	return $user;
}

// Get Auth URL
function kapost_auth_url($instance, $dest='', $ignore_sso=false)
{
	$url = !empty($dest) ? $dest : $instance['url'];
	if($instance['sso'] == 'on' && kapost_user_logged_in() && $ignore_sso == false)
	{
		global $current_user; get_currentuserinfo();
		$token = KapostSSO::token(	$instance['newsroom_subdomain'], 
									$instance['newsroom_token'],
									$current_user->ID,
									$current_user->user_email,
									$current_user->display_name,
									false,
									false,
									$instance['newsroom_domain'] );
			
		if($token) 
		{
			$url .= ((strpos($url,"?")===false) ? '?sso=' : '&sso=').$token;
			return $url;
		}
	}

	if(!empty($instance['token']))
		$url .= ((strpos($url,"?")===false) ? '?api_key=' : '&api_key=').$instance['token'];

	return $url;
}

function kapost_message($msg)
{
	if(empty($msg))	return;
	echo "<div class=\"updated fade\" id=\"message\" style=\"background-color:#fffbcc;\"><p><strong>{$msg}</strong></p></div>";
}

//! End
function kapost_settings_form_update_finish($instance, $msg, $sync=false)
{
	//! Update the settings and spit out the message
	update_option('kapost_settings', $instance);

	//! Flush all caches
	kapost_cache_flush();

	//! Show Message
	kapost_message($msg);

	return $instance;
}

//! Add / Update Login Settings
function kapost_settings_form_update_login($new_instance, $old_instance, $silent=false)
{
	$instance = kapost_clear_user_login($old_instance);

	$instance['username'] = strip_tags(stripslashes($new_instance['username']));
	$instance['password'] = stripslashes($new_instance['password']);
	$instance['url'] = kapost_clean_url('http://'.strip_tags(stripslashes($new_instance['url'])));

	$user = kapost_settings_form_register($instance);

	if(kapost_is_error($user))
	{
		$instance = kapost_clear_user_login($instance);
		$msg = kapost_format_error($user);
	}
	else
	{
		$instance = array_merge($instance, (array) $user);
		$msg = "Successfully registered.";
	}

	// Enable XMLRPC
	update_option('enable_xmlrpc', 1);
	return kapost_settings_form_update_finish($instance, ($silent == false) ? $msg : '');
}

//! Add / Update for LogOut
function kapost_settings_form_update_logout($new_instance, $old_instance)
{
	$msg = "Successfully logged out.";
	return kapost_settings_form_update_finish(kapost_clear_user_login($old_instance),$msg);
}

// Add / Update Settings
function kapost_settings_form_update($new_instance, $old_instance)
{
	$instance = $old_instance;

	global $KAPOST_DEFAULT_SETTINGS;
	$instance['contribute'] = $KAPOST_DEFAULT_SETTINGS['contribute'];

	if($new_instance['contribute']['custom'] == 1)
	{
		foreach($new_instance['contribute'] as $k=>$v)
		{
			if(isset($instance['contribute'][$k]))
				$instance['contribute'][$k] = strip_tags(stripslashes($v));
		}
	}

	if($new_instance['sso'] == 'on')
		$instance['sso'] = 'on';
	else
		unset($instance['sso']);

	if($new_instance['attr_create_user'] == 'on')
		$instance['attr_create_user'] = 'on';
	else
		unset($instance['attr_create_user']);

	$msg = "Settings successfully updated.";
	return kapost_settings_form_update_finish($instance, $msg, true);
}

function kapost_active_widget()
{
	$widgets = wp_get_sidebars_widgets();
	unset($widgets['wp_inactive_widgets']);

	foreach($widgets as $widget)
	{
		foreach($widget as $k=>$v)
		{
			if(strpos($v,"kapost_widget")!==false) 
				return true;
		}
	}

	return false;
}

function kapost_settings_tab($tab=null)
{
	if($tab == null) return (isset($_REQUEST['tab'])?$_REQUEST['tab']:'tab1');
	return ($_REQUEST['tab'] == $tab);
}

function kapost_settings_options() 
{
    if(!current_user_can('manage_options'))  
        wp_die('You do not have sufficient permissions to access this page.');

	$old_instance = kapost_settings();

	echo '<div class="wrap"><h2>Kapost Settings</h2>';
	
	if(isset($_REQUEST['submit']))
	{
		if(isset($_POST['kapost_settings']))
			$old_instance = kapost_settings_form_update($_POST['kapost_settings'], $old_instance);
		else if(isset($_POST['kapost_login']))
			$old_instance = kapost_settings_form_update_login($_POST['kapost_login'], $old_instance);
	} 
	else if(isset($_REQUEST['kapost_logout']))
	{
		$old_instance = kapost_settings_form_update_logout(array(), $old_instance);
	}

	if(kapost_user_logged_in($old_instance) && kapost_active_widget() === false)
		kapost_message('Your widget has not been enabled. Click <a href="'.admin_url("widgets.php").'">here</a> to enable it.');

	$tabs = array("tab1"=>"WordPress","tab2"=>"Integration","tab3"=>"About");
	if(!kapost_user_logged_in($old_instance))
	{
		foreach($tabs as $t=>$v)
		{
			if($t == "tab1") continue;
			unset($tabs[$t]);
		}
	}
	
	$tab = kapost_settings_tab();
	if(!isset($tabs[$tab])) $tab = "tab1";

	foreach($tabs as $t=>$v)
	{
		$selected = ($t == $tab) ? " selected" : "";
		echo  '<a href="'.kapost_settings_url().'&tab='.$t.'" class="kapost-tab'.$selected.'">'.$v.'</a>';
	}

	echo '<div class="kapost-tabbed-settings">';

	switch($tab)
	{
		case 'tab2':
		{
			$url = $old_instance['url']; $subdomain = $old_instance['newsroom_subdomain'];
			$integration_url = $url . 'newsrooms/' . $subdomain . '/edit?settings_tab=integration&remote=true';

			$url = kapost_auth_url($old_instance,$integration_url,true);
			echo '<iframe src="'.$url.'" style="width:970px;height:380px;" scrolling="no"></iframe>';
		}
		break;

		case 'tab3':
		{

			echo '<div>
					<h3>Version Information:</h3>
					<blockquote>
					<p><strong>Plugin:</strong> '.KAPOST_VERSION.'</p>
					<p><strong>WordPress:</strong> '.KAPOST_WP_VERSION.'</p>
					<p><strong>PHP:</strong> '.PHP_VERSION.'</p>
					<p><strong>WebServer:</strong> '.$_SERVER['SERVER_SOFTWARE'].'</p>
					<p><strong>GetExceptionial:</strong> '.(defined('KAPOST_EXCEPTIONAL')?"is active.":"is not active.").'</p>';

					if(!KAPOST_RELEASE_MODE)
						echo '<p style="color:red"><strong>Warning: The plugin is running in DEBUG mode !!!</strong></p>';

			echo '</blockquote></div>';
		}
		break;

		default:
		{
			echo '<p>The field below allows you to associate your community site with your blog. ';
			echo 'You will need to either sign-in with the username ';
			echo 'and password you used to create your account, or create a new community site.';
			echo '</p>';

			kapost_settings_form($old_instance);
		}
		break;
	}

	echo '</div>';
}
?>
