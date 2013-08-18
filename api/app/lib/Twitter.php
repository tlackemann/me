<?php
require_once('twitteroauth.php');

class Twitter
{
	/**
	 * The maximum width of the embedded tweet
	 */
	protected $_width = '520';
	protected $_consumerKey = TomConfig::TWITTER_CONSUMER_KEY;
	protected $_consumerSecret = TomConfig::TWITTER_CONSUMER_SECRET;
	protected $_accessToken = TomConfig::TWITTER_ACCESS_TOKEN;
	protected $_accessTokenSecret = TomConfig::TWITTER_ACCESS_TOKEN_SECRET;

	protected $_requestTokenUrl = 'https://api.twitter.com/oauth/request_token';
	protected $_authorizeUrl = 'https://api.twitter.com/oauth/authorize';
	protected $_accessTokenUrl = 'https://api.twitter.com/oauth/access_token';
	protected $_requestUrl = 'statuses/user_timeline.json?screen_name=tlackemann&count=10';
	protected $_callbackUrl = '';

	protected $_connection;
	protected $_tweets;

	public function connect()
	{
		$this->_connection = new TwitterOAuth($this->_consumerKey, $this->_consumerSecret, $this->_accessToken, $this->_accessTokenSecret);
		return $this;
	}

	public function getTweets()
	{
		$this->_tweets = $this->_connection->get("statuses/user_timeline.json?screen_name=tlackemann&count=10");
		return $this->_tweets;
	}

	public function request($cached = array())
	{
		$results = array();
		$i = 0;
		foreach($this->_tweets as $tweet)
		{
			if (!in_array($tweet, $cached))
			{
				$results[$i]['tweet'] = $this->_connection->get("statuses/show", array('id' => $tweet->id, 'trim_user' => 'true'));
				$results[$i]['created_at'] = $tweet->created_at;
				$i++;
			}
			else
			{
				// store cached tweet without retrieving
			}
		}

		return $results;
	}

 
}