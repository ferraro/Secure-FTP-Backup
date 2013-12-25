#!/usr/bin/php
<?php
/*
 * Secure FTP Backup - PHP script
 *
 * (C) Stephan Ferraro, 2013 Germany
 *
*/

// FTP backup class
class ftp_backup
{
	var $host;
	var $port = 21;
	var $user;
	var $password;
	var $quota;
	var $backupDirList = array();
	var $compressor;
	var $encryption;
	
	public function setHost($param) {
		$host = $param;
	}
	
	public function setPort($param) {
		$port = (int)$param;
	}
	
	public function setUser($param) {
		$user = $param;
	}
	
	public function setPassword($param) {
		$password = $param;
	}
	
	public function setQuota($param) {
		$quota = $param;
	}
	
	public function setBackupDir($paramList) {
		$backupDirList = $paramList;
	}
	
	public function setCompressor($param) {
		$compressor = $param;
	}
	
	public function setEncryption($param) {
		$encryption = $param;
	}

	public function run() {
		
	}
}

// Main
$backup = new ftp_backup();

// Check program arguments
if (count( $GLOBALS['argv']) !== 2) {
	echo "usage: ".$GLOBALS['argv'][0]." ftp-backup.conf\n";
	exit(1);
}

// Parse configuration file
$fp = @fopen($GLOBALS['argv'][1], 'r') or die("Can't open configuration file '".$GLOBALS['argv'][1]."'.\n");
while ($line = fgets($fp)) {
	$line = rtrim($line);
	$line = str_replace(' ', '', $line);

	if (strlen($line) > 0 && $line[0] != '#') {
		list($key, $value) = explode('=', $line);
		switch ($key) {
			case 'ftp_host':
				$backup->setHost($value);
				break;
			case 'ftp_port':
				$backup->setPort($value);
				break;
			case 'ftp_user':
				$backup->setUser($value);
				break;
			case 'ftp_password':
				$backup->setPassword($value);
				break;
			case 'ftp_quota':
				$backup->setQuota($value);
				break;
			case 'backup_dirs':
				$dirList = explode(';', $value);
				$backup->setBackupDir($dirList);
				break;
			case 'compressor':
				$backup->setCompressor($value);
				break;
			case 'encryption':
				$backup->setEncryption($value);
				break;

			default:
				die("Unknown key: '$key' in configuration file '".$GLOBALS['argv'][1]."'.\n");
		}
	}
}
fclose($fp);

$backup->run();
