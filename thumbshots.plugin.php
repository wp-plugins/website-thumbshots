<?php
/*
Plugin Name: Website Thumbshots
Plugin URI: http://www.thumbshots.ru/en/website-thumbshots-wordpress-plugin
Author: Thumbshots.RU Dev Team
Author URI: http://www.thumbshots.ru/
Description: This plugin uses the Thumbshots.RU API to replace special tags in posts with website screenshots.
Version: 1.4.6
*/

/**
 * This file implements the Website Thumbshots plugin
 *
 * Author: Sonorth Corp. - {@link http://www.sonorth.com/}
 * License: GPL version 3 or any later version
 * License info: {@link http://www.gnu.org/licenses/gpl.txt}
 *
 * Version: 1.4.6
 * Date: 26-Feb-2013
 */

// Load common functions
require_once dirname(__FILE__).'/inc/_common.funcs.php';

// Load SonorthPluginHelper class
require_once dirname(__FILE__).'/inc/_plugin-helper.class.php';


class thumbshots_plugin extends SonorthPluginHelper
{
	var $name = 'Website Thumbshots';
	var $code = 'thumbshots_plugin';
	var $version = '1.4.6';
	var $help_url = 'http://www.thumbshots.ru/en/website-thumbshots-wordpress-plugin';

	var $debug = 0;
	var $debug_IP = '';
	var $cache_dirname = 'thumbs_cache';
	var $default_key = 'DEMOKEY002PMK1CERDMUI5PP5R4SPCYO';

	// Internal
	var $thumbshots_class = 'inc/_thumbshots.class.php';
	var $thumbnails_path;
	var $display_preview = '#';		// fallback to plugin setting
	var $link_to_exit_page = '#';	// fallback to plugin setting
	var $_service_images;
	var $_head_scripts = array();


	function thumbshots_plugin()
	{
		$this->menu_text = 'Thumbshots';
		$this->pre = 'thumb_';
		$this->uri = snr_get_request('uri');
		$this->foldername = 'website-thumbshots';
		$this->filename = basename(__FILE__);

		$this->thumbnails_path = WP_CONTENT_DIR.'/'.$this->cache_dirname.'/';
		$this->thumbnails_url = content_url('/'.$this->cache_dirname.'/');

		// Register shortcode tags
		add_shortcode( 'thumb', array($this, 'parse_shortcode') );
		add_shortcode( 'thumbshot', array($this, 'parse_shortcode') );

		// Register plugin settigs
		$this->initialize_options();

		register_activation_hook( __FILE__, array($this, 'plugin_activate') );

		// Action hooks
		$this->add_action('init');
		$this->add_action('wp_ajax_thumb_reload');
		$this->add_action('wp_ajax_clear_thumb_cache');
		$this->add_action('admin_menu');
		$this->add_action('wp_head');

		// Add our button to the post edit form
		$this->add_action('dbx_post_sidebar');

		// Add plugin action links
		add_filter( 'plugin_action_links', array($this, 'add_action_links'), 10, 2 );
	}


