<?php
/**
 *  @license
 *  Copyright 2014 Bit API Hub
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *
 *  Generate analytics data to send to Segment.io
 *  @link https://segment.com/docs/libraries
 *  @link https://github.com/BitAPIHub/FuelPHP-Segment-io
 */

namespace Segment;

class Analytics
{
	/**
	 * @var object $instances The Analytics object instances
	 * @access protected
	 */
	protected static $instances = array();
	
	/**
	 * @var array $identity Contains anonymousId and/or userId for tracking the user
	 */
	public $identity = array();
	
	/**
	 * @var string $_ga_cookie_id The contents of the _ga cookie for Universal Analytics
	 * @access private
	 */
	private $_ga_cookie_id = null;
	
	/**
	 * @var array $_js_scripts The array of javascript snippets in queue for render()
	 * @access private
	 */
	private $_js_scripts = array();

	/**
	 * @var bool $_js_debug Set this to true to enable the JS debugging features.
	 * @access private
	 */
	private $_js_debug = false;
	
	/**
	 * @var bool If the browser's "Do Not Track" header is set, we disable all analytics operations.
	 */
	private $_dnt = false;
	
	/**
	 * Pull an instance out of thin air. If the named instance does not exist, it will be created and returned.
	 * 
	 * @param string $name The name of the instance to return
	 * @return object The requested instance or a new instance if the desired one does not exist.
	 */
	public static function instance($name = '_default_')
	{
		if (!array_key_exists($name, static::$instances)) {
			
			static::$instances[$name] = static::forge(); 
			
		}
		
		return static::$instances[$name];
	}
	
	/**
	 * Generate a new instance.
	 * 
	 * @return \Segment\Analytics
	 */
	public static function forge()
	{
		return new static;
	}
	
	/**
	 * Load the default configuration settings
	 */
	public function __construct()
	{
		// Respect the customer's "Do Not Track" headers.
		$this->_dnt = \Input::server('HTTP_DNT', 0) == 1 ? true : false;
		
		// Don't track a customer if they don't want to be tracked.
		if ($this->_dnt === true) {
			
			return;
			
		}
		
		\Config::load('segment', true);
		\Analytics::init(\Config::get('segment.write_key'), \Config::get('segment.configure'), array());
		
		/**
		 * This also serves as something to check to see if Google Analytics is in use. Although the cookie could
		 * be set through alternative means, such as a separate UA tracking code, sending the extra data won't hurt
		 * anything.
		 */
		$this->_set_ga_cookie_id();
		
		// Set the debug mode for JS
		$this->_js_debug = \Config::get('segment.configure.debug', false);
		
		$this->identity = \Session::get('segment.identity');
		if (empty($this->identity)) {

			$this->identity = array('anonymousId' => $this->_generate_random_id());
			\Session::set('segment.identity', $this->identity);
			
		}
	}
	
	/**
	 * Set the userId field for all methods.
	 * 
	 * @param string $user_id	The userId to send with all Segment calls
	 */
	public function set_user_id($user_id)
	{
		// Don't track a customer if they don't want to be tracked.
		if ($this->_dnt === true) {
			
			return;
			
		}
		$this->identity = array_merge($this->identity, array('userId' => $user_id));
		\Session::set('segment.identity', $this->identity);
	}
	
