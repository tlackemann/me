<?php
require_once('lib/Config.php'); #TomConfig class - you can probably figure out what goes in here
require_once('lib/Database.php');
require_once('lib/OAuth.php');
require_once('lib/Twitter.php');
require_once('lib/Instagram.php');
#require_once('lib/GooglePlus.php');
require_once('lib/Rss.php'); # Blog related things

class Tom extends Database
{
	/**
	 * Debugging enabled - will display errors
	 */
	protected $_debug = true;

	/**
	 * Logging enabled - will log errors
	 */
	protected $_log = false;

	/**
	 * If logging enabled, the full filename and path of the log
	 */
	protected $_path = 'log/tom.log';

	/**
	 * The method which the API will listen for
	 */
	protected $_method = 'get';

	/**
	 * The result format returned by the API
	 */
	protected $_format = 'json';

	/**
	 * Determine if the API should store cache
	 */
	protected $_cache = true;

	/**
	 * If cache enabled, the method for which to store the data
	 * Supported: file | redis
	 */
	protected $_cacheType = 'redis';

	/**
	 * If file cache enabled, the file path
	 */
	protected $_cachePath = 'app/cache/';

	/**
	 * If cache enabled, the default cache lifetime
	 */
	protected $_lifetime = 900; # 900 = 15 minutes

	/**
	 * Cron flag
	 */
	protected $_cron = false;

	/**
	 * Params for cron to run
	 */
	protected $_params = array();

	/**
	 * Available API methods
	 */
	protected $_calls = array(
		'twitter'	=> true, #tweets
		'instagram'	=> true, #instagram photos
		'rss'		=> true, #blogs and rss feeds
		'gplus'		=> true, #google plus
		'projects'	=> true, #portfolio pieces and work
		'events'	=> true, #birthdays, certifications, etc.
		'from'		=> true, #get events from date (gmt)
		'to'		=> true, #get events to date (gmt)
		'force'		=> '', #force a no cache
	);

	/**
	 * RSS feeds to scan for articles and related content
	 */
	protected $_feeds = array(
		'http://whoistom.me/blog/?feed=rss2',
		'http://pixafy.com/custom-rss/?user=thomas'
	);

	/**
	 * Results instance
	 */
	protected $_result = array();

	/**
	 * Action instance
	 */
	protected $_action;

	/**
	 * Redis instance
	 */
	protected $_redis;

	/**
	 * Cache key instances
	 */
	protected $_keys = array();

	/**
	 * Sets debugging, instantiates necessary dependencies
	 */
	public function __construct()
	{
		if ($this->_debug)
		{
			$this->setDebug(1);
		}

		if ($this->_cacheType == 'redis')
		{
			$this->_redis = new Redis();
			$this->_redis->connect('/tmp/redis.sock');
			$this->_redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
		}
	}

	/**
	 * Gathers all the feed data from cache if available and stores the data
	 * @return string
	 */
	public function run()
	{
		$this->_processParams();
		return $this;
	}

	/**
	 * Sorts the objects before they're returned
	 * @param string $sort
	 */
	public function sortTimeline($sort = 'desc')
	{
		#date_default_timezone_set('UTC');
		$sortedResult = array();
		$results = $this->_result;
		foreach($results as $action => $results)
		{
			foreach ($results as $id => $result)
			{

				$a = (substr($action, 0, 3) == 'rss') ? 'rss' : $action;
				/* Check the method, depending on the call we're going to check for different timestamps */
				switch ($a)
				{
					case 'twitter' :
						$createdAt = $this->_convertGmtToLocal($result['created_at']);
						#var_dump("Twitter: ".$createdAt);
						$sortedResult['tom-'.$createdAt][$action] = $result;

						break;
					case 'rss' : 
							$createdAt = $this->_convertGmtToLocal($result['pubDate']);
							#var_dump("RSS: ".$createdAt);
							$sortedResult['tom-'.$createdAt][$a] = $result;
						
						
						break;
					case 'instagram' :
						foreach($result as $instagram)
						{
							if (isset($instagram['caption']['created_time']))
							{
								$createdAt = $this->_convertGmtToLocal($instagram['caption']['created_time']);
								#var_dump("Instagram: ".$createdAt);
								$sortedResult['tom-'.$createdAt][$action] = $instagram;
							}
							
						}
						break;
					case 'events' :
						$createdAt = $this->_convertGmtToLocal($result['date']);
						$sortedResult['tom-'.$createdAt][$action] = $result;
						break;
				}
			}
		}
		switch ($sort)
		{
			default :
			case 'desc' :
				krsort($sortedResult);
				break;
			case 'asc' :
				ksort($sortedResult);
				break;
		}

		$this->_result = $sortedResult;
		return $this;
	}

