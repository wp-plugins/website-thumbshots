<?php
/**
 * This file implements the plugin helper class
 *
 * Author: Sonorth Corp. - {@link http://www.sonorth.com/}
 * License: GPL version 3 or any later version
 * License info: {@link http://www.gnu.org/licenses/gpl.txt}
 *
 * API specification and examples: {@link http://thumbshots.ru/api}
 *
 */


class SonorthPluginHelper
{
	var $name;
	var $foldername;
	var $filename;
	var $version;
	var $help_url;
	var $pre;
	var $uri;
	var $menu_text;
	var $options;
	var $msg = array();
	var $admin_debug = false;


	function plugin_activate()
	{
		if( method_exists( $this, 'BeforeInstall' ) )
		{
			if( ! $this->BeforeInstall() ) die;
		}
	}


	function initialize_options()
	{
		if( method_exists( $this, 'GetDefaultSettings' ) )
		{
			$this->options = $this->GetDefaultSettings();

			foreach( $this->options as $k => $param )
			{
				if( isset($param['layout']) ) continue;
				if( empty($k) || !isset($param['defaultvalue']) ) continue;

				$this->add_option( $k, $param['defaultvalue']);
			}
		}
	}


	function T_( $string )
	{	// Wrapper for b2evolution T_ function
		return __($string);
	}


	function add_action_links( $links, $file )
	{
		if( $file == plugin_basename( $this->foldername.'/'.$this->filename ) )
		{
			array_unshift( $links, '<a href="admin.php?page='.$this->pre.'settings'.'">'.$this->T_('Settings').'</a>' );
		}
		return $links;
	}


	function add_option( $name = '', $value = '' )
	{
		if( $value === 0 || $value === '0' ) $value = serialize(0); // stupid wp
		if( add_option($this->pre.$name, $value) ) return true;
		return false;
	}


	function update_option( $name = '', $value = '' )
	{
		if( $value === 0 || $value === '0' ) $value = serialize(0); // stupid wp
		if( update_option($this->pre.$name, $value) ) return true;
		return false;
	}


	function get_option( $name = '', $stripslashes = true )
	{
		if( $option = get_option($this->pre.$name) )
		{
			if( @unserialize($option) !== false ) return unserialize($option);
			if( $stripslashes ) $option = stripslashes_deep($option);

			return $option;
		}
		return false;
	}


	function delete_option( $name = '' )
	{
		if( !empty($name) && delete_option($this->pre.$name) ) return true;
		return false;
	}


	function add_action( $action, $function = null, $priority = 10, $params = 1 )
	{
		if( add_action($action, array($this, ((empty($function)) ? $action : $function)), $priority, $params) ) return true;
		return false;
	}


	function admin_menu()
	{
		$icon_url = '';
		if( file_exists( dirname(__FILE__).'/../menu-icon.png') )
		{	// Add menu icon if any
			$icon_url = plugins_url().'/'.$this->foldername.'/menu-icon.png';
		}

		add_menu_page( $this->name, $this->menu_text, 10, $this->pre.'settings', array($this, 'admin_settings'), $icon_url );
	}


	function admin_settings()
	{
		if( empty($_GET['page']) ) return;
		if( $_GET['page'] != $this->pre.'settings' ) return;

		$this->BeforeInstall();

		if( isset($_POST['submit']) )
		{	// Update settings
			unset($_POST['submit']);

			foreach( $this->options as $k => $param )
			{
				if($k == 'layout') continue;

				if( isset($_POST[$k]) )
				{
					if( !empty($this->options[$k]['valid_range']) )
					{	// Check valid range
						$range = $this->options[$k]['valid_range'];

						$err = array();
						if( ! preg_match('~^[-+]?\d+$~', $_POST[$k]) )
						{
							$err[] = 'be numeric';
						}
						if( isset($range['min']) && $range['min'] > $_POST[$k] )
						{
							$err[] = 'not be less than <strong>"'.$range['min'].'"</strong>';
						}
						if( isset($range['max']) && $range['max'] < $_POST[$k] )
						{
							$err[] = 'not exceed <strong>"'.$range['max'].'"</strong>';
						}

						if( !empty($err) )
						{
							$this->msg( sprintf('<strong>"%s"</strong> value must %s. This setting was not saved!', $this->options[$k]['label'], implode('and ', $err)), 'error' );
							continue;
						}
					}

					if( $k == 'access_key' && trim($_POST[$k]) != $this->get_option($k) )
					{	// Clear cache after changing the API key
						$this->msg( __('API key has chenged, clearing thumbshot cache...'), 'success' );
						snr_cleardir_r( $this->thumbnails_path, true );
					}

					$this->update_option($k, $_POST[$k]);
				}
				elseif( $param['type'] == 'checkbox' )
				{	// special case for empty checkboxes
					$this->options[$k]['defaultvalue'] = 0; // :(
					$this->update_option($k, 0);
				}
			}
			$this->msg( __('Configuration settings have been saved'), 'success' );
		}

		// Display settings page
		$this->render('settings', false, true);
	}


