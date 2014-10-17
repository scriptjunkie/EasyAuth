<?php
//Controls session variables, specifically for CSRF protection
//Session cookie good for one day, the whole site, the current domain, must be secure, and not JS-accessible
//This prevents against stealing CSRF tokens if the site is dual-served over HTTP.
session_set_cookie_params (60 * 60 * 24, '/', $_SERVER["HTTP_HOST"], true, true);
session_start();
//Gets the CSRF token. It's a 20 byte hex string (40 chars) that's as securely random as php gets.
function getCSRFtoken(){
	if(!isset($_SESSION['csrftoken'])){
		$_SESSION['csrftoken'] = bin2hex(openssl_random_pseudo_bytes(20));
	}
	return $_SESSION['csrftoken'];
}
//Returns the HTML to put into a form to send the CSRF token
function getCSRFinputcode(){
	return '<input type="hidden" name="csrftoken" value="'.getCSRFtoken().'">';
}
//Decides whether the current form submission included the CSRF token
function passesCSRFcheck(){
	$token = getCSRFtoken();
	return $_POST['csrftoken'] == $token; //Must be POST to avoid accidentally giving away CSRF token in URL
}
