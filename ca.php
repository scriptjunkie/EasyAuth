<?php
/*
This file holds all the certificate generation and issuance code for our auth system.
It uses elliptic curve cryptography where possible for performance and security.
It's important to note that while this does create a CA, the CA isn't trusted.
Users are associated with and identified by their actual public key in the database, not a CA signature.
This is to implement the "no permanent stored creds" policy on the server.
This is so an information disclosure vulnerability that discloses the CA private key (or anything else on the server) cannot compromise any additional accounts or data.
Also, a stealthy intruder who gains access at one point in time cannot maintain access without making detectable modifications to the server, such as by leaving malware or making their own user an admin.
This system is easier than trying to use x509 certificate revocation mechanisms to manage certificates:
This allows us to take advantage of certs provided by untrusted 3rd party CA's that we can't revoke ourselves, and reduce the number of certificates users have to have on their computer.
It also allows users to use and manage access with smart cards that were issued by their own company.
*/
//Creates openSSL CA
function setupCA(){
	echo "<pre>";
	echo "Creating rootca directory...\n";
	$oldcwd = getcwd(); //Save old dir
	chdir(dirname(__FILE__)); //start with known location
	mkdir('rootca');
	chdir('rootca');
	mkdir('newcerts');
	mkdir('tmp');
	//Block direct reading of this dir, to discourage easily seeing what keys have been generated
	file_put_contents('.htaccess', 'order deny,allow
deny from all
');
	echo "Generating CA private key...\n";
	passthru('openssl ecparam -name secp384r1 -genkey -noout -param_enc explicit -out caprivkey.pem');
	echo "Creating self-signed CA certificate...\n";
	file_put_contents('cainput.txt', 'US
TX

Scriptjunkie Franchise

SimpleAuth

');
	passthru('openssl req -new -x509 -key caprivkey.pem -out ca.pem -days 36500 < cainput.txt');
	unlink('cainput.txt');
	echo "Validating CA cert...\n";
	passthru('openssl x509 -in ca.pem -text -noout');
	echo "Creating CA structure...\n";
	file_put_contents('serial',"1000\n");
	file_put_contents('index.txt',"");
	file_put_contents('ca.conf','[ ca ]
default_ca      = CA_default
[ CA_default ]
dir            = ' . getcwd() . '
database       = $dir/index.txt
new_certs_dir  = $dir/newcerts
certificate    = ' . getcwd() . '/ca.pem
serial         = $dir/serial
private_key    = ' . getcwd() . '/caprivkey.pem
RANDFILE       = $dir/private/.rand
default_days   = 18250
default_crl_days= 6000
default_md     = sha1
policy         = policy_any
email_in_dn    = yes
name_opt       = ca_default
cert_opt       = ca_default
copy_extensions = none
[ policy_any ]
countryName            = supplied
stateOrProvinceName    = optional
organizationName       = optional
organizationalUnitName = optional
commonName             = supplied
emailAddress           = optional
');
	chdir($oldcwd); //return to previous location
	echo "</pre>";
}
//Issues a certificate from the temporary CA
function issueCert($username, $key){
	if(!ctype_alnum($username) || empty($username)){
		die('Username must be alphanumeric!');
	}
	$rootcadir = dirname(__FILE__) . '/rootca'; //start with known location
	chdir($rootcadir);
	date_default_timezone_set('UTC');
	$CAorg = 'SimpleAuth';
	$CAcountry = 'US';
	$CAstate = 'TX';
	$confpath = $rootcadir . '/ca.conf';
	$cadb = $rootcadir . '/index.txt'; //will need to be reset
	$days = 18250; //50 years by default. We don't care about expiration.
	$f = fopen($cadb, 'w'); //reset CA DB
	fclose($f);
	//Save key to an spkac file
	$keyreq = "SPKAC=".str_replace(str_split(" \t\n\r\0\x0B"), '', $key);
	$keyreq .= "\nCN=".$username;
	$keyreq .= "\norganizationName=".$CAorg;
	$keyreq .= "\ncountryName=".$CAcountry;
	$keyreq .= "\nstateOrProvinceName=".$CAstate;
	$uniqpath = tempnam('tmp/','certreq');
	file_put_contents($uniqpath.".spkac",$keyreq);
	//Now sign the file 
	$command = 'openssl ca -config "' . $confpath . '" -days ' . $days . ' -notext -batch -spkac "' . $uniqpath . '.spkac" -out "' . $uniqpath . '.out"';
	shell_exec($command);
	//And send it back to the user
	$length = filesize($uniqpath . ".out");
	header('Last-Modified: ' . date('r+b'));
	header('Accept-Ranges: bytes');
	header('Content-Length: ' . $length);
	header('Content-Type: application/x-x509-user-cert');
	readfile($uniqpath . ".out");
	unlink($uniqpath . ".out");
	unlink($uniqpath . ".spkac");
	unlink($uniqpath);
	exit;
}
