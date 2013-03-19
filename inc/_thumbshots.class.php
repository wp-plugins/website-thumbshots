<?php
/**
 * Thumbshots PHP class for {@link http://Thumbshots.RU}
 *
 * Author: Sonorth Corp. - {@link http://www.sonorth.com/}
 * License: GPL version 3 or any later version
 * License info: {@link http://www.gnu.org/licenses/gpl.txt}
 *
 * API specification and examples: {@link http://thumbshots.ru/api}
 *
 * Version: 1.7.7
 * Date: 24-Feb-2013
 *
 */
if( !defined('THUMBSHOT_INIT') ) die( 'Please, do not access this page directly.' );


class Thumbshot
{
	var $debug = 0;					// Set 1 to display debug information
	var $debug_IP = 'Your IP here';	// Enable debug for selected IP only

	// Personal access key
	// Register on http://my.thumbshots.ru to get your own key
	var $access_key = '';

	// Thumbshot url address
	var $url;

	var $idna_url = '';				// (str) encoded IDN URL (internationalized domain name)
	var $link_url = '';				// (str) alternative url for image link
	var $create_link = true;		// (bool) display clickable images
	var $link_noindex = false;		// (bool) add rel="noindex" attribute to image links
	var $link_nofollow = false;		// (bool) add rel="nofollow" attribute to image links

	// Return image resource, otherwise complete <img> tag will be returned
	var $return_binary_image = false;

	// Display a link to reload/refresh cached thumbshot image
	var $display_reload_link = false;
	var $reload_link_url = '';

	// Link thumbshots to an exit "goodbye" page
	var $link_to_exit_page = false;
	var $exit_page_url = '';

	// Default image settings
	var $width = 120;
	var $height = 90;
	var $quality = 95;

	// Original image requested form server
	var $original_image_w = 640;	// width
	var $original_image_h = 480;	// height
	var $original_image_size = 'L';	// XS, S, M, L (free) and XL, XXL, XXXL, XXXXL (paid)
	var $original_image_q = 95;		// JPEG image quality (1-100)

	// Display image header preview on mouse hover
	var $display_preview = true;
	var $preview_width = 640;
	var $preview_height = 200;

	// Cache control
	var $thumbnails_path;		// Path to the cache directory, with trailing slash
	var $thumbnails_url;		// Absolute URL to the cache directory, with trailing slash
	var $cache_days = 7;		// Regular images
	var $err_cache_days = 2;	// Service and error images
	var $queued_cache_days = 0;	// Images displayed on queue
	var $chmod_files = 0644;	// chmod created files
	var $chmod_dirs = 0755;		// chmod created directories

	// CSS class of displayed image
	var $image_class = 'thumbshots_plugin';

	// Associative array of custom service images
	// key - (string) error code. For complete list of error codes see http://www.thumbshots.ru/error-codes
	// value - (string) absolute or relative URL to JPEG image
	var $service_images = array(
				// 'all'	=> 'http://domain.tld/image-general.jpg',	// Global override. Any kind of request other than "success"
				// '0x0'	=> 'http://domain.tld/image-queued.jpg',	// Thumbshot queued
				// '0x12'	=> 'http://domain.tld/image-bad-host.jpg',	// Invalid remote host
			);

	// Add custom params to thumbshot request, they will be added to request URL
	// http://www.thumbshots.ru/api
	var $args = array( 'type' => 'json' );

	var $dispatcher = 'http://get.thumbshots.ru/?';


	// Internal
	protected $_name = 'Thumbshots PHP';
	protected $_version = '1.7.6';
	protected $_thumbnails_path_status = false;
	protected $_error_detected = false;
	protected $_error_code = false;
	protected $_custom_service_image = false;
	protected $_md5 = '';
	protected $_uppercase_url_params = false;


	// ################################################################3

