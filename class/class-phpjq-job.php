<?php

class PHPJQ_Job {

    public $method = null;
    public $data = null;
    public $id = null;
    public $return = null;

    function __construct($method, $data, $id = false, $return = false) {
        if ($id)
            $this->id = $id;
        
        if($return)
            $this->return = $return;
        
        $this->method = $method;
        $this->data = $data;
    }

}
