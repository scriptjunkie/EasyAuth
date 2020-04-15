<?php
define('NOCONNECT',true); //Don't autoconnect to the DB
require_once('db.php');
require_once('ca.php');
$title = 'Setup';
include('header.php');
if($_SERVER['REQUEST_METHOD']==='POST'){
	if(!file_exists('config.php')){
		if(isset($_POST['rootpass'])){
			setupDB($_POST['dbhost'], $_POST['rootpass'], $_POST['adminemail'], $_POST['adminapproval']);
		}else{
			setupDB(NULL, NULL, NULL, NULL);
		}
		setupCA();
		echo '<h1>Congratulations!</h1>
	<p class="text">Your EasyAuth base system is set up.</p>';
		if(getUser(GetCertId()) == NULL){
			echo '<p class="text"><a href="register">Go to registration</a> to complete setup.</p>';
		}
	}else{
		setupDB(NULL, NULL, NULL, NULL);
	}
}else{
?>
<h1>EasyAuth setup</h1>
<p class="text">To set up this authentication system, you first need a mysql database. You will then either need to manually create a config.php or run this installer with the database host and root password. You must then register; as the first user you will be granted admin permissions.
<h2>Setup with mysql root DB account:</h2>
<form method="post" name="setupform" action="setup">
<table style="text-align: right">
<tbody>
<tr><td>Database host:</td><td><input type="text" name="dbhost" value="localhost"></td></tr>
<tr><td>DB root password:</td><td><input type="password" name="rootpass" value=""></td></tr>
<tr><td>Admin email:</td><td><input type="email" name="adminemail" value=""></td></tr>
<tr><td>Require admin approval for new accounts:</td><td><input type="checkbox" name="adminapproval"></td></tr>
<tr><td></td><td><input type="submit" value="Setup"></td></tr>
</tbody>
</table>
</form>
<hr>
<h2>Setup with manual config.php:</h2>
<?php
if(file_exists('config.php')){
?>
<form method="post" name="setupform" action="setup">
<table>
<tbody>
<tr><td><input type="submit" value="Setup"></td></tr>
</tbody>
</table>
</form>
<?php
}else{
	echo '<p class="text">You have not created a config.php file yet.</p>';
}
}
include('footer.php');
