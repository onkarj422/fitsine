<?php

class ReportError {

    public function __construct()	{
        
    }

    public function sendMail($errmessage, $errno, $function) {
    	$to = "onkarj422@gmail.com";
    	$subject = "Error Report from Fitsine";
    	$message = "Following error occured in function ".$function." -\n\n".$errno." : ".$errmessage.".";
    	mail($to,$subject,$message);
    }
}

?>