	function GetDefaultSettings()
	{
		$locale = get_locale();
		if( $locale != 'ru_RU' )
		{
			$locale = 'en_US';
		}

		$register_url = 'https://my.thumbshots.ru/auth/register.php?locale='.str_replace('_', '-', $locale);
		$error_codes_url = 'http://www.thumbshots.ru/error-codes';

		$max_w = 1280;
		$onclick = 'onclick="Javascript:jQuery.get(this.href); jQuery(this).replaceWith(\'<span style=\\\'color:red\\\'>done</span>\'); return false;"';

		$r = array(
			'access_key' => array(
				'label' => $this->T_('Access Key'),
				'size' => 50,
				'note' => sprintf( $this->T_('Enter your access key here.<br /><a %s>Get your FREE account now</a>!'), 'href="'.$register_url.'" target="_blank"' ),
				'defaultvalue' => $this->default_key,
			),
			'link' => array(
				'label' => $this->T_('Link images'),
				'defaultvalue' => true,
				'type' => 'checkbox',
				'note' => $this->T_('Check this to display clickable images.'),
			),
			'link_noindex' => array(
				'label' => $this->T_('Add rel="noindex" to links'),
				'defaultvalue' => false,
				'type' => 'checkbox',
				'note' => $this->T_('Check this to add rel="noindex" attribute to image links.'),
			),
			'link_nofollow' => array(
				'label' => $this->T_('Add rel="nofollow" to links'),
				'defaultvalue' => false,
				'type' => 'checkbox',
				'note' => $this->T_('Check this to add rel="nofollow" attribute to image links.'),
			),
			'sep0' => array(
				'layout' => 'separator',
			),
			'allow_reloads' => array(
				'label' => $this->T_('Allow thumbshot reloads'),
				'type' => 'checkbox',
				'defaultvalue' => 1,
				'note' => $this->T_('Check this if you want to allow admins to reload/refresh individual thumbshots. Reload button pops-up when you hover over a thumbshot.'),
			),
			'link_to_exit_page' => array(
				'label' => $this->T_('Link to exit page'),
				'type' => 'checkbox',
				'defaultvalue' => 0,
				'note' => $this->T_('Check this if you want to link external URLs to an exit "goodbye" page.'),
			),
			'thumb_popups' => array(
				'label' => $this->T_('Display website preview'),
				'type' => 'checkbox',
				'defaultvalue' => 1,
				'note' => $this->T_('Display website previews when you hover over external post link.'),
			),
			'sep1' => array(
				'layout' => 'separator',
			),
			'quality' => array(
				'label' => $this->T_('Thumbshot quality'),
				'size' => 3,
				'defaultvalue' => 95,
				'type' => 'integer',
				'valid_range' => array( 'min' => 1, 'max' => 100 ),
				'note' => $this->T_('JPEG quality [1-100].'),
			),
			'width' => array(
				'label' => $this->T_('Default thumbshot width'),
				'size' => 3,
				'defaultvalue' => 120,
				'type' => 'integer',
				'valid_range' => array( 'max' => $max_w ),
				'note' => $this->T_('px.'),
			),
			'height' => array(
				'label' => $this->T_('Default thumbshot height'),
				'size' => 3,
				'defaultvalue' => 90,
				'type' => 'integer',
				'note' => $this->T_('px. Enter 0 to get full-length thumbshots.'),
			),
			'sep2' => array(
				'layout' => 'separator',
			),
			/*'original_image_q' => array(
				'label' => $this->T_('Original image quality'),
				'size' => 3,
				'defaultvalue' => 98,
				'type' => 'integer',
				'valid_range' => array( 'min' => 1, 'max' => 100 ),
				'note' => $this->T_('JPEG quality [1-100].'),
			),*/
			'original_image_w' => array(
				'label' => $this->T_('Original image width'),
				'size' => 3,
				'defaultvalue' => 640,
				'type' => 'integer',
				'valid_range' => array( 'max' => $max_w ),
				'note' => $this->T_('px. '),
			),
			'original_image_h' => array(
				'label' => $this->T_('Original image height'),
				'size' => 3,
				'defaultvalue' => 0,
				'type' => 'integer',
				'note' => $this->T_('px. Enter 0 to request full-length images from server.'),
			),
			'sep3' => array(
				'layout' => 'separator',
			),
			'display_preview' => array(
				'label' => $this->T_('Display header preview'),
				'type' => 'checkbox',
				'defaultvalue' => 1,
				'note' => $this->T_('Check this if you want to display website header preview on image hover (tooltip).'),
			),
			'preview_width' => array(
				'label' => $this->T_('Preview image width'),
				'size' => 3,
				'defaultvalue' => 320,
				'type' => 'integer',
				'note' => $this->T_('px.'),
			),
			'preview_height' => array(
				'label' => $this->T_('Preview image height'),
				'size' => 3,
				'defaultvalue' => 90,
				'type' => 'integer',
				'note' => $this->T_('px. Enter 0 to get full-length preview (not recommended).'),
			),
			'sep4' => array(
				'layout' => 'separator',
			),
			'cache_days' => array(
				'label' => $this->T_('Cache images'),
				'size' => 3,
				'defaultvalue' => 7,
				'valid_range' => array( 'min' => 0 ),
				'type' => 'integer',
				'note' => sprintf( $this->T_('days. How many days do you want to store image cache. Clear cache: <a %s>files</a> | <a %s>files and folders</a>.'),
					'href="'.$this->get_url('clear').'files" '.$onclick,
					'href="'.$this->get_url('clear').'everything" '.$onclick ),
			),
			'queued_cache_days' => array(
				'label' => $this->T_('Cache "queued" images'),
				'size' => 3,
				'defaultvalue' => 0,
				'valid_range' => array( 'min' => 0 ),
				'type' => 'integer',
				'note' => $this->T_('days. How many days do you want to store "queued" image cache.'),
			),
			'err_cache_days' => array(
				'label' => $this->T_('Cache "error" images'),
				'size' => 3,
				'defaultvalue' => 3,
				'valid_range' => array( 'min' => 0 ),
				'type' => 'integer',
				'note' => $this->T_('days. How many days do you want to store "error" image cache.'),
			),
			'sep5' => array(
				'layout' => 'separator',
			),
			'service_images_enabled' => array(
				'label' => $this->T_('Custom service images'),
				'type' => 'checkbox',
				'defaultvalue' => false,
				'note' => $this->T_('Check this to enable custom service images defined below.'),
			),
			'service_images' => array(
				'label' => $this->T_('Image definitions'),
				'note' => sprintf( $this->T_('[Error code = URL to JPEG image]<br />Here you can define custom service images displayed when a thumbshot cannot be loaded from our server.<br />See <a %s>this page</a> for a complete list of error codes you can use.'), 'href="'.$error_codes_url.'" target="_blank"' ),
				'type' => 'html_textarea',
				'rows' => 5,
				'cols' => 70,
				'defaultvalue' => '
all = http://domain.tld/image-general.jpg
0x0 = http://domain.tld/image-queued.jpg
0x12 = http://domain.tld/image-bad-host.jpg
',
			),
			'sep6' => array(
				'layout' => 'separator',
			),
			'debug' => array(
				'label' => $this->T_('Enable debug mode'),
				'type' => 'checkbox',
				'defaultvalue' => false,
				'note' => $this->T_('Display debug information during thumbshots processing. Warning: this will break your website layout!'),
			),
			'debug_ip' => array(
				'label' => $this->T_('Debug for selected IP'),
				'size' => 20,
				'note' => '[255.255.255.255]<br />'.$this->T_('Display debug information for this IP address only. Warning: this will break your website layout!'),
				'defaultvalue' => '',
			),
		);

		return $r;
	}


