<?php
/**
 * This file implements common functions for Website Thumbshots plugin
 *
 * Author: Sonorth Corp. - {@link http://www.sonorth.com/}
 * License: GPL version 3 or any later version
 * License info: {@link http://www.gnu.org/licenses/gpl.txt}
 *
 */


/**
 * Get $ReqPath, $ReqURI, $ReqHost
 *
 * @return array ($ReqPath,$ReqURI,$ReqHost);
 */
function snr_get_request( $mode = 'all' )
{
	if( isset($_SERVER['REQUEST_URI']) && !empty($_SERVER['REQUEST_URI']) )
	{ // Warning: on some IIS installs it is set but empty!
		$ReqURI = $_SERVER['REQUEST_URI'];

		// Build requested Path without query string:
		if( $pos = strpos( $ReqURI, '?' ) )
		{
			$ReqPath = substr( $ReqURI, 0, $pos );
		}
		else
		{
			$ReqPath = $ReqURI;
		}
	}
	elseif( isset($_SERVER['URL']) )
	{ // ISAPI
		$ReqPath = $_SERVER['URL'];
		$ReqURI = isset($_SERVER['QUERY_STRING']) && !empty( $_SERVER['QUERY_STRING'] ) ? ($ReqPath.'?'.$_SERVER['QUERY_STRING']) : $ReqPath;
	}
	elseif( isset($_SERVER['PATH_INFO']) )
	{ // CGI/FastCGI
		if( isset($_SERVER['SCRIPT_NAME']) )
		{
			if ($_SERVER['SCRIPT_NAME'] == $_SERVER['PATH_INFO'] )
			{	/* both the same so just use one of them
				 * this happens on a windoze 2003 box
				 */
				$ReqPath = $_SERVER['PATH_INFO'];
			}
			else
			{
				$ReqPath = $_SERVER['SCRIPT_NAME'].$_SERVER['PATH_INFO'];
			}
		}
		else
		{
			$ReqPath = $_SERVER['PATH_INFO'];
		}
		$ReqURI = isset($_SERVER['QUERY_STRING']) && !empty( $_SERVER['QUERY_STRING'] ) ? ($ReqPath.'?'.$_SERVER['QUERY_STRING']) : $ReqPath;
	}
	elseif( isset($_SERVER['ORIG_PATH_INFO']) )
	{ // Tomcat 5.5.x with Herbelin PHP servlet and PHP 5.1
		$ReqPath = $_SERVER['ORIG_PATH_INFO'];
		$ReqURI = isset($_SERVER['QUERY_STRING']) && !empty( $_SERVER['QUERY_STRING'] ) ? ($ReqPath.'?'.$_SERVER['QUERY_STRING']) : $ReqPath;
	}
	elseif( isset($_SERVER['SCRIPT_NAME']) )
	{ // Some Odd Win2k Stuff
		$ReqPath = $_SERVER['SCRIPT_NAME'];
		$ReqURI = isset($_SERVER['QUERY_STRING']) && !empty( $_SERVER['QUERY_STRING'] ) ? ($ReqPath.'?'.$_SERVER['QUERY_STRING']) : $ReqPath;
	}
	elseif( isset($_SERVER['PHP_SELF']) )
	{ // The Old Stand-By
		$ReqPath = $_SERVER['PHP_SELF'];
		$ReqURI = isset($_SERVER['QUERY_STRING']) && !empty( $_SERVER['QUERY_STRING'] ) ? ($ReqPath.'?'.$_SERVER['QUERY_STRING']) : $ReqPath;
	}
	else
	{
		$ReqPath = false;
		$ReqURI = false;
	}

	$ReqHost = false;
	if( !empty($_SERVER['HTTP_HOST']) )
	{
		$ReqHost = ((isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] != 'off')) ? 'https://' : 'http://').$_SERVER['HTTP_HOST'];
	}

	switch($mode)
	{
		case 'url':		return $ReqHost.$ReqURI;	break;
		case 'uri':		return $ReqURI;				break;
		case 'path':	return $ReqPath;			break;
		case 'host':	return $ReqHost;			break;
		default:		return array($ReqPath,$ReqURI,$ReqHost);
	}
}


/**
 * Get the base domain (without protocol and any subdomain) of an URL.
 *
 * @param string URL
 * @return string the base domain
 */
function snr_get_hostname( $url )
{
	$domain = preg_replace( '~^https?://(www([0-9]+)?\.)?~i', '', $url );
	$domain = preg_replace( '~^([^:/#]+)(.*)$~i', '\\1', $domain );

	return $domain;
}


function snr_cleardir_r( $path, $save_dirs = true )
{
	if( is_file($path) )
	{
		return @unlink($path);
	}
	elseif( is_dir($path) )
	{
		$scan = glob(rtrim($path,'/').'/*');
		foreach( $scan as $index=>$path )
		{
			snr_cleardir_r($path);
		}

		if( $save_dirs ) return true;

		return @rmdir($path);
	}
}


/**
 * Add a trailing slash, if none present
 *
 * @param string the path/url
 * @return string the path/url with trailing slash
 */
function snr_trailing_slash( $path )
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
 * NOTE: this is done with the "recursive" param in PHP5
 *
 * @param string directory name
 * @param integer permissions
 * @return boolean
 */
function snr_mkdir_r( $dirName, $cmod = 0755 )
{
	if( is_dir($dirName) )
	{ // already exists:
		return true;
	}

	if( version_compare(PHP_VERSION, 5, '>=') )
	{
		$r = @mkdir( $dirName, $chmod, true );
		@chmod( $dirName, $cmod );

		return $r;
	}

	$dirName = snr_trailing_slash($dirName);

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
				if( ! @mkdir( $loop_dir, $cmod ) )
				{
					return false;
				}
				@chmod( $dirName, $cmod );
			}
			return true;
		}
	}
	return true;
}

?>