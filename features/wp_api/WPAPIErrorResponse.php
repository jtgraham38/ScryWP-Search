<?php

//this class creates a unified response object for errors from the api
class ScryWp_WPAPIErrorResponse{

    public $error;                  //contains the error object, if any
    public string $error_msg;       //contains the error message, if any
    public string $error_code;      //contains the error code, if any
    public string $error_source;    //the source of the error (the scrywp api or the wordpress ap)

    //constructor
    public function __construct($error, $error_msg, $error_code, $error_source = "wp"){
        $this->error = $error;
        $this->error_msg = $error_msg;
        $this->error_code = $error_code;
        $this->error_source = $error_source;
    }

    //define behavior when returned by the api
    public function __toString(){
        return new WP_REST_Response([
            'error' => $this->error,
            'error_msg' => $this->error_msg,
            'error_code' => $this->error_code,
            'error_source' => $this->error_source
        ]);
    }

}