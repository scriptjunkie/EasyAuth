<?php
header("X-Frame-Options: DENY"); // Clickjacking protection; included in all files
//redirect to HTTPS
if(!isset($_SERVER["HTTPS"]) || !$_SERVER["HTTPS"]){
	header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
}
?>
<!DOCTYPE html>
<html>
<head>
	<title>The Easier, Secure Cloud<?php
if($title){
	echo ' - ' . $title;
}?></title>
	<meta http-equiv="X-FRAME-OPTIONS" content="DENY">
	<meta http-equiv="content-type" content="text/html; charset=UTF-8">
	<link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>
<div id="wrap">
<div id="main">
<div class="header">
	<div style="text-align:center"><p class="head"> The Easier, Secure Cloud | <a href="readme">README</a> | <a href=".">Home</a>
<?php
//Show menu options to those who can use them
if(isset($certid) && getUser($certid) != NULL && !isTemporary($certid)){
	echo ' | <a href="profile">Manage profile</a>';
	if(isset($curusr) and isAdmin($curusr)){
		echo ' | <a href="admin">Admin Panel</a>';
	}
}
?>
</p></div> 
</div>
<div class="content">
<div class="caption"><?php
if($title){
	echo '<h2>' . $title . '</h2>';
}?>
</div>
<div class="innercontent">
