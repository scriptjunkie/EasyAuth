<?php
//This page is included in case you want to uninstall EZA
include('db.php');
if(!isset($curusr) or !isAdmin($curusr)){
	die("must be an admin to view this page.");
}
$title = "Destructor";
include('header.php');
function rrmdir($dir) {
	if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) {
			if ($object != "." && $object != "..") {
				if (filetype($dir."/".$object) == "dir"){
					rrmdir($dir."/".$object);
				}else{
					unlink($dir."/".$object);
				}
			}
		}
		reset($objects);
		rmdir($dir);
	}
}
if($_SERVER['REQUEST_METHOD']==='POST'){
	if(isset($_POST['rootpass'])){
		//Drops a database
		unlink('config.php');
		$dbname = "authdb";
		$newuser = "authuser";
		$newpass = base64_encode(openssl_random_pseudo_bytes(15));
		$rootsqli = new mysqli($_POST['dbhost'], "root",$_POST['rootpass'], "mysql");
		if($rootsqli->connect_error){
			die("Could not connect with root account with those creds: " . $rootsqli->error);
		}
		if(!$rootsqli->query("DROP DATABASE ".$dbname)){
			die("dropping database failed: " . $rootsqli->error);
		}
		if(!$rootsqli->query("DROP USER '".$rootsqli->escape_string($newuser)."'@'%'")){
			die("dropping user failed: " . $rootsqli->error);
		}
		$rootsqli->close();
		rrmdir('rootca');
	}
?>
<h1>Congratulations!</h1>
<p class="text">Your EasyAuth database and database user have been wiped.</p>
<a href="index">Go to home page</a>
<?php
}else{
?>
<h1>EasyAuth destruct</h1>
<p class="text">To wipe this system, you need to run this destructor with the database host and root password.</p>
<form method="post" name="setupform" action="wipe">
<table>
<tbody>
<tr><td>Database host:</td><td><input type="text" name="dbhost" value="localhost"></td></tr>
<tr><td>DB root password:</td><td><input type="password" name="rootpass" value=""></td></tr>
<tr><td></td><td><input type="submit" value="Destroy"></td></tr>
</tbody>
</table>
</form>
<?php
}
include('footer.php');
