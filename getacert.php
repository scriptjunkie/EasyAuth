<?php
require_once('db.php');
require_once('ca.php');
if($certid != NULL){
	header('Location: register');
	die("You are already using a certificate! ".$_SERVER["SSL_CLIENT_S_DN_CN"] . ' <a href="register">Go to registration.</a>');
}
if($_SERVER['REQUEST_METHOD'] == 'POST'){
	issueCert($_POST['username'], $_POST['key']);
	exit;
}
$title = 'Certificate Generation';
include('header.php');
?>
	<h1>Certificate Generation</h1>
	<h2>Modern browsers</h2>
	<p>Hit the Generate button and then install the certificate it gives you in your browser.</p>
	<p>All modern browsers (not Internet Explorer) should be compatible.</p>
	<form method="post">
		<keygen name="key">
		The username I want: <input type="text" name="username" value="" required pattern="[a-zA-Z0-9]+">
		<input type="submit" name="pubkey" value="Generate">

	</form>
	<strong>Wait a minute, then <a href="register">register for an account</a> to see your new cert in action!</strong>
	<h2>Internet Explorer</h2>
	<p>Internet Explorer does not natively support generating keys this way. Instead, follow the following steps to generate a certificate:</p>
	<ul>
	<li>Open powershell (Hold the Windows key and press R then let go, type <strong><code>powershell</code></strong> and hit enter)</li>
	<li>In the powershell window that appears, type <strong><code>New-SelfSignedCertificate -CertStoreLocation Cert:\CurrentUser\My -DnsName me</code></strong> and hit enter.</li>
	</ul>
	<p>Then close and re-open your browser and come back to this site.</p>
<?php
include('footer.php');
