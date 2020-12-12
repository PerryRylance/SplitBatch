<?php

namespace PerryRylance\SplitBatch;

use \Doctrine\DBAL\DriverManager;

class Connection
{
	public static $tablePrefix = "";
	public static $instance;
	
	public static function fromWordPress()
	{
		global $table_prefix;
		
		if(
			!defined('DB_NAME') ||
			!defined('DB_USER') ||
			!defined('DB_PASSWORD') ||
			!defined('DB_HOST')
			)
			throw new \Exception("One or more constants are not defined");
		
		$connection = DriverManager::getConnection([
			'dbname'	=> DB_NAME,
			'user'		=> DB_USER,
			'password'	=> DB_PASSWORD,
			'host'		=> DB_HOST,
			'driver'	=> 'mysqli'
		]);
		
		$connection->tablePrefix = $table_prefix;
		
		Connection::$instance = $connection;
		
		return $connection;
	}
}
