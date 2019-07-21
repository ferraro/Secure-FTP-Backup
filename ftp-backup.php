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
	var $VERSION		= '1.0';
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
	var $ftpCmd;
	var $cutCmd;
	var $awkCmd;
	var $LOCKFILE = '/tmp/ftp-backup.lock';
	var $lockfileFp;
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
		$list = array();
		foreach ($paramList as $param) {
			if ($param !== '/') {
				// Remove beginning / if not backuping a full root / filesystem
				$param = ltrim($param, '/');
			}
			$list[]= $param;
		}
		$this->backupDirList = $list;
	}
	
	public function setPrefix($param) {
		$this->prefix = $param;
	}

	public function setFTPCmd($param) {
		$this->ftpCmd = $param;
	}

	public function setCutCmd($param) {
		$this->cutCmd = $param;
	}

	public function setAwkCmd($param) {
		$this->awkCmd = $param;
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
	
	public function logAndDie() {
		syslog(LOG_ALERT, 'Fatal error. FTP backup has failed. Please try to run it manually.');
		exit(1);
	}
	
	private function _archiveDirectories() {
		$tarDirs	= implode($this->backupDirList, ' ');
		$date		= date("Ymd_G:i:s");

		$filename				= '/tmp/'.$this->prefix.$date.'.tar.gpg';
		$this->backupFilename	= $filename;
		fprintf(STDERR, "Create backup file $filename\n");

		$encryptionCmd = $this->encryption.' -e -r "'.$this->encryptionName.'" -';
		// Be able to TAR a whole root file system / by not taring other file systems like e.g. /proc
		$cmd = 'cd /; '.$this->archive.' cf - --exclude='.$filename.' --one-file-system '.$tarDirs.' | '.$encryptionCmd.' > '.$filename;
		fprintf(STDERR, "Execute: $cmd\n");
		$returnVar = 0;
		system($cmd, $returnVar);
		if ($returnVar) {
			fprintf(STDERR, "Fatal error: GnuPG encryption failed\n");
			$this->logAndDie();
		}
	}

	private function _ftpTakenSpaceInBytes() {
		// Get bytes of all files on the FTP server in the main directory
		fprintf(STDERR, "Get from FTP server free space information\n");
		$cmd = '(echo passive; echo open '.$this->host.' '.$this->port.'; echo user '.$this->user.' '.$this->password.
				'; echo ls; echo quit) | '.$this->ftpCmd.' -n | grep -v \'Passive mode on.\' | tr -s \' \' | '.$this->cutCmd.' -d \' \' -f 5 | '.$this->awkCmd.' \'{s+=$1} END {print s}\'';
		return (float)rtrim(`$cmd`);
	}

	private function _deleteFileOnFTP($filename) {
		fprintf(STDERR, "Delete $filename file on remote FTP server\n");
		$cmd = '(echo passive; echo open '.$this->host.' '.$this->port.'; echo user '.$this->user.' '.$this->password.
				'; echo delete '.$filename.'; echo quit) | '.$this->ftpCmd.' -n';
		$returnVar = 0;
		system($cmd, $returnVar);
		if ($returnVar) {
			fprintf(STDERR, "Fatal error: Could not delete file on FTP server\n");
			$this->logAndDie();
		}
	}
	
	private function _purgeFTPSpace() {
		// Get oldest file name in the main directory, then delete it
		// Files in sub directories will not be purged
		$cmd = '((echo passive; echo open '.$this->host.' '.$this->port.'; echo user '.$this->user.' '.$this->password.
				'; echo ls -lrt; echo quit) | '.$this->ftpCmd.' -n | grep -v \'Passive mode on.\' | tr -s \' \' | '.$this->cutCmd.' -d \' \' -f 9 | head -n 1) 2>/dev/null';
		$oldestFileName = rtrim(`$cmd`);
		$this->_deleteFileOnFTP($oldestFileName);
	}
	
	private function _uploadFileToFTP() {
		fprintf(STDERR, "Upload $this->backupFilename file to FTP server\n");
		$singleFileName	= basename($this->backupFilename);
		$dirPath		= dirname($this->backupFilename);
		$cmd = '(echo passive; echo open '.$this->host.' '.$this->port.'; echo user '.$this->user.' '.$this->password.
				"; echo lcd $dirPath; echo put $singleFileName; echo quit) | $this->ftpCmd -nv";
		system($cmd);
	}
	
	public function ftpLs() {
		$cmd = '(echo passive; echo open '.$this->host.' '.$this->port.'; echo user '.$this->user.' '.$this->password.
				'; echo ls -lrt; echo quit) | '.$this->ftpCmd.' -n | grep -v \'Passive mode on.\'';
		system($cmd);
	}
	
	private function _lockProcess() {
		$this->lockfileFp = fopen($this->LOCKFILE, 'a');

		if (!flock($this->lockfileFp, LOCK_EX)) { // exklusive Sperre
		   fprintf(STDERR,
					$GLOBALS['argv'][0].
					": Could not lock $this->LOCKFILE file. Another backup process is currently running.\n");
		}
	}

	private function _unlockProcess() {
		// Tell operating system to delete file as soon as possible
		unlink($this->LOCKFILE);
		// Unlock file
		flock($this->lockfileFp, LOCK_UN);
		// On UNIX a file is really deleted when its last file descriptor is closed.
		fclose($this->lockfileFp);
	}
	
	public function validate() {
		// Validate all parameters and give auto-suggestion if somehting is wrong
		$validateList = array(
			'host'				=>	'ftp_host',
			'port'				=>	'ftp_port',
			'user'				=>	'ftp_user',
			'password'			=>	'ftp_password',
			'quota'				=>	'ftp_quota',
			'prefix'			=>	'prefix',
			'backupDirList'		=>	'backup_dirs',
			'archive'			=>	'archive_cmd',
			'encryption'		=>	'encryption_cmd',
			'encryptionName'	=>	'encryption_key_name',
			'ftpCmd'			=>	'ftp_cmd',
			'cutCmd'			=>	'cut_cmd',
			'awkCmd'			=>	'awk_cmd'
		);
		foreach ($validateList as $internalVariable => $configurationParameter) {
			if (empty($this->$internalVariable)) {
				fprintf(STDERR, "'$configurationParameter' parameter not set in configuration file ".$GLOBALS['argv'][1]."\n");
				$this->logAndDie();
			}
		}
		
		// Check executables
		$validateList = array(
			'archive'			=>	'archive_cmd',
			'encryption'		=>	'encryption_cmd',
			'ftpCmd'			=>	'ftp_cmd',
			'cutCmd'			=>	'cut_cmd',
			'awkCmd'			=>	'awk_cmd'
		);
		foreach ($validateList as $internalVariable => $configurationParameter) {
			$filename = $this->$internalVariable;
			if (!file_exists($filename)) {
				fprintf(STDERR, "'$configurationParameter' parameter with ".$this->$internalVariable." not found in ".$GLOBALS['argv'][1]."\n");
				$this->logAndDie();
			}
		}	
	}

	public function run() {
		// Lock process
		$this->_lockProcess();
		
		$startTime = time();
		
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
			$this->logAndDie();
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
		fprintf(STDERR, "Delete temporary backup file %s\n", $this->backupFilename);
		unlink($this->backupFilename);
		
		// Unlock process
		$this->_unlockProcess();
		$totalTime	= time() - $startTime;
		$info		= "Backup complete of ".basename($this->backupFilename)." ($fileSize bytes) in $totalTime seconds.";
		syslog(LOG_INFO, $info);
		fprintf(STDERR, $info."\n");
	}
}

