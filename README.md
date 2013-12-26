Secure-FTP-Backup
=================

Create secure high encrypted FTP backups.
This script creates backups which are encrypted with GnuPG and are then sent to a FTP server.
It should be used if you place your backups on an unsecure FTP server (for example on a FTP host of your ISP).
As the backups are already encrypted on the server, you don't need to worry if the backups are unsecurely transfered (FTP instead FTPS).
It checks too if there is still enough space free on the FTP server. If not it deletes the oldest files that there is exactly enough space.
As default encryption software GnuPG will be used which automatically compress the archived files.

EXAMPLE HOW TO USE IT
=====================

CONFIGURATION EXAMPLE FILE
--------------------------
ftp-backup.conf:
# Secure FTP Backup Configuration File

# FTP host settings
ftp_host=hostname
ftp_port=21
ftp_user=user
ftp_password=password
# Quota is defined in bytes
ftp_quota=100000000000

# Backup directories with their absolute paths
# Multiple directories can be written with semicolons ';'
backup_dirs=/home/user1;/home/user2

# Prefix for the backup file names
prefix=yourhostname.tld_backup_

# GnuPG encryption settings
encryption_cmd=/usr/bin/gpg
encryption_key_name=Secure FTP Backup - yourhostname.tld

# Mandatory programs
ftp_cmd=/usr/bin/ftp
cut_cmd=/usr/bin/cut
awk_cmd=/usr/bin/awk
archive_cmd=/bin/tar


HOW TO USE IT
-------------
Run as root:
$ ftp-backup.php /etc/ftp-backup.conf

Simply add this in your cronjob file:
crontab:
0 5 * * * root /usr/local/bin/ftp-backup.php -c /etc/ftp-backup.conf > /dev/null 2>&1

REQUIREMENTS
------------
- PHP 5
- GnuPG
- ftp command line tool
- tar command line tool
- gzip/bzip2 command line tool
- awk
- FTP server access
- UNIX compatible operating system

INSTALLATION
------------
1. Configure the encryption by creating a new GnuPG secret and public key:
$ gpg --gen-key
a) Choose 4096 bits key length, to have today the strongest possible key length, as your key could not be cracked so early in future as compute power is increasing all the time.
b) Use a passphrase which you would need to decrypt your data
c) As full name choose for example "Secure FTP Backup - yourhostname.tld", as the backup script will match exactly this name of encryption_name configuration field
d) For the email address, you can put yours if you like, its not mandatory
e) Backup your private GPG key on a secure place (it would not be your FTP backup space server). Use the command:
$ gpg --export-secret-keys

After the configuration, check that your key has been created:
$ gpg --list-key
/Users/saf/.gnupg/pubring.gpg
-----------------------------
pub   1024D/0FD9E71F 2013-12-25
uid                  Secure FTP Backup <name@email.tld>
sub   4096g/2B362D79 2013-12-25

2. Copy script file to /usr/local/bin/ftp-backup.php:
$ cp ftp-backup.php /usr/local/bin
And set file to be executable:
$ chmod 755 /usr/local/bin/ftp-backup.php

3. Copy and configure the configuration file at /etc/ftp-backup.conf
4. Try if it works by executing as root:
$ ftp-backup.php /etc/ftp-backup.conf
5. Add a crontab entry in /etc/crontab:
# Secure FTP Backup
0 5 * * * root /usr/local/bin/ftp-backup.php -c /etc/ftp-backup.conf > /dev/null 2>&1
6. Restart cron:
$ /etc/init.d/cron restart

You can check your /var/log/syslog log file to see if the script runned successfully.

DECRYPTING & UNCOMPRESSING ARCHIVES
-----------------------------------
1. Log on the FTP server, download the encrypted compressed archived file.
2. Decrypt, uncompress and unarchive it:
a) For gzip archives:
$ gpg -d /tmp/backup_20131225_21\:03\:31.tar.bz2.gpg | gzip -dc | tar xfzv -
b) For bzip2 archives:
$ gpg -d /tmp/backup_20131225_21\:03\:31.tar.bz2.gpg | bzip2 -dc | tar xfzv -

The output of GnuPG would be similar to that:
gpg: encrypted with 4096-bit ELG-E key, ID 2B362D79, created 2013-12-25
      "Secure FTP Backup <contact@ferraro.net>"
gpg: Signature made Wed Dec 25 22:03:31 2013 CET using DSA key ID 0FD9E71F
gpg: Good signature from "Secure FTP Backup <contact@ferraro.net>"

NOTE
----
The script can not parse sub directories of the FTP service to count the taken quota space.
It can only count the bytes of the file which are in the main directory of the backup FTP service.

LICENSE
-------
The MIT License (MIT)

AUTHOR
------
Stephan Ferraro