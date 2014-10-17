<?php 
require_once('db.php');
require_once('session.php'); #necessary for CSRF protection
require_once('computer_info.php'); #Just to show a nice user-agent summary
if($curusr === NULL){
	header('Location: .');
	die("You are not logged in currently.");
}
if(isTemporary($certid) !== false){
	die("You are using a temporary device and cannot manage your profile.");
}
$devs = getUserDevices($curusr);
$currentF = getMinFactors($curusr);
$numdevs = count($devs);
$numactivedevs = 0;
$devsbyid = array();
//Figure out how many active devs I have and make them indexable by certid
foreach($devs as $dev){
	$devsbyid[$dev["certid"]] = $dev;
	if($dev["active"] === 1 and $dev['expires'] === '9999-01-01 00:00:00'){
		$numactivedevs += 1;
	}
}
$userdetails = getUsers($curusr, 1, true)[0]; //Get user details
if($_SERVER['REQUEST_METHOD']==='POST'){
	if(!passesCSRFcheck()){
		die("Failed CSRF check. Cookies must be enabled for this site to work.");
	}
	header('Location: profile'); // Avoid refresh warnings
	//Deleting a certificate. This doesn't require multi-factor auth. I suppose you could change that, but it's not as critical.
	if($_POST['action'] == 'revoke'){
		if($curusr === NULL){
			die("invalid certificate");
		}
		if($_POST["certid"] == $certid){
			die("You can't delete your current certificate. You can only delete other devices.");
		}
		if($numactivedevs <= $currentF and $devsbyid[$_POST["certid"]]["active"] === 1  and $devsbyid[$_POST["certid"]]['expires'] === '9999-01-01 00:00:00'){
			die("You have $currentF-factor authentication and only $numactivedevs devices. You can't delete any more!");
		}
		//Now delete it. Don't worry, it only deletes it if it's owned by the current user
		removeDeviceKey($_POST["certid"], $curusr);
	}elseif($_POST['action'] == 'approve'){
		$temporary = isset($_POST['temporary']);
		requestKeyAdd($curusr, $certid, $_POST["certid"], $temporary);
	}elseif($_POST['action'] == 'changefactor'){
		$submittedfactors = intval($_POST['numfactors']);
		if($submittedfactors < 1 || $submittedfactors > $numactivedevs){
			die('Invalid number of factors ' . $submittedfactors);
		}
		//Marks that this cert supports changing factors to X
		//Changing number of factors requires multi-factor votes and agreement
		requestMFAchange($curusr, $certid, $submittedfactors);
	}elseif($_POST['action'] == 'updateaddress'){
		if(!(isset($_POST['address']) and isset($_POST['city']) and 
				isset($_POST['state']) and isset($_POST['postcode']) and isset($_POST['country']))){
			die("Must fill out all fields!");
		}
		requestAddressChange(getCertId(), $curusr, $_POST['address'], $_POST['city'], $_POST['state'], $_POST['postcode'], $_POST['country']);
	}elseif($_POST['action'] == 'canceladdress'){
		removeAddressVotes($curusr);
	}
	exit;
}
$title = 'Profile';
include('header.php');
$addressChanges = getAllAddressVotes($curusr);
echo '<h1>My Profile</h1>
<p class="text">Make sure your profile has your current address. If you lose access to your account, you can have a reset code sent to your address.</p>
<form method="post" action="profile" onsubmit="return confirm(&apos;Are you sure you want to change your address settings?&apos;);">
<input type="hidden" name="action" value="updateaddress">
<table>
<tbody>
<tr><td>Username:</td><td>'.htmlspecialchars($curusr).'</td></tr>
<tr><td>Address:</td><td><input type="text" name="address" value="'.htmlspecialchars($userdetails['address']).'">';
foreach($addressChanges as $address){
	echo '</td><td>'.htmlspecialchars($address['address']);
}
echo '</td></tr>
<tr><td>City:</td><td><input type="text" name="city" value="'.htmlspecialchars($userdetails['city']).'">';
foreach($addressChanges as $address){
	echo '</td><td>'.htmlspecialchars($address['city']);
}
echo '</td></tr>
<tr><td>State:</td><td><input type="text" name="state" value="'.htmlspecialchars($userdetails['state']).'">';
foreach($addressChanges as $address){
	echo '</td><td>'.htmlspecialchars($address['state']);
}
echo '</td></tr>
<tr><td>Zip code:</td><td><input type="text" name="postcode" value="'.htmlspecialchars($userdetails['postcode']).'">';
foreach($addressChanges as $address){
	echo '</td><td>'.htmlspecialchars($address['postcode']);
}
echo '</td></tr>
<tr><td>Country:</td><td><input type="text" name="country" value="'.htmlspecialchars($userdetails['country']).'">';
foreach($addressChanges as $address){
	echo '</td><td>'.htmlspecialchars($address['country']);
}
echo '</td></tr>
<tr><td></td><td><input type="submit" value="Update"></td>';
foreach($addressChanges as $address){
	echo '</td><td>Approved by '.htmlspecialchars($address['count']).'.';
	//If I haven't approved, show the approve button
	if(intval(getAddressVotes(getCertId(), $curusr, $address['address'], $address['city'], $address['state'], $address['postcode'], $address['country'])) === intval($address['count'])){
		echo '<form method="post" action="profile" onsubmit="return confirm(&apos;Are you sure you want to change your address?&apos;);">
<input type="hidden" name="action" value="updateaddress">
<input type="hidden" name="address" value="'.htmlspecialchars($address['address']).'">
<input type="hidden" name="city" value="'.htmlspecialchars($address['city']).'">
<input type="hidden" name="state" value="'.htmlspecialchars($address['state']).'">
<input type="hidden" name="postcode" value="'.htmlspecialchars($address['postcode']).'">
<input type="hidden" name="country" value="'.htmlspecialchars($address['country']).'">
<input type="submit" value="Approve">'. getCSRFinputcode().'</form>';
	}else{
		echo ' including this device.';
	}
}
echo '</tr>
</tbody>
</table> '. getCSRFinputcode().'</form>';
if(count($addressChanges) > 0){
	echo '<form method="post" action="profile" onsubmit="return confirm(&apos;Are you sure you want to cancel your address changes?&apos;);">
<input type="hidden" name="action" value="canceladdress">
<input type="submit" value="Cancel address changes">'. getCSRFinputcode().'</form>';
}
?>
<h1>Devices</h1>
<table>
<thead>
<tr><th>Status</th><th>Signup IP address</th><th>Signup Device/Browser</th><th>Issued To</th><th>Issued by</th></tr>
</thead>
<tbody>
<?php
$revoking = ($numdevs > 0);
foreach($devs as $dev){
	$row = '<tr><td>';
	if($dev["active"] === 1){
		//Temporary
		if($dev['expires'] === '9999-01-01 00:00:00'){
			$row .= 'Active';
		}else{
			$row .= 'Temporary, expires '.$dev['expires'];
		}
	}else{
		$row .= '<strong>Waiting to be approved</strong>';
	}
	$row .= '</td><td>' . htmlspecialchars($dev["signupip"]);
	$row .= '</td><td>' . htmlspecialchars(getComputerInfo($dev["useragent"]));
	$row .='</td><td>' . htmlspecialchars(str_replace(',', ' ', $dev['certsdn']));
	$row .='</td><td>' . htmlspecialchars(str_replace(',', ' ', $dev['certidn']));
	$row .='</td><td>';
	$revokelabel = 'Revoke';
	//Approve button if requested
	if($dev["active"] != 1){
		$revokelabel = 'Deny';
		$showApprove = true;
		if($currentF > 1){ //multi-factor. Let's see how many have approved it.
			$votes = getKeyVotes($curusr, $dev['certid']);
			$row .= 'Marked as approved by ' . count($votes) . '/' . $currentF . ' devices. (You have '.$currentF.'-factor auth) ';
			$iapproved = in_array($certid,$votes);
			if($iapproved){
				$row .= 'You already voted to approve this change from this device.';
				$showApprove = false;
			}
		}
		if($showApprove){
			//Approve button
			$row .= '<form action="profile" method="post" onsubmit="return confirm(&apos;Are you sure you want to grant this device access? If you did not just request to activate it, hit no now, then deny the request.&apos;);">
			<input type="hidden" name="certid" value="' . htmlspecialchars($dev['certid']) . '">
			<input type="hidden" name="action" value="approve">';
			$row .= getCSRFinputcode();
			$row .= '<input type="Submit" value="Approve"></form>';
			$row .= ' ';
			//Approve temporarily button
			$row .= '<form action="profile" method="post" onsubmit="return confirm(&apos;Are you sure you want to grant this device temporary access? If you did not just request to activate it, hit no now, then deny the request.&apos;);">
			<input type="hidden" name="certid" value="' . htmlspecialchars($dev['certid']) . '">
			<input type="hidden" name="action" value="approve">
			<input type="hidden" name="temporary" value="true">';
			$row .= getCSRFinputcode();
			$row .= '<input type="Submit" value="Temporarily Approve"></form>';
			$row .= ' ';
		}
	}
	//Can't delete your own cert. Can't delete if you're tight on MFA devices.
	//Always can delete temp certs.
	if($certid != $dev['certid'] and ($dev["active"] !== 1 or $dev['expires'] !== '9999-01-01 00:00:00' or $numactivedevs > $currentF)){
		//Show revoke/deny button
		$row .= '<form action="profile" method="post" onsubmit="return confirm(&apos;Do you really want to remove this key?&apos;);">
		<input type="hidden" name="certid" value="' . htmlspecialchars($dev['certid']) . '">
		<input type="hidden" name="action" value="revoke">';
		$row .= getCSRFinputcode();
		$row .= '<input type="Submit" value="' . $revokelabel . '"></form>';
	}
	$row .= '</td></tr>';
	echo $row;
}
?>
</tbody>
</table>
<h1>Multi-factor authentication</h1>
<?php
if($numactivedevs > 1){ //Eligible for 2FA
	$invalidChoices = array();
	if($currentF < 2){
		echo '<p class="text">You have multiple devices registered and activated and are able to use multi-factor authentication.</p>';
	}else{
		echo '<p class="text">You currently have ' . $currentF . '-factor authentication enabled. Changing your multi-factor authentication settings, changing address, or adding a new device requires approval from ' . $currentF . ' devices, or your recovery code and ' . ($currentF - 1) . ' more.</p>';
		//multi-factor change in progress. Let's see how many have approved it.
		foreach(getMFAVotes($curusr) as $factor => $votes){
			echo '<p class="text">A change to '.$factor.'-factor auth has been approved by '.count($votes)."/$currentF required devices";
			$iapproved = in_array($certid, $votes);
			if($iapproved){
				echo ', including the one you are currently using';
				$invalidChoices[$factor] = true;
			}
			echo '.</p>';
		}
	}
	echo '<form action="profile" method="post" onsubmit="return confirm(&apos;Do you really want to change your multi-factor authentication settings?&apos;);">
	<input type="hidden" name="action" value="changefactor">
	<p class="text"><strong>Require <select name="numfactors">';
	for ($x=1; $x<=$numdevs; $x++) {
		if(!isset($invalidChoices[$x])){
			echo '<option value="' . $x . '"';
			if($x === $currentF){
				echo ' selected';
			}
			echo '>' . $x . '</option>';
		}
	}
	echo '</select> devices to add a new device or make other changes to your account.';
	echo getCSRFinputcode() . '</strong></p>';
	if($currentF < 2){
		echo '<p class="text"><input type="submit" value="Apply"></p></form>';
	}else{
		echo '<p class="text"><input type="submit" value="Request"></p></form>';
	}
} else {
	echo '<p class="text">You do not have multiple active devices. Add another device to your account to use multi-factor authentication</p>';
}
include('footer.php');