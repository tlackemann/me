<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once('app/Tom.php');

$app = new Tom();

try
{
	$app->run()->sortTimeline('desc')->returnResult();
}
catch (Exception $e)
{

}
