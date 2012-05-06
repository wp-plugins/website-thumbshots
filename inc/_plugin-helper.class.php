<?php
/**
 *
 * This file implements the plugin helper class
 *
 * Author: Sonorth Corp. - {@link http://www.sonorth.com/}
 * License: Creative Commons Attribution-ShareAlike 3.0 Unported
 * License info: {@link http://creativecommons.org/licenses/by-sa/3.0/}
 *
 */


class SonorthPluginHelper
{
	var $name;
	var $version;
	var $help_url;
	var $pre;
	var $uri;
	var $menu_text;
	var $options;
	var $msg = array();
	var $admin_debug = false;
	

	function T_( $string )
	{	// Wrapper for b2evolution T_ function
		return __($string);
	}
	
	
	function initialize_options( $params = array() )
	{
		$this->options = $params;
		
		foreach( $params as $k => $param )
		{
			if( isset($param['layout']) ) continue;
			if( empty($k) || !isset($param['defaultvalue']) ) continue;
			
			$this->add_option( $k, $param['defaultvalue']);
		}
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
		add_options_page( __($this->menu_text, $this->name), __($this->menu_text, $this->name), 10, $this->pre.'settings', array($this, 'admin_settings') );
	}
	
	
	function admin_settings()
	{
		if( empty($_GET['page']) ) return;
		if( $_GET['page'] != $this->pre.'settings' ) return;
		
		$this->check_cache_directory();
		
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
				$img = plugins_url().'/'.$this->code.'/plugin-logo.png';
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