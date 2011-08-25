<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Singleton API wrapper for Smugmug
 *
 * @package    Optisailing
 * @author     Mark Steve Samson <contact@marksteve.me>
 */
class Smugmug {

	private static $session_id;

	public static function __callStatic($method, $args = array())
	{
		return self::call(str_replace('_', '.', $method), $args);
	}

	public static function call($method, $args = array()) {
		$cache = Cache::instance();

		// Check if session doesn't exist and if we're not logging in
		if (!(self::$session_id = Cookie::get('smugmug_session_id'))
			AND $method != 'login.anonymously')
		{
			$response = self::call('login.anonymously');
			self::$session_id = $response['Session']['id'];
			Cookie::set('smugmug_session_id', self::$session_id);
		}

		// Setup request
		$request = array_merge($args, array(
			'method' => 'smugmug.'.$method,
			'APIKey' => Kohana::config('smugmug.api_key'),
		));

		// Append session id
		if (isset(self::$session_id))
		{
			$request += array('SessionID' => self::$session_id);
		}

    // Maintain own cache
		$cache_key = serialize($request);

		// Check if response is available in cache
		if (!($parsed_response = $cache->get($cache_key, FALSE)))
		{

			// Send request to smugmug endpoint
      $response = Request::factory(Kohana::config('smugmug.endpoint'))
        ->method(Request::POST)
        ->post($request)
        ->execute();

			$parsed_response = unserialize($response);

			// Check for failure
			if ($parsed_response['stat'] == 'fail')
			{
				throw new Exception("Smugmug API Error: [{$parsed_response['code']}] {$parsed_response['message']}");
			}
			else
			{
				// Cache if response is valid
				$cache->set($cache_key, $parsed_response, Kohana::config('smugmug.cache_lifetime'));
			}
		}

		// Actual response is the first element
		return array_pop($parsed_response);
	}

}
