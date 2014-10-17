<?php
require_once 'session.php';
require_once 'db.php';
$title = "Registration";
include('header.php');
if($curusr !== NULL){
	echo '<h1>No Knowledge Auth Registration</h1>
	<p>You&apos;re already signed up! <a href=".">Take me to the home page</a></p>';
	include('footer.php');
	exit;
}elseif(getUser($certid, false) != NULL){ //Waiting user approval
	echo '<h1>No Knowledge Auth Registration</h1>
	<p>Your request to add this cert to an existing account is waiting for approval. Log in with another device to approve it.</p>';
	include('footer.php');
	exit;
}elseif(getUser($certid, true, false) != NULL){ //Waiting admin approval
	echo '<h1>No Knowledge Auth Registration</h1>
	<p>Your request to add a new user is waiting for admin approval.</p>';
	include('footer.php');
	exit;
}
if($_SERVER['REQUEST_METHOD'] !== 'POST'){ //No form submission
?>
<h1>No Knowledge Auth Registration</h1>
<?php
	if($certid === NULL){
?>
<p class="text">To sign up, you need to first get a certificate. You can do that <a href="getacert">on this page</a>.</p>
<p class="text">Then visit this page again selecting your certificate when prompted. If you already got a certificate but are still seeing this message, try closing and re-opening your browser.</p>
<?php
	}else{
?>
<p class="text">Your current certificate is issued to "<?php echo htmlspecialchars($_SERVER["SSL_CLIENT_S_DN"]); ?>" and issued by "<?php echo htmlspecialchars($_SERVER["SSL_CLIENT_I_DN"]); ?>"</p>
<p class="text">If you want to sign up with this certificate that you are currently using, fill out and submit the following form:</p>
<p class="text">Make sure your printer is ready, because after we create your account, we'll give you a recovery code to print out in case you lose your computer or devices with your certificates on them.</p>
<p class="text">We request your address because in case you lose the printed recovery code, for a small fee, we can mail you a replacement.</p>
<form method="post" action="register">
<table>
<tbody>
<tr><td>Username:</td><td><input type="text" name="username"></td></tr>
<tr><td>Address:</td><td><input type="text" name="address"></td></tr>
<tr><td>City:</td><td><input type="text" name="city"></td></tr>
<tr><td>State:</td><td><input type="text" name="state"></td></tr>
<tr><td>Zip code:</td><td><input type="text" name="postcode"></td></tr>
<tr><td>Country:</td><td><input type="text" name="country" value="USA"></td></tr>
<tr><td></td><td><input type="submit" value="Sign up"></td></tr>
</tbody>
</table>
<?php echo getCSRFinputcode(); ?>
</form>

<?php
	}
}else{ // Form submission
	if(!passesCSRFcheck()){
		die("failed CSRF check! Cookies are required to sign up for this web application.");
	}
	if(!ctype_alnum($_POST['username'])){ //Username must be alphanumeric
		die("Must provide an alphanumeric username!");
	}
	if(userExists($_POST['username'])){ //User already created
		die("This user already exists!");
	}
	$certid = getCertId();
	if($certid === NULL){ //No client cert
		die("You must use a client certificate when signing up!");
	}
	if(!(isset($_POST['username']) and isset($_POST['address']) and isset($_POST['city']) and 
			isset($_POST['state']) and isset($_POST['postcode']) and isset($_POST['country']))){
		die("Must fill out all fields!");
	}
	//OK, let's do this!
	$resetcode = addUser($_POST['username'], $_POST['address'], $_POST['city'], $_POST['state'], $_POST['postcode'], $_POST['country']);
	associateKey($_POST['username'], $certid);
?>
<h1>Congratulations!</h1>
<p class="text">You have been signed up.</p>
<p class="text">Print and save the following recovery code:</p>
<h2><?php echo $resetcode; ?></h2>
<p class="text">If you lose access to the keys associated with your account, this recovery code is the only way to regain access to your account without costing you money!</p>
<p class="text"><a href="index">I printed it. Now take me to the home page!</a></p>
<?php
}
include('footer.php');