	protected function _convertGmtToLocal($timestamp)
	{
		$datetime = (!is_numeric($timestamp)) ? strtotime($timestamp) : $timestamp;
		return $datetime;
	}

	/**
	 * Determines whether to run the cron or not
	 * Cron responsible for clearing cache and reauthenticating with external APIs
	 * @param boolean $cron
	 * @return Tom
	 */
	public function setCron($cron = false)
	{
		$this->_cron = $cron;

		// Important! We need to change the default cache path
		$this->_cachePath = 'cache/';
		return $this;
	}

	/**
	 * Toggles debugging on/off
	 * @param boolean $debug
	 * @return Tom
	 */
	public function setDebug($debug)
	{
		if ($debug)
		{
			error_reporting(E_ALL);
			ini_set('display_errors', '1');
		}
		else
		{
			error_reporting(0);
			ini_set('display_errors', '0');
		}

		return $this;
	}

	/**
	 * Checks if the request method matches the API listening method
	 * @return boolean
	 */
	protected function _checkParams()
	{
		if (strtolower($_SERVER['REQUEST_METHOD']) === $this->_method)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Sets parameters to run
	 * @param array $params
	 * @return Tom
	 */
	public function setParams($params = array())
	{
		foreach($params as $param => $value)
		{
			$this->_params[$param] = $value;
		}
		return $this;
	}

	/**
	 * Gets the parameters to run
	 * @param array $params
	 * @return Tom
	 */
	public function getParams()
	{
		return $this->_params;
	}

	/**
	 * Runs all API calls based on the parameters passed
	 * @throws Exception
	 */
	protected function _processParams()
	{
		if (!$this->_cron && $this->_checkParams())
		{
			
				$type = (strtoupper($this->_method) == 'GET') ? $_GET : $_POST;
				foreach($type as $key => $value)
				{
					if ($this->_checkApiCall($key) && $value != false && $value != 0)
					{
						if ($key == 'rss')
						{
							foreach($this->_feeds as $feed)
							{
								$this->_fetchResults($key.$feed);
							}
						}
						else
						{
							$this->_fetchResults($key);
						}
					}
				}
		}
		elseif ($this->_cron)
		{
			foreach($this->getParams() as $key => $value)
			{
				if ($this->_checkApiCall($key) && $value != false && $value != 0)
				{
					if ($key == 'rss')
					{
						foreach($this->_feeds as $feed)
						{
							$this->_fetchResults($key.$feed, true);
						}
					}
					else
					{
						$this->_fetchResults($key, true);
					}
				}
			}
		}
		else
		{
			throw new Exception('Mismatching method types');
		}
	}

	/**
	 * Checks if the API call is a valid method
	 * @param string $method
	 * @return boolean
	 */
	protected function _checkApiCall($method)
	{
		if (isset($this->_calls[$method]))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Fetches the result from cache if available or creates the cache if not
	 *
	 * @param string action  
	 */
	protected function _fetchResults($action, $noCache = false)
	{
		$key = $this->_translateCacheKey($action);
		if ($this->_checkCache($key) && !$noCache)
		{
			switch($this->_cacheType)
			{
				case 'file' : 
					$this->_result[$action] = json_decode(file_get_contents($this->_cachePath.$key), true);
					break;

				case 'redis' :
					$this->_result[$action] = json_decode($this->_redis->get($key),true);
					break;
			}
		}
		else
		{
			$this->_getResult($action);
		}

		return $this;
	}

	/**
	 * Gets the result of the action
	 * @param string $action
	 */
	protected function _getResult($action, $noCache = false)
	{
		$a = (substr($action, 0, 3) == 'rss') ? 'rss' : $action;
		switch($a)
		{

			case 'twitter' :
				if (!$noCache)
				{
					$_result = $this->_getTwitter();
					$this->_createCache($action, $_result);
					$this->_result[$action] = $_result;
				}
				else
				{
					return $this->_result[$action];
				}
				break;
			case 'rss' : 
				foreach($this->_feeds as $feed)
				{
					$_result = $this->_getRss($feed);
					$this->_createCache($a.$feed, $_result);
					$this->_result[$a] = $_result;
				}
				break;
			case 'instagram' :
				$_result = $this->_getInstagram();
				$this->_createCache($action, $_result);
				break;
			case 'events' :
				$_result = $this->_getEvents();
				$this->_createCache($action, $_result);
				break;
		}
		
	}

	/**
	 * Creates the cache in the appropriate format based on the cacheKey and cacheValue
	 * @param string $action
	 * @param string $cacheValue
	 * @return Tom
	 */
	protected function _createCache($action, $cacheValue)
	{
		$key = $this->_translateCacheKey($action);
		switch($this->_cacheType)
		{
			case 'file' :
				$file = fopen($this->_cachePath.$key, 'w');
				fwrite($file, $this->_translateResult($cacheValue));
				fclose($file);
				break;

			case 'redis' : 
				$this->_redis->set($key, $this->_translateResult($cacheValue));
				break;
		}
		return $this;
	}

	/**
	 * Returns the result based on the set format (similar to _handleResult($echo))
	 * @param boolean $echo
	 * @return string
	 */
	public function returnResult($echo = true)
	{
		$this->_handleResult($echo);
	}

	/**
	 * Translates the result into the appropriate format
	 * @param boolean $echo
	 * @return string
	 */
	protected function _handleResult($echo = true)
	{
		$return = $this->_translateResult();

		if ($echo)
		{
			echo $return;
		}
		else
		{
			return $return;
		}
	}

	protected function _translateResult($result = null)
	{
		if ($result === null)
		{
			$result = $this->_result;
		}
		switch ($this->_format)
		{
			case 'xml' :
				$return = '';
				break;
			default :
			case 'json' :
				$return = json_encode($result);
				break;
		}

		return $return;
	}

	protected function _untranslateResult($result = null)
	{
		if ($result === null)
		{
			$result = $this->_result;
		}
		switch ($this->_format)
		{
			case 'xml' :
				$return = '';
				break;
			default :
			case 'json' :
				$return = json_decode($result, true);
				break;
		}

		return $return;
	}

	/**
	 * Translates a plaintext string into a hash or returns the stored hash if found
	 * @param string $cacheKey
	 * @return string
	 */
	protected function _translateCacheKey($cacheKey)
	{
		$key = md5($cacheKey);

		/* We passed in a hash, return the hash again */
		if (isset($this->_keys[$cacheKey]))
		{
			return $cacheKey;
		}
		/* We passed in a string, store the hash and return it */
		else
		{
			$this->_keys[$key] = $cacheKey;
		}
		return $key;
	}

	/**
	 * Checks the cache for a specific hash
	 * @param string $cacheKey
	 * @return boolean
	 */
	protected function _checkCache($cacheKey)
	{
		$key = $this->_translateCacheKey($cacheKey);
		switch($this->_cacheType)
		{
			case 'file' :
				return (file_exists($this->_cachePath.$key)) ? true : false;
				break;

			case 'redis' :
				return ($this->_redis->get($key)) ? true : false;
				break;
		}
		return $this;
	}

	/**
	 * Fetches Twitter results
	 * @return string
	 */
	protected function _getTwitter()
	{
		$twitter = new Twitter();
		#var_dump('hi');
		// Establish a connection and get the latest 20 tweets
		$tweets = $twitter->connect()->getTweets();

		// Load previous tweets from cache
		#$cachedTweets = $this->_fetchResults('twitter')->_getResult('twitter', true);

		// If any new tweets, put them in the queue
		// $queuedTweets = array();
		// $cachedTweetUrls = array();
		// foreach($cachedTweets as $cachedTweet)
		// {
		// 	#var_dump($cachedTweet);
		// 	$cachedTweetUrl = $cachedTweet['tweet']['url'];

		// 	foreach($tweets as $tweet)
		// 	{
		// 		var_dump($tweet);
		// 	}
		// }

		
		
		#var_dump($queuedTweets);
		#exit;

		// Make the request
		$result = $twitter->request();

		return $result;
	}

	protected function _getInstagram()
	{
		$instagram = new Instagram();
		#$result = array();
		/* Step 1: Login */
		#header("Location: {$instagram->getLoginUrl()}");
		/* Step 2: Authorize the temp code */
		#$data = $instagram->getOAuthToken('bf7cd191ce6a4c7b94cc1ee510debe8c');
		#var_dump($data);
		/* Store the OAuth token for later use by modifying Instagram.php */
		$result = $instagram->getUserMedia();
		return $result;
	}

	/**
	 * Fetches RSS and blog results
	 * @return string
	 */
	protected function _getRss($feed)
	{
		$rss = new Rss();
		$result = $rss->setUrl($feed)->request();
		return $result;

	}

	protected function _getEvents()
	{
		$events = array(
			array(
				'name' 	=> 'Happy 23<sup>rd</sup> Birthday',
				'image' => 'http://whoistom.me/img/IMG6863-4-L.jpg',
				'date'	=> 'June 19, 2012 10:34:00'
			),
			array(
				'name'	=> 'Magento Certified Developer Plus',
				'image' => 'http://whoistom.me/img/magento-denise.jpg',
				'date'	=> 'July 10, 2013 12:00:00'
			)
		);

		return $events;
	}
}