	function debug( $var = array() )
	{
		if( $this->admin_debug )
		{
			echo '<pre>'.var_export($var, true).'</pre>';
			flush();
		}
		return true;
	}


	function msg( $message, $type = 'error' )
	{
		$this->render( $type, array('message' => $message), true);
		flush();
	}


	function render( $file = '', $params = array(), $output = true )
	{
		if( !empty($params) )
		{
			foreach( $params as $key => $val ) { ${$key} = $val; }
		}

		switch( $file )
		{
			case 'success':
			case 'note':
				$data = '<div id="notice" class="updated fade clear"><p>'.$message.'</p></div>';
				break;

			case 'error':
				$data = '<div id="notice" class="error fade clear"><p>'.$message.'</p></div>';
				break;

			case 'settings':
				$image = '';
				$img = plugins_url().'/'.$this->foldername.'/plugin-logo.png';
				if( file_exists( dirname(__FILE__).'/../plugin-logo.png') )
				{
					$image = '<img src="'.$img.'" align="left" style="padding:0 15px 15px 0" alt="" />';
					if( $this->help_url )
					{
						$image = '<a href="'.$this->help_url.'" target="_blank" title="Open plugin\'s support page">'.$image.'</a>';
					}
				}

				echo '<div class="wrap">
						<h2>'.$image.'<span style="display:block; padding-top:35px; font-size: 28px">'
						.$this->name.' <span style="font-size:12px">v'.$this->version.'</span></span></h2>';

				echo '<form action="'.$this->uri.'" method="post">
						<table class="form-table">';

				foreach( $this->options as $k => $param )
				{	// Disaplay plugin settings
					if( isset($param['layout']) )
					{
						echo '<tr><td colspan="2"><hr /></td></tr>';
					}
					else
					{
						if( empty($k) || !isset($param['defaultvalue']) ) continue;

						if( ($value = $this->get_option($k)) === false )
						{
							$value = $param['defaultvalue'];
						}

						$notes_styles = 'display:inline';
						switch( $param['type'] )
						{
							case 'checkbox':
								$input = '<input type="checkbox" id="'.$this->pre.$k.'" name="'.$k.'" value="1" '.( $value ? 'checked="checked"' : '')
											.((!empty($param['size'])) ? ' size="'.$param['size'].'"' : '').' />';
								break;

							case 'textarea':
							case 'html_textarea':
								$cols = ((!empty($param['cols'])) ? ' cols="'.$param['cols'].'"' : '');
								$rows = ((!empty($param['rows'])) ? ' rows="'.$param['rows'].'"' : '');

								$input = '<textarea id="'.$this->pre.$k.'" name="'.$k.'" '.$cols.$rows.'">'.$value.'</textarea>';
								$notes_styles = 'display: block;';
								break;

							case 'integer':
							case 'text':
							default:
								$input = '<input type="text" id="'.$this->pre.$k.'" name="'.$k.'" value="'.$value.'"'
											.((!empty($param['size'])) ? ' size="'.$param['size'].'"' : '').' />';
								break;
						}

						$notes = '';
						if( !empty($param['note']) ) $notes .= $param['note'];
						if( !empty($param['notes']) ) $notes .= $param['notes'];

						echo '<tr>';
						echo '<th><label for="'.$this->pre.$k.'">'.$param['label'].'</label></th>';
						echo '<td>'.$input.' <span class="howto" style="'.$notes_styles.'">'.$notes.'</span></td>';
						echo '</tr>';
					}
				}

				echo '</table>';
				echo '<p class="submit"><input type="submit" class="button-primary" name="submit" value="'.__('Save Configuration', $this->name).'" /></p>';
				echo '</form></div>';

				break;

			case (!empty($file)):
				$filename = dirname(__FILE__).'/'.$file.'.views.php';

				if( file_exists($filename) )
				{
					ob_start();
					include $filename;
					$data = ob_get_clean();
				}
				break;
		}

		if( !empty($data) )
		{
			if( $output )
			{
				echo $data;
				flush();
			}

			return $data;
		}
		return false;
	}
}

?>