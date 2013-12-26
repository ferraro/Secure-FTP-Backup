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
	var $encryption;
	var $encryptionName;
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
	
	public function setEncryption($param) {
		$this->encryption = $param;
	}

	public function setEncryptionName($param) {
			$this->encryptionName = $param;
	}
	
	private function _archiveDirectories() {
		$tarDirs	= implode($this->backupDirList, ' ');
		// Remove first / as we will tar from /
		$tarDirs	= ltrim($tarDirs, '/');
		$date		= date("Ymd_G:i:s");

		$filename				= '/tmp/'.$this->prefix.$date.'.tar.gpg';
		$this->backupFilename	= $filename;
		fprintf(STDERR, "Create backup file $filename\n");

		$encryptionCmd = $this->encryption.' -e -r "'.$this->encryptionName.'" -';
		$cmd = 'cd /; '.$this->archive.' cf - '.$tarDirs.' | '.$encryptionCmd.' > '.$filename;
		fprintf(STDERR, "Execute: $cmd\n");
		system($cmd);
	}

	private function _ftpTakenSpaceInBytes() {
		// Get bytes of all files on the FTP server in the main directory
		fprintf(STDERR, "Get from FTP server free space information\n");
		$cmd = '(echo open '.$this->host.' '.$this->port.'; echo user '.$this->user.' '.$this->password.
				'; echo ls; echo quit) | /usr/bin/ftp -n | tr -s \' \' | /usr/bin/cut -d \' \' -f 5 | /usr/bin/awk \'{s+=$1} END {print s}\'';
		return (float)rtrim(`$cmd`);
	}

	private function _deleteFileOnFTP($filename) {
		fprintf(STDERR, "Delete $filename file on remote FTP server\n");
		$cmd = '(echo open '.$this->host.' '.$this->port.'; echo user '.$this->user.' '.$this->password.
				'; echo delete '.$filename.'; echo quit) | /usr/bin/ftp -n';
		system($cmd);
	}
	
	private function _purgeFTPSpace() {
		// Get oldest file name in the main directory, then delete it
		// Files in sub directories will not be purged
		$cmd = '((echo open '.$this->host.' '.$this->port.'; echo user '.$this->user.' '.$this->password.
				'; echo ls -lrt; echo quit) | /usr/bin/ftp -n | tr -s \' \' | /usr/bin/cut -d \' \' -f 9 | head -n 1) 2>/dev/null';
		$oldestFileName = rtrim(`$cmd`);
		$this->_deleteFileOnFTP($oldestFileName);
		exit(1);
	}
	
	private function _uploadFileToFTP() {
		fprintf(STDERR, "Upload $this->backupFilename file to FTP server\n");
		$cmd = '(echo open '.$this->host.' '.$this->port.'; echo user '.$this->user.' '.$this->password.
				'; echo put '.$this->backupFilename.'; echo quit) | /usr/bin/ftp -n';
		system($cmd);
	}
	
	public function run() {
		date_default_timezone_set('UTC');
		// Backups should not be possible to be readen by another user than the user which is
		// running this script.
		umask(0177);
		$this->_archiveDirectories();

		// Now check if there is enough free space on the FTP server, if not we need to delete
		// the oldest files
		$ftpTakenSpace	= $this->_ftpTakenSpaceInBytes();
		$percent		= ($ftpTakenSpace / $this->quota * 100);

		fprintf(STDERR, "FTP account takes currently $ftpTakenSpace of ".$this->quota." bytes (%02.02f%%)\n", $percent);
		
		$statList	= lstat($this->backupFilename);
		$fileSize	= $statList['size'];
		
		// Check if file size is bigger than full available FTP quota
		if ($fileSize > $this->quota) {
			fprintf(STDERR,
					$GLOBALS['argv'][0].
					": Fatal error: File size of backup ($fileSize bytes) is bigger than full available space on FTP server (".
					$this->quota." bytes)\n");
			exit(1);
		}

		if ($fileSize + $ftpTakenSpace > $this->quota) {
			fprintf(STDERR, "FTP space is full, purging files to get free space\n");
			while ($fileSize + $ftpTakenSpace > $this->quota) {
				// Purge FTP space as long there is enough space free by deleting files which are the oldest
				$this->_purgeFTPSpace();
				// Fetch new free space of FTP server
				$ftpTakenSpace	= $this->_ftpTakenSpaceInBytes();
			}
		}
		
		// There is enough space on the FTP server, upload file
		$this->_uploadFileToFTP();
		
		// Delete temporary file
		unlink($this->backupFilename);
	}
}

// Main
$backup = new ftp_backup();

// Check program arguments
if (count( $GLOBALS['argv']) !== 2) {
	fprintf(STDERR, "usage: ".$GLOBALS['argv'][0]." ftp-backup.conf\n");
	exit(1);
}

// Parse configuration file
$fp = @fopen($GLOBALS['argv'][1], 'r') or die("Can't open configuration file '".$GLOBALS['argv'][1]."'.\n");
while ($line = fgets($fp)) {
	$line = rtrim($line);

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
			case 'encryption':
				$backup->setEncryption($value);
				break;
			case 'encryption_key_name':
				$backup->setEncryptionName($value);
				break;	

			default:
				die("Unknown key: '$key' in configuration file '".$GLOBALS['argv'][1]."'.\n");
		}
	}
}
fclose($fp);

$backup->run();