	/**
	 * Send a page view through Segment
	 * 
	 * @param array $page_data		The array of page data as specified for the Segment.io PHP library
	 * @param bool $js				Set this parameter to true to generate the JS code instead of using PHP.
	 * @param array $js_options		The array of options to set for the JS "options" parameter - "integrations"
	 * 								options get specified in $page_data, but may be overridden here.
	 * @param string $js_callback	If you want to use a callback function with "page," specify it here.
	 */
	public function page(
		array $page_data	= array(),
		$js					= true,
		array $js_options	= array(),
		$js_callback		= null
	){
		// Don't track a customer if they don't want to be tracked.
		if ($this->_dnt === true) {
			
			return;
			
		}
		
		// Set the userId or anonymousId if userId is missing.
		$page_data = $this->_set_identity($page_data);

		// Add the context data.
		$page_data = array_merge_recursive($this->_get_context($js), $page_data);
		
		if ($js !== true) {
			
			// JS sets the defaults on its own, so we do this only for PHP.
			$page_data = array_merge_recursive($this->_get_page_properties(), $page_data);
			\Analytics::page($page_data);
			
		} else {
			
			// Category
			$js_params[] = !empty($page_data['category']) ? "'".$page_data['category']."'" : "null";
			
			// Name
			$js_params[] = !empty($page_data['name']) ? "'".$page_data['name']."'" : "null";
			
			// Properties
			$js_params[] = !empty($page_data['properties']) ? json_encode($page_data['properties']) : "{}";
			
			// Options
			$js_params[] = $this->_set_js_options($js_options, $page_data);
			
			// Callback
			$js_params[] = !empty($js_callback) ? $js_callback : "null";
			
			$js_output = 'analytics.page('.implode(',', $js_params).');';
			
			/**
			 * To make things easier, developers can skip calling this method without any parameters.
			 * The render() method will always generate the analytics.page() call, so we don't set that
			 * here.
			 * 
			 * @todo Find a scalable way to check this.
			 */
			if ($js_output !== 'analytics.page(null,null,{},{},null);') {

				// Add it to the queue.
				$this->_js_scripts['page'] = $js_output;
				
			}
			
		}
	}
	
	/**
	 * Alias the user in the analytics system
	 *
	 * @param array $alias 			The array of data for Segment's \Analytics::alias()
	 * @param bool $js				Set this to generate the content as JS once you run render()
	 * @param array $js_options		The array of options to set for the JS "options" parameter - "integrations"
	 * 								options get specified in $alias, but may be overridden here.
	 * @param string $js_callback	If you want to use a callback function with "alias," specify it here.
	 */
	public function alias(array $alias = array(), $js = true, array $js_options = array(), $js_callback = null)
	{
		// Don't track a customer if they don't want to be tracked.
		if ($this->_dnt === true) {
			return;
		}
		
		// Set the previousId
		$alias['previousId'] = empty($alias['previousId']) ? $this->identity['anonymousId'] : $alias['previousId'];
		
		// Try to locate the userId. Throw an error if we can't find one.
		if (empty($alias['userId'])) {
			
			if (empty($this->identity['userId'])) {
				throw new \FuelException('The userId must be specified.');
			}
			
			$alias['userId'] = $this->identity['userId'];
			
		}
		
		// We need an anonymousId for PHP based calls.
		if (empty($alias['previousId']) && $js === false) {
			
			throw new \FuelException('The previousId must be specified when sending the call through PHP.');
			
		}
		
		// We always need a userId.
		if (empty($alias['userId'])) {
			
			throw new \FuelException('The userId must be specified.');
			
		}
		
		// Be sure to keep things synchronized.
		if (!empty($alias['userId'])) {
			
			$this->set_user_id($alias['userId']);
			
		}
		
		if ($js !== true) {
			
			\Analytics::alias($alias);
			
		} else {
			
			// User ID
			$js_params[] = "'".$alias['userId']."'";
			
			// Anonymous ID
			$js_params[] = !empty($alias['previousId']) ? "'".$alias['previousId']."'" : "null";
			
			// JS Options
			$js_params[] = $this->_set_js_options($js_options, $alias);
			
			// JS Callback
			$js_params[] = !empty($js_callback) ? $js_callback : 'null';

			// Add it to the queue.
			$this->_js_scripts['alias'] = 'analytics.alias('.implode(',', $js_params).');analytics.flush();';
			
		}
	}
	
