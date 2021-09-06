<?php
require_once "webapi.php";

$restful_str =  substr($_SERVER["REQUEST_URI"],strlen($_SERVER["SCRIPT_NAME"]));
$restful_token = "";
$restful_token = explode("&" , $_SERVER["QUERY_STRING"]);
$func = $restful_token[0];
new WebAPI($func);
exit();