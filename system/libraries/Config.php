<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 4.3.2 or newer
 *
 * @package		CodeIgniter
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2008 - 2010, EllisLab, Inc.
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * CodeIgniter Config Class
 *
 * This class contains functions that enable config files to be managed
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		ExpressionEngine Dev Team
 * @link		http://codeigniter.com/user_guide/libraries/config.html
 */
class CI_Config {

	var $config = array();
	var $is_loaded = array();

	/**
	 * Constructor
	 *
	 * Sets the $config data from the primary config.php file as a class variable
	 *
	 * @access   public
	 * @param   string	the config file name
	 * @param   boolean  if configuration values should be loaded into their own section
	 * @param   boolean  true if errors should just return false, false if an error message should be displayed
	 * @return  boolean  if the file was successfully loaded or not
	 */
	function CI_Config()
	{
		$this->config =& get_config();
		log_message('debug', "Config Class Initialized");

		// Set the base_url automatically if none was provided
		if ($this->config['base_url'] == '')
		{
			if(isset($_SERVER['HTTP_HOST']))
			{
				$base_url = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
				$base_url .= '://'. $_SERVER['HTTP_HOST'];
				$base_url .= str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
			}

			else
			{
				$base_url = 'http://localhost/';
			}

			$this->set_item('base_url', $base_url);
		}
		
		// Add defines for BASE_URL and LONG_URL(plus with subpath)
		if(defined('SUBPATH')){
			$t=$this->item('base_url');
			if(!strcasecmp(substr($t,-strlen(SUBPATH)),SUBPATH)){
				$this->set_item('base_url',substr($t,0,-strlen(SUBPATH)));
			}
		}
		define('BASE_URL',$this->item('base_url'));
		define('LONG_URL',defined('SUBPATH')?BASE_URL.SUBPATH:BASE_URL);
		
		// Setup time zone
		$timezone=$this->item('timezone');
		if($timezone!=''){
			if(!function_exists('date_default_timezone_set'))@putenv($timezone);
			else date_default_timezone_set($timezone);
		}
		
		// Multi controller super class
		if(!defined('SUBPATH')||!$this->load('extends/'.rtrim(SUBPATH,'/'),false,true)){
			$this->load('extends',false,true);
		}
	}
  	
	// --------------------------------------------------------------------

	/**
	 * Load Config File
	 *
	 * @access	public
	 * @param	string	the config file name
	 * @return	boolean	if the file was loaded correctly
	 */	
	function load($file = '', $use_sections = FALSE, $fail_gracefully = FALSE)
	{
		$file = ($file == '') ? 'config' : str_replace(EXT, '', $file);
	
		if (in_array($file, $this->is_loaded, TRUE))
		{
			return TRUE;
		}

		if ( ! file_exists(APPPATH.'config/'.$file.EXT))
		{
			if ($fail_gracefully === TRUE)
			{
				return FALSE;
			}
			show_error('The configuration file '.$file.EXT.' does not exist.');
		}
	
		include(APPPATH.'config/'.$file.EXT);

		if ( ! isset($config) OR ! is_array($config))
		{
			if ($fail_gracefully === TRUE)
			{
				return FALSE;
			}
			show_error('Your '.$file.EXT.' file does not appear to contain a valid configuration array.');
		}

		if ($use_sections === TRUE)
		{
			if (isset($this->config[$file]))
			{
				$this->config[$file] = array_merge($this->config[$file], $config);
			}
			else
			{
				$this->config[$file] = $config;
			}
		}
		else
		{
			$this->config = array_merge($this->config, $config);
		}

		$this->is_loaded[] = $file;
		unset($config);

		log_message('debug', 'Config file loaded: config/'.$file.EXT);
		return TRUE;
	}
  	
	// --------------------------------------------------------------------

	/**
	 * Fetch a config file item
	 *
	 *
	 * @access	public
	 * @param	string	the config item name
	 * @param	string	the index name
	 * @param	bool
	 * @return	string
	 */
	function item($item, $index = '')
	{	
		if ($index == '')
		{	
			if ( ! isset($this->config[$item]))
			{
				return FALSE;
			}

			$pref = $this->config[$item];
		}
		else
		{
			if ( ! isset($this->config[$index]))
			{
				return FALSE;
			}

			if ( ! isset($this->config[$index][$item]))
			{
				return FALSE;
			}

			$pref = $this->config[$index][$item];
		}

		return $pref;
	}
  	
  	// --------------------------------------------------------------------

	/**
	 * Fetch a config file item - adds slash after item
	 *
	 * The second parameter allows a slash to be added to the end of
	 * the item, in the case of a path.
	 *
	 * @access	public
	 * @param	string	the config item name
	 * @param	bool
	 * @return	string
	 */
	function slash_item($item)
	{
		if ( ! isset($this->config[$item]))
		{
			return FALSE;
		}

		$pref = $this->config[$item];

		if ($pref != '' && substr($pref, -1) != '/')
		{	
			$pref .= '/';
		}

		return $pref;
	}
  	
	// --------------------------------------------------------------------

	/**
	 * Site URL
	 *
	 * @access	public
	 * @param	string	the URI string
	 * @return	string
	 */
	function site_url($uri = '')
	{
		$long_url=LONG_URL;
		
		if ($uri == '')
		{
			return $long_url.$this->item('index_page');
		}

		if ($this->item('enable_query_strings') == FALSE)
		{
			if (is_array($uri))
			{
				$uri = implode('/', $uri);
			}
			
			if($uri[0]=='['&&($n=strpos($uri,']'))){
				$subpath=substr($uri,1,$n-1);
				$uri=substr($uri,$n+1);
				$long_url=('/'==$subpath)?BASE_URL:BASE_URL.trim($subpath,'/').'/';
			}

			$index = $this->item('index_page') == '' ? '' : $this->item('index_page').'?';
			$suffix = ($this->item('url_suffix') == FALSE) ? '' : $this->item('url_suffix');
			return $long_url.$index.trim($uri, '/').$suffix;
		}
		
		if (is_array($uri))
		{
			$i = 0;
			$str = '';
			foreach ($uri as $key => $val)
			{
				$prefix = ($i == 0) ? '' : '&';
				$str .= $prefix.$key.'='.$val;
				$i++;
			}

			$uri = $str;
		}
		return $long_url.$this->item('index_page').'?'.$uri;
	}
	
	// --------------------------------------------------------------------

	/**
	 * System URL
	 *
	 * @access	public
	 * @return	string
	 */
	function system_url()
	{
		$x = explode("/", preg_replace("|/*(.+?)/*$|", "\\1", BASEPATH));
		return $this->slash_item('base_url').end($x).'/';
	}
  	
	// --------------------------------------------------------------------

	/**
	 * Set a config file item
	 *
	 * @access	public
	 * @param	string	the config item key
	 * @param	string	the config item value
	 * @return	void
	 */
	function set_item($item, $value)
	{
		$this->config[$item] = $value;
	}

}

// END CI_Config class

/* End of file Config.php */
/* Location: ./system/libraries/Config.php */