	function BeforeInstall()
	{
		if( ! function_exists('gd_info') )
		{
			$this->msg( $this->T_('You will not be able to automatically generate thumbnails for images. Enable the gd2 extension in your php.ini file or ask your hosting provider about it.'), 'error' );
			return false;
		}

		// Create cache directory
		snr_mkdir_r( $this->thumbnails_path );

		if( is_writable($this->thumbnails_path) )
		{	// Hide directory listing
			@touch( $this->thumbnails_path.'index.html' );
		}
		else
		{
			$this->msg( sprintf( $this->T_('You must create the following directory with write permissions (777):%s'), '<br />'.$this->thumbnails_path ), 'error' );
			return false;
		}
		return true;
	}


	function get_thumbshot( $params )
	{
		if( is_string($params) )
		{
			$params = array('url' => $params);
		}

		// Set defaults
		$params = array_merge( array(
				'url'		=> '',
				'width'		=> false,
				'height'	=> false,
				'display'	=> false,
				'exit_page' => '',
				'noindex'	=> NULL,
				'nofollow'	=> NULL,
			), $params );

		// Get thumbshot image
		$r = $this->get_image( $params['url'], $params['width'], $params['height'], $params['exit_page'], $params['noindex'], $params['nofollow'] );

		if( $params['display'] ) echo $r;

		return $r;
	}


	function init_thumbshot_class()
	{
		if( defined('THUMBSHOT_INIT') ) return;

		define('THUMBSHOT_INIT', true);

		require_once dirname(__FILE__).'/'.$this->thumbshots_class;

		$Thumbshot = new Thumbshot();

		if( $this->get_option('access_key') )
		{	// The class may use it's own preset key
			$Thumbshot->access_key = $this->get_option('access_key');
		}

		$Thumbshot->quality = $this->get_option('quality');
		$Thumbshot->create_link = $this->get_option('link');
		$Thumbshot->link_noindex = $this->get_option('link_noindex');
		$Thumbshot->link_nofollow = $this->get_option('link_nofollow');

		$Thumbshot->original_image_w = $this->get_option('original_image_w');
		$Thumbshot->original_image_h = $this->get_option('original_image_h');
		//$Thumbshot->original_image_q = $this->get_option('original_image_q');

		$Thumbshot->cache_days = $this->get_option('cache_days');
		$Thumbshot->err_cache_days = $this->get_option('err_cache_days');
		$Thumbshot->queued_cache_days = $this->get_option('queued_cache_days');

		// Use custom service images
		$Thumbshot->service_images = $this->get_service_images();

		if( $this->display_preview == '#' )
		{	// Global override setting
			$Thumbshot->preview_width = $this->get_option('preview_width');
			$Thumbshot->preview_height = $this->get_option('preview_height');
			$Thumbshot->display_preview = $this->get_option('display_preview');
		}

		if( $this->is_reload_allowed() )
		{	// Display a link to reload/refresh cached thumbshot image
			$Thumbshot->display_reload_link = true;
			$Thumbshot->reload_link_url = $this->get_url('reload');
		}

		$Thumbshot->debug = ( $this->debug || $this->get_option('debug') );
		$Thumbshot->debug_IP = ( $this->debug_IP ? $this->debug_IP : $this->get_option('debug_ip') );

		$Thumbshot->image_class = 'thumbshots_plugin';
		$Thumbshot->thumbnails_url = $this->thumbnails_url;
		$Thumbshot->thumbnails_path = $this->thumbnails_path;

		//set_param( 'Thumbshot', $Thumbshot );
		$GLOBALS['Thumbshot'] = $Thumbshot;
	}