	/**
	 * Identify the user in the analytics system
	 * 
	 * @param array $identification The array of data for Segment.io's \Analytics::identify() (Empty
	 * 								generates an anonymousId.)
	 * @param bool $js				Set this to generate the content as JS once you run render()
	 * @param bool $render_safe		Set this to true to enable running \Security::htmlentities() on all traits.
	 * @param array $js_options		The array of options to set for the JS "options" parameter - "integrations"
	 * 								options get specified in $identification, but may be overridden here.
	 * @param string $js_callback	If you want to use a callback function with "identify," specify it here.
	 */
	public function identify(
		array $identification	= array(),
		$js						= true,
		array $js_options		= array(),
		$js_callback			= null
	)
	{
		// Don't track a customer if they don't want to be tracked.
		if ($this->_dnt === true) {
			
			return;
			
		}
		
		$identification = empty($identification) ? $this->identity : $identification;
		
		/**
		 * Set this for anywhere in the system that needs access to it. It's already part of $identification,
		 * so we don't merge it in.
		 */
		if (!empty($identification['userId'])) {
			
			$this->set_user_id($identification['userId']);
			
		} else {
			
			$identification['anonymousId'] = !empty($identification['anonymousId']) ? $identification['anonymousId'] : $this->identity['anonymousId'];
			
		}
		
		$identification = array_merge_recursive($this->_get_context($js), $identification);
		
		if ($js !== true) {
			
			\Analytics::identify($identification);
			
		} else {
			
			// User ID (JS generates an anonymous ID if we don't send this.)
			$js_params[] = !empty($identification['userId']) ? "'".$identification['userId']."'" : "null";
			
			// Traits
			$js_params[] = !empty($identification['traits']) ? json_encode($identification['traits']) : "{}";
			
			// Integrations
			$js_params[] = $this->_set_js_options($js_options, $identification);
			
			// Callback function
			$js_params[] = !empty($js_callback) ? $js_callback : "null";

			// Add it to the queue.
			$this->_js_scripts['identify'] = 'analytics.identify('.implode(',', $js_params).');';
		}
	}
	
	/**
	 * Place people in groups, such as teams and companies.
	 * 
	 * @param array $group			The array of group data for Segment's \Analytics::group()
	 * @param bool $js				Set this to generate the content as JS once you run render()
	 * @param array $js_options		The array of options to set for the JS "options" parameter - "integrations"
	 * 								options get specified in $group, but may be overridden here.
	 * @param string $js_callback	If you want to use a callback function with "group," specify it here.
	 * 
	 * @throws \FuelException
	 */
	public function group(
		array $group,
		$js					= true,
		array $js_options	= array(),
		$js_callback		= null
	)
	{
		// Don't track a customer if they don't want to be tracked.
		if ($this->_dnt === true) {
			
			return;
			
		}
		
		// We need a groupId.
		if (empty($group['groupId'])) {
			
			throw new \FuelException('groupId must be set for this call.');
			
		}
		
		// Set the userId or anonymousId if userId is missing.
		$group = $this->_set_identity($group);
		
		// Add the context data.
		$group = array_merge_recursive($this->_get_context($js), $group);
		
		if ($js !== true) {
			
			\Analytics::group($group);
			
		} else {
			
			// groupId
			$js_params[] = "'".$group['groupId']."'";
			
			// Traits
			$js_params[] = !empty($group['traits']) ? json_encode($group['traits']) : "{}";
			
			// Integrations and options
			$js_params[] = $this->_set_js_options($js_options, $group);
			
			// Callback function
			$js_params[] = !empty($js_callback) ? $js_callback : "null";

			// Add it to the queue.
			$this->_js_scripts['group'] = "analytics.group(".implode(',', $js_params).");";
			
		}
	}
	
	/**
	 * Track what people are doing on your website.
	 * 
	 * @param array $track				The array of group data for Segment's \Analytics::track()
	 * @param bool $js					Set this to generate the content as JS once you run render()
	 * @param array $js_options			The array of options to set for the JS "options" parameter - "integrations"
	 * 									options get specified in $track, but may be overridden here.
	 * @param string $js_callback		If you want to use a callback function with "track," specify it here.
	 * @param bool $noninteraction		Set this variable to true to tell Google Analytics that the event is a
	 * 									non-interaction event.
	 * @throws \FuelException
	 */
	public function track(
		array $track,
		$js					= true,
		array $js_options	= array(),
		$js_callback		= null,
		$noninteraction		= true
	)
	{
		// Don't track a customer if they don't want to be tracked.
		if ($this->_dnt === true) {
			
			return;
			
		}
		
		// We need an event.
		if (empty($track['event'])) {
			
			throw new \FuelException('"event" must be set for this call.');
			
		}
		
		// Set the userId or anonymousId if userId is missing.
		$track = $this->_set_identity($track);
		
		// Add the context data.
		$track = array_merge_recursive($this->_get_context($js), $track);
		
		// If we're processing noninteractive calls through Google Analytics...
		if ($noninteraction === true) {
		
			if (empty($track['properties'])) {
					
				$track['properties'] = array('nonInteraction' => 1);
					
			} else {
					
				$track['properties'] = array_merge_recursive(array('nonInteraction' => 1), $track['properties']);
					
			}
		
		}
		
		if ($js !== true) {
			
			\Analytics::track($track);
			
		} else {
			
			// Event
			$js_params[] = "'".$track['event']."'";
			
			// Properties
			$js_params[] = !empty($track['properties']) ? json_encode($track['properties']) : "{}";
			
			// Integrations and options
			$js_params[] = $this->_set_js_options($js_options, $track);
			
			// Callback function
			$js_params[] = !empty($js_callback) ? $js_callback : "null";
			
			// Add it to the queue.
			$this->_js_scripts['track'][] = "analytics.track(".implode(',', $js_params).");";
			
		}
	}
	
