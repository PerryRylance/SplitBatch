# Split Batch

This library provides a means to do large batch processing when unix cron jobs aren't necessarily available.

This is useful when you need to process a large amount of data, or carry out a time consuming task, where cron isn't available, such as on low tiered shared hosting.

## Overview

The library provides a base class for you to extend from which you can use to manage imitated cron jobs, or split batch jobs.

A base class is provided to define batch jobs.

A daemon is used to manage these jobs.

The daemon will *run* jobs. A single *run* will usually consist of multiple *iterations*.

The daemon times each run, records the number of iterations, and will manage the frequency at which the job runs, limit iterations where needed, and limit the elapsed time for any given run. A run will terminate when either the maximum iterations per run is reached, or, the maximum period in milliseconds has elapsed.

## Requirements

- PHP >= 7.1 (for Doctrine)
- MySQL >= 5.6.4 (for fractional seconds)

## Requirements (Testing)

- WordPress installation one directory up
- max_allowed_packet > 5M in your MySQL ini file

The test file enclosed calculates the first 50,000 prime numbers, and fills a CSV out with this data in the databases memory. Once this has been completed the result will be written to disk. This is a large amount of data, and will need a higher packet size.

In practical applications, it's recommended that you implement a strategy for working with file pointers or similar, rather than working with the entire dataset in your jobs state.

## Requirements (Building)

- You'll need to run `npm install` to install the node modules used to build this libraries autoloader, and run `gulp` on the command line to watch PHP files and adjust the class autoloader on the fly.

## Installation
I recommend using Composer to install this package.

## Extensibility

These instructions assume that you are using WordPress, which is presently the only supported framework.

The library uses Doctrine for database interaction. There is potential to expand on the `Connection` class in this library to add more integrations, any database which Doctrine can connect to can be used with this library.

## Usage

Please see the example in `test.php` for step by step implementation instructions.

### Database connectivity

This library uses Doctrine for database interaction and can potentially be compatible with any database which Doctrine can work with.

`Connection::$instance` must hold an instance of `\Doctrine\DBAL\Connection` in order for the library to function.

A convenience function is provided for working in a WordPress environment:

`\PerryRylance\SplitBatch\Connection::fromWordPress()` will initialise `Connection::$instance` based on the constants (and table prefix) defined in `wp-config.php`. Therefore, this connection must be set up *after* WordPress' config file has loaded.

### Starting the daemon

This library uses a daemon which oversees setting up the database tables and actually running jobs.

After setting up your `Connection`, you need to instantiate the daemon.

`$daemon = new \PerryRylance\SplitBatch\Daemon();`

The daemon will initialise the database tables if necessary.

### Defining a Job

1. Subclass `\PerryRylance\SplitBatch\Job` to start working with the library.
2. You need to implement the abstract function `init` on your subclass of `Job`.
    - Setting `frequency` (milliseconds) on your `Job` adjusts the minimum number of milliseconds before a `Job` can be run again.
    - Setting `max_iterations` on your `Job` adjust the maximum number of iterations a `Job` is allowed to do in one run.
    - Setting `max_period` on your `Job` defines a time limit for the run.
    - The `state` property on your `Job` is where you need to hold the state of your iterator, for instance, which row of a spreadsheet you are presently working with, a byte cursor representing a file pointer, or anything else you need to keep track of the state of any given job.
3. You need to implement the abstract function `iterate` on your subclass of `Job`. This function is where the work will be done.
    - Your iteration function should call `success` or `abort` once is has reached a completed state, whether it completed successfully or failed.
    - Your iteration function should `return false` if it wants iteration to stop. This can be used in conjunction with the above, or, it can be used to cease iteration without destroying the job when you want to stop iterating for the current run based on some kind of custom condition.
    - Once a job completes, whether successful or aborted, the job will be removed from the database. You can override `success`, `abort` or `complete` to take any actions you need at this point (for example, logging).

### Instantiating your Job

4. Use the static method `Job::create` to create an instance of your job. You must pass the fully qualified class name of your job, followed by a *handle* which will be used to uniquely identify this instance of the job.
    - If a job with this handle doesn't exist already, it will be created and `init` will be called on the job.
    - If a job with this handle already exists, the instance data will be loaded from the database, the class will be instantiated and you'll receive the existing job from the database. This isn't usually necessary, but is provided as a convenience. It's recommended that you implement all logic within your subclass itself rather than working on the object externally.

### Running the daemon

5. Call `$daemon->run();` on the daemon you previously created. The daemon will run all applicable jobs, managing limiting iteration, timing and scheduling along the way.

### Running the example

If you would like to run the example included with this library, please have a valid WordPress installation set up with your web server serving it up.

Extract or clone this repository into a subfolder one level below your web root, then visit `/SplitBatch/test.php` on your server.

The test will read an input CSV which currently contains 50,000 rows. It will then start figuring out the n-th prime number for each row.

The test script will reload itself every 1 second to trigger another iteration until the job has completed and all 50,000 primes have been found.

At this point the result is written to `test-result.csv` and downloaded by the browser.

Depending on the performance of the server this is run on, you'll probably see a high number of iterations (up to 10,000) at first, possibly completing before the self imposed 1,000ms default limit on execution time.

As the job progresses, you'll likely see that the 1,000ms limit is being hit with below 10,000 iterations completing. Because of the nature of the algorithm used to find primes in this example, this will get progressively slower until the job completes.