<?php 
require_once('db.php');
$title = 'Home';
include('header.php');
if($certid === NULL){
?>
<h1>No Knowledge Authentication</h1>
<h2>The Easier Secure Cloud</h2>
<p class="text">You don't seem to be using a certificate, which is required to use this site.</p>
<p class="text">To sign up, add a new device to your account, or recover an existing account, you need to first get a certificate. You can do that <a href="getacert">on this page</a>.</p>
<p class="text">If you have a certificate but are still getting this message, try restarting your browser and selecting your certificate when prompted. In the rare event you still don't see a certificate, you might have an older or incompatible browser. Try installing <a href="https://google.com/chrome">Google Chrome</a> or <a href="https://www.mozilla.org/en-US/firefox/new">Mozilla Firefox</a>.</p>
<?php
}elseif($curusr === NULL){
?>
<h1>New Certificate</h1>
<?php
if(getUser($certid, true, false) != NULL){ //Waiting admin approval
	echo '<p class="text">Your request to add a new user is waiting for admin approval.</p>';
}elseif(getUser($certid, false) != NULL){
	echo '<p class="text">Your request to add this certificate to an existing account is still pending. Log in from an already registered device to approve this request.</p>';
}else{
	echo '<p class="text">It seems you are using an unregistered certificate we do not know about yet.</p>
<p class="text">You can <a href="register">sign up here</a> for a new account with this certificate</p>
<p class="text">Or if you want to add this certificate to an existing account, you can <a href="newdevice">do so here</a></p>';
}
?>
<p class="text">If you have lost access to all your account keys and want to recover it, you can <a href="recovery">do so here</a></p>
<p class="text">If you have multi-factor authentication enabled, recovery will require at least one device. First you need to request adding your new certificate to your existing account, then you need to use one or more of your devices to approve the request, and then you can use a saved or mailed recovery code for the last factor.</p>
<?php
}else{
	echo '<h1>Welcome, ' . $curusr . '!</h1><p class="text">You are successfully logged in.</p>';
	$expirydate = isTemporary($certid);
	if($expirydate === false){
		echo '<p class="text">Want to <a href="profile">manage your devices and profile</a>?</p>';
	}else{
		echo '<p class="text">You have been granted temporary access. You cannot make any account changes, and you will lose access at '.$expirydate.'</p>';
	}
}
include('footer.php');
