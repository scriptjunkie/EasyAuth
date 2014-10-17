<?php 
require_once('db.php');
require_once('session.php');
$title = 'Account recovery';
include('header.php');
if($_SERVER['REQUEST_METHOD'] === 'POST' && passesCSRFcheck()){
	$code = doRecover($_POST['username'], $_POST['resetcode']);
	if($code){
		echo '<h1>Congratulations!</h1>';
		echo '<p>You have successfully reset your account.</p>';
		echo '<h1>Your new reset code is: ' . $code . '</h1>';
		echo '<p>Once you have printed your new recovery code, you can head back to the <a href=".">home page</a>.</p>';
	}
}elseif($curusr !== NULL){
	echo '<p>You do not need a reset, you are already logged in!</p>
	<p>If you are using multi-factor authentication and want to use this as one of the factors to add a new device, first you need to request adding your new certificate to your existing account from the new device, then approve the request from here, and then you can use a saved or mailed recovery code for the last factor.</p>';
}elseif($certid === NULL){
?>
<h1>Account recovery</h1>
<p>Have you lost access to your account and all your keys? If you still have your recovery code you printed out when you signed up, or can receive mail to the address you used when you signed up, you can get it back.</p>
<h2>Use a recovery code</h2>
<p>Step 0. Find the recovery code you used when you signed up, and ensure you are on a computer or device you can print from; you will be issued a new recovery code when you reset your account.</p>
<p>Step 1. Get a certficate at <a href="getacert">getacert</a></p>
<p>Step 2. Come back to this page with your certificate and submit your recovery code with your username.</p>
<h2>Have a recovery code mailed to you.</h2>
<p>To have a recovery code mailed to you, you will need to pay $10 to cover expenses and the admins' time.</p>
<p>Send $10 to the admin email address <strong><?php echo ADMINEMAIL;?></strong> from this link: <a href="https://www.paypal.com/us/sendmoney?amount=10.00&payment_type=Goods">https://www.paypal.com/us/sendmoney?amount=10.00&payment_type=Goods</a> Include your username and address in the PayPal message, so the admins can verify who initiated the reset.</p>
<?php
}else{
?>
<h1>Account recovery</h1>
<p>Have you lost access to your account and all your keys? If you still have your recovery code you printed out when you signed up, or can receive mail to the address you used when you signed up, you can get it back.</p>
<p class="text"><strong>Note: if you have multi-factor authentication enabled, your recovery code can only function as one factor. First you need to request adding your new certificate to your existing account, then you need to use one or more of your devices to approve the request, and then you can use a saved or mailed recovery code for the last factor.</p>
<h2>Use a recovery code</h2>
<p>Step 0. Find the recovery code you used when you signed up, and ensure you are on a computer or device you can print from; you will be issued a new recovery code when you reset your account.</p>
<p><del>Step 1. Get a certficate at <a href="getacert">getacert</a></del> You already have a certificate.</p>
<p>Step 2. Submit your username and recovery code in the following form:</p>
<form method="post" action="recovery">
<?php echo getCSRFinputcode(); ?>
<table>
<tbody>
<tr><td>Username:</td><td><input type="text" name="username"></td></tr>
<tr><td>Reset code:</td><td><input type="text" name="resetcode"></td></tr>
<tr><td></td><td><input type="submit" value="Recover account"></td></tr>
</tbody>
</table>
</form>
<h2>Have a recovery code mailed to you.</h2>
<p>To have a recovery code mailed to you, you will need to pay $10 to cover expenses and the admins' time.</p>
<p>Send $10 to the admin email address <strong>scriptjunkie@scriptjunkie.us</strong> from this link: <a href="https://www.paypal.com/us/sendmoney?amount=10.00&payment_type=Goods">https://www.paypal.com/us/sendmoney?amount=10.00&payment_type=Goods</a> Include your username and address in the PayPal message, so the admins can verify who initiated the reset.</p>
<?php
}
include('footer.php');