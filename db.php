<?php
/*
This file holds most of the backend logic for managing users, devices, and registration.
*/
$authdb = NULL;
if(!defined('NOCONNECT')){
	getDBConnection();
	$certid = getCertId();
	$curusr = getUser($certid);
}
//Logs into a db using defined credentials if they exist, sets the global authdb variable
function getDBConnection(){
	if(!file_exists("config.php")){
		header('Location: setup');
		die('Not configured yet');
	}
	require_once('config.php');
	global $authdb;
	$authdb = new mysqli(DBHOST, DBUSER, DBPASS, DBNAME);
	$GLOBALS['authdb'] = $authdb;
	if($authdb->connect_error){
		die("Could not log in to database! Check whether your database is running and whether config.php is correct.");
	}
}
//Sets up the database initially given root creds
//Creates a user and DB and relevant tables
function setupDB($host, $rootpass, $adminemail, $adminapproval){
	global $authdb;
	if($rootpass !== NULL){//Root setup method 
		if(file_exists("config.php")){
			die('Database has already been set up!');
		}
		if(!filter_var($adminemail, FILTER_VALIDATE_EMAIL)){
			die('Invalid admin email!');
		}
		if($adminapproval){
			$adminapproval = "true";
		}else{
			$adminapproval = "false";
		}
		$confout = fopen("config.php", "w") or die("Unable to open config.php for writing!");
		$dbname = "authdb";
		$newuser = "authuser";
		$newpass = base64_encode(openssl_random_pseudo_bytes(15));
		$rootsqli = new mysqli($host, "root", $rootpass, "mysql");
		if($rootsqli->connect_error){
			die("Could not connect with root account with those creds: " . $rootsqli->error);
		}
		//mysqli cannot do a CREATE USER with a prepared statement, so just use escape_string
		if(!$rootsqli->query("CREATE USER '".$rootsqli->escape_string($newuser)."'@'%' IDENTIFIED BY '".$rootsqli->escape_string($newpass)."'")){
			die("Creating user failed: " . $rootsqli->error);
		}
		if(!$rootsqli->query("CREATE DATABASE ".$dbname)){
			die("Creating database failed: " . $rootsqli->error);
		}
		if(!$rootsqli->query("GRANT ALL ON ".$dbname.".* TO '".$rootsqli->escape_string($newuser)."'@'%'")){
			die("Granting privileges failed: " . $rootsqli->error);
		}
		$rootsqli->close();
		//Save the config
		fwrite($confout, "<?php\n");
		fwrite($confout, "define('DBHOST', '" . addslashes($host) . "');\n");
		fwrite($confout, "define('DBUSER', '" . addslashes($newuser) . "');\n"); //Not like this could ever be injected anyway, but addslashes is good practice
		fwrite($confout, "define('DBPASS', '" . addslashes($newpass) . "');\n");
		fwrite($confout, "define('DBNAME', '" . addslashes($dbname) . "');\n");
		fwrite($confout, "define('ADMINEMAIL', '" . addslashes($adminemail) . "');\n");
		fwrite($confout, "define('ADMINAPPROVAL', " . $adminapproval . ");\n"); //clamped to true or false
		fclose($confout);
	}
	getDBConnection(); //Now let's load it up and see if it works
	if($rootpass === NULL && count(getUsers('', 1)) > 0){
		die("Database is already set up!");
	}
	//And make the tables
	if(!$authdb->query("CREATE TABLE IF NOT EXISTS users(username VARCHAR(40) NOT NULL PRIMARY KEY, admin BOOLEAN NOT NULL DEFAULT FALSE, active BOOLEAN NOT NULL DEFAULT TRUE, address VARCHAR(100) NOT NULL, city VARCHAR(40) NOT NULL, state VARCHAR(40) NOT NULL, postcode VARCHAR(20) NOT NULL, country VARCHAR(20) NOT NULL, resetcode CHAR(40) NOT NULL, minfactors INTEGER NOT NULL DEFAULT 1)")){
		die("Creating users table failed: " . $authdb->error);
	}
	if(!$authdb->query("CREATE TABLE IF NOT EXISTS devices(certid CHAR(40) NOT NULL PRIMARY KEY, username VARCHAR(40) NOT NULL, active BOOLEAN NOT NULL, expires DATETIME NOT NULL DEFAULT '9999-01-01 00:00:00', signupip VARCHAR(39) NOT NULL, useragent VARCHAR(200) NOT NULL, certsdn VARCHAR(200) NOT NULL, certidn VARCHAR(200) NOT NULL)")){
		die("Creating devices table failed: " . $authdb->error);
	}
	//Votes to handle MFA num factors, need multiple votes to change MFA settings
	if(!$authdb->query("CREATE TABLE IF NOT EXISTS mfavotes(certid CHAR(40) NOT NULL, username VARCHAR(40) NOT NULL, newnumfactors INTEGER NOT NULL, CONSTRAINT UNIQUE KEY certidusername (certid, username))")){
		die("Creating mfavotes table failed: " . $authdb->error);
	}
	//Votes to handle adding keys, need multiple votes
	if(!$authdb->query("CREATE TABLE IF NOT EXISTS keyvotes(certid CHAR(40) NOT NULL, username VARCHAR(40) NOT NULL, newcertid CHAR(40) NOT NULL, CONSTRAINT UNIQUE KEY certidusername (certid, username))")){
		die("Creating keyvotes table failed: " . $authdb->error);
	}
	//Votes to handle changing address
	if(!$authdb->query("CREATE TABLE IF NOT EXISTS addressvotes (certid CHAR(40) NOT NULL, username VARCHAR(40) NOT NULL,  address VARCHAR(100) NOT NULL, city VARCHAR(40) NOT NULL, state VARCHAR(40) NOT NULL, postcode VARCHAR(20) NOT NULL, country VARCHAR(20) NOT NULL, CONSTRAINT UNIQUE KEY certidusername (certid, username))")){
		die("Creating addressvotes table failed: " . $authdb->error);
	}
}
//Lists first X users matching a given pattern
function getUsers($searchterm, $limit = 50, $strict = false){
	global $authdb;
	$stmt = $authdb->prepare("SELECT username, admin, active, address, city, state, postcode, country, minfactors from users WHERE username LIKE ? LIMIT ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	if(!$strict){
		$searchterm = $searchterm . "%";
	}
	$stmt->bind_param("si", $searchterm, $limit);
	if(!$stmt->execute()){
		die("Could not query users: ".$authdb->error);
	}
	$results = array();
	$stmt->bind_result($username, $admin, $active, $address, $city, $state, $postcode, $country, $minfactors);
    while($stmt->fetch()){
		array_push($results, array("username" => $username, "admin" => $admin, "active" => $active, "address" => $address, "city" => $city, "state" => $state, "postcode" => $postcode, "country" => $country, "minfactors" => $minfactors));
	}
	return $results;
}
//Gets the cert ID
function getCertId(){
	$key = '';
	if(!isset($_SERVER["SSL_CLIENT_CERT"])){
		error_log("getCertId: no SSL Cert passed", 0);
		return NULL;
	}
	$key = $_SERVER["SSL_CLIENT_CERT"];
	if(strlen($key) == 0)
	{
		error_log("getCertId: cert length 0", 0);
		return NULL;
	}
	if(isset($_SERVER['SERVER_SOFTWARE']) && $_SERVER['SERVER_SOFTWARE'] != ''){
		//Split it out.
		error_log("getCertId: SERVER_SOFTWARE was: " . $_SERVER['SERVER_SOFTWARE'], 0);
		if(strpos($_SERVER['SERVER_SOFTWARE'],'nginx') !== false)
		{
			return sha1($key);
		}
		else
		{
			$string = str_replace(array("\r", "\n","\t"), '', $_SERVER["SSL_CLIENT_CERT"]);
			$keyres = openssl_get_publickey($string);
			if($keyres === FALSE){
				error_log("getCertId: no public key retrieved", 0);
				error_log("getCertId: cert is as follows", 0);
				error_log($_SERVER["SSL_CLIENT_CERT"], 0);
				error_log($string,0);
				return NULL;
			}
			$key = openssl_pkey_get_details($keyres)['key'];
			if(!$key){
				error_log("getCertId: no hash key retrieved", 0);
				return NULL;
			}	
		}
    }
	return sha1($key);
}
//Decides if a device is a temporary device or not, returning expiration date if so
function isTemporary($certid){
	global $authdb;
	$stmt = $authdb->prepare("SELECT expires from devices WHERE certid = ? AND expires < '9999-01-01 00:00:00'");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("s",$certid);
	if(!$stmt->execute()){
		die("Could not query temporary devices: ".$authdb->error);
	}
	$stmt->bind_result($expires);
    if($stmt->fetch()){
		return $expires;
	}else{
		return false;
	}
}
//Get rid of a cert if it's expired
function pruneOldCert($certid){
	global $authdb;
	$stmt = $authdb->prepare("DELETE FROM devices WHERE certid = ? AND expires < NOW()");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("s",$certid);
	if(!$stmt->execute()){
		die("Could not prune users: ".$authdb->error);
	}
}
//Gets a user from certid, returning username
function getUser($certid, $active = true, $activeuser = true){
	if($certid == NULL){
		return NULL;
	}
	global $authdb;
	//mysqli can't parameterize boolean, so clamp to TRUE/FALSE and drop into query
	$activestr = 'FALSE';
	if($active){
		$activestr = 'TRUE';
	}
	$activeuserstr = 'FALSE';
	if($activeuser){
		$activeuserstr = 'TRUE';
	}
	$stmt = $authdb->prepare("SELECT users.username FROM users, devices WHERE devices.username = users.username AND certid = ? AND users.active = $activeuserstr AND devices.active = $activestr AND devices.expires > NOW()");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("s",$certid);
	if(!$stmt->execute()){
		die("Could not query users: ".$authdb->error);
	}
	$stmt->bind_result($username);
    if($stmt->fetch()){
		return $username;
	}else{
		pruneOldCert($certid); //Maybe it's expired?
		return NULL;
	}
}
//Sees how many factors this user requires for changes
function getMinFactors($username){
	global $authdb;
	$stmt = $authdb->prepare("SELECT minfactors from users WHERE username = ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("s",$username);
	if(!$stmt->execute()){
		die("Could not query users: ".$authdb->error);
	}
	$stmt->bind_result($minfactors);
    if($stmt->fetch()){
		return intval($minfactors);
	}else{
		return NULL;
	}
}
//Directly changes a user's MFA requirements
function setMFA($user, $factors){
	global $authdb;
	$stmt = $authdb->prepare("UPDATE users SET minfactors = ? WHERE username = ?");
	if($stmt == false){
		die("Could not prepare update minfactors statement: ".$authdb->error);
	}
	$stmt->bind_param("is", $factors, $user);
	if(!$stmt->execute()){
		die("Could not update number of factors: ".$authdb->error);
	}
}
//Counts the number of votes that have been made for this change excluding current certID
//If no certID is provided, all votes are counted
function getMFAVotes($user, $certid = 'zzz'){
	global $authdb;
	$stmt = $authdb->prepare("SELECT newnumfactors, certid FROM mfavotes WHERE username = ? AND certid <> ? GROUP BY newnumfactors");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("ss", $user, $certid);
	if(!$stmt->execute()){
		die("Could not query MFA votes: ".$authdb->error);
	}
	$stmt->bind_result($factor, $certidout);
	$results = array();
    while($stmt->fetch()){
		if(isset($results[$factor])){
			$results[$factor][] = $certidout;
		}else{
			$results[$factor] =  array($certidout);
		}
	}
	return $results;
}
//Adds a vote to the MFA table
function addMFAVote($user, $certid, $factors, $oldfactors){
	global $authdb;
	//First remove any votes with this certid. You only get to vote for one at a time
	$stmt = $authdb->prepare("DELETE FROM mfavotes WHERE certid = ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("s", $certid);
	if(!$stmt->execute()){
		die("Could not remove MFA votes: ".$authdb->error);
	}
	//A request to keep the status quo does not need a vote table addition, just previous vote deletion
	if($oldfactors === $factors){
		return;
	}
	//Then add new vote
	$stmt = $authdb->prepare("INSERT INTO mfavotes (username, certid, newnumfactors) VALUES(?, ?, ?)");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("ssi", $user, $certid, $factors);
	if(!$stmt->execute()){
		die("Could not insert MFA vote: ".$authdb->error);
	}
}
//Cleans up votes that have been made for this change. Used when cancelling or fulfilling a change.
function removeMFAVotes($user){
	global $authdb;
	$stmt = $authdb->prepare("DELETE FROM mfavotes WHERE username = ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("s", $user);
	if(!$stmt->execute()){
		die("Could not remove MFA votes: ".$authdb->error);
	}
}
//Cleans up votes that have been made for this change. Used when cancelling or fulfilling a change.
function removeKeyVotes($user, $newcertid){
	global $authdb;
	$stmt = $authdb->prepare("DELETE FROM keyvotes WHERE username = ? AND newcertid = ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("ss", $user, $newcertid);
	if(!$stmt->execute()){
		die("Could not remove Key votes: ".$authdb->error);
	}
	$stmt->store_result();
}
//Counts the number of votes that have been made for this change excluding current certID
function countKeyVotes($user, $certid, $newcertid){
	global $authdb;
	$stmt = $authdb->prepare("SELECT COUNT(*) FROM keyvotes WHERE username = ? AND certid <> ? AND newcertid = ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("sss", $user, $certid, $newcertid);
	if(!$stmt->execute()){
		die("Could not query users: ".$authdb->error);
	}
	$stmt->bind_result($count);
    if($stmt->fetch()){
		return $count;
	}
	return 0;
}
//Requests to change number of factors required in MFA.
//If MFA is already enabled, adds a vote, and if enough votes have been recorded,
//makes the change.
function requestMFAchange($user, $certid, $factors){
	$currentMF = getMinFactors($user);
	//If MFA is not enabled, just make the change
	if($currentMF < 2){
		setMFA($user, $factors);
	}elseif(getMFAVotes($user, $certid)[$factors] >= $currentMF - 1){
		//This is our last required vote, make the change.
		setMFA($user, $factors);
		removeMFAVotes($user); //And delete votes
	}else{
		addMFAVote($user, $certid, $factors, $currentMF);
		return false;
	}
	return true;
}
//Adds a vote to the keyvotes table
function addKeyVote($user, $currentcertid, $newcertid){
	global $authdb;
	$stmt = $authdb->prepare("INSERT INTO keyvotes(certid, username, newcertid) VALUES (?,?,?)");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("sss", $currentcertid, $user, $newcertid);
	if(!$stmt->execute()){
		die("Could not add key vote: ".$authdb->error);
	}
	$stmt->store_result();
}
//Returns the key votes for a given user and key
function getKeyVotes($user, $newcertid){
	global $authdb;
	$stmt = $authdb->prepare("SELECT certid FROM keyvotes WHERE username = ? AND newcertid = ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("ss", $user, $newcertid);
	if(!$stmt->execute()){
		die("Could not query key votes: ".$authdb->error);
	}
	$stmt->bind_result($certid);
	$results = array();
    while($stmt->fetch()){
		$results[] = $certid;
	}
	return $results;
}
//Associates a key with a user. Inner function that assumes security checks have already been done.
//Handles both first key add and activating a requested key
function associateKey($username, $certid, $temporary = false){
	global $authdb;
	//First see if there's already a devices request for this username
	$stmt = $authdb->prepare("SELECT username from devices WHERE certid = ? AND active = FALSE AND username = ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("ss", $certid, $username);
	if(!$stmt->execute()){
		die("Could not query users: ".$authdb->error);
	}
	$stmt->bind_result($username);
	$stmt->store_result();
	$expires = "'9999-01-01 00:00:00'"; //expires is clamped to  default expiry or temp expiry
	if($temporary === true){
		$expires = 'DATE_ADD(NOW(), INTERVAL 2 HOUR)';
	}
    if($stmt->fetch()){
		//Already requested. Activate this device ID
		$stmt2 = $authdb->prepare("UPDATE devices SET active = TRUE, expires = $expires WHERE certid = ?");
		if($stmt2 == false){
			die("Could not prepare update statement: ".$authdb->error);
		}
		$stmt2->bind_param("s", $certid);
		if(!$stmt2->execute()){
			die("Could not associate user key: ".$authdb->error);
		}
	}elseif(certExists($certid)){
		die('This cert is already associated with an account or an account request!');
	}elseif(!userExists($username)){
		die('This user does not exist.');
	}else{
		//Add a new line into devices
		$stmt2 = $authdb->prepare("INSERT INTO devices (certid, username, active, expires, signupip, useragent, certsdn, certidn) VALUES (?, ?, TRUE, $expires, ?, ?, ?, ?)");
		if($stmt2 == false){
			die("Could not prepare statement: ".$authdb->error);
		}
		$stmt2->bind_param("ssssss", $certid, $username, $_SERVER["REMOTE_ADDR"], $_SERVER["HTTP_USER_AGENT"], $_SERVER["SSL_CLIENT_S_DN"], $_SERVER["SSL_CLIENT_I_DN"]);
		if(!$stmt2->execute()){
			die("Could not associate user key: ".$authdb->error);
		}
	}
}
//Puts in a request to associate a key with a user.
function requestKeyAdd($user, $currentcertid, $certid, $temporary=""){
	global $authdb;
	//First see if this is a brand new request
    if(!certExists($currentcertid)){
		//Create request
		$stmt = $authdb->prepare("INSERT INTO devices (certid, username, active, signupip, useragent, certsdn, certidn) VALUES (?, ?, FALSE, ?, ?, ?, ?)");
		if($stmt == false){
			die("Could not prepare statement: ".$authdb->error);
		}
		$stmt->bind_param("ssssss", $certid, $user, $_SERVER["REMOTE_ADDR"], $_SERVER["HTTP_USER_AGENT"], $_SERVER["SSL_CLIENT_S_DN"], $_SERVER["SSL_CLIENT_I_DN"]);
		if(!$stmt->execute()){
			die("Could not add key association: ".$authdb->error);
		}
		return;
	//The current cert owner must be the same as the account owner and cannot have already requested an add
	}elseif(getUser($currentcertid) != $user){
		die('You are not authorized to request a key addition to this user.');
	}
	//Sanity check username
	if(!userExists($user)){
		die('This user does not exist.');
	}else{
		//Check MFA
		$currentMF = getMinFactors($user);
		if($currentMF < 2){ //Just one factor auth; add key
			associateKey($user, $certid, $temporary);
		}elseif(countKeyVotes($user, $currentcertid, $certid) >= $currentMF - 1){
			//Last required vote! Make the change.
			associateKey($user, $certid, $temporary);
			removeKeyVotes($user, $certid); //And delete votes
		}else{ //Add a vote
			addKeyVote($user, $currentcertid, $certid);
			echo "Added vote for $certid  $user  ".$_SERVER["REMOTE_ADDR"].' '.$_SERVER["HTTP_USER_AGENT"].' '.$_SERVER["SSL_CLIENT_S_DN"].' '.$_SERVER["SSL_CLIENT_I_DN"];
		}
	}
}
//Decides whether the given user is an admin
function isAdmin($user){
	global $authdb;
	$stmt = $authdb->prepare("SELECT admin from users WHERE username = ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("s",$user);
	if(!$stmt->execute()){
		die("Could not query users: ".$authdb->error);
	}
	$stmt->bind_result($isadmin);
	$stmt->fetch();
	if($isadmin){ //Might return 0 or 1. Clamp to TRUE/FALSE
		return TRUE;
	}else{
		return FALSE;
	}
}
//Promotes or demotes the user
function setAdmin($user, $admin){
	$adminstr = "FALSE";
	if($admin){
		$adminstr = "TRUE";
	}
	//You can't bind boolean params, at least as boolean.
	global $authdb;
	$stmt = $authdb->prepare("UPDATE users SET admin = $adminstr WHERE username = ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("s", $user);
	if(!$stmt->execute()){
		die("Could not set admin: ".$authdb->error);
	}
}
//Activates or deactivates the user
function setActive($user, $active){
	$activestr = "FALSE";
	if($active){
		$activestr = "TRUE";
	}
	//You can't bind boolean params, at least as boolean.
	global $authdb;
	$stmt = $authdb->prepare("UPDATE users SET active = $activestr WHERE username = ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("s", $user);
	if(!$stmt->execute()){
		die("Could not set active: ".$authdb->error);
	}
}
//Adds a user, creating and returning a reset code.
function addUser($username, $address, $city, $state, $postcode, $country){
	$adminstr = "FALSE";
	$activestr = "TRUE";
	if(count(getUsers('', 1)) < 1){ //No existing users; I'm the first! So I get admin privs.
		$adminstr = "TRUE";
	}elseif(ADMINAPPROVAL){
		$activestr = "FALSE";
	}
	$resetcode = strtoupper(base64_encode(openssl_random_pseudo_bytes(21)));
	global $authdb;
	$stmt = $authdb->prepare("INSERT INTO users (username, admin, active, address, city, state, postcode, country, resetcode) ".
			"VALUES (?, $adminstr, $activestr, ?, ?, ?, ?, ?, ?)");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	//Only the sha1 of the reset code is stored. 
	//This ensures a DB dump contains no easily used credentials.
	$sha1reset = sha1($resetcode);
	$stmt->bind_param("sssssss", $username, $address, $city, $state, $postcode, $country, $sha1reset);
	if(!$stmt->execute()){
		die("Could not add user: ".$authdb->error);
	}
	return $resetcode;
}
//Change a user's address
function changeUserAddress($username, $address, $city, $state, $postcode, $country){
	global $authdb;
	$stmt = $authdb->prepare("UPDATE users SET address = ?, city = ?, state = ?, postcode = ?, country = ? WHERE username = ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("ssssss", $address, $city, $state, $postcode, $country, $username);
	if(!$stmt->execute()){
		die("Could not add user: ".$authdb->error);
	}
}
//Add a vote for an address
function addAddressVote($certid, $user, $address, $city, $state, $postcode, $country){
	global $authdb;
	$stmt = $authdb->prepare("INSERT INTO addressvotes (certid, username, address, city, state, postcode, country) VALUES (?, ?, ?, ?, ?, ?, ?)");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("sssssss", $certid, $user, $address, $city, $state, $postcode, $country);
	if(!$stmt->execute()){
		die("Could not add address association: ".$authdb->error);
	}
}
//Returns a summary of all addresses currently being requested by this user
function getAllAddressVotes($user){
	global $authdb;
	$stmt = $authdb->prepare("SELECT COUNT(certid), address, city, state, postcode, country FROM addressvotes WHERE username = ? GROUP BY address, city, state, postcode, country");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("s", $user);
	if(!$stmt->execute()){
		die("Could not query address votes: ".$authdb->error);
	}
	$stmt->bind_result($count, $address, $city, $state, $postcode, $country);
	$results = array();
    while($stmt->fetch()){
		array_push($results, array("count" => $count, "address" => $address, "city" => $city, "state" => $state, "postcode" => $postcode, "country" => $country));
	}
	return $results;
}
//Removes address votes for a user
function removeAddressVotes($user){
	global $authdb;
	$stmt = $authdb->prepare("DELETE FROM addressvotes WHERE username = ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("s", $user);
	if(!$stmt->execute()){
		die("Could not delete address association: ".$authdb->error);
	}
}
//Returns the address votes for a given user and key
function getAddressVotes($certid, $user, $address, $city, $state, $postcode, $country){
	global $authdb;
	$stmt = $authdb->prepare("SELECT COUNT(*) FROM addressvotes WHERE certid <> ? AND username = ? AND address = ? AND city = ? AND state = ? AND postcode = ? AND country = ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("sssssss", $certid, $user, $address, $city, $state, $postcode, $country);
	if(!$stmt->execute()){
		die("Could not query address votes: ".$authdb->error);
	}
	$stmt->bind_result($count);
	$stmt->fetch();
	return $count;
}
//Requests a user address change
function requestAddressChange($certid, $user, $address, $city, $state, $postcode, $country){
	$currentMF = getMinFactors($user);
	//If MFA is not enabled, just make the change
	if($currentMF < 2){
		changeUserAddress($user, $address, $city, $state, $postcode, $country);
	}elseif(getAddressVotes($certid, $user, $address, $city, $state, $postcode, $country) >= $currentMF - 1){
		//This is our last required vote, make the change.
		changeUserAddress($user, $address, $city, $state, $postcode, $country);
		removeAddressVotes($user); //And delete votes
	}else{
		addAddressVote($certid, $user, $address, $city, $state, $postcode, $country);
	}
}
//Decides whether a certificate already exists; has been requested or issued
function certExists($certid){
	global $authdb;
	//First see if there's already a devices entry
	$stmt = $authdb->prepare("SELECT username from devices WHERE certid = ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("s", $certid);
	if(!$stmt->execute()){
		die("Could not query device ID's: ".$authdb->error);
	}
	$stmt->bind_result($username);
    if($stmt->fetch()){
		return true;
	}
	return false;
}
//Decides whether a user already exists
function userExists($username){
	global $authdb;
	//First see if there's already a devices entry
	$stmt = $authdb->prepare("SELECT username from users WHERE username = ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("s", $username);
	if(!$stmt->execute()){
		die("Could not query users: ".$authdb->error);
	}
	$stmt->bind_result($usernamez);
    if($stmt->fetch()){
		return true;
	}
	return false;
}
//Gets a list of devices
function getUserDevices($user){
	global $authdb;
	$stmt = $authdb->prepare("SELECT certid, active, expires, signupip, useragent, certsdn, certidn from devices WHERE username = ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("s",$user);
	if(!$stmt->execute()){
		die("Could not query user devices: ".$authdb->error);
	}
	$stmt->bind_result($certid, $active, $expires, $signupip, $useragent, $certsdn, $certidn);
	$results = array();
    while($stmt->fetch()){
		$results[] = array("certid" => $certid, "active" => $active, "expires" => $expires, "signupip" => $signupip, "useragent" => $useragent, 'certsdn' => $certsdn, 'certidn' => $certidn);
	}
	return $results;
}
//Drops a key/user association
function removeDeviceKey($certid, $user){
	global $authdb;
	//Delete all key votes for the cert ID in case it's being approved now
	removeKeyVotes($user, $certid);
	//AND delete all votes FROM the cert in case it's voting.
	$stmt = $authdb->prepare("DELETE FROM keyvotes WHERE certid = ? AND username = ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("ss", $certid, $user);
	if(!$stmt->execute()){
		die("Could not delete key votes from device: ".$authdb->error);
	}
	//Now delete the MFA votes from this ID
	$stmt = $authdb->prepare("DELETE FROM mfavotes WHERE certid = ? AND username = ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("ss", $certid, $user);
	if(!$stmt->execute()){
		die("Could not remove MFA votes: ".$authdb->error);
	}
	//Now delete the actual device
	$stmt = $authdb->prepare("DELETE FROM devices WHERE certid = ? AND username = ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("ss", $certid, $user);
	if(!$stmt->execute()){
		die("Could not delete user device: ".$authdb->error);
	}
}
//Wipes user devices; should not be used unless recovering or deleting a user
function wipeUserDevices($username){
	global $authdb;
	$delstmt = $authdb->prepare("DELETE FROM devices WHERE username = ?");
	if($delstmt == false){
		die("Could not prepare delete devices statement: ".$authdb->error);
	}
	$delstmt->bind_param("s", $username);
	if(!$delstmt->execute()){
		die("Could not clear old devices: ".$authdb->error);
	}
	$delstmt->store_result();
	$stmt = $authdb->prepare("DELETE FROM keyvotes WHERE username = ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("s", $username);
	if(!$stmt->execute()){
		die("Could not remove Key votes: ".$authdb->error);
	}
	$stmt->store_result();
	$stmt = $authdb->prepare("DELETE FROM mfavotes WHERE username = ?");
	if($stmt == false){
		die("Could not prepare statement: ".$authdb->error);
	}
	$stmt->bind_param("s", $username);
	if(!$stmt->execute()){
		die("Could not remove Key votes: ".$authdb->error);
	}
	$stmt->store_result();
}
//Does what it says
function deleteUser($username){
	wipeUserDevices($username);
	global $authdb;
	$delstmt = $authdb->prepare("DELETE FROM users WHERE username = ?");
	if($delstmt == false){
		die("Could not prepare delete devices statement: ".$authdb->error);
	}
	$delstmt->bind_param("s", $username);
	if(!$delstmt->execute()){
		die("Could not delete user: ".$authdb->error);
	}
	$delstmt->store_result();
}
//Recovers an account using the recovery code
//Generates and returns a new recovery code
function doRecover($username, $resetcode){
	$certid = getCertId();
	if($certid == NULL){
		die('You must be using a certificate to reset your account. Get one at <a href="getacert">getacert</a>');
	}
	if(getUser($certid) != NULL){
		die('You do not need a reset, you are already logged in!');
	}
	global $authdb;
	$shacode = sha1($resetcode);
	$stmt = $authdb->prepare("SELECT username FROM users WHERE username = ? AND resetcode = ?");
	if($stmt == false){
		die("Could not prepare query users for reset code statement: ".$authdb->error);
	}
	$stmt->bind_param("ss", $username, $shacode);
	if(!$stmt->execute()){
		die("Could not query users for reset code: ".$authdb->error);
	}
	$stmt->bind_result($username);
	$stmt->store_result();
    if(!$stmt->fetch()){
		die('Invalid reset code or username.');
	}
	//Check if it's multi-factor
	$currentMF = getMinFactors($username);
	if($currentMF > 1){
		$votes = countKeyVotes($username, '', $certid);
		if($votes <  $currentMF - 1){
			return "ERROR: This account has $currentMF-factor authentication enabled. In order to reset it and activate this key, you must approve the reset from ".($currentMF - 1 - $votes).' of your devices.';
		}
		//OK, do it!
		setMFA($user, $factors);
	}
	//Save the new key
	associateKey($username, $certid);
	//Now generate a new recovery code
	return newReset($username);
}
// Change user's reset code. For admins to mail a new one.
function newReset($user){
	global $authdb;
	//Now generate a new recovery code
	$newresetcode = strtoupper(base64_encode(openssl_random_pseudo_bytes(21)));
	$updatestmt = $authdb->prepare("UPDATE users SET resetcode = ? WHERE username = ?");
	if($updatestmt == false){
		die("Could not prepare reset code update statement: ".$authdb->error);
	}
	$sha1reset = sha1($newresetcode);
	$updatestmt->bind_param("ss", $sha1reset, $user);
	if(!$updatestmt->execute()){
		die("Could not update reset code: ".$authdb->error);
	}
	return $newresetcode;
}