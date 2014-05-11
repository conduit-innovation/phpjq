<?php

class PHPJQ_Worker {

    private $worker_id = null;
    private $server = null;
    private $methods = array();
    private $db_name = null;

    function __construct($worker_id, $db_name) {
        $this->worker_id = (int) $worker_id;
        $this->db_name = $db_name;
    }

    public static function validate_request() {
        global $argv;

        if (php_sapi_name() !== 'cli')
            return false;

        if (count($argv) != 3)
            return false;

        return true;
    }

    private function consume() {

        while ($job = $this->get_unallocated_job_or_free()) {

            if (isset($this->methods[$job->method])) {
                $result = call_user_func($this->methods[$job->method], json_decode($job->data));

                $this->job_success($job, $result);
            } else {
                /**
                 * @TODO: Report failure - method doesn't exist. Push back job if < max_retries.
                 */
                $this->job_failure($job);
            }
        }

        die("worker finished");
    }

    private function job_success($job, $result) {
        $this->server->db->exec("BEGIN EXCLUSIVE TRANSACTION;");

        $q = $this->server->db->prepare("UPDATE phpjq_jobs SET running = 0, worker = :worker, return = :return WHERE id = :id");
        $q->bindValue(":id", $job->id);
        $q->bindValue(":worker", $this->worker_id);
        $q->bindValue(":return", json_encode($result));
        $q->execute();

        $this->server->db->exec("COMMIT TRANSACTION;");
    }

    private function job_failure($job) {
        die("FAIL");
    }

    private function get_unallocated_job_or_free() {

        /**
         * Atomic ownership - should prevent races under load
         */
        $this->server->db->exec("BEGIN EXCLUSIVE TRANSACTION;");

        $job = $this->server->db->querySingle("SELECT * FROM phpjq_jobs WHERE running = 0 AND worker = -1", true);

        /*
         * Set false if query failed, or no jobs in queue
         */

        $job = !$job || count($job) < 1 ? false : $job;

        if ($job) {

            $q = $this->server->db->prepare("UPDATE phpjq_jobs SET running = 1, worker = :worker WHERE id = :id");
            $q->bindValue(":id", $job['id']);
            $q->bindValue(":worker", $this->worker_id);
            $q->execute();

            
        } else {
            
            /*
             * There's no point wasting resources if there's nothing to do, so free the worker and shut down
             * When another job is dispatched, the server will be forked again
             */

            $this->server->free_worker($this->worker_id);

        }

        if ($job) {
            $job = new PHPJQ_Job($job['method'] , $job['data'], $job['id']);
        }

        $this->server->db->exec("COMMIT TRANSACTION;");
        
        return $job;
    }

    public function start() {
        /*
         * Load the server object
         */

        $this->server = new PHPJQ_Server($this->db_name);

        /*
         * Insert this PID into phpjq_options worker_pid_<id>, and die if one is already running with same worker_id
         */

        if (!$this->server->register_worker($this->worker_id))
            die("failed");

        /*
         * Enter the consume loop
         */

        $this->consume();
    }

    public function register($fn, $cb) {
        $this->methods[$fn] = $cb;
    }

}
