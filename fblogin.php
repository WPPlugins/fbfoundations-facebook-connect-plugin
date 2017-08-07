<?php

/*
Plugin Name: FBFoundations
Plugin URI: http://staynalive.com/fbfoundations/
Description: Gives you a bare-bones Facebook Connect install.  On install you get:<ul><li>Facebook XFBML Compatibility - your site will support XFBML</li><li>Facebook Javascript Client library inclusion - you can make Javascript calls to the Facebook API</li><li>Log in buttons - a log in button is added above the comments section and sidebar. Upon login, the buttons disappear.  Also, you have the option for a popup that will appear and prompt users to log in the first 3 (configurable) times they visit your site.</li><li>Meta tags - your blog will be <a href="http://staynalive.com/fbshare">FB Share</a> compatible.  Facebook share buttons will load the post title, description, and first image of the post by default on single post pages (instead of the entire blog). See author's <a href="http://staynalive.com/fbshare">FB Share</a> plugin to enable Facebook Share on your blog that is compatible with this.</li><li>When logged in, the log in buttons disappear and you can then add other FBFoundations-compatible Wordpress plugins, or write your own stuff that uses the user's Facebook authorization.</li></ul>
Version: .4
Author: Jesse Stay
Author URI: http://staynalive.com

Copyright (c) 2009 Jesse Stay
Released under the GNU General Public License (GPL)
http://www.gnu.org/licenses/gpl.txt
*/

/* Changelog:
10/13/2009 - 0.2 - updated script loading to wp_enqueue_scripts thanks to Otto
10/26/2009 - 0.3 - make popup on login optional (and defaulted to off) - adds login button to top of sidebar
10/26/2009 - 0.4 - Compatible with Disqus now. No need for #commentform div in markup any more. Detects post images and adds meta tags for first image, title, and description
*/

$defaultdata = array(
	'repetition' 	=> '3',
	'api_key'	=> "",
	'popup_onload'	=> 0,
	);
	
add_option('fbfoundations_settings', $defaultdata, 'Options for FBFoundations');
$fbfoundations_settings = get_option('fbfoundations_settings');

add_action('wp_enqueue_scripts','fbfoundations_jquery');
add_action('wp_enqueue_scripts','fbfoundations_featureloader');
add_action('wp_head','insert_meta');
add_action('admin_menu', 'add_fbfoundations_options_page');
add_action('init', 'fbfoundations_set_cookie');
add_action('wp_footer', 'fbfoundations_code');
add_action('get_sidebar','insert_login_button_sidebar');

add_filter('the_content','insert_login_button_comments',1);

function insert_meta() {

	global $post;

	$excerpt = '';
	if (is_single()) {
		$data = get_post($post->ID);
		$excerpt = substr(strip_tags($data->post_content),0,255);
?>
<meta name="title" content="<?php if ( is_single() ) { single_post_title('', true); } else { bloginfo('name'); echo " - "; bloginfo('description'); } ?>" />
<meta name="description" content="<?php if ( is_single() ) { echo htmlentities($excerpt); } else { bloginfo('name'); echo " - "; bloginfo('description'); } ?>" />
<meta name="medium" content="blog" />
<?php


		$media = array();

		// now get all images
		if ( preg_match_all('/<img (.+?)>/', $data->post_content, $matches) ) {
			foreach ( $matches[1] as $attrs ) {
				$item = $img = array();
				foreach ( wp_kses_hair($attrs, array('http')) as $attr )
					$img[$attr['name']] = $attr['value'];
				if ( !isset($img['src']) )
					continue;
				// skip emoticons
				if ( isset( $img['class'] ) && false !== strpos( $img['class'], 'wp-smiley' ) )
					continue;	
				$id = false;
				if ( isset( $lookup[$img['src']] ) ) {
					$id = $lookup[$img['src']];
				} elseif ( isset( $img['class'] ) && preg_match( '/wp-image-(\d+)/', $img['class'], $match ) ) {
					$id = $match[1];
				}
				if ( $id ) {
					// It's an attachment, so we will get the URLs, title, and description from functions
					$src = wp_get_attachment_image_src( $id, 'full' );
					if ( !empty( $src[0] ) )
						$img['src'] = $src[0];
				}
				// If this is the first image in the markup, make it the post thumbnail
				if ( ++$images == 1 ) {
					?><link rel="image_src" href="<?php echo $img['src'] ?>" /><?php
				}

			}
		}
	}

}

