<?php

//exception class that remembers the response, to allow the coai api response to bubble up through the stack
class ScryWpSearch_ResponseException extends Exception{
    public $response;
    public $error_source;
    public function __construct($message = "", $response = null, $error_source = "wp"){
        parent::__construct($message);

        //decode the response if it is a string
        if (is_string($response)){
            $response = json_decode($response, true);
        }
        else{
            $this->response = $response;
        }
        $this->error_source = $error_source;
    }
}