// Main
$backup = new ftp_backup();

function usage($backup)
{
	fprintf(STDERR, "Secure FTP Backup - (C) Stephan Ferraro 2013 - Version ".$backup->VERSION."\n");
	fprintf(STDERR, "usage: ".$GLOBALS['argv'][0]." ftp-backup.conf [ls]\n");
	exit(1);
}

// Check program arguments
if (count($GLOBALS['argv']) !== 2 && count($GLOBALS['argv']) !== 3) {
	usage($backup);
}
if (count($GLOBALS['argv']) === 3 && $GLOBALS['argv'][2] !== 'ls') {
	usage($backup);
}

// Parse configuration file
$fp = @fopen($GLOBALS['argv'][1], 'r') or die("Can't open configuration file '".$GLOBALS['argv'][1]."'.\n");
while ($line = fgets($fp)) {
	$line = rtrim($line);

	if (strlen($line) > 0 && $line[0] != '#') {
		if (strstr($line, '=') === false) {
			fprintf(STDERR, "Error in configuration file '".$GLOBALS['argv'][1]."'. Invalid line: $line\n");
			$backup->logAndDie();
		}
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
			case 'ftp_cmd':
				$backup->setFTPCmd($value);
				break;
			case 'cut_cmd':
				$backup->setCutCmd($value);
				break;
			case 'awk_cmd':
				$backup->setAwkCmd($value);
				break;				
			case 'archive_cmd':
				$backup->setArchive($value);
				break;
			case 'encryption_cmd':
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

// Check if all parameters are correct
$backup->validate();

if (count($GLOBALS['argv']) === 3) {
	$backup->ftpLs();
} else {
	$backup->run();	
}