	/**
	 * Create raw function entries
	 * 
	 * @param string $raw_function_code The raw JS function to add to the queue
	 */
	public function custom($raw_function_code)
	{
		// Don't track a customer if they don't want to be tracked.
		if ($this->_dnt === true) {
			
			return;
			
		}
		
		$this->_js_scripts['custom'][] = $raw_function_code;
	}
	
	/**
	 * Render the JS output.
	 * 
	 * @param array $order	The list specifying the display order of the JS functions
	 * 
	 * @return string The rendered JS content
	 */
	public function render(array $order = array('page', 'alias', 'identify', 'group', 'track', 'custom'), $auto_page_view = true)
	{
		// Don't track a customer if they don't want to be tracked.
		if ($this->_dnt === true) {
			
			return;
			
		}
		
		// Initialize the variable.
		$output = null;
		
		// Enable debug mode if we need to.
		if ($this->_js_debug === true) {
				
			$output = 'analytics.debug();';
				
		}
			
		/**
		 * Segment.io requires at least one page view to be sent for a page. Segment's code should not include the
		 * analytics.page() call, so as to avoid sending the page with every view, as well as any named pages. If
		 * we didn't add a named page, then we must send Segment.io an unnamed page view.
		 */
		if (empty($this->_js_scripts['page']) && $auto_page_view === true) {
			
			$output .= 'analytics.page();';
			
		}
		
		// Loop through the function list and tack it to the output if it's not empty.
		foreach ($order as $key => $function) {
			
			$function = \Str::lower($function);
			if (!empty($this->_js_scripts[$function])) {
				
				if (in_array($function, array('custom', 'track'))) {
					
					foreach ($this->_js_scripts[$function] as $function_key => $function_value) {

						$output .= $function_value;
						
					}
					
				} else {
					
					$output .= $this->_js_scripts[$function];
				
				}
					
			}
			
		}
		
		return $output;
	}
	
	/**
	 * PRIVATE PARTS
	 */
	
	/**
	 * Add the key/value pair to the array if the value isn't empty.
	 * 
	 * @param string $key	The key to set the value on
	 * @param string $value	The value to set for the key
	 * @param array $array	The array to perform the operation on
	 */
	private function _add_element($key, $value, array $array)
	{
		if (!empty($value)) {
			
			// Allow the user to override these values by passing the new value to array_merge() first.
			$array = array_merge(array($key => $value), $array);
			
		}
	}
	
	/**
	 * Get the current locale for the user
	 * 
	 * @return string The locale the user requested, or the default system language
	 */
	private function _get_locale()
	{
		foreach (\Agent::languages() as $key => $val) {
	
			// Is it a locale?
			if (substr_count($val, '-') || substr_count($val, '_')) {
	
				return $val;
	
			}
	
		}
		
		// Use the system default.
		return \Config::get('locale');
	}
	
	/**
	 * Generate values for the "properties" key for the page() method.
	 * 
	 * @return array The array of data for the "properties" key.
	 */
	private function _get_page_properties()
	{
		$properties_data['properties'] = array(
			
			'url'		=> \Uri::main(),
			'referrer'	=> \Input::referrer(),
			'path'		=> '/'.\Uri::string()
			
			// This key must get set by the developer, as there's no way to grab it in PHP.
			// 'title'		=> '',
			
		);
		
		return $properties_data;
	}
	
	/**
	 * Generate the default data for the context object.
	 * 
	 * @param bool $js	Set this to generate the context for JS
	 *  
	 * @return array The array to use for the "context" object
	 */
	private function _get_context($js = true)
	{
		$context_data = array(
			
			'context'	=> array(
				
				'locale'	=> $this->_get_locale(),
				'timezone'	=> date('e'),
				'os'		=> array(
		
					'name'		=> \Agent::platform(),
					'version'	=> \Agent::property('platform_version'),
		
				)
				
			),
			
		);
		
		if ($js !== true) {
			
			$php_context = array(
				
				'ip'		=> \Input::real_ip(),
				'userAgent'	=> \Input::user_agent(),
				
			);
			
			$context_data['context'] = array_merge_recursive($context_data['context'], $php_context);
		
			// Don't use \Arr::set() since that will always add the keys.
			$context['campaign'] = $this->_add_element('name', \Input::get('utm_campaign'), array());
			$context['campaign'] = $this->_add_element('source', \Input::get('utm_source'), $context['campaign']);
			$context['campaign'] = $this->_add_element('medium', \Input::get('utm_medium'), $context['campaign']);
			$context['campaign'] = $this->_add_element('term', \Input::get('utm_term'), $context['campaign']);
			$context['campaign'] = $this->_add_element('content', \Input::get('utm_content'), $context['campaign']);
			
			if (!empty($context['campaign'])) {
					
				$context_data['context'] = array_merge_recursive($context_data['context'], $context);
					
			}
			
			// If we're using Google Analytics, we add it's ID.
			if (!empty($this->_ga_cookie_id)) {
				
				\Arr::set($context_data, 'integrations.Google Analytics.clientId', $this->_ga_cookie_id);
				
			}
		
		}
		
		return $context_data;
	}
	
	/**
	 * Add either a userId or anonymousId based on what's available.
	 * 
	 * @param array $data The data sent to the package possibly containing the ID
	 * 
	 * @return array The $data array with the identity values added as needed.
	 */
	private function _set_identity(array $data)
	{
		if (empty($data['userId'])) {
		
			if (!empty($this->identity['userId'])) {
					
				$data['userId'] = $this->identity['userId'];
					
			} else {
					
				$data['anonymousId'] = !empty($data['anonymousId']) ? $data['anonymousId'] : $this->identity['anonymousId'];
					
			}
		
		}
		
		return $data;
	}
	
	/**
	 * Grab the ClientID from the _ga cookie for Universal Analytics
	 * IMPORTANT: The cookie doesn't exist until the page is sent for the first time, so this dependency will
	 * fail for the PHP library when the customer first views the site without the cookie.
	 * 
	 * @link https://segment.com/docs/integrations/google-analytics/#server-side
	 */
	private function _set_ga_cookie_id()
	{
		$ga_cookie = explode('.', \Input::cookie('_ga'));
		
		// The explosives create an array with an empty string at position 0 when the cookie doesn't exist.
		if (!empty($ga_cookie[0])) {
			
			$this->_ga_cookie_id = $ga_cookie[count($ga_cookie)-2].'.'.$ga_cookie[count($ga_cookie)-1];
			
		}
	}
	
	/**
	 * Set the options parameter for analytics.js to include both manually set options, and integrations.
	 * 
	 * @param array $options		The array of manually set options
	 * @param array $data			The array of containing sformatted for PHP calls
	 * 
	 * @return string The JSON string ready for use in the options parameter.
	 */
	private function _set_js_options(array $js_options, array $data)
	{
		
		// Nothing by default
		$return = array();
		
		// User specified options
		if (!empty($js_options)) {
			
			$return = array_merge_recursive($return, $js_options);
			
		}
		
		// Integrations
		if (!empty($data['integrations'])) {
			
			$return = array_merge_recursive(array('integrations' => (array)$data['integrations']), $return);
			
		}
		
		// Context data
		if (!empty($data['context'])) {
			
			$return = array_merge_recursive(array('context' => (array)$data['context']), $return);
			
		}
		
		// Keep the anonymousId synchronized between JS, and PHP.
		$return = $this->_set_identity($return);
		
		return json_encode($return);
	}
	
	/**
	 * Generate a random anonymous ID in the same fashion that Segment.io does.
	 * 
	 * @link https://gist.github.com/dahnielson/508447#file-uuid-php-L74
	 * 
	 * @return string Version 4 UUID - A pseudo-random string
	 */
	private function _generate_random_id()
	{
		return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
	
			// 32 bits for "time_low"
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
	
			// 16 bits for "time_mid"
			mt_rand(0, 0xffff),
	
			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 4
			mt_rand(0, 0x0fff) | 0x4000,
	
			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			mt_rand(0, 0x3fff) | 0x8000,
	
			// 48 bits for "node"
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}
}
