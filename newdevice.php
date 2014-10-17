<?php
require_once('db.php');
require_once('session.php');
require_once('computer_info.php');
$inactiveusr = getUser($certid, false);
$title = "New Device";
include('header.php');
?>
<h1>Easier Secure Cloud - Add device to account</h1>
<?php
if($certid === NULL){ //No cert!
?>
<p>First you need to get a certificate for this device. If you need a new one, you can <a href="getacert">get one here</a>. If you have one but didn't select it when loading this page, try closing and re-opening your browser, and be sure to select it when you load this page.</p>
<?php
}elseif($curusr !== NULL){
	echo '<p>You are already set up! Your username is ' . htmlspecialchars($curusr) . '.</p>';
}elseif($inactiveusr !== NULL){
	echo '<p>You have already requested to activate this certificate for user ' . htmlspecialchars($inactiveusr) . '.</p>';
}else{
	if($_SERVER['REQUEST_METHOD']==='POST'){
		if(!passesCSRFcheck()){
			die("Failed CSRF check. Cookies must be enabled for this site to work.");
		}
		if($_POST['action'] == 'newdevice'){
			//put in a request for a new device
			requestKeyAdd($_POST['username'], $certid, $certid);
			echo "<p>Your request has been submitted. Please log in from an active device to approve this request.</p>
	<p>Then head to the <a href=\".\">home page</a> and you'll be logged in!</p>";
			include('footer.php');
			exit;
		}
	}
?>
<form method="post" action="newdevice"> 
<input type="hidden" name="action" value="newdevice">
<p class="text">If you already have an existing account and want to add this device or certificate to it, enter your username and click Submit to put in an account access request.</p>
<p class="text"><input type="text" name="username" value=""> <input type="submit" value="Submit"></p>
<p class="text">Device making request: <code><?php echo htmlspecialchars(getComputerInfo($_SERVER["HTTP_USER_AGENT"]));?></code></p>
<p class="text">Certificate subject: <code><?php echo htmlspecialchars(str_replace(',', ' ', $_SERVER["SSL_CLIENT_S_DN"]));?></code></p>
<p class="text">Certificate issuer: <code><?php echo htmlspecialchars(str_replace(',', ' ', $_SERVER["SSL_CLIENT_I_DN"]));?></code></p>
<p class="text">Once you submit your request, you'll have to log in from one of your already-set-up devices to approve it.</p>
<?php echo getCSRFinputcode();?>
</form>
<?php
}
include('footer.php');