function fbfoundations_jquery() {
	wp_enqueue_script( 'jquery');
}

function fbfoundations_featureloader() {
	wp_enqueue_script( 'fb-featureloader', 'http://static.ak.connect.facebook.com/js/api_lib/v0.4/FeatureLoader.js.php', array(), '0.4', true );
}

function add_fbfoundations_options_page() {

	if (function_exists('add_options_page')) {
		add_options_page('FB Foundations', 'FBFoundations', 8, basename(__FILE__), 'fbfoundations_options_subpanel');
	}

}

function fbfoundations_options_subpanel() {

	global $fbfoundations_settings, $_POST;

	if (isset($_POST['submit'])) {
		$fbfoundations_settings['api_key'] = $_POST['api_key'];
		$fbfoundations_settings['repetition'] = $_POST['repetition'];
		$fbfoundations_settings['popup_onload'] = $_POST['popup_onload'];

		update_option('fbfoundations_settings', $fbfoundations_settings);
	}
	?>
	<div class="wrap">
	<h2>FB Foundations</h2>
	<p>Sets up the basic foundations for a Facebook Connect-enabled blog. Add other FB Foundations-compatible plugins to this to customize your blog how you like.</p>
	<form action="" method="post">
	<h3>Facebook API Key for Connect Site</h3>
	<p>Go to <a href="http://developers.facebook.com">http://developers.facebook.com</a> to set up your site and get an API key.</p>
	<p><input type="text" name="api_key" value="<?php echo $fbfoundations_settings['api_key']; ?>" size="150" /></p>
	<h3>Pop up a login dialog on page load?</h3>
	<p>
	<select name="popup_onload">
	<option value="0"<?php if (!$fbfoundations_settings['popup_onload']) { ?> selected="selected"<?php } ?>>No</option>
	<option value="1"<?php if ($fbfoundations_settings['popup_onload']) { ?> selected="selected"<?php } ?>>Yes</option>
	</select>
	</p>
	<h3>Repetition (only applies if "Pop up on login" is enabled above)</h3>
	<p>Prompt the user (with a popup dialog) to log in to Facebook the first <input type="text" name="repetition" value="<?php echo $fbfoundations_settings['repetition']; ?>" size="3" /> times the user visits.</p>
	<p><input type="submit" name="submit" value="Save Settings" /></p>
	</form>
	</div>
	<?php
	
}

function fbfoundations_set_cookie()
{
	global $fbfoundations_visits;
	
	if (!is_admin())
	{
		if (isset($_COOKIE['fbfoundations_visits']))
		{
			$fbfoundations_visits = $_COOKIE['fbfoundations_visits'] + 1;
		}
		else
		{
			$fbfoundations_visits = 1;
		}
		$url = parse_url(get_option('home'));
		setcookie('fbfoundations_visits', $fbfoundations_visits, time()+60*60*24*365, $url['path'] . '/');
	}
}

function fbfoundations_code() {

	global $fbfoundations_visits, $fbfoundations_settings, $fbfoundations_messagedisplayed;
	
	if (!is_feed() && $fbfoundations_settings['api_key']) {


       		echo '
<script type="text/javascript">

	function setCookie(c_name,value,expiredays) {
		var exdate=new Date();
		exdate.setDate(exdate.getDate()+expiredays);
		document.cookie=c_name+ "=" +escape(value)+
		((expiredays==null) ? "" : ";expires="+exdate.toGMTString());
	}

	var callback = function() {
		setCookie("fbfoundations_visits",0,60*60*24*365);
		location.reload(true);
	};

  	FB_RequireFeatures(["XFBML"],
	function()
  	{
    		FB.Facebook.init("'.$fbfoundations_settings['api_key'].'", "/wp-content/plugins/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)).'xd_receiver.htm",{"reloadIfSessionStateChanged":true});
	';

	if ($fbfoundations_visits <= $fbfoundations_settings['repetition'] && !$fbfoundations_messagedisplayed) {
        	$fbfoundations_messagedisplayed = true;
		echo '
		FB.Connect.get_status().waitUntilReady( function( status ) {
   			switch ( status ) {
   				case FB.ConnectState.connected:
      					loggedIn = true;
      					break;
   				case FB.ConnectState.appNotAuthorized:
		';
		if ($fbfoundations_settings['popup_onload']) {
			echo '
         				FB.Connect.requireSession();
			';
		}
		echo '
   				case FB.ConnectState.userNotLoggedIn:
		';
		if ($fbfoundations_settings['popup_onload']) {
			echo '
         				FB.Connect.requireSession();
      					loggedIn = false;
			';
		}
		echo '
   		}});
		';
	}

	echo '
  	});

</script>
		';
	}

}

function insert_login_button_comments($content) {

	if (is_single()) {
		$content .= '
<div id="fblogin_comments"></div>
<script type="text/javascript">
	var ready = 0;
	jQuery(document).ready(function($) {
		var status = FB.Connect.get_status();
		if (status && status.result == FB.ConnectState.appNotAuthorized || status.result == FB.ConnectState.userNotLoggedIn) {
			$("#fblogin_comments").append("<div class=\"login-button\"><fb:login-button length=\"long\" autologoutlink=\"true\" onlogin=\"callback\"></fb:login-button></div>");
			FB.XFBML.Host.parseDomTree();
		}
	});
</script>
		';
	}

	return $content;

}

function insert_login_button_sidebar() {

	echo '
<div id="fblogin_sidebar"></div>
<script type="text/javascript">
	var ready = 0;
	jQuery(document).ready(function($) {
		var status = FB.Connect.get_status();
		if (status && status.result == FB.ConnectState.appNotAuthorized || status.result == FB.ConnectState.userNotLoggedIn) {
			$("#fblogin_sidebar").append("<div class=\"login-button\"><fb:login-button length=\"long\" autologoutlink=\"true\" onlogin=\"callback\"></fb:login-button></div>");
			FB.XFBML.Host.parseDomTree();
		}
	});
</script>
	';

}

// Courtesy http://wphackr.com/get-images-attached-to-post/
function bdw_get_images() {

	global $post;

    	// Get the post ID
    	$iPostID = $post->ID;
	if (!$iPostID) { return; }

    	// Get images for this post
    	$arrImages =& get_children('post_type=attachment&post_mime_type=image&post_parent=' . $iPostID );

    	// If images exist for this page
	if($arrImages) {

        // Get array keys representing attached image numbers
        $arrKeys = array_keys($arrImages);

	/******BEGIN BUBBLE SORT BY MENU ORDER************/
	// Put all image objects into new array with standard numeric keys (new array only needed while we sort the keys)
	foreach($arrImages as $oImage) {
		$arrNewImages[] = $oImage;
	}

	// Bubble sort image object array by menu_order TODO: Turn this into std "sort-by" function in functions.php
	for($i = 0; $i < sizeof($arrNewImages) - 1; $i++) {
		for($j = 0; $j < sizeof($arrNewImages) - 1; $j++) {
			if((int)$arrNewImages[$j]->menu_order > (int)$arrNewImages[$j + 1]->menu_order) {
				$oTemp = $arrNewImages[$j];
				$arrNewImages[$j] = $arrNewImages[$j + 1];
				$arrNewImages[$j + 1] = $oTemp;
			}
		}
	}

	// Reset arrKeys array
	$arrKeys = array();

	// Replace arrKeys with newly sorted object ids
	foreach($arrNewImages as $oNewImage) {
		$arrKeys[] = $oNewImage->ID;
	}
	/******END BUBBLE SORT BY MENU ORDER**************/

        // Get the first image attachment
        $iNum = $arrKeys[0];

        // Get the thumbnail url for the attachment
        $sThumbUrl = wp_get_attachment_thumb_url($iNum);

        // UNCOMMENT THIS IF YOU WANT THE FULL SIZE IMAGE INSTEAD OF THE THUMBNAIL
        //$sImageUrl = wp_get_attachment_url($iNum);

        // Build the <img> string
        $sImgString = '<a href="' . get_permalink() . '">' .
                            '<img src="' . $sThumbUrl . '" width="150" height="150" alt="Thumbnail Image" title="Thumbnail Image" />' .
                        '</a>';

        // Print the image
        echo $sImgString;
	}
}
?>
