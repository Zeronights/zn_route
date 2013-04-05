<?php

require_once 'Route.php';

use \Zeronights\Route\Route;

$uri = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';


/**
 * Register routes without a callback.
 */
Route::register('hello/world');
Route::register('hello/:int');
Route::register('hello/:@a|b|c@');


// If $uri = 'hello/b', the 3rd registered route will run.
Route::run($uri, function($segments) {
	
	var_dump($segments);
	/*
	 * Array
	 * [0] => 'hello',
	 * [1] => 'b'
	 */
});


/**
 * Register routes with a callback.
 */
Route::register('binary/:bin', function($segments) {
	
	echo 'I like binary: ' . $segments[1];
});

Route::register('binary/:hex', function($segments) {

	echo 'I used a hex modifier, oops :(';
});


// If $uri = 'binary/1010101', the 2nd registered route and callback will run.
Route::run($uri);

/*
 * I like binary: 1010101
 */



/**
 * Run an instance of the route with temporary routes
 */
Route::run($uri, [
	
	
	// This is a option group
	[
		'name' => 'simple',
		'case_sensitive' => false,
		'regex' => false
	],
	
	'no/:@regex@',
	
	
	// Calling the reset option will reset all current options to default
	[
		'reset' => true
	],
	
	'yay/:@regex@',
	
	
	// You can define routes with temporary options
	'temp/:int' => [
		
		
		// This only resets within the scope of this route
		'reset' => true,
		'callback' => function($segments) {
	
			echo 'Temp options';
		}
	],
		
		
	// You can use a named group elsewhere
	'named/groups' => [
		'use' => 'simple'
	],
			
			
	// And best of all, you can have a basic route and callback
	'basic' => function($segments) {
		
		echo 'Basic';
	},
	
	
	// Bonus round, you can also set default routes
	// Is equal to ':*'
	Route::RT_DEFAULT => function($segments) {
		
		echo 'No route matched';
	}

	
// Default callback that gets fired if matched route has no callback
], function ($segments) {
	
	echo 'Default callback';
});


/**
 * Expected results
 */

// If $uri = 'no/:@regex@'
// [ 'no', ':@regex@' ]
// -- Default callback

// If $uri = 'yay/regex'
// [ 'yay', 'regex' ]
// -- Default callback

// If $uri = 'temp/-132'
// [ 'temp', '-132' ]
// -- Temp options

// If $uri = 'basic'
// [ 'basic' ]
// Basic

// If $uri = 'thiswontmatch/any/route'
// [ 'thiswontmatch/any/route' ]
// No route matched