	// Returns thumbshot
	function get( $force = false )
	{
		$this->debug_disp('&lt;&lt;== Getting the thumbshot ==&gt;&gt;');

		if( $this->width < 1 || !is_numeric($this->width) )
		{	// Debug
			$this->debug_disp( 'Invalid width: "'.$this->width.'"' );
			return;
		}

		if( empty($this->url) )
		{
			$this->debug_disp( 'Empty URL: "'.$this->url.'"' );
		}

		if( !preg_match( '~^https?://~i', $this->url ) )
		{
			$this->url = 'http://'.$this->url;
		}

		if( !$this->validate_url($this->url) )
		{	// Debug
			$this->debug_disp( 'Invalid URL', $this->url );
			return;
		}

		if( !$this->access_key )
		{	// Do not cache if there's no key
			$force = true;
		}

		$this->url = trim($this->url);

		if( $this->url )
		{
			if( !$this->check_dir() ) return;

			$this->_md5 = md5($this->url.'+'.$this->dispatcher);
			$image_src = $this->get_thumbnail_url().'-'.$this->width.'_'.$this->height.'.jpg';

			if( $image_path = $this->get_resized_thumbnail( $force ) )
			{	// Got an image, let's display it

				if( $this->return_binary_image )
				{	// We want to display an image and exit immediately
					if( !headers_sent() && is_readable($image_path) )
					{
						header('Content-Type: image/jpeg');
						header('Content-Length: '.filesize($image_path) );

						readfile($image_path);
					}
					else
					{
						echo $image_src;
					}
					exit;
				}

				if( $mtime = @filemtime($image_path) )
				{	// Add mtime param
					$image_src .= '?mtime='.$mtime;
				}

				$parsed = @parse_url($this->url);

				$title = $this->html_attr($parsed['host']);
				$alt = $title;

				// Image header preview
				if( !$this->access_key ) $this->display_preview = false;

				if( $this->display_preview )
				{
					$this->debug_disp('<br />==&gt;&gt; Image header preview');

					$this->width = $this->preview_width;
					$this->height = $this->preview_height;
					$header_image_src = $this->get_thumbnail_url().'-'.$this->width.'_'.$this->height.'.jpg';

					if( $header_image_path = $this->get_resized_thumbnail() )
					{
						if( $mtime = @filemtime($header_image_path) )
						{
							$header_image_src .= '?mtime='.$mtime;
						}
						$alt = $header_image_src;
					}

					$this->debug_disp('&lt;&lt;== Image header done<br /><br />');
				}

				// <img> tag
				$output = '<img class="'.$this->image_class.'" src="'.$image_src.'" title="'.$title.'" alt="'.$alt.'" />';

				$this->debug_disp('&lt;&lt;== Script finished (successful) ==&gt;&gt;');

				if( $this->create_link )
				{
					if( ! $this->link_url )
					{	// Set alternative link URL
						$this->link_url = $this->url;
					}

					if( $this->link_to_exit_page && $this->exit_page_url )
					{
						$this->link_url = str_replace( array('#md5#', '#url#'), array(md5($this->link_url.'+'.$this->dispatcher), base64_encode($this->link_url)), $this->exit_page_url );

					}

					$this->debug_disp('Alternative URL', $this->link_url);

					$rel = '';
					if( $this->link_noindex || $this->link_nofollow )
					{	// Add NOINDEX and/or NOFOLLOW attributes
						$attr = array();
						if( $this->link_noindex ) $attr[] = 'noindex';
						if( $this->link_nofollow ) $attr[] = 'nofollow';
						$rel = ' rel="'.implode( ' ', $attr ).'"';
					}

					$output = '<a href="'.$this->link_url.'"'.$rel.' target="_blank">'.$output.'</a>';
				}

				if( $this->display_reload_link )
				{
					if( $this->reload_link_url )
					{
						$request_url = str_replace( array('#md5#', '#url#'), array($this->_md5, base64_encode($this->url)), $this->reload_link_url );
					}
					else
					{
						$this->args['refresh'] = 1;
						$request_url = $this->get_request_url( $this->url );
					}

					$reload_link = '<a class="thumb-reload" rel="nofollow" target="_blank" href="'.$request_url.'" onclick="Javascript:jQuery.get(this.href); jQuery(this).hide(); return false;"><img src="data:image/png;base64,
iVBORw0KGgoAAAANSUhEUgAAABkAAAAZCAIAAABLixI0AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5
ccllPAAAAyBpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1w
Q2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9
IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYwIDYxLjEzNDc3NywgMjAxMC8wMi8xMi0xNzozMjowMCAgICAgICAgIj4g
PHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4g
PHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8x
LjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0
dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRv
YmUgUGhvdG9zaG9wIENTNSBXaW5kb3dzIiB4bXBNTTpJbnN0YW5jZUlEPSJ4bXAuaWlkOjlBQTkwRDczRTQ2RTEx
REZBMkQzOUM1NURGRDA5REM4IiB4bXBNTTpEb2N1bWVudElEPSJ4bXAuZGlkOjlBQTkwRDc0RTQ2RTExREZBMkQz
OUM1NURGRDA5REM4Ij4gPHhtcE1NOkRlcml2ZWRGcm9tIHN0UmVmOmluc3RhbmNlSUQ9InhtcC5paWQ6OUFBOTBE
NzFFNDZFMTFERkEyRDM5QzU1REZEMDlEQzgiIHN0UmVmOmRvY3VtZW50SUQ9InhtcC5kaWQ6OUFBOTBENzJFNDZF
MTFERkEyRDM5QzU1REZEMDlEQzgiLz4gPC9yZGY6RGVzY3JpcHRpb24+IDwvcmRmOlJERj4gPC94OnhtcG1ldGE+
IDw/eHBhY2tldCBlbmQ9InIiPz5k8xNVAAACVElEQVR42mL8//8/A5UA4wgwi+E/IfD7x/f7+1fvq/R4d/cyfpUs
eKz59fXzg/2r3l/bImOgxsvHyMTKSaYfHx/b+mRvs7IRm5i8CAObwotbrI+vveSS11X3z2LhwG4oEy5LOAREmZl+
iykyM/x6yfD4lITQfVMbYUm212f6U7+/f01y2H98fOf6omRjhz+sv/7ePv1USoCDm1/5xz/l84++mpfMZWJmJmDW
x0c3f3x4w8jMLKiow8rF8+nJ3avzorQMft966sTE9E/20xoxYZWHj38zu1fLWHgQ8OP5nrBv1+be29j28fEtIJdP
Rlk7aem5k8JSRs7G6X33WV1/fH4iy/HpxeGVhMOL4/tTRU0ufkkZTkFxiAifjIpD805JY1cgWz2g9OGb30ws35ge
nyFs1n8OIYYXz9j/fvj6+ikiIJiYmFhAqYdHUuEnEx8DBwvLz3eEzWJRtP375JG0FM/93XPBwX/rRG8mMAQhsr+/
fvr+4d2Hdz++/WYkHI/Pzx34sSpC0cb60b1vH4TsucUV/tzd+ublF8Ps6VwiEr+/fXl4aO3/v3/YBUTlrP0ImPX/
37+TbSFGvFfZZHSev/zz4NE7I/+wf6zs53cd0k/t4haVIi3df3397FJ/uJ7AU24JGQY+eYbfvxl+PPklYXXuzGO1
sCohZV3S8hAwgK4tbWa5t1ma97cwPzsDw7/f/1mvvRNk1g3TCS8ip8z58Oj27W4PLVmGu49//1Jwk/fKEFE3AsYp
LvX4yglOQdGPf3juCHnJhcUJKmiOltGD2CyAAAMA9pVY15kNU24AAAAASUVORK5CYII=" alt="" title="Reload this thumbshot" /></a>';

					$output = $reload_link.$output;
				}
				return '<span class="thumbshots_plugin">'.$output.'</span>';
			}
			else
			{
				$this->debug_disp('Failed to get resized image');
			}
		}
		$this->debug_disp('&lt;&lt;== Script finished (failed) ==&gt;&gt;');

		return NULL;
	}


