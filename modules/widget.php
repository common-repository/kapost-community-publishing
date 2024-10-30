<?php
//! Kapost Widget Class
class Kapost_Widget extends WP_Widget 
{
	//! Default Settings
	var $defaults = array(	'feed'				=> 'recent', //recent, popular, none
						  	'title' 			=> '', 
							'show' 				=> '4', 
							'author' 			=> 'on', 
							'date' 				=> '', 
							'contribute' 		=> 'on',
							'contribute_text' 	=> 'Contribute',
							'description_text'	=> 'This is content created by our users.',
							'style'				=> 'default'); // li, span, default
	//! Default Module Title
	var $default_title = "Feed";
	//! Default Contribute Button Text
	var $default_contribute = "Contribute";

	//! Constructor
	function Kapost_Widget() 
	{
		$options = array('classname' => 'widget_kapost', 'description' => "A list of posts on your community site." );
		$this->WP_Widget('kapost_widget', 'Kapost: '.$this->default_title, $options); //! call superclass
	}

	//! Configure Message
	function configure_message()
	{
		$msg = "Your Kapost plugin has not been configured, you must do so for any data to appear.<br/><br/>";
		$msg.= 'Please sign-in <a href="'.kapost_settings_url().'">here</a> .';

		return "<p><strong>Warning: </strong>${msg}</p>";
	}

	//! Fetch and Update Cache Data
	function fetch($settings, $instance, $flush=false)
	{
		$cache_key = $this->id . "_cache";

		if(1!=1 && !$flush)
		{
			$expired = false;
			$data = kapost_cache_get($cache_key, $expired);

			//! Schedule a flush
			if($expired)
			{
				$refresh_cache = create_function('', 'kapost_widget_cache_flush("'.$this->id.'");');
				add_action('shutdown', $refresh_cache);
			}
		}
		else
		{
			$data = null;
		}

		if(!is_array($data))
		{
			$data = array();

			if(!is_numeric($instance['show']))
				$instance['show'] = $this->defaults['show'];

			if($instance['feed'] == 'promoted')
			{
				$posts = kapost_get_posts($instance['show']);

				foreach($posts as $k=>$post)
				{
					$user = get_userdata($post['post_author']);

					$posts[$k]['title'] = $post['post_title'];
					$posts[$k]['uri'] = $post['guid'];
					$posts[$k]['user_profile'] = $user->user_url;
					$posts[$k]['user_name'] =  $user->display_name;
					$posts[$k]['changed'] = strtotime($posts[$k]['post_modified']);
					$posts[$k]['created'] = strtotime($posts[$k]['post_date']);
				}

				$data['posts'] = $posts;
			}
			else
			{
				$req = kapost_request_json($settings['url'].'posts.json?count='.$instance['show']);
				if(kapost_is_error($req))
				{
					$data['error'] = kapost_format_error($req);
				}
				else
				{
					$posts = array();

					if(!is_array($req->body))
						$req->body = array();

					foreach($req->body as $post)
						$posts[] = (array) $post;

					//! Re-format posts here
					foreach($posts as $k=>$v)
					{
						$posts[$k]['uri'] = $settings['url'].'posts/'.$posts[$k]['slug']; 
						$posts[$k]['user_profile'] = $settings['url'].'users/'.$posts[$k]['user_id'];
						$posts[$k]['user_name'] = $posts[$k]['author_name'];
						$posts[$k]['changed'] = strtotime($posts[$k]['updated_at']);
						$posts[$k]['created'] = strtotime($posts[$k]['created_at']);
					}

					$data['posts'] = $posts;
				}
			}

			kapost_cache_set($cache_key, $data);
		}
	
		return $data;
	}

	//! Flush
	function flush()
	{
		$settings = kapost_settings();
		$instance = get_option('widget_kapost_widget');
		if(is_array($instance[$this->number]))
			$instance = $instance[$this->number];
		else
			$instance = array();

		if(empty($instance['feed']))
			$instance['feed'] = 'recent';

		//! None
		if($instance['feed'] == 'none')	return;

		//! Get Username
		$username = $settings['username'];
		if(empty($username)) return;

		//! Get Password
		$password = $settings['password'];
		if(empty($password)) return;

		//! Get Url
		$url = $settings['url'];
		if(empty($url)) return;

		//! Update
		$this->fetch($settings, $instance, true);
	}

