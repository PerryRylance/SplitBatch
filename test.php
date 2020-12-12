<?php

namespace PerryRylance\SplitBatch;

?><!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="refresh" content="1">
	</head>
	<body><?php

// Load 3rd party libraries and use our local class autoloader
require_once('vendor/autoload.php');
require_once('autoload.php');

// Load database credentials from a WordPress installation one folder up
require_once('../wp-config.php');

// Let's define a class for our job. This class will read a CSV, and for each row in the CSV it will figure out the n-th prime number and fill out the second column. Once the job completes, the result will be written to disk.
class ExampleJob extends Job
{
	protected function init()
	{
		// Allow this job to do up to 10,000 iterations per run
		$this->max_iterations = 10000;
		
		// Set up the initial state
		$this->state	= (object)[
			'filename'	=> 'test.csv',
			'csv'		=> [],
			'row'		=> 0,
			'counter'	=> 0
		];
		
		// Load CSV data. In practise, it's recommended that you use file pointers rather than storing the entire file in memory
		$fh				= fopen($this->state->filename, 'r');
		
		while($row = fgetcsv($fh, pow(2, 16)))
			$this->state->csv []= $row;
		
		// Let's keep track of which row we are on with regards to our CSV data
		$this->state->row		= 0;
		
		// Let's also keep track of a counter which will be used to find prime numbers
		$this->state->counter	= 0;
		
		fclose($fh);
	}
	
	public function run()
	{
		$start			= Clock::milliseconds();
		$iterations		= Parent::run();
		$elapsed		= Clock::milliseconds() - $start;
		
		$percent		= round( $this->state->row / count($this->state->csv) * 100 );
		
		echo "Did $iterations iterations in $elapsed ms - $percent% complete";
	}
	
	protected function iterate()
	{
		$row			= $this->state->csv[$this->state->row];
		
		if($this->isPrime($this->state->counter))
		{
			$this->state->csv[$this->state->row][1]	= $this->state->counter;
			
			if(++$this->state->row >= count($this->state->csv))
			{
				$this->success();
				return false;
			}
		}
		
		$this->state->counter++;
	}
	
	protected function success()
	{
		$filename		= pathinfo($this->state->filename, PATHINFO_FILENAME) . '-result.csv';
		
		$fh				= fopen($filename, 'w');
		$count			= count($this->state->csv);
		
		for($i = 0; $i < $count; $i++)
			fputcsv($fh, $this->state->csv[$i]);
		
		fclose($fh);
		
		ob_clean();
		
		header('Content-Disposition: attachment');
		header('Content-Type: text/plain');
		
		echo file_get_contents($filename);
		
		Job::success();
		
		exit;
	}
	
	private function isPrime($number)
	{
		for ($i = 2; $i < $number; $i++)
		{
			if($number % $i == 0)
				return false;
		}
		
		return $number > 1;
	}
}

Connection::fromWordPress();

$daemon			= new Daemon();
$job			= Job::create('\PerryRylance\SplitBatch\ExampleJob', 'example-job');
$daemon->run();

?>
	</body>
</html>