	function get_remote_thumbnail()
	{
		if( function_exists( 'set_time_limit' ) )
		{
			set_time_limit(30); // 30 sec
		}
		ini_set( 'max_execution_time', '30' );
		ini_set( 'max_input_time', '30' );

		$request_url = $this->get_request_url( $this->url );

		// Debug
		$this->debug_disp( 'Requesting new image from server<br />Request URL:', $request_url );

		// Get server response
		if( !$data = $this->get_data( $request_url ) )
		{	// Debug
			$this->debug_disp( 'Unable to get data from server' );
			return false;
		}

		// Debug
		$this->debug_disp( 'Received data', htmlentities($data) );

		if( !$Thumb = $this->json_to_array($data) )
		{	// Debug
			$this->debug_disp( 'Unable to get Thumb array from JSON data' );
			return false;
		}

		if( empty($Thumb['url']) )
		{	// Debug
			$this->debug_disp( 'Malformed Thumb array provided' );
			return false;
		}

		// Debug
		$this->debug_disp( 'Thumb array', $Thumb );

		$imageurl = $Thumb['url'];

		if( $Thumb['status'] != 'success' )
		{	// Return error image
			$this->_error_detected = true;
			$this->_error_code = (string) $Thumb['statuscode'];

			if( $this->check_debug() )
			{	// Debug
				$cache_days = $this->get_cache_days();
				if( $this->_error_code != '0x0' )
				{
					$this->debug_disp( 'An error occured processing your request', $this->_error_code.' -> '.urldecode($Thumb['statusdescription']) );
				}
				$this->debug_disp( 'This is an error image (code '.$this->_error_code.').<br />It\'s cached for '.$cache_days.' days.' );
			}

			if( !empty($this->service_images['all']) )
			{	// Get custom service image URL (global override)
				$this->debug_disp( 'Trying to get custom remote image [all]', $this->service_images['all'] );
				$imageurl = $this->service_images['all'];
				$this->_custom_service_image = true;
			}
			elseif( !empty($this->service_images[$this->_error_code]) )
			{	// Get custom service image URL for specified error code
				$this->debug_disp( 'Trying to get custom remote image ['.$this->_error_code.']', $this->service_images[$this->_error_code] );
				$imageurl = $this->service_images[$this->_error_code];
				$this->_custom_service_image = true;
			}
		}

		if( empty($imageurl) || (!$this->_custom_service_image && !preg_match( '~^https?://.{5}~i', $imageurl )) )
		{
			$this->debug_disp( 'Invalid image URL: "'.$imageurl.'"' );
			return false;
		}
		return $imageurl;
    }


