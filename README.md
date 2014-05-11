PHPJQ
=====

... is a lightweight PHP asynchronous job queue.

It's in super alpha at the moment (ie. hot off the keyboard), so don't expect to use it in production for a little while.

## You should use PHPJQ if...
- You _hunt bugs for pleasure_.
- You are in need of a lightweight asynchronous job queue that is highly portable.
- You don't need distributed jobs.
- Installing a *proper* job / message queue isn't desirable.


## Introduction

PHPJQ is based on a simple dispatcher -> worker architecture.


```
Dispatcher ----> Server ----> Worker -\
                                      |
                               Do Something Slow
                                      |
Receiver ->---<- Server ----<---------/
```

Functions which block, and may take a long time to return, can be passed off to the worker, which then persists the result in the SQLite database. When you are ready, you can check the status of the job, and retrieve the return value.

I wrote it for use with Deploy WP (unreleased) - it's role being to manage long-running server provisioning jobs, whilst allowing the admin interface to remain responsive. It's intended for use in a WordPress context, but it **does not depend on WordPress in any way**, and should be good for any PHP project.

## Requirements

PHPJQ was designed to be lightweight, and easy to set up. That said, it does have a few dependencies:

- PHP >= 5.3.2
- PHP SQLite3 Extension
- Debian / Ubuntu - other \*nix environments may work, but aren't tested. *Why not help out?*
- PHP5-CLI

## Quickstart

### 1.1 Installation (Composer)

- Add `"talss89/phpjq":"dev-master"` as a dependency to your composer.json
- Run `composer install`

### 1.2 Installation (Non-Composer)

- Download / clone from GitHub
- Include in your project, all files in the `class\` directory

### 2. Set up the worker

The worker is simply a PHP-CLI script, which will consume and process all outstanding jobs, and store the result in the SQLite DB. 

You register methods with the worker, which are run against jobs matching the method name.

It's always called from the command line, and never accessed directly. Leave execution of the worker script to PHPJQ.

- First, copy the worker-example.php template contained in this repo to a location of your choice. Make sure all the PHPJQ classes are accessible from the worker script. Either include your Composer autoload.php file, **or** include the 3 files in `class\` manually.
- Now you're ready to add methods. Let's add a simple "Hello World" job. Just after the line `$worker = new PHPJQ_Worker($argv[1], $argv[2]);` add:

```php
$worker->register("hello-world", function($params){
    return "Hello World!";
});

```

The first parameter to `PHPJQ_Worker::register()` is the method name. The second is either a callback or inline function that should be run when the job is executed. This is where slow / long running code should go. Any data returned from the function is saved as the result of the job in the job queue, whilst the only argument, `$params`, contains any input data passed to the job.

You can add as many methods as you like, but calls to `PHPJQ_Worker::register()` must be made before `PHPJQ_Worker::start()` is called.

### 3. Set up the server class

In order to dispatch jobs and receive the result, you must create an instance of the `PHPJQ_Server` class. *This should be in a separate script to the worker*.

The server class constructor takes two parameters: 

```php
$server = new PHPJQ_Server($path_to_database, $path_to_worker);
```

Specify the **fully qualified path** to both the SQLite3 database (if the file does not exist, it will be created), and the worker script.

### 4. Dispatch a job

Use the `PHPJQ_Server::dispatch()` method to send jobs to the queue.

The `dispatch()` method accepts one parameter; an instance of `PHPJQ_Job`.

Here's an example of posting a job, which will run our Hello World method.

```php
$job_id = $server->dispatch(new PHPJQ_Job("hello-world", array("some" => "data")));
```

Oddly enough, the job ID is now held in `$job_id`. This function won't block, so shouldn't hold up execution.

### 5. Get the result!

Now we can poll the job queue to get the status of the job, and if complete, receive the return data.

It's easy. Assuming `$job_id` is set to a valid pre-existing job number, we can do:

```php
if($server->is_complete($job_id)
    $data = $server->recieve($job_id);
```

Just bear in mind, that dispatching a job, then checking for completion in the same request is probably pointless. It's unlikely the job will have finished in such a small time. The job id is intended to be passed between requests and polled to indicate completion.

## TODO / Problems

**PHPJQ is nowhere near production ready**

- Allow multiple concurrent workers (some of the code is already in place, but untested)
- There's no way of telling if a job has failed
- Test under heavy load
- Write unit tests
- Write API documentation


