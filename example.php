<?php

include "workestra/workestraSDK.php";
use \Workestra\WorkestraSDK as Workestra;

$workestra = new Workestra\WorkestraSDK();
//Users can create an api key in workestra, see https://www.workestra.co/developers/docs#authentication for more details.
$workestra->setApiKey("{YOUR-API-KEY}");
//or you can use the login method to obtain the api key
$workestra->setBasicAuth("{YOUR-EMAIL}","{YOUR-PASSWORD}");

//Get a list of the recent notifications
$response = $workestra->listNotifications();

//$response is an object representing the HTTP response. Errors can be detected by testing the statusCode:
if($response->statusCode < 200 || $response->statusCode > 299) {
	trigger_error("Login Failure");
}
//or by using $response->isError()
if($response->isError()) {
	trigger_error("Login Failure");
}

$json = $response->getContentJSON();
