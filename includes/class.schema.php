<?php

namespace PerryRylance\SplitBatch;

use \Doctrine\DBAL\Schema\Table;

class Schema
{
	const VERSION		= "0.0.1";
	const TABLE_NAME	= "split_batch_jobs";
	
	public function install()
	{
		$connection			= Connection::$instance;
		
		$schemaManager		= $connection->getSchemaManager();
		$schema				= new \Doctrine\DBAL\Schema\Schema();
		$platform			= $connection->getDatabasePlatform();
		$name				= $connection->tablePrefix . Schema::TABLE_NAME;
		
		// TODO: Uncomment
		if($schemaManager->tablesExist([$name]))
			return;
		
		// TODO: Add upgrade mechanism
		
		$table				= $schema->createTable($name);
		
		$table->addColumn("id",				"integer", ["unsigned" => true, "autoincrement" => true]);
		$table->addColumn("class",			"string", ["length" => 512]);
		$table->addColumn("handle",			"string", ["length" => 512]);
		$table->addColumn("created",		"datetime", ["default" => "CURRENT_TIMESTAMP"]);
		$table->addColumn("last_run",		"datetime");
		$table->addColumn("frequency",		"integer", ["unsigned" => true]); // NB: Milliseconds as units
		$table->addColumn("max_iterations",	"integer", ["unsigned" => true]);
		$table->addColumn("max_period",		"integer", ["unsigned" => true]);
		$table->addColumn("state",			"string", ["length" => pow(2, 32) - 1]);
		
		$table->setPrimaryKey(["id"]);
		$table->addUniqueIndex(["handle"]);
		$table->setComment(json_encode(["version" => Schema::VERSION]));
		
		if($schemaManager->tablesExist($name))
		{
			$queries		= $schema->toDropSql($platform);
			
			foreach($queries as $qstr)
				$connection->query($qstr);
		}
		
		$queries			= $schema->toSql($platform);
		
		foreach($queries as $qstr)
			$connection->query($qstr);
		
		// NB: Fix for fractional second times not presently working with Doctrine\DBAL\Schema\Schema
		$connection->query("ALTER TABLE `$name` CHANGE `last_run` `last_run` DATETIME(3) NOT NULL;");
	}
}
