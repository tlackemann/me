<?php

class Rss
{
	protected $_url;

	public function setUrl($url)
	{
		$this->_url = $url;
		return $this;
	}

	public function request()
	{
		$content = file_get_contents($this->_url);
		$x = new SimpleXmlElement($content);
		$result = array();
		foreach($x->channel->item as $entry)
		{
			$result[] = $this->_objectToArray($entry);
		}

		return $result;
	}

	protected function _objectToArray($object)
	{
		$array = array();
		//var_dump($object);
		foreach($object as $member => $data)
		{
			if (isset($array[$member]) && $member == 'category')
			{
				$array[$member][] = (string)$data;
			}
			else
			{
				if ($member == 'category')
				{
					$array[$member][] = (string)$data;
				}
				else
				{
					$array[$member] = (string)$data;
				}
			}
			//var_dump($member.' => '.$data);
		}
		return $array;
	}
}