	function get_image( $url, $w = false, $h = false, $exit_page = '', $noindex = NULL, $nofollow = NULL )
	{
		global $Thumbshot;

		if( empty($url) )
		{
			return;
		}

		if( ! function_exists('gd_info') )
		{	// GD is not installed
			return;
		}

		if( empty($Thumbshot) )
		{	// Initialize Thumbshot class and set defaults
			$this->init_thumbshot_class();
		}

		if( strstr( $url, '|http' ) )
		{
			$tmpurl = @explode( '|http', $url );
			$url = $tmpurl[0];
		}

		if( preg_match( '~[^(\x00-\x7F)]~', $url ) && function_exists('idna_encode') )
		{	// Non ASCII URL, let's convert it to IDN:
			$idna_url = idna_encode($url);
		}

		$Thumbshot->url = $url;
		$Thumbshot->link_url = isset($tmpurl[1]) ? 'http'.$tmpurl[1] : '';
		$Thumbshot->idna_url = isset($idna_url) ? $idna_url : '';

		$Thumbshot->width = ($w === false) ? $this->get_option('width') : $w;
		$Thumbshot->height = ($h === false) ? $this->get_option('height') : $h;

		$Thumbshot->display_preview = ($this->display_preview != '#') ? $this->display_preview : $this->get_option('display_preview');

		if( $exit_page == '' )
		{
			$exit_page = $this->get_option('link_to_exit_page');
		}

		if( $exit_page == 1 )
		{	// Link thumbshot to an exit "goodbye" page
			$Thumbshot->link_to_exit_page = true;
			$Thumbshot->exit_page_url = $this->get_url('exit');
		}
		else
		{
			$Thumbshot->link_to_exit_page = false;
			$Thumbshot->exit_page_url = '';
		}

		if( is_null($noindex) )
		{
			$noindex = $this->get_option('link_noindex');
		}
		$Thumbshot->link_noindex = $noindex;

		if( is_null($nofollow) )
		{
			$nofollow = $this->get_option('link_nofollow');
		}
		$Thumbshot->link_nofollow = $nofollow;

		// Get the thumbshot
		return $Thumbshot->get();
	}


	function parse_shortcode( $p, $url )
	{
		$p = shortcode_atts( array('w'=>false, 'h'=>false, 'e'=>'', 'nofollow'=>NULL, 'noindex'=>NULL), $p );
		return $this->get_image( $url, $p['w'], $p['h'], $p['e'], $p['noindex'], $p['nofollow'] );
	}


	function get_service_images()
	{
		if( is_null($this->_service_images) )
		{
			$this->_service_images = array();
			if( $this->get_option('service_images_enabled') && $this->get_option('service_images') )
			{
				$service_images = array();
				$ims = $this->get_option('service_images');
				$ims = explode( "\n", trim($ims) );

				foreach( $ims as $img )
				{
					list($k,$v) = explode( '=', $img );

					$k = trim($k);
					$v = trim($v);

					if( preg_match( '~^((.+x\d+)|all)$~', $k ) && preg_match( '~^https?://.{3}~', $v ) )
					{	// It looks like a valid image definition
						$service_images[$k] = $v;
					}
				}
				$this->_service_images = $service_images;
			}
		}

		return $this->_service_images;
	}