	//! Show the widget
	function widget($args, $instance) 
	{
		extract($args);

		$settings = kapost_settings();

		//! Apply any filters on the title
		$title = apply_filters('widget_title', $instance['title']);
		if(empty($title)) $title = $this->default_title;

		$url = $settings['url'];

		if(!kapost_user_logged_in($settings) || empty($url))
		{
			echo $before_widget;
			echo "{$before_title}{$title}{$after_title}";
			echo $this->configure_message();
			echo $after_widget;
			return;
		}

		//! Get number of posts to show
		$show = $instance['show'];

		//! Show WordPress's Before Widget boilerplate code
		echo $before_widget;
		
		//! Show Widget Title
		echo "{$before_title}{$title}{$after_title}";

		if($instance['feed'] != 'none')
		{
			if($instance['style'] == 'default')
			{
				echo '<div style="margin-left:10px;margin-right:10px;">';
				echo $instance['description_text'];
				if($instance['contribute'] == 'on')
				{
					$c_text = $instance['contribute_text'];
					if(!empty($c_text)) $c_text = $this->default_contribute;
					echo ' If you want to add something, click the "'.$c_text.'" button below.';
				}
			}

			//! Get Data
			$data = $this->fetch($settings, $instance);

			//! Handle any error codes here
			if(isset($data['error']))
			{
				echo "<p>".$data['error']."</p>";
			}
			else if(isset($data['posts'])) //! Format and show posts here
			{
				$posts = $data['posts'];

				$i = 0;

				$author = ($instance['author'] == 'on');
				$date = ($instance['date'] == 'on');

				$style = $instance['style'];

				if($style == 'default')
				{
					echo '<div style="margin-top:10px; margin-bottom:10px;">';
					foreach($posts as $post)
					{
						echo '<hr style="margin:0px;margin-top:5px"/>';
						echo '<div>';	
						echo '<div><a href="'.$post['uri'].'">'.$post['title'].'</a></div>';
						
						echo "<div>";
						if($author)
						{
							echo 'by <a href="'.$post['user_profile'].'">'.kapost_trim($post['user_name'],15).'</a>';
						}
						if($date) 
						{
							$d = (is_numeric($post['changed'])?$post['changed']:$post['created']);
							echo ' | '.kapost_time_since($d).' ago';
						}
						echo "</div>";
				
						echo "</div>";	

						if(++$i == $show)
							break;
					}
					echo "</div>";
				}
				else
				{
					if($style == 'li') echo "<ul>";
					foreach($posts as $post)
					{
						echo "<${style}>";	
						echo '<a href="'.$post['uri'].'">'.$post['title'].'</a>';
						
						if($author) echo ' by <a href="'.$post['user_profile'].'">'.kapost_trim($post['user_name'],15).'</a>';

						$d = (is_numeric($post['changed'])?$post['changed']:$post['created']);
						if($date) echo ' '.kapost_time_since($d).' ago';
				
						echo "</${style}>";	
						echo '<br/>';

						if(++$i == $show)
							break;
					}
					if($style == 'li') echo "</ul>";
				}
			}
		}

		$url2 = "http://" . $settings['newsroom_domain'] . "/";

		//! Show the contribute button
		if($instance['contribute'] == 'on') kapost_contribute_button($url2, $instance, $this->default_contribute);

		if($instance['feed'] != 'none' && $instance['style'] == 'default') echo '</div>';

		// SSO time
		if($settings['sso'] == 'on')
		{
			if(is_user_logged_in())
			{
				global $current_user; get_currentuserinfo();
				print KapostSSO::script($settings['newsroom_subdomain'], 
										$settings['newsroom_token'], 
										$current_user->ID, 
										$current_user->user_email, 
										$current_user->display_name,
										false, // no avatar
										false, // no bio
										$settings['newsroom_domain']);
			}
		}

		//! Show WordPress's After Widget boilerplate code
		echo $after_widget;
	}

