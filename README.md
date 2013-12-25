Secure-FTP-Backup
=================

Create secure high encrypted FTP backups.
This script creates backups which are encrypted with GnuPG and then sent to a FTP server.
It checks too if there is still enough space free on the FTP server. If not it deletes the oldest files that there is exactly enough space.

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
ftp_quota=100GB

# Backup directories with their absolute paths
# Multiple directories can be written with semicolons ';'
backup_dirs=/home/user1;/home/user2

# Prefix for the backup file names
prefix=backup_

archive=/usr/bin/tar
# Use as compressor bzip2 or gzip
compressor=/usr/bin/bzip2
encryption=/usr/bin/gpg

HOW TO USE IT
-------------
Run as root:
$ ftp-backup.php mybackup.conf

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
- FTP server access
- UNIX compatible operating system

LICENSE
-------
The MIT License (MIT)

AUTHOR
------
Stephan Ferraro