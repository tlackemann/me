<?php

abstract class Database
{
	/**
	 * The database host model
	 */
	protected $_dbh;

	/**
	 * Type of connection (default: mysql)
	 */
	protected $_dbType = 'mysql';

	/**
	 * Database name
	 */
	protected $_dbName = 'tom_development';

	/**
	 * Database host
	 */
	protected $_dbHost = 'localhost';
	/**
	 * Database username
	 */
	protected $_dbUser = 'root';

	/**
	 * Database password
	 */
	protected $_dbPass = 'root';

	/**
	 * The table to query
	 */
	protected $_table;

	/**
	 * Attempts to make a connection to the database and stores the connection if successful
	 */
	public function connect()
	{
		try
		{
		    $this->_dbh = new PDO("{$this->_dbType}:host={$this->_dbHost};dbname={$this->_dbName}", $this->_dbUser, $this->_dbPass);
		}
		catch (PDOException $e)
		{
		    throw new Exception($e->getMessage());
		}

		return $this;
	}

	public function getConnection()
	{
		if ($this->_dbh)
		{
			return $this->_dbh;
		}
		else
		{
			throw new Exception('No database connection');
		}

		return $this;
	}

	public function setTable($table)
	{
		$this->_table = $table;

		return $this;
	}

	public function load($value, $key)
	{
		$results = $this->_dbh->query("SELECT * FROM `{$this->_table}` WHERE `{$value}` = '{$key}'");
		return $results;
	}
}