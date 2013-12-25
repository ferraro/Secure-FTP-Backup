Secure-FTP-Backup
=================

Create secure high encrypted FTP backups.
This PHP script creates backups which are encrypted with GnuPG and then sent to a FTP server.
It checks too if there is still enough space free on the FTP server. If not it deletes the oldest files that there is exactly enough space.

EXAMPLE HOW TO USE IT
=====================

CONFIGURATION
-------------
ftp-backup.conf:
# FTP host settings
ftp_host=hostname
ftp_port=port
ftp_user=user
ftp_password=password
ftp_quota=100GB

# Backup directories and how to backup them
backup_dir=/home/user1;/home/user2
compressor=bzip2 # or "gzip" if wished
gpg=/usr/bin/gpg

HOW TO USE IT
-------------
$ ftp-backup -c mybackup.conf

Simply add this in your cronjob file:
crontab:
0 5 * * * root /usr/local/bin/ftp-backup -c /etc/ftp-backup.conf > /dev/null 2>&1

REQUIREMENTS
------------
- PHP
- GnuPG
- ftp command line tool
- tar command line tool
- gzip/bzip2 command line tool
- FTP server access
- UNIX compatible operating system

LICENSE
-------
MIT

AUTHOR
------
Stephan Ferraro