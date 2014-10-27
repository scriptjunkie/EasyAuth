# EasyAuth - The Easier, Secure Cloud
## Overview
EasyAuth (EZA) is a proof-of-concept authentication system based on client SSL certificates that doesn't require users to remember any secrets.

* It's much easier on your users than the typical password and secret question systems. Ordinary people just can't create and remember random passwords for every site.
* This system stops attackers who can find out or guess security questions or guess or brute-force passwords. These are the same kind of attacks that have worked again and again against many celebrities, website owners, and ordinary people.
* Because EZA uses modern crypto, malicious websites with fake login pages that can steal passwords won't work. You can re-use the same certificate on all websites and unlike re-used passwords, even if one site got hacked or was malicious itself, you'll still be secure on the other sites. Or you can easily use different certificates to maintain anonymity.
* This system even stops advanced attackers who can "man-in-the-middle" your connection and strip the encryption of other sites with fraudulent certificates. Hundreds of organizations can issue certificates and many have issued bad certificates before. This system doesn't rely on trusting any of those organizations, since it verifies your actual key!
* This system supports two factor (or 3 factor or 4 factor or...) authentication that's stronger than even other multi-factor authentication systems.
* EZA has stronger account reset processes, using multiple devices and/or a printed or mailed reset code, not like the typical insecure account reset questions whose answers are all too easy to guess or find out.
* EZA even supports smart cards for users that have them, for true multi-factor authentication and the highest level of security.
* EZA does not require any new hardware, and it is compatible with almost every browser and platform in use today.

**It's time to stop blaming the victims and give people something better.**

## Installation

If you have a MySQL database and an Apache HTTPS server with PHP that supports .htaccess files, just extract the noknowledgeauth directory, visit the setup.php script in your browser, and enter your database creds.

The below commands have been verified to work on a fresh vanilla Ubuntu server install when run as root:

    apt-get install git lamp-server^
    a2enmod ssl
    a2enmod rewrite
    ln -s /etc/apache2/sites-available/default-ssl.conf /etc/apache2/sites-enabled/
    sed -ibak 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
    rm /var/www/html/index.html
    git clone https://github.com/scriptjunkie/EasyAuth.git /var/www/html/
    chown -R www-data:www-data /var/www/html/
    service apache2 restart

If you have the root mysql password, you can enter that into the setup.php page in your browser. This will create a database and a limited user with a randomly generated password, saving the configuration in the config.php file it generates. 

Otherwise you will have to first create the database, db user, and config.php file yourself.

## Using this code in your own project

This code is licensed under the [Simpified BSD license](LICENSE.txt), so you are free and encouraged to use it in your own projects!

It was written in PHP because 16 of the top 17 self-hosted content management systems are written in PHP. ([source](http://goo.gl/OwYtYW)) This means it could easily be translated into an authentication plugin for more sites than other languages, since PHP still leads in hosting support, developer familiarity, and ease of cutting & pasting code into other projects. Prefer another language? Translate into your favorite language, and post a version so even more sites can use it.

### Generating and issuing certificates
To see how to generate and issue certificates, just check out the ca.php file and getacert.php files. The CA file has a function to create a certificate authority automatically, and another to issue a certificate. The getacert file shows how to create the frontend page that the client sees when getting a certificate. EZA uses elliptic curve crypto for the CA, which is faster to validate. Note that EZA does NOT trust the CA key; rather than rely on CA validation, EZA keeps track of which keys go with which users directly. There are concerns of possible backdoors in the NIST curves implemented by OpenSSL, so you may prefer to stick with an RSA key of high bitlength instead if you choose to modify this system and trust the CA key.

### Requesting and obtaining a client's certificate
EZA requests any client certificates by using the Apache *SSLVerifyClient optional_no_ca* directive and makes certificate data accessible to the web app with the *SSLOptions +StdEnvVars +ExportCertData* directive. Both may be set in .htaccess files unless prohibited by main apache configuration. Using optional_no_ca allows clients to use smart cards and certificates from elsewhere that are convenient for the client, but it's important to note that the certificate subject name cannot be taken at face value, since anybody could have issued that certificate. Instead, you'll have to validate the key (what EZA does) or the certificate thumbprint and keep track of which keys go with which accounts. This is a plain public/private key approach instead of a public key infrastructure approach, which in my opinion saves us a lot of complexity and is more secure.
