<?php

namespace PerryRylance\SplitBatch;

class Daemon
{
	private static $hasRunAlready = false;
	
	protected $connection;
	
	public function __construct()
	{
		$this->schema		= new Schema();
		$this->schema->install();
	}
	
	public function run()
	{
		if(Daemon::$hasRunAlready)
			trigger_error("The split batch daemon should not run more than once per request", E_USER_WARNING);
		
		Daemon::$hasRunAlready = true;
		
		$connection			= Connection::$instance;
		$name				= $connection->tablePrefix . Schema::TABLE_NAME;
		
		$qstr = "
			SELECT class, handle 
			FROM $name 
			WHERE (NOW(3) - last_run) * 1000 >= frequency
		";
		
		$result				= $connection->query($qstr);
		$results			= $result->fetchAll();
		
		foreach($results as $arr)
		{
			$job			= Job::create($arr['class'], $arr['handle']);
			$job->run();
		}
	}
}