	function get_thumbnail( $force = false )
	{
		$cutoff = time() - 3600 * 24 * $this->cache_days;
        $file = $this->get_thumbnail_path().'.jpg';

		if( (file_exists($file) && !$this->is_image($file)) ||
			($this->original_image_w < $this->width) ||
			($this->original_image_h > 0 && $this->original_image_h < $this->height) )
		{
			@unlink( $file );
		}

		if( $f_time = @filemtime($file) )
		{
			$d  = 'Image time: '.$f_time.'<br />';
			$d .= 'CutOff time: '.$cutoff.'<br />';
			$d .= 'Image time - CutOff time = '.($f_time-$cutoff).'<br /><br />';
			$d .= 'Cache expires in '.number_format(($f_time-$cutoff)/3600, 2).' hours OR '.
					number_format(($f_time-$cutoff)/24/3600, 2).' days<br />';
			$this->debug_disp( 'Image Cache info (original)', $d );
		}

		if( $force || !file_exists($file) || @filemtime($file) <= $cutoff )
        {
			if( $this->check_debug() )
			{	// Debug
				if( !file_exists($file) )
				{
					$this->debug_disp('Original image not found. Retriving new one');
				}
				elseif( ($filetime = @filemtime($file)) <= $cutoff )
				{
					$this->debug_disp( 'Image cache expired (original)', $filetime.' <= '.$cutoff );
				}
				else
				{
					$this->debug_disp('Image refresh forced (original)');
				}
			}

			// Requesting remote thumbnail
			if( $jpgurl = $this->get_remote_thumbnail() )
            {
				if( !$data = $this->get_data($jpgurl) )
				{	// Debug
					$this->debug_disp( 'Unable to retrive remote image', $jpgurl );
					return false;
				}

				$tmpfilename = time().rand(1,1000).'.jpg';
				if( !$tmpfile = $this->save_to_file( $data, $tmpfilename ) )
				{	// Debug
					$this->debug_disp( 'Unable to save temp image', $tmpfilename );
					return false;
				}
				else
				{
					$this->debug_disp('Temp image retrieved from remote server and saved', $tmpfile);
				}

				if( $im = $this->load_image( $tmpfile, true ) )
				{	// Debug
					$this->debug_disp('Temp image loaded');

					// Create thumbnail subdirectory
					if( !$this->mkdir_r( $this->get_thumbnail_path( true ) ) )
					{	// Debug
						$this->debug_disp( 'Unable to create thumbnail subdir', $this->get_thumbnail_path( true ) );
					}

                	imagejpeg($im, $file, $this->original_image_q);
					imagedestroy($im);
				}
				else
				{
					$this->debug_disp( 'Unable to load temp image', $tmpfile );
					return false;
				}

				if( $this->_error_detected )
				{	// Cache error image
					@touch( $file, $cutoff + 3600 * 24 * $this->get_cache_days() );
				}
				@unlink( $tmpfile );
			}
			else
			{	// Debug
				$this->debug_disp( 'Couldn\'t get remote thumbnail' );
			}
			// Debug
			$this->debug_disp( 'Image URL is', $jpgurl );
		}
		else
		{
			// Debug
			$this->debug_disp( 'Original image found.' );
		}

		if( @file_exists($file) )
		{
			@chmod( $file, $this->chmod_files );
			return $file;
		}

        return false;
    }


