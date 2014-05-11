<?php

class PHPJQ_Server {

    public $db_name = null;
    public $worker_count = null;
    public $worker_path = null;
    public $db = null;
    public $config = null;

    function __construct($db_name, $worker_path = false, $worker_count = false) {
        $this->db_name = $db_name;
        $this->worker_path = $worker_path;

        $this->db = new SQLite3($this->db_name);

        $options_exist = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='phpjq_options';");

        if (!$options_exist || !$options_exist->fetchArray())
            $this->install();

        if ($worker_count)
            $this->set_worker_count($worker_count);

        $this->config = $this->db->query("SELECT * FROM phpjq_options;")->fetchArray();
    }

    public function dispatch($job) {
        $q = $this->db->prepare("INSERT INTO phpjq_jobs (method, data, running, worker) VALUES(:method, :data, 0, -1);");
        $q->bindValue(":method", $job->method);
        $q->bindValue(":data", json_encode($job->data));
        $q->execute();

        $this->ensure_running();

        return $this->db->lastInsertRowID();
    }

    public function is_complete($id) {
        $q = $this->db->prepare("SELECT * FROM phpjq_jobs WHERE id = :id LIMIT 1");
        $q->bindValue(":id", $id);
        $job = $q->execute()->fetchArray();

        if (!$job)
            return false;

        if ($job['worker'] != -1 && $job['running'] != 1)
            return true;

        return false;
    }

    public function recieve($id) {
        $q = $this->db->prepare("SELECT * FROM phpjq_jobs WHERE id = :id LIMIT 1");
        $q->bindValue(":id", $id);
        $job = $q->execute()->fetchArray();

        if (!$job)
            return false;

        if ($job['worker'] == -1 || $job['running'] == 1)
            return false;

        $q = $this->db->prepare("DELETE FROM phpjq_jobs WHERE id = :id");
        $q->bindValue(":id", $id);
        $q->execute();

        return new PHPJQ_Job($job['method'], json_decode(stripslashes($job['data'])), $job['id'], json_decode(stripslashes($job['return'])));
    }

    public function ensure_running() {
        if (!$this->is_running(0))
            $this->spawn_worker(0);
    }

    public function is_running($worker_id = 0) {

        $key = "worker_pid_" . $worker_id;

        $q = $this->db->prepare("SELECT value FROM phpjq_options WHERE key = :key");
        $q->bindValue(":key", $key);
        $result = $q->execute()->fetchArray();

        if (!$result)
            return false;

        $pid = $result[0];

        exec('ps ' . $pid, $output, $exit);

        if (count($output) == 2) {
            return true;
        }

        return false;
    }

    /**
     * Installs the DB schema
     */
    private function install() {

        /*
         * Create the options table
         */

        $this->db->exec("CREATE TABLE phpjq_options (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            key TEXT NOT NULL,
            value TEXT
          );");

        $this->db->exec("INSERT INTO phpjq_options (key, value) VALUES(\"worker_count\", \"1\");");

        /*
         * Create the jobs table
         */

        $this->db->exec("CREATE TABLE phpjq_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            method TEXT NOT NULL,
            data TEXT NOT NULL,
            return TEXT,
            running BOOL,
            worker INTEGER
          );");
    }

    private function spawn_worker($worker_id = 0) {
        /**
         * @TODO: Foreach worker...
         */
        if(!$this->worker_path || !file_exists($this->worker_path))
            return false;
        
        $pid = array();

        exec(sprintf("%s > %s 2>&1 & echo $!", "php " . $this->worker_path . " " . $worker_id . " '" . $this->db_name . "'", dirname(__FILE__) . "/out.tmp"), $pid);
        
        $pid = $pid[0];

        return true;
    }

    private function set_worker_count($count) {

        if ($count < 1)
            $count = 1;

        $this->worker_count = $count;

        $this->db->exec("DELETE FROM phpjq_options WHERE key=\"worker_count\";");

        $q = $this->db->prepare("INSERT INTO phpjq_options ('key', 'value') VALUES(\"worker_count\", :count);");
        $q->bindValue(':count', $count, SQLITE3_INTEGER);
        $q->execute();


        return;
    }

    /**
     * Registers current process as $worker_id worker. Called only by PHPJQ_Worker internally.
     * @param integer $worker_id
     */
    public function register_worker($worker_id) {

        if (!is_int($worker_id))
            return false;

        $key = "worker_pid_" . $worker_id;

        /*
         * If an old worker is running, then return false and do nothing.
         */

        if ($this->is_running($worker_id))
            return false;

        /*
         * Otherwise, register the pid with the worker_id
         */

        $q = $this->db->prepare("DELETE FROM phpjq_options WHERE key=:key;");
        $q->bindValue(":key", $key);
        $q->execute();

        $q = $this->db->prepare("INSERT INTO phpjq_options ('key', 'value') VALUES(:key, :pid);");
        $q->bindValue(':key', $key);
        $q->bindValue(':pid', getmypid());
        $q->execute();

        return true;
    }

    public function free_worker($worker_id) {
        
        if (!is_numeric($worker_id))
            return false;

        $worker_id = (int) $worker_id;
        
        $key = "worker_pid_" . $worker_id;

        $q = $this->db->prepare("DELETE FROM phpjq_options WHERE key=:key;");
        $q->bindValue(":key", $key);
        $q->execute();

        return;
    }

}