	function init()
	{
		// Display an exit page if requested, then exit
		$this->display_exit_page();

		$plugin_url = plugins_url( '/', __FILE__ );
		wp_enqueue_style( $this->code, $plugin_url.'thumbshots.css' );

		if( $this->is_reload_allowed() )
		{	// Add jQuery for reload links
			wp_enqueue_script('jquery');
		}

		if( $this->display_preview && $this->get_option('display_preview') )
		{	// Add internal preview javascript
			$this->_head_scripts[] = 'ThumbshotPreview("ThumbshotPreview");';
			wp_enqueue_script( $this->code, $plugin_url.'thumbshots.js', array('jquery') );
		}
		if( $this->get_option('thumb_popups') )
		{	// Add external javascript
			$this->_head_scripts[] = 'ThumbshotExt("ThumbshotExt");';
			wp_enqueue_script( $this->code, $plugin_url.'thumbshots.js', array('jquery') );
		}
	}


	function wp_head()
	{
		if( $this->_head_scripts )
		{
			echo '
<script type="text/javascript">
//<![CDATA[
	jQuery(function() { '.implode( ' ', $this->_head_scripts ).' });
//]]>
</script>';
		}
	}


	function dbx_post_sidebar( $content )
	{
		echo '<div class="postbox" id="thumbshots_plugin_button" style="display:none"><div class="handlediv" title="Click to toggle"><br></div>
				<h3 class="hndle"><span>'.$this->T_( $this->name ).'</span></h3>
				<div class="inside" style="padding: 5px 0 3px 10px">
					<span><a href="#" class="button thumbshots-plugin-button">'.$this->T_('Add thumbshot').'</a></span>
				</div>
			</div>';

		echo '<script type="text/javascript">
			//<![CDATA[
			jQuery(function() {
				jQuery("#thumbshots_plugin_button").insertAfter( jQuery("#submitdiv") );
				jQuery("#thumbshots_plugin_button").show();

				jQuery(".thumbshots-plugin-button").click(function(event) {
					event.preventDefault();

					var t_url = prompt( "'.$this->T_('Site URL').'", "http://" );

					if( t_url == null || t_url.length < 8 ) return;

					var t_width = prompt( "'.$this->T_('Thumbshot width').'", "'.$this->get_option('width').'" );
					var t_height = prompt( "'.$this->T_('Thumbshot height').'", "'.$this->get_option('height').'" );
					var t_url2 = prompt( "'.$this->T_('Link thumbshot to URL (optional)').'", "http://" );
					var t_ext = confirm( "'.$this->T_('Display an exit page if thumbshot is linked to URL').'?" );
					var t_noindex = confirm( "'.$this->T_('Add rel=\"noindex\" to thumbshot link').'?" );
					var t_nofollow = confirm( "'.$this->T_('Add rel=\"nofollow\" to thumbshot link').'?" );

					if( t_url2 !== null && t_url2.length > 7) t_url = t_url + "|" + t_url2;

					if(t_ext) t_ext = 1;
					else t_ext = 0;

					if(t_noindex) t_noindex = 1;
					else t_noindex = 0;

					if(t_nofollow) t_nofollow = 1;
					else t_nofollow = 0;

					var code = "[thumb";
					if(t_width) {
						t_width = t_width.replace(/[^0-9]*/g, "");
						if( t_width !="" ) code += " w=\"" + t_width + "\"";
					}
					if(t_height) {
						t_height = t_height.replace(/[^0-9]*/g, "");
						if( t_height !="" ) code += " h=\"" + t_height + "\"";
					}
					code += " e=\"" + t_ext + "\"";
					code += " noindex=\"" + t_noindex + "\"";
					code += " nofollow=\"" + t_nofollow + "\"";
					code += "]" + t_url + "[/thumb]";

					tinyMCE.execCommand("mceInsertContent",false,("<br />"+code+"<br />"));
					jQuery("#content").val( jQuery("#content").val() + ("\n" + code + "\n") );
				});

			});
			//]]>
		</script>';
	}


	function get_url( $type = 'reload' )
	{
		switch( $type )
		{
			case 'reload':
				return admin_url('/admin-ajax.php').'?thumb-reload/#md5#/#url#&amp;action=thumb_reload';
				break;

			case 'clear':
				return admin_url('/admin-ajax.php').'?action=clear_thumb_cache&amp;clear_thumb_cache=';
				break;

			case 'exit':
				return site_url('/').'?thumb-exit/#md5#/#url#&amp;action=thumb_exit&amp;redirect_to='.rawurlencode(snr_get_request('uri')).'&amp;lang='.get_bloginfo('language');
				break;
		}
		return false;
	}


	function is_reload_allowed()
	{
		if( $this->get_option('allow_reloads') && is_super_admin() )
		{
			return true;
		}
		return false;
	}


