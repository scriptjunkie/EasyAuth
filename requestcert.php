<?php
require_once('db.php');
if(isset($certid)){
	echo '1';
}
else
{
	header('Connection: close');
	echo '0';
}