	// Get scaled image
	function get_resized_thumbnail( $force = false )
	{
		$cutoff = time() - 3600 * 24 * $this->cache_days;
        $file = $this->get_thumbnail_path().'-'.$this->width.'_'.$this->height.'.jpg';
		$file_orig = $this->get_thumbnail_path().'.jpg';

		if( $this->check_debug() )
		{	// Debug
			$this->debug_disp( 'MD5', 'md5( '.$this->url.'+'.$this->dispatcher.' )' );
			$this->debug_disp( 'Original image SRC', $this->get_thumbnail_url().'.jpg' );

			$msg = 'Original image PATH';
			if( file_exists($file_orig) ) $msg .= ' (found)';
			$this->debug_disp( $msg, $file_orig );

			if( $f_time = @filemtime($file) )
			{
				$d  = 'Image time: '.$f_time.'<br />';
				$d .= 'CutOff time: '.$cutoff.'<br />';
				$d .= 'Image time - CutOff time = '.($f_time-$cutoff).'<br /><br />';
				$d .= 'Cache expires in '.number_format(($f_time-$cutoff)/3600, 2).' hours OR '.
						number_format(($f_time-$cutoff)/24/3600, 2).' days<br />';
				$this->debug_disp( 'Image Cache info (resized)', $d );
			}
		}

        if( $force || !file_exists($file) || @filemtime($file_orig) <= $cutoff )
		{
			ini_set( 'memory_limit', '400M' );

			if( $this->check_debug() )
			{	// Debug
				if( !file_exists($file) )
				{
					$this->debug_disp('No saved resized image<br />Trying to find the original one');
				}
				elseif( ($filetime = @filemtime($file)) <= $cutoff )
				{
					$this->debug_disp( 'Image cache expired (resized)', $filetime.' <= '.$cutoff );
				}
				else
				{
					$this->debug_disp('Image refresh forced (resized)');
				}
			}
			$img = $this->get_thumbnail( $force );

			if( !empty($img) )
			{
				if( $this->check_debug() )
				{	// Debug
					$d  = 'w: '.$this->original_image_w.' ~ '.$this->width.'<br />';
					$d .= 'h: '.$this->original_image_h.' ~ '.$this->height.'<br />';
					$d .= 'q: '.$this->original_image_q.' ~ '.$this->quality;
					$this->debug_disp('Image params (original vs requested)',$d);
				}

				if( $this->original_image_w == $this->width &&
					$this->original_image_h == $this->height &&
					$this->original_image_q == $this->quality )
				{	// Don't resample if params match
					$this->debug_disp('Skip original image resizing');
					if( @copy( $img, $file ) )
					{
						$this->debug_disp('Image copied', array('from'=>$img, 'to'=>$file));
					}
					else
					{
						$this->debug_disp('Unable to copy image', array('from'=>$img, 'to'=>$file));
						return false;
					}
				}
				elseif( $im = $this->load_image($img) )
				{	// Resize image

					$this->debug_disp('Start resizing original image');

					list( $xw, $xh ) = getimagesize($img);
					$ratio = $xw/$xh;
					$crop_h = $this->width/$ratio;
					$height = $this->height;

					// Full-length thumbs
					if( $height == 0 ) $height = $crop_h;

					// Create a white background image
					$scaled = imagecreatetruecolor( $this->width, $height );
					$image_bg = imagecolorallocate($im, 255, 255, 255);
					imagefill($scaled, 0, 0, $image_bg);

					if( imagecopyresampled( $scaled, $im, 0, 0, 0, 0, $this->width, $crop_h, $xw, $xh ) )
					{	// Debug
						$this->debug_disp('Image successfully scaled.');
						imagejpeg( $scaled, $file, $this->quality );
						imagedestroy($im);
					}
				}

				if( $this->_error_detected && file_exists($file) )
				{	// Cache error images
					@touch( $file, $cutoff + 3600 * 24 * $this->get_cache_days() );

					$this->_error_detected = false;
					$this->status_code = false;
				}
			}
		}
		else
		{	// Debug
			$this->debug_disp('Displaying cached image');
		}

		if( @file_exists($file) )
		{
			@chmod( $file, $this->chmod_files );
			return $file;
		}
        return false;
    }


	function is_image( $file )
	{
		if( function_exists( 'exif_imagetype' ) )
		{
			if( @exif_imagetype($file) ) return true;
		}
		elseif( function_exists( 'getimagesize' ) )
		{
			if( @getimagesize($file) ) return true;
		}
		return false;
	}


	function get_thumbnail_url( $dir_only = false )
	{
		$r = '';
		if( $this->thumbnails_url )
		{
			$r = $this->thumbnails_url.substr( $this->_md5, 0, 3 ).'/';

			if( !$dir_only )
			{
				$r .= $this->_md5;
			}
		}
		return $r;
	}


	function get_thumbnail_path( $dir_only = false )
	{
		$r = '';
		if( $this->thumbnails_path )
		{
			$r = $this->thumbnails_path.substr( $this->_md5, 0, 3 ).'/';

			if( !$dir_only )
			{
				$r .= $this->_md5;
			}
		}
		return $r;
	}


	function get_cache_days()
	{
		$cache_days = $this->err_cache_days;
		if( $this->_error_code == '0x0' )
		{	// Image queued
			$cache_days = $this->queued_cache_days;
		}
		elseif( in_array($this->_error_code, array('0x62','0x63','0x64','0x68') ) )
		{	// Fatal errors that should never get in cache
			$cache_days = 0;
		}
		return $cache_days;
	}


	function debug_disp( $title = NULL, $var = NULL )
	{
		if( !$this->check_debug() ) return;

		$r = '<pre style="clear:both; float:none; margin:10px 5px 5px 5px; padding:5px; border:1px solid #333; text-align:left; max-width:400px; color:red; font-size:11px; line-height:normal; font-family: Arial, Helvetica, sans-serif">';
		$r .= '<div style="color:green; font-size:12px; font-weight:bold">'.$title.'</div>';
		if( !empty($var) )
		{
			$r .= '<div style="overflow:auto">';
			$r .= var_export($var, true);
			$r .= '</div>';
		}
		$r .= '</pre>';

		echo $r;
	}


	function check_debug()
	{
		if( !$this->debug )
		{	// Debug disabled
			if( @$_SERVER['REMOTE_ADDR'] === $this->debug_IP )
			{	// IP matches
				return true;
			}
			return false;
		}
		return true;
	}