	//! Save / Update Settings Form
	function update($new_instance, $old_instance) 
	{
		$instance = $old_instance;

		$instance['title'] = strip_tags(stripslashes($new_instance['title']));

		$show =  absint($new_instance['show']);

		//! Don't do anything crazy
		if($show > 10) $show = absint($this->defaults['show']);

		if(!$show) $show = $this->defaults['show'];

		$instance['show'] =	$show;
		$instance['author'] = strip_tags(stripslashes($new_instance['author']));
   		$instance['date'] = strip_tags(stripslashes($new_instance['date']));
		$instance['contribute'] = strip_tags(stripslashes($new_instance['contribute']));
		$instance['contribute_text'] = strip_tags(stripslashes($new_instance['contribute_text']));
		$instance['description_text'] = strip_tags(stripslashes($new_instance['description_text']));

		$instance['style'] = strip_tags(stripslashes($new_instance['style']));
		if(!in_array($instance['style'],array('li','span','default')))
			$instance['style'] = 'default';

		$instance['feed'] = strip_tags(stripslashes($new_instance['feed']));
		if(!in_array($instance['feed'],array('recent','promoted','none')))
			$instance['feed'] = 'recent';

		//! Always turn on the contribute button when the feed type is None
		if($instance['feed'] == 'none')	$instance['contribute'] = 'on';
		
		//! Flush Caches
		if($instance['feed'] != 'none') $this->flush();

		return $instance;
	}

	//! Show Settings Form
	function form($instance) 
	{
		//! Merge Widget Settings
		$instance = wp_parse_args((array) $instance, $this->defaults);

		if(!kapost_user_logged_in())
			echo $this->configure_message();

		//! Feed
		$id = $this->get_field_id('feed');
		$name = $this->get_field_name('feed');
		echo '<p><label for="'.$id.'">Select feed to show: ';
		echo '<select id="'.$id.'" name="'.$name.'">';
		$feeds = array('recent'=>'Recent Items','promoted'=>'Promoted Items','none'=>'No Items');
		foreach($feeds as $k=>$v)
		{
			$selected = ($k == $instance['feed']) ? ' selected="selected"' : '';
			echo '<option value="'.$k.'"'.$selected.'>'.$v.'</option>';
		}
		echo '</select></label></p>';

		//! Title
		kapost_form_input($this, 'title', 'Give the feed a title (optional):', $instance);

		//! Appearance
		$id = $this->get_field_id('style');
		$name = $this->get_field_name('style');
		echo '<p><label for="'.$id.'">Feed Appearance: ';
		echo '<select id="'.$id.'" name="'.$name.'">';
		$styles = array('span'=>'Regular','li'=>'List','default'=>'Default');
		foreach($styles as $k=>$v)
		{
			$selected = ($k == $instance['style']) ? ' selected="selected"' : '';
			echo '<option value="'.$k.'"'.$selected.'>'.$v.'</option>';
		}
		echo '</select></label></p>';

		//! How many posts to show?
		$id = $this->get_field_id('show');
		$name = $this->get_field_name('show');
		echo '<p><label for="'.$id.'">How many items would you like to display? ';
		echo '<select id="'.$id.'" name="'.$name.'">';
		for($i=1;$i<=10;$i++)
		{
			$selected = ($i == $instance['show']) ? ' selected="selected"' : '';
			echo '<option value="'.$i.'"'.$selected.'>'.$i.'</option>';
		}
		echo '</select></label></p>';

		//! Checkboxes
		kapost_form_checkbox($this, 'contribute', 'Display "Contribute" button?', $instance);
		kapost_form_input($this, 'contribute_text', 'Text on button:', $instance);
		kapost_form_input($this, 'description_text', 'Description Text:', $instance);

		kapost_form_checkbox($this, 'author', 'Display item author?', $instance);
		kapost_form_checkbox($this, 'date', 'Display item date?', $instance);
	}
}
//! Register our Widget with the `nervous system`
add_action('widgets_init', 'kapost_widget_init');
function kapost_widget_init() 
{
	register_widget('Kapost_Widget');
}

//! Flush Cache Hook
add_action('kapost_cache_flush', 'kapost_widget_cache_flush');
function kapost_widget_cache_flush($id=null)
{
	if($id != null)
	{
		$widgets = array('widgets'=>array($id));
	}
	else
	{
		$widgets = wp_get_sidebars_widgets();
		unset($widgets['wp_inactive_widgets']);
	}
	 
	foreach($widgets as $widget)
	{
		foreach($widget as $k=>$v)
		{
	 		if(strpos($v,"kapost_widget")!==false)
			{
				$w = new Kapost_Widget();
				$w->id = $v;
				$w->number = absint(str_replace("kapost_widget-","",$v));
				$w->flush();
			}
		}
	}
}
?>
