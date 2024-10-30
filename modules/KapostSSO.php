<?php
	class KapostSSO
	{		
		/*
			Generates a Kapost SSO token for the given +guid+, +email+, +name+, +avatar+, 
			and +bio+ signed with the provided +subdomain+ and +key+.
		*/
		function token($subdomain, $key, $guid, $email, $name=false, $avatar=false, $bio=false, $domain=false)
		{
			$token = 0;
			
			if(!$subdomain || !$key || !$guid || !$email) return 0;
			if(!$domain) $domain = "$subdomain.kapost.com";	
			
			$data = array
			(
				'subdomain'=>$subdomain,
				'key'=>$key,
				'guid'=>$guid,
				'email'=>$email
			);
			
			if($name) 		$data['name'] 	= $name;
			if($avatar) 	$data['avatar'] = $avatar;
			if($bio) 		$data['bio'] 	= $bio;
			
			$post = array
			(
				'http' => array
				(
              		'method' => 'POST',
              		'content' => http_build_query($data)
            	)
            );
            
  			$fp = @fopen("http://$domain/sso/token.json",'rb',false,stream_context_create($post));
  			if($fp && ($response = @stream_get_contents($fp))!==false) 
 	 		{
 	 			$token = @json_decode($response);
 	 			if(is_object($token) && isset($token->token))
 	 				$token = $token->token;
 	 			else
 	 				$token = 0;
  			}
			
			return $token;
		}
		
		/*
			Generates a Kapost SCRIPT tag. The script will automatically rewrite all Kapost
			URLs to include a 'sso' query parameter with a signed Kapost SSO token.
		*/
		function script($subdomain, $key, $guid, $email, $name=false, $avatar=false, $bio=false, $domain=false)
		{
			if(!$domain) $domain = "$subdomain.kapost.com";	
		
			$token = KapostSSO::token(	$subdomain,
										$key,
										$guid,
										$email,
										$name,
										$avatar,
										$bio,
										$domain );
	
			if($token)
			{
				$token = urlencode($token);
			}
			else
			{
				return '';
			}
	
			$script = <<<EOF
<script type="text/javascript">
(function()
{		
	var scr = document.createElement("script");
	scr.src = 'http://$domain/javascripts/sso.js';
	scr.id = 'kapostsso';
			
	var s = document.getElementsByTagName('script')[0]; 
	s.parentNode.insertBefore(scr, s);
	
	window.onload = (function()
	{
		var oldonload = window.onload;
		return function()
		{	
			if(oldonload && typeof oldonload == 'function') oldonload.apply(this, arguments);
			setTimeout(function(){try{KapostSSO.instance('$token','$domain');}catch(err){}},100);
		};
	})();
	
})();
</script>
EOF;
			return $script;
		}
	}
?>
