<?php 
require_once('db.php');
require_once('session.php');
require_once('computer_info.php');
if($curusr === NULL){
	die("You are not logged in currently.");
}
if(!isAdmin($curusr)){
	die("You are not an admin! You must be an admin to see this page.");
}
if(isTemporary($certid) !== false){
	die("You are using a temporary device and cannot make any changes.");
}
$title = 'Admin Panel';
include('header.php');
if($_SERVER['REQUEST_METHOD']==='POST'){
	if(!passesCSRFcheck()){
		die("Failed CSRF check. Cookies must be enabled for this site to work.");
	}
	if($_POST['action'] == 'delete'){
		if($_POST['username'] == $curusr){
			die("You can't delete yourself! You can only delete other users.");
		}
		deleteUser($_POST['username']);
	}elseif($_POST['action'] == 'promote'){
		setAdmin($_POST['username'], true);
	}elseif($_POST['action'] == 'activate'){
		setActive($_POST['username'], true);
	}elseif($_POST['action'] == 'demote'){
		if($_POST['username'] == $curusr){
			die("You can't demote yourself! You can only demote other admins.");
		}
		setAdmin($_POST['username'], false);
	}elseif($_POST['action'] == 'newreset'){
		$reset = newReset($_POST['username']);
		echo '<h1>New reset code for '.htmlspecialchars($_POST['username']).": $reset</h1>";
	}
}
?>
<h1>User Management</h1>
<form action="admin" method="post">
<p>Search: <input type="text" name="search" value=""></p>
<input type="submit" value="Go">
<?php echo getCSRFinputcode(); ?>
</form>
<table>
<thead>
<tr><th>Username</th><th>Address</th><th>City</th><th>State</th><th>Postal/zip code</th><th>Country</th><th>Admin</th></tr>
</thead>
<tbody>
<?php
$search = '';
if(isset($_POST['search'])){
	$search = $_POST['search'];
}
$users = getUsers($search);
foreach($users as $user){
	$row = '<tr><td>' . htmlspecialchars($user['username']);
	$row .= '</td><td>' . htmlspecialchars($user['address']);
	$row .= '</td><td>' . htmlspecialchars($user['city']);
	$row .= '</td><td>' . htmlspecialchars($user['state']);
	$row .= '</td><td>' . htmlspecialchars($user['postcode']);
	$row .= '</td><td>' . htmlspecialchars($user['country']);
	//Activate user
	if($user['active']){
		//Demote/promote admin buttons
		$row .= '</td><td><form action="admin" method="post" onsubmit="return confirm(&apos;Do you really want to change admin rights?&apos;);">
		<input type="hidden" name="username" value="' . htmlspecialchars($user['username']) . '">';
		$row .= getCSRFinputcode();
		if($user['admin']){ //Demote
			$row .= '<input type="hidden" name="action" value="demote">';
			$row .= '<input type="Submit" value="Demote to user"></form>';
		}else{//Promote
			$row .= '<input type="hidden" name="action" value="promote">';
			$row .= '<input type="Submit" value="Promote to admin"></form>';
		}
	}else{//Activate
		$row .= '</td><td><form action="admin" method="post" onsubmit="return confirm(&apos;Do you really want to activate this user?&apos;);">
		<input type="hidden" name="username" value="' . htmlspecialchars($user['username']) . '">';
		$row .= getCSRFinputcode();
		$row .= '<input type="hidden" name="action" value="activate">';
		$row .= '<input type="Submit" value="Activate user"></form>';
	}
	//Delete button
	$row .= '</td><td><form action="admin" method="post" onsubmit="return confirm(&apos;Do you really want to delete this user?&apos;);">
	<input type="hidden" name="username" value="' . htmlspecialchars($user['username']) . '">';
	$row .= getCSRFinputcode();
	$row .= '<input type="hidden" name="action" value="delete">';
	$row .= '<input type="Submit" value="Delete user"></form>';
	//Generate new reset code button
	if($user['active']){
		$row .= '</td><td><form action="admin" method="post" onsubmit="return confirm(&apos;WARNING: This invalidates previous printed reset codes! Only use when mailing a new reset code. Continue?&apos;);">
		<input type="hidden" name="username" value="' . htmlspecialchars($user['username']) . '">';
		$row .= getCSRFinputcode();
		$row .= '<input type="hidden" name="action" value="newreset">';
		$row .= '<input type="Submit" value="Regenerate reset code"></form>';
	}
	$row .= '</td></tr>';
	echo $row;
}
?>
</tbody>
</table>
<?php
include('footer.php');