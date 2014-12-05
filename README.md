workestra-php-sdk
===========

A PHP SDK library for the [Workestra API](https://www.workestra.co/developers/docs)

Quick Start
===========
You will need an API key to get started. You can find instructions on obtaining an API key [here](https://www.workestra.co/developers/docs#authentication)

Once you have that, the following code will get the latest notifications (as long as your user is able to access those notifications)

````
<?php
include "Workestra/workestraSDK.php";

use \Workestra\WorkestraSDK;

$workestra = new WorkestraSDK();
$workestra->setApiKey("{YOUR-API-KEY}");
$response = $workestra->listNotifications();
if($response->isError()) {
   trigger_error("Error communicating with Workestra: " . $response->showErrorMessage());
}
$json = $response->getContent();
...
?>
````
After that, you may want to explore the stream [api](https://www.workestra.co/developers/docs#sream), or just look through the wrapper code.
