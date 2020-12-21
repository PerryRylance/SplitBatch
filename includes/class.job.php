<?php

namespace PerryRylance\SplitBatch;

abstract class Job
{
	protected		$id;
	protected		$class;
	protected		$handle;
	protected		$created;
	protected		$last_run			= 0;
	protected		$frequency			= 1000;
	protected		$max_iterations		= 1000;
	protected		$max_period			= 1000;
	
	protected		$state;
	
	public static function create($class, string $handle, array $options=null)
	{
		if(!is_subclass_of($class, '\PerryRylance\SplitBatch\Job'))
			throw new \Exception("Argument must be a class name which extends Job");
		
		$existing				= Job::get($handle);
		
		if($existing)
			return $existing;
		
		$instance				= new $class();
		
		$instance->class		= $class;
		$instance->handle		= $handle;
		$instance->created		= date(\DateTime::ISO8601);
			
		$instance->init($options);
		$instance->save();
		
		return $instance;
	}
	
	public static function get(string $handle)
	{
		$record					= Job::getRecord($handle);
		
		if(!$record)
			return null;
		
		$class					= $record['class'];
		$instance				= new $class();
		
		foreach($record as $key => $value)
		{
			if($key == "state")				
				$instance->{$key} = unserialize($value);
			else
				$instance->{$key} = $value;
		}
		
		return $instance;
	}
	
	private static function getRecord(string $handle)
	{
		$connection		= Connection::$instance;
		$name			= $connection->tablePrefix . Schema::TABLE_NAME;
		
		$sql			= "SELECT * FROM $name WHERE handle = ?";
		$stmt			= $connection->prepare($sql);
		
		$stmt->bindValue(1, $handle);
		$result			= $stmt->execute();
		
		$record			= $result->fetch();
		
		return $record;
	}
	
	abstract protected function init();
	abstract protected function iterate();
	
	public function run()
	{
		$start			= Clock::milliseconds();

		$now			= \DateTime::createFromFormat('U.u', (string)microtime(true));
		$this->last_run	= $now->format("Y-m-d H:i:s.v");
		
		for($iterations = 0, $elapsed = 0;
		
			$iterations < $this->max_iterations &&
			$elapsed <= $this->max_period;
			
			$iterations++, $elapsed = Clock::milliseconds() - $start)
		{
			$result = $this->iterate();
			
			if($result === false)
				return;	// NB: Signal to stop iterating
		}
		
		$this->save();
		
		return $iterations;
	}
	
	protected function success()
	{
		$this->complete();
	}
	
	public function abort()
	{
		$this->complete();
	}
	
	protected function complete()
	{
		$this->destroy();
	}
	
	public function save()
	{
		if(empty($this->state))
			throw new \Exception("Job has no state");

		$inserting		= Job::getRecord($this->handle) ? false : true;
		
		$connection		= Connection::$instance;
		$name			= $connection->tablePrefix . Schema::TABLE_NAME;
	
		$arr			= $connection->query("SHOW COLUMNS FROM $name");
		$columns		= [];
		
		foreach($arr->fetchAll() as $definition)
			$columns	[]= $definition['Field'];
		
		array_splice($columns, array_search('id', $columns), 1);
		
		if(!$inserting)
			array_splice($columns, array_search('handle', $columns), 1);
					
		$imploded		= implode(', ', $columns);
		$placeholders	= implode(', ', array_fill(0, count($columns), '?'));
		
		if($inserting)
		{
			$qstr		= "INSERT INTO $name ($imploded) VALUES ($placeholders)";
			$stmt		= $connection->prepare($qstr);
			
			for($i = 0; $i < count($columns); $i++)
			{
				$column	= $columns[$i];
				
				if($column == 'state')
					$value = serialize($this->state);
				else
					$value = $this->{$columns[$i]};
				
				$stmt->bindValue($i + 1, $value);
			}
		}
		else
		{
			unset($columns[array_search('id', $columns)]);
			unset($columns[array_search('handle', $columns)]);
			
			$columns	= array_values($columns);
			
			$qstr		= "UPDATE $name SET ";
			$setters	= [];
			
			for($i = 0; $i < count($columns); $i++)
			{
				$column = $columns[$i];
				
				$setters []= "$column = ?";
			}
			
			$qstr		.= implode(", ", $setters);
			$qstr		.= " WHERE handle = ?";
			
			$stmt		= $connection->prepare($qstr);
			
			for($i = 0; $i < count($columns); $i++)
			{
				$column = $columns[$i];
				
				if($column == 'state')
					$value = serialize($this->state);
				else
					$value = $this->{$columns[$i]};
				
				$stmt->bindValue($i + 1, $value);
			}
			
			$stmt->bindValue(count($columns) + 1, $this->handle);
		}
		
		$stmt->execute();
	}
	
	public function destroy()
	{
		$connection		= Connection::$instance;
		$name			= $connection->tablePrefix . Schema::TABLE_NAME;
		
		$stmt			= $connection->prepare("DELETE FROM $name WHERE handle = ?");
		$stmt->bindValue(1, $this->handle);
		$stmt->execute();
	}
}
