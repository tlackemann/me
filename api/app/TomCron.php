<?php

require_once('Tom.php');

$app = new Tom();

try
{
	$app->setParams(array(
		'rss'		=> 1,
		'twitter'	=> 0,
		'instagram'	=> 0,
		'events'	=> 0,
		

	))
	->setCron(true)
	->run()
	->returnResult();
}
catch (Exception $e)
{
	echo "Error: ".$e->getMessage();
}