	/**
	 * Check the validity of a given URL
	 *
	 * @param string Url to validate
	 * @return true or false
	 */
	function validate_url( $url )
	{
		if( empty($url) ) return false;

		$allowed_uri_schemes = array(
				'http',
				'https',
			);

		// Validate URL structure
		if( preg_match( '~^\w+:~', $url ) )
		{ // there's a scheme and therefore an absolute URL:

			$this->debug_disp( 'Validating URL', $url );

			if( $this->idna_url )
			{	// Use IDN URL if exists
				$url = $this->idna_url;
				$this->debug_disp( 'IDNa URL supplied, using it instead', $url );
			}

			if( ! preg_match('~^                 # start
				([a-z][a-z0-9+.\-]*)             # scheme
				://                              # authorize absolute URLs only ( // not present in clsid: -- problem? ; mailto: handled above)
				(\w+(:\w+)?@)?                   # username or username and password (optional)
				( localhost |
						[\p{L}a-z0-9]([\p{L}a-z0-9\-])*     # Don t allow anything too funky like entities
						\.                               	# require at least 1 dot
						[\p{L}a-z0-9]([\p{L}a-z0-9.\-])+    # Don t allow anything too funky like entities
				)
				(:[0-9]+)?                       # optional port specification
				.*                               # allow anything in the path (including spaces, but no newlines).
				$~ixu', $url, $match) )
			{ // Cannot validate URL structure
				return false;
			}

			$scheme = strtolower($match[1]);
			if( ! in_array( $scheme, $allowed_uri_schemes ) )
			{ // Scheme not allowed
				return false;
			}
			return true;
		}
		return false;
	}


	// Read remote or local file
	function get_data( $filename )
	{
		// Set user agent
		@ini_set( 'user_agent', $this->_name.' v'.$this->_version.' (+http://www.thumbshots.ru)' );

		if( ! $content = @file_get_contents($filename) )
		{
			$content = $this->fetch_remote_page( $filename, $info );

			// Remove chunks if any
			$content = preg_replace( '~^[^{]*({.*?})[^}]*$~', '\\1', $content );

			if($info['status'] != '200') $content = '';

			$this->debug_disp( 'Server response', $info );
		}

		// Return content
		if( !empty($content) ) return $content;

		return false;
	}


	function save_to_file( $content, $filename, $mode = 'w' )
	{
		if( $f = @fopen( $this->thumbnails_path.$filename, $mode ) )
		{
			$r = @fwrite( $f, $content );
			@fclose($f);

			if( $r )
			{
				@chmod( $this->thumbnails_path.$filename, $this->chmod_files );
				return $this->thumbnails_path.$filename;
			}
		}
		return false;
	}


	function html_attr( $content = '' )
	{
		$content = strip_tags($content);
		$content = str_replace( array('"', "'"), array('&quot;', '&#039;'), $content );

		return $content;
	}


	function check_dir()
	{
		if( $this->_thumbnails_path_status == 'ok' ) return true;

		if( $this->_thumbnails_path_status == 'error' )
		{
			$this->debug_disp('Thumbshots directory does not exist or is not writable.');
			return false;
		}

		if( !is_dir($this->thumbnails_path) ) $this->mkdir_r( $this->thumbnails_path );

		if( !@is_writable($this->thumbnails_path) )
		{
			$this->debug_disp('Thumbshots directory does not exist or is not writable.');
			$this->_thumbnails_path_status = 'error';
			return false;
		}
		$this->_thumbnails_path_status = 'ok';

		// Create empty index.html file
		$file = $this->thumbnails_path.'index.html';

		if( !file_exists($file) )
		{
			//$data = 'deny from all';
			$data = ' ';
			$fh = @fopen($file,'a');
			@fwrite($fh, $data);
			@fclose($fh);
			if( !file_exists($file) )
			{
				$this->debug_disp('Unable to create <i>index.html</i> file!');
				return false;
			}
		}
		return true;
	}


	/**
	 * Get the last HTTP status code received by the HTTP/HTTPS wrapper of PHP.
	 *
	 * @param array The $http_response_header array (by reference).
	 * @return integer|boolean False if no HTTP status header could be found,
	 *                         the HTTP status code otherwise.
	 */
	function http_wrapper_last_status( & $headers )
	{
		for( $i = count( $headers ) - 1; $i >= 0; --$i )
		{
			if( preg_match( '|^HTTP/\d+\.\d+ (\d+)|', $headers[$i], $matches ) )
			{
				return $matches[1];
			}
		}

		return false;
	}


	/**
	 * Fetch remote page
	 *
	 * Attempt to retrieve a remote page using a HTTP GET request, first with
	 * cURL, then fsockopen, then fopen.
	 *
	 * @param string URL
	 * @param array Info (by reference)
	 *        'error': holds error message, if any
	 *        'status': HTTP status (e.g. 200 or 404)
	 *        'used_method': Used method ("curl", "fopen", "fsockopen" or null if no method
	 *                       is available)
	 * @param integer Timeout (default: 15 seconds)
	 * @return string|false The remote page as a string; false in case of error
	 */
	function fetch_remote_page( $url, & $info, $timeout = NULL )
	{
		$info = array(
			'error' => '',
			'status' => NULL,
			'mimetype' => NULL,
			'used_method' => NULL,
		);

		if( ! isset($timeout) )
			$timeout = 15;

		if( extension_loaded('curl') )
		{	// CURL:
			$info['used_method'] = 'curl';

			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $timeout );
			curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
			curl_setopt( $ch, CURLOPT_HEADER, true );
			@curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
			curl_setopt( $ch, CURLOPT_MAXREDIRS, 3 );
			$r = curl_exec( $ch );

			$info['mimetype'] = curl_getinfo( $ch, CURLINFO_CONTENT_TYPE );
			$info['status'] = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			$info['error'] = curl_error( $ch );
			if( ( $errno = curl_errno( $ch ) ) )
			{
				$info['error'] .= ' (#'.$errno.')';
			}
			curl_close( $ch );

			if ( ( $pos = strpos( $r, "\r\n\r\n" ) ) === false )
			{
				$info['error'] = 'Could not locate end of headers';
				return false;
			}

			// Remember headers to extract info at the end
			$headers = explode("\r\n", substr($r, 0, $pos));

			$r = substr( $r, $pos + 4 );
		}

		if( function_exists( 'fsockopen' ) ) // may have been disabled
		{	// FSOCKOPEN:
			$info['used_method'] = 'fsockopen';

			if ( ( $url_parsed = @parse_url( $url ) ) === false
				 || ! isset( $url_parsed['host'] ) )
			{
				$info['error'] = 'Could not parse URL';
				return false;
			}

			$host = $url_parsed['host'];
			$port = empty( $url_parsed['port'] ) ? 80 : $url_parsed['port'];
			$path = empty( $url_parsed['path'] ) ? '/' : $url_parsed['path'];
			if( ! empty( $url_parsed['query'] ) )
			{
				$path .= '?'.$url_parsed['query'];
			}

			$out = 'GET '.$path.' HTTP/1.0'."\r\n"; // Use HTTP/1.0 to prevent chunking
			$out .= 'Host: '.$host;
			if( ! empty( $url_parsed['port'] ) )
			{	// we don't want to add :80 if not specified. remote end may not resolve it. (e-g b2evo multiblog does not)
				$out .= ':'.$port;
			}
			$out .= "\r\n".'Connection: Close'."\r\n\r\n";

			$fp = @fsockopen( $host, $port, $errno, $errstr, $timeout );
			if( ! $fp )
			{
				$info['error'] = $errstr.' (#'.$errno.')';
				return false;
			}

			// Send request:
			fwrite( $fp, $out );

			// Set timeout for data:
			stream_set_timeout( $fp, $timeout );

			// Read response:
			$r = '';
			// First line:
			$s = fgets( $fp );
			if( ! preg_match( '~^HTTP/\d+\.\d+ (\d+)~', $s, $match ) )
			{
				$info['error'] = 'Invalid response.';
				fclose( $fp );
				return false;
			}

			while( ! feof( $fp ) )
			{
				$r .= fgets( $fp );
			}
			fclose($fp);

			if ( ( $pos = strpos( $r, "\r\n\r\n" ) ) === false )
			{
				$info['error'] = 'Could not locate end of headers';
				return false;
			}

			// Remember headers to extract info at the end
			$headers = explode("\r\n", substr($r, 0, $pos));

			$info['status'] = $match[1];
			$r = substr( $r, $pos + 4 );
		}
		elseif( ini_get( 'allow_url_fopen' ) )
		{	// URL FOPEN:
			$info['used_method'] = 'fopen';

			$fp = @fopen( $url, 'r' );
			if( ! $fp )
			{
				if( isset( $http_response_header )
					&& ( $code = $this->http_wrapper_last_status( $http_response_header ) ) !== false )
				{	// fopen() returned false because it got a bad HTTP code:
					$info['error'] = 'Invalid response';
					$info['status'] = $code;
					return '';
				}

				$info['error'] = 'fopen() failed';
				return false;
			}
			// Check just to be sure:
			else if ( ! isset( $http_response_header )
					  || ( $code = $this->http_wrapper_last_status( $http_response_header ) ) === false )
			{
				$info['error'] = 'Invalid response';
				return false;
			}
			else
			{
				// Used to get info at the end
				$headers = $http_response_header;

				// Retrieve contents
				$r = '';
				while( ! feof( $fp ) )
				{
					$r .= fgets( $fp );
				}

				$info['status'] = $code;
			}
			fclose( $fp );
		}

		// Extract info from headers
		if( isset($r) )
		{
			$headers = array_map( 'strtolower', $headers );
			foreach( $headers as $header )
			{
				if( preg_match( '~^x-thumb-(\w+):(.*?)$~i', $header, $matches ) )
				{	// Collect all "X-Thumb" headers
					$info['x-thumb'][$matches[1]] = $matches[2];
				}

				if( substr($header, 0, 13) == 'content-type:' )
				{
					$info['mimetype'] = trim(substr($header, 13));
				}
			}

			return $r;
		}

		// All failed:
		$info['error'] = 'No method available to access URL!';
		return false;
	}


	/**
	 * Add a trailing slash, if none present
	 *
	 * @param string the path/url
	 * @return string the path/url with trailing slash
	 */
	function trailing_slash( $path )
	{
		if( empty($path) || substr( $path, -1 ) == '/' )
		{
			return $path;
		}
		else
		{
			return $path.'/';
		}
	}


	/**
	 * Create a directory recursively.
	 *
	 * @param string directory name
	 * @param integer permissions
	 * @return boolean
	 */
	function mkdir_r( $dirName )
	{
		if( is_dir($dirName) )
		{ // already exists:
			return true;
		}

		if( version_compare(PHP_VERSION, 5, '>=') )
		{
			$r = @mkdir( $dirName, $this->chmod_dirs, true );
			@chmod( $dirName, $this->chmod_dirs );

			return $r;
		}

		$dirName = $this->trailing_slash($dirName);

		$parts = array_reverse( explode('/', $dirName) );
		$loop_dir = $dirName;
		$create_dirs = array();
		foreach($parts as $part)
		{
			if( ! strlen($part) )
			{
				continue;
			}
			// We want to create this dir:
			array_unshift($create_dirs, $loop_dir);
			$loop_dir = substr($loop_dir, 0, 0 - strlen($part)-1);

			if( is_dir($loop_dir) )
			{ // found existing dir:
				foreach($create_dirs as $loop_dir )
				{
					if( ! @mkdir( $loop_dir, $this->chmod_dirs ) )
					{
						return false;
					}
					@chmod( $dirName, $this->chmod_dirs );
				}
				return true;
			}
		}
		return true;
	}


	/**
	 * Load an image from a file into memory
	 *
	 * @param string pathname of image file
	 * @return array resource image handle or false
	 */
	function load_image( $path, $delete_bad_image = false )
	{
		@ini_set('memory_limit', '500M'); // artificially inflate memory if we can

		$image_info = @getimagesize($path);
		if( !empty($image_info['mime']) )
		{
			$mime_function = array(
					'image/jpeg' => 'imagecreatefromjpeg',
					'image/gif'  => 'imagecreatefromgif',
					'image/png'  => 'imagecreatefrompng',
				);

			if( isset($mime_function[$image_info['mime']]) )
			{
				$function = $mime_function[$image_info['mime']];

				if( $imh = $function($path) )
				{
					return $imh;
				}
				else
				{
					if( $delete_bad_image ) unlink($path);
				}
			}
		}
		return false;
	}


	function get_request_url( $url )
	{
		$this->args['url'] = urlencode($url);

		$args = array_merge( array(
				  'w'		=> $this->original_image_w,
				  'h'		=> $this->original_image_h,
				  'q'		=> $this->original_image_q,
				  'size'	=> $this->original_image_size,
				  'key'		=> $this->access_key,
			), $this->args );

		$arr = array();
		foreach( $args as $k => $v )
		{
			if( $this->_uppercase_url_params )
			{
				$arr[] = ucfirst($k).'='.$v;
			}
			else
			{
				$arr[] = $k.'='.$v;
			}
		}
		$query = implode( '&', $arr );

		// Debug
		$this->debug_disp( 'Request params:', $args );

		return $this->dispatcher.$query;
	}


	function json_to_array($json)
	{
		if( function_exists('json_decode') )
		{
			return json_decode( $json, true );
		}

		$comment = false;
		$out = '$x=';

		for( $i=0; $i<strlen($json); $i++ )
		{
			if( !$comment )
			{
				if( ($json[$i] == '{') || ($json[$i] == '[') )
				{
					$out .= ' array(';
				}
				elseif( ($json[$i] == '}') || ($json[$i] == ']') )
				{
					$out .= ')';
				}
				elseif( $json[$i] == ':' )
				{
					$out .= '=>';
				}
				else
				{
					$out .= stripslashes($json[$i]);
				}
			}
			else
			{
				$out .= stripslashes($json[$i]);
			}

			if( $json[$i] == '"' && $json[($i-1)] != "\\" )
			{
				$comment = !$comment;
			}
		}
		@eval($out.';');

		if( isset($x) ) return $x;

		return false;
	}
}

?>