	function wp_ajax_clear_thumb_cache()
	{
		if( empty($_GET['clear_thumb_cache']) || !is_super_admin() ) return;

		// Let's clear thumbnails cache
		switch( $_GET['clear_thumb_cache'] )
		{
			case 'files':
				snr_cleardir_r( $this->thumbnails_path, true );
				$this->msg( sprintf( $this->T_('Thumbnails cache has been cleared (%s)'), $this->T_('files') ), 'success' );
				break;

			case 'everything':
				snr_cleardir_r( $this->thumbnails_path, false );
				$this->msg( sprintf( $this->T_('Thumbnails cache has been cleared (%s)'), $this->T_('files and folders') ), 'success' );
				break;
		}

		$this->BeforeInstall();
	}


	function wp_ajax_thumb_reload()
	{
		global $Thumbshot;

		if( ! $this->is_reload_allowed() ) return;

		if( preg_match( '~^\?thumb-reload/([a-z0-9]{32})/(aHR0c.*?)&~i', str_replace( admin_url('/admin-ajax.php'), '', snr_get_request('url') ), $matches ) )
		{
			if( empty($Thumbshot) )
			{	// Initialize Thumbshot class and set defaults
				$this->init_thumbshot_class();
			}

			// Stage 1: request thumbshot reload
			$Thumbshot->args['refresh'] = 1;

			$url = @base64_decode($matches[2]);
			$md5 = md5($url.'+'.$Thumbshot->dispatcher);

			if( $md5 != $matches[1] )
			{
				echo 'Bad URL'; die;
			}

			$r = $Thumbshot->get_data( $Thumbshot->get_request_url($url) );

			// Stage 2: invalidate local cache
			if( $Thumbshot->cache_days > 1 )
			{
				$dir = $this->thumbnails_path.substr( $md5, 0, 3 ).'/';

				if( is_dir($dir) )
				{
					$scan = glob(rtrim($dir,'/').'/*');
					foreach( $scan as $index=>$path )
					{
						if( is_file($path) && strstr( $path, $dir.$md5 ) )
						{	// Change modification time so cache expires in ~ 1h 20m
							@touch( $path, time() - 3600 * 24 * ($Thumbshot->cache_days - 0.05) );
						}
					}
				}
			}
			exit();
		}
	}


	function display_exit_page()
	{
		global $Thumbshot;

		if( preg_match( '~^\?thumb-exit/([a-z0-9]{32})/(aHR0c.*?)&~i', str_replace( site_url('/'), '', snr_get_request('url') ), $matches ) )
		{
			if( empty($Thumbshot) )
			{	// Initialize Thumbshot class and set defaults
				$this->init_thumbshot_class();
			}

			$url = @base64_decode($matches[2]);
			$md5 = md5($url.'+'.$Thumbshot->dispatcher);

			if( $md5 != $matches[1] )
			{
				echo 'Bad URL'; die;
			}

			if( ($cookie = @$_COOKIE['thumb_skip_exit_page']) && $cookie = 1 )
			{	// We found a cookie, let's redirect without asking
				header('Location: '.$url);
				exit;
			}

			$exit_template = 'exit_page';
			if( !empty($_GET['lang']) && is_scalar($_GET['lang']) )
			{
				$lang = strtolower( substr( trim($_GET['lang']), 0, 2 ) );
				if( file_exists(dirname(__FILE__).'/inc/'.$exit_template.'-'.$lang.'.tpl') )
				{
					$exit_template .= '-'.$lang;
				}
			}

			if( $content = @file_get_contents( dirname(__FILE__).'/inc/'.$exit_template.'.tpl' ) )
			{
				$redirect_to = '/';
				if( !empty($_GET['redirect_to']) && is_scalar($_GET['redirect_to']) )
				{
					// Sanitize
					$redirect_to = preg_replace( '~\r|\n~', '', trim(strip_tags($_GET['redirect_to'])) );

					// Don't allow absolute URLs
					if( preg_match( '~^https?://~i', $redirect_to ) ) $redirect_to = '/';
				}

				echo str_replace( array('{LEAVING_HOST}', '{LEAVING_URL}', '{TARGET_HOST}', '{TARGET_URL}'),
								  array(snr_get_hostname(snr_get_request('host')), $redirect_to, snr_get_hostname($url), $url),
								  $content );
			}
			else
			{
				echo $this->T_('Template file not found');
			}
			exit();
		}
	}
}

$ThumbshotsPlugin = new thumbshots_plugin();

?>