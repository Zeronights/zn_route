<?php

namespace Zeronights\Route;

class Route {
	
	
	/**
	 * Default route constant, basically a hack for a wildcard modifier.
	 */
	const RT_DEFAULT = ':*';
	
	
	/**
	 * Default options for route parsing engine.
	 *  
	 * @var array
	 */
	protected static $options = [
		'case_sensitive' => false,
		'regex' => true,
		'modifiers' => true,
		'user_modifiers' => true,
		'callback' => null
	];
	
	
	/**
	 * Contains all options for a route parsing instance.
	 * 
	 * @var array
	 */
	protected static $temp_options = [];
	
	
	/**
	 * Temporarily stores named option groups.
	 * 
	 * @var array
	 */
	protected static $current_options = [];
	
	
	/**
	 *
	 * @var type 
	 */
	protected static $current_option_groups = [];
	
	
	/**
	 * Stores routes which are registered.
	 * 
	 * @var array
	 */
	protected static $routes = [];
	
	
	/**
	 * Stores all predefined segment modifiers.
	 * 
	 * @var array
	 */
	protected static $modifiers = [
		
		
		// Matches any signed or unsigned integer
		':int' => '-?[0-9]{1,}',
		
		
		// Matches any numerical digit
		':digit' => '[0-9]{1,}',
		
		
		// Matches any signed or unsigned floating point number
		':float' => '-?[0-9]{1,}\.[0-9]{1,}',
		
		
		// Matches any digit
		':num' => '\d+',
		
		
		// Matches any hex number, also supports starting with 0x
		':hex' => '(?:0x)?[A-F0-9]+',
		
		
		// Matches anything within the section
		':any' => '[^/]+',
		
		
		// Matches everything from this point onwards
		':*' => '.*',
		
		
		// Matches any binary sequence
		':bin' => '[0-1]+'
	];
	
	
	/**
	 * Contains all user modifiers and their associated closure.
	 * 
	 * @var array
	 */
	protected static $user_modifiers = [];
	
	
	/**
	 * Stores the current segment index of the route.
	 * 
	 * @var integer
	 */
	protected static $route_index = 0;
	
	
	/**
	 * Stores exploded version of given URI.
	 * 
	 * @var array 
	 */
	protected static $route_uri = [];
	
	
	/**
	 * Register a route which will be used to match against a given URI.
	 * 
	 * @param string $route
	 * @param mixed $options
	 */
	public static function register($route, $options = null) {
		
		static::$routes[$route] = $options;
	}
	
	
	/**
	 * Register a user modifier which can be used as part of a route using
	 * the double colon identifier, ::$name.
	 * 
	 * @param string $name
	 * @param closure $callback
	 */
	public static function register_modifier($name, $callback) {
		
		static::$user_modifiers[$name] = $callback;
	}
	
	
	/**
	 * Run the given URI against all of the available routes and try to find
	 * a match for the URI using the routes and their options. Upon success,
	 * fire a callback if specified. 
	 * 
	 * @param string $uri
	 * @param array | callable $routes
	 * @param callable $callback
	 */
	public static function run($uri, $routes = [], $callback = null) {
		
		
		// Clean the uri
		$uri = trim((string) $uri, '/');
	
		
		// Set the callback if no routes are set
		if (is_callable($routes)) {
			$callback = $routes;
		}
		
		
		// Select the routes
		$routes = !is_array($routes) || empty($routes) ? static::$routes : $routes;
		
		
		// Reset current variables
		static::$current_options = static::$options;		
		static::$current_option_groups = [];
		
		
		// Make an array out of the uri
		static::$route_uri = explode('/', $uri);
		
		
		// Loop through the routes and options
		foreach ($routes as $route => $options) {
			
			
			// Check if reset is called and reset options but only in option groups
			if (is_int($route) && is_array($options) && isset($options['reset']) && $options['reset']) {

				static::$current_options = static::$options;
			}
			
			
			// Reset temporary options
			static::$temp_options = static::$current_options;
			
			
			// If it is just a options setter
			if (is_int($route) && is_array($options)) {
				
				
				// Merge options
				static::$current_options = $options + static::$current_options;
				
				
				// Save options if named
				if (isset($options['name'])) {
					
					static::$current_option_groups[$options['name']] = $options;
				}
				
				continue;
			}
			
			
			// Check if route with options
			if (is_string($route) && is_array($options)) {
				
				if (isset($options['reset']) && $options['reset']) {
					
					static::$temp_options = static::$options;
				}
				
				if (isset($options['use']) && isset(static::$current_option_groups[$options['use']])) {
					
					static::$temp_options = static::$current_option_groups[$options['use']] + static::$temp_options;
				}
				
				static::$temp_options = $options + static::$temp_options;
			}
			
			
			// Check if just route and callback
			if (is_string($route) && is_callable($options)) {
				
				static::$temp_options['callback'] = $options;
			}
			
			
			// Check if only route is passed
			if (is_int($route) && is_string($options)) {
				
				$route = $options;
			}
			
			
			// Set the temporary options with modifiers and user modifiers
			if (static::$temp_options['modifiers'] === true)
				static::$temp_options['modifiers'] = static::$modifiers;
			elseif (!static::$temp_options['modifiers'])
				static::$temp_options['modifiers'] = [];
			
			if (static::$temp_options['user_modifiers'] === true)
				static::$temp_options['user_modifiers'] = static::$user_modifiers;
			elseif (!static::$temp_options['user_modifiers'])
				static::$temp_options['user_modifiers'] = [];
	
			
			// Start the route index
			static::$route_index = 0;
			
			
			// See if route is default route
			if ($route == static::RT_DEFAULT) {
				
				static::$temp_options['modifiers'] = [
					$route => static::$modifiers[$route]
				];
			}
			
			
			// Clean up the route if it contains wildcard modifier
			$route = preg_replace('@((^|/):\*(/|$)).*$@', '$1', $route);
			
			
			// Parse the route
			$route = preg_replace_callback('@(?<=/|)(\\\\)?([^/]+)@', 'static::parse_route', $route);
			
			
			// Clean up regex modifiers
			$route = preg_replace('@([^/]+)@', '($1)', $route);

			
			// Build route regex
			$route = '@^' . $route . '$@' . (static::$temp_options['case_sensitive'] ? '' : 'i');
			

			$segments = [];

			
			// Try to match the route with the uri
			if (preg_match($route, $uri, $segments)) {

				
				// Remove global group match
				array_shift($segments);
				
				
				// Set callback if in options
				if (is_callable(static::$temp_options['callback'])) {
					
					$callback = static::$temp_options['callback'];
				}
				
				
				// If there is a callable callback, run it and pass segments as parameter
				if (is_callable($callback)) {
					
					$callback($segments);
				}
				
				break;
			}
		}
	}
	
	
	/**
	 * The URI segment parsing engine which gets called via the
	 * preg_replace_callback in the run() method.
	 * 
	 * @param array $matches
	 * @return string
	 */
	protected static function parse_route($matches) {
		
		static::$route_index++;
		
		
		// Check if modifier exists and if it does, return the logic
		if (isset(static::$temp_options['modifiers'][$matches[0]]) && $matches[1] == '') {
			
			return static::$temp_options['modifiers'][$matches[0]];
		}
		
		
		// Check if start of user modifier
		if (strpos($matches[0], '::') === 0) {
			
			$match = ltrim($matches[0], ':');
			
			
			// Check if user modifier exists and if route segment exists
			if (isset(static::$temp_options['user_modifiers'][$match]) && isset(static::$route_uri[static::$route_index - 1])) {
				
				$callback = static::$temp_options['user_modifiers'][$match];
				
				
				// Run callback and return segment pass or fail
				if ($callback(static::$route_uri[static::$route_index - 1])) {
					
					return preg_quote(static::$route_uri[self::$route_index - 1], '@');
				} else {
					
					return '_' . $matches[0];
				}
			}
		}
		
		
		// See if segment was not escaped
		$match = $matches[1] == '\\' ? $matches[2] : $matches[0];
		
		
		// Check if regex modifier and if regex is allowed
		if ($matches[1] == '' && isset(static::$temp_options['regex']) && static::$temp_options['regex']) {
			
			
			// Format regex modifier for route
			return preg_replace('/^:@([^\/]*)@$/', '$1', $match);
		}
		
		
		// Sanitize what remains
		return preg_quote($match, '@');
	}
}