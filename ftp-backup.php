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
	var $port			= 21;
	var $user;
	var $password;
	var $quota;
	var $backupDirList	= array();
	var $prefix;
	var $archive;
	var $compressor;
	var $encryption;
	var $backupFilename;
	
	public function setHost($param) {
		$this->host = $param;
	}
	
	public function setPort($param) {
		$this->port = (int)$param;
	}
	
	public function setUser($param) {
		$this->user = $param;
	}
	
	public function setPassword($param) {
		$this->password = $param;
	}
	
	public function setQuota($param) {
		$this->quota = $param;
	}
	
	public function setBackupDir($paramList) {
		$this->backupDirList = $paramList;
	}
	
	public function setPrefix($param) {
		$this->prefix = $param;
	}

	public function setArchive($param) {
		$this->archive = $param;
	}
	
	public function setCompressor($param) {
		$this->compressor = $param;
	}
	
	public function setEncryption($param) {
		$this->encryption = $param;
	}
	
	private function _compressDirectories() {
		$tarDirs	= implode($this->backupDirList, ' ');
		// Remove first / as we will tar from /
		$tarDirs	= ltrim($tarDirs, '/');
		$date		= date("Ymd_G:i:s");
		
		$suffix		= '.gz';
		if (strstr($this->compressor, 'bzip2')) {
			// If gzip is not used, then only bzip is allowed, so choose .bz2 as suffix
			$suffix = '.bz2';
		}

		$filename				= '/tmp/'.$this->prefix.$date.'.tar'.$suffix;
		$this->backupFilename	= $filename;
		fprintf(STDERR, "Create backup file $filename\n");

		$cmd = 'cd /; '.$this->archive.' cf - '.$tarDirs.' | '.$this->compressor.' -9 > '.$filename;
		fprintf(STDERR, "Execute: $cmd\n");
		system($cmd);
	}
	
	private function _encrypt() {
		fprintf(STDERR, "Encrypt file ".$this->backupFilename."\n");
		system($this->encryption.' -esr "Secure FTP Backup" '.$this->backupFilename);
	}
	
	private function _ftpTakenSpaceInBytes() {
		// Get bytes of all files on the FTP server in the main directory
		$cmd = '(echo open '.$this->host.' '.$this->port.'; echo user '.$this->user.' '.$this->password.
				'; echo ls; echo quit) | /usr/bin/ftp -n | tr -s \' \' | /usr/bin/cut -d \' \' -f 5 | /usr/bin/awk \'{s+=$1} END {print s}\'';
		return rtrim(`$cmd`);
	}

	public function run() {
		date_default_timezone_set('UTC');
		// Backups should not be possible to be readen by another user than the user which is
		// running this script.
		umask(0177);
		$this->_compressDirectories();
		$this->_encrypt();
		
		// Now check if there is enough free space on the FTP server, if not we need to delete
		// the oldest files
		$ftpTakenSpace	= $this->_ftpTakenSpaceInBytes();
		$percent		= ($ftpTakenSpace / $this->quota * 100);

		fprintf(STDERR, "FTP account takes currently $ftpTakenSpace of ".$this->quota." bytes (%02.02f%%)\n", $percent);
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
			case 'prefix':
				$backup->setPrefix($value);
				break;
			case 'archive':
				$backup->setArchive($value);
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
