<?php
/**
 * Class Aoe_Backup_Model_Cron
 *
 * @author Fabrizio Branca
 * @since 2014-10-06
 */
class Aoe_Backup_Model_Cron {

    CONST DB_DIR = 'database';
    CONST FILES_DIR = 'files';

    protected $usingTempDir = false;
    protected $localDir;

    /**
     * backup
     *
     * @param Aoe_Scheduler_Model_Schedule $schedule
     * @return array
     */
    public function backup(Aoe_Scheduler_Model_Schedule $schedule) {
        if (!Mage::getStoreConfig('system/aoe_backup/enable')) {
            return 'NOTHING: Backup is disabled in configuration';
        }

        $didSomething = false;

        // if not enabled return $this (status skipped)
        $statistics = array();
        $statistics['Durations'] = array();
        $startTime = microtime(true);

        if (Mage::getStoreConfigFlag('system/aoe_backup/backup_database')) {
            $didSomething = true;
            $this->createDatabaseBackup();

            $stopTime = microtime(true);
            $statistics['Durations']['DB backup'] = number_format($stopTime - $startTime, 2);
            $startTime = $stopTime;
        }

        if (Mage::getStoreConfigFlag('system/aoe_backup/backup_files')) {
            $didSomething = true;
            $this->createMediaBackup();

            $stopTime = microtime(true);
            $statistics['Durations']['media backup'] = number_format($stopTime - $startTime, 2);
            $startTime = $stopTime;
        }

        if (!$didSomething) {
            return 'NOTHING: Database and file backup are disabled.';
        }

        $statistics['uploadinfo'] = $this->upload();

        $stopTime = microtime(true);
        $statistics['Durations']['upload'] = number_format($stopTime - $startTime, 2);

        // delete tmp directory if it was created
        // return some statistics (duration, filesize)
        return $statistics;
    }

    /**
     * Create Database Backup
     *
     * @return void
     * @throws Mage_Core_Exception
     */
    protected function createDatabaseBackup() {
        $res = touch(Mage::getBaseDir('var') . '/db_dump_in_progress.lock');
        if (!$res) {
            Mage::throwException('Error while creating lock file');
        }

        $helper = Mage::helper('Aoe_Backup'); /* @var $helper Aoe_Backup_Helper_Data */


        $excludedTables = Mage::getStoreConfig('system/aoe_backup/excluded_tables');
        $excludedTables = $helper->pregExplode('/\s+/', $excludedTables);

        $targetFile = $this->getLocalDirectory() . DS . self::DB_DIR . DS . 'combined_dump.sql';

        if (is_file($targetFile . '.gz')) {
            $res = unlink($targetFile . '.gz');
            if (!$res) {
                Mage::throwException('Error while deleting existing db dump at ' . $targetFile . '.gz');
            }
        }

        $output = $helper->runN98Magerun(array(
            '-q',
            'db:dump',
            '--compression=gzip',
            '--strip="'.implode(' ', $excludedTables).'"',
            $targetFile // n98-magerun will create a combined_dump.sql.gz instead because of the compression
        ));

        if (!is_file($targetFile . '.gz')) {
            Mage::throwException('Could not find generated database dump at ' . $targetFile . '.gz');
        }
        $filesize = filesize($targetFile . '.gz');
        if ($filesize < 1024 * 10) { // 10 KB
            Mage::throwException('File is too small. Check contents at ' . $targetFile . '.gz');
        }

        // created.txt
        $filename = $this->getLocalDirectory() . DS . self::DB_DIR . DS . 'created.txt';
        $res = file_put_contents($filename, time());
        if ($res === FALSE) {
            Mage::throwException('Error while writing ' . $filename);
        }

        $res = unlink(Mage::getBaseDir('var') . '/db_dump_in_progress.lock');
        if ($res === FALSE) {
            Mage::throwException('Error while deleting lock file');
        }
    }

    /**
     * Create Media Backup
     *
     * @return void
     * @throws Mage_Core_Exception
     */
    protected function createMediaBackup() {

        $helper = Mage::helper('Aoe_Backup'); /* @var $helper Aoe_Backup_Helper_Data */
        $excludedDirs = Mage::getStoreConfig('system/aoe_backup/excluded_directories');
        $excludedDirs = $helper->pregExplode('/\s+/', $excludedDirs);

        $options = array(
            '--archive',
            '--no-o --no-p --no-g',
            '--force',
            '--omit-dir-times',
            '--ignore-errors',
            '--partial',
            '--delete-after',
            '--delete-excluded',
        );

        foreach ($excludedDirs as $dir) {
            $options[] = '--exclude='.$dir;
        }

        // source
        $options[] = rtrim(Mage::getBaseDir('media'), DS) . DS;

        // target
        $options[] = $this->getLocalDirectory() . DS . self::FILES_DIR . DS;


        $path_rsync = Mage::getStoreConfig('system/aoe_backup/path_rsync');
        if (empty($path_rsync)) {
            Mage::throwException('No $path_rsync found (system/aoe_backup/path_rsync)');
        }


        $output = array();
        $returnVar = null;
        exec($path_rsync . ' ' . implode(' ', $options), $output, $returnVar);
        if ($returnVar) {
            Mage::throwException('Error while rsyncing files to local directory');
        }

        // TODO: optionally minify files first

        // created.txt
        $filename = $this->getLocalDirectory() . DS . self::FILES_DIR . DS . 'created.txt';
        $res = file_put_contents($filename, time());
        if ($res === FALSE) {
            Mage::throwException('Error while writing ' . $filename);
        }
    }

    /**
     * Upload to S3
     *
     * @return array
     * @throws Mage_Core_Exception
     */
    protected function upload() {

        $region = Mage::getStoreConfig('system/aoe_backup/aws_region');
        $keyId = Mage::getStoreConfig('system/aoe_backup/aws_access_key_id');
        $secret = Mage::getStoreConfig('system/aoe_backup/aws_secret_access_key');
        $pathAwsCli = Mage::getStoreConfig('system/aoe_backup/path_awscli');

        if (empty($pathAwsCli)) {
            Mage::throwException('No pathAwsCli found (system/aoe_backup/path_awscli)');
        }

//        if (empty($region)) {
//            Mage::throwException('No region found (system/aoe_backup/aws_region)');
//        }

        if (!empty($keyId)) {
            $keyId = Mage::helper('core')->decrypt($keyId);
            putenv("AWS_ACCESS_KEY_ID=$keyId");
        }
        if (!empty($secret)) {
            $secret = Mage::helper('core')->decrypt($secret);
            putenv("AWS_SECRET_ACCESS_KEY=$secret");
        }

        $targetLocation = Mage::getStoreConfig('system/aoe_backup/aws_target_location');
        if (strpos($targetLocation, 's3://') !== 0) {
            Mage::throwException('Invalid S3 target location (must start with s3://)');
        }
        $targetLocation = rtrim($targetLocation, DS);



        putenv("AWS_SESSION_TOKEN"); // will be removed
        if ($region) {
            putenv("AWS_DEFAULT_REGION=$region");
        } else {
            putenv("AWS_DEFAULT_REGION"); // will be removed
        }

        $uploadInfo = array();

        $options = array();
        if ($region) { $options[] = '--region ' . $region; }
        $options[] = 's3 sync';
        $options[] = '--exact-timestamps';
        $options[] = $this->getLocalDirectory();
        $options[] = $targetLocation . DS;

        $output = array();
        $returnVar = null;
        $command = $pathAwsCli .' ' . implode(' ', $options);
        exec($command, $output, $returnVar);
        if ($returnVar) {
            Mage::throwException('Error while syncing directories. Command: '.$command.' // Output: ' . implode("\n", $output));
        }
        $uploadInfo['sync'] = array(
            'output' => implode("\n", $output),
            'returnVar' => $returnVar,
        );

        $type = array();

        if (Mage::getStoreConfigFlag('system/aoe_backup/backup_database')) {
            $type[] = self::DB_DIR;
        }

        if (Mage::getStoreConfigFlag('system/aoe_backup/backup_files')) {
            $type[] = self::FILES_DIR;
        }

        // force upload created.txt (since sync might not detect changes since the filesize doesn't change)
        foreach ($type as $dirSegment) {
            $localFile = $this->getLocalDirectory() . DS . $dirSegment . DS . 'created.txt';
            $remoteFile = $targetLocation . DS . $dirSegment . DS . 'created.txt';

            $options = array();
            if ($region) { $options[] = '--region ' . $region; }
            $options[] = 's3 cp';
            $options[] = $localFile;
            $options[] = $remoteFile;

            $output = array();
            $returnVar = null;
            $command = $pathAwsCli .' ' . implode(' ', $options);
            exec($command, $output, $returnVar);
            if ($returnVar) {
                Mage::throwException('Error while copying '.$remoteFile.'. Command: '.$command.' // Output: ' . implode("\n", $output));
            }
            $uploadInfo[$dirSegment] = array(
                'output' => implode("\n", $output),
                'returnVar' => $returnVar,
            );
        }

        return $uploadInfo;
    }

    /**
     * Get Local Directory
     *
     * @return string
     * @throws Mage_Core_Exception
     */
    protected function getLocalDirectory() {
        if (empty($this->localDir)) {

            // if configuration is empty create tmp directory and store that information
            $this->localDir = Mage::getStoreConfig('system/aoe_backup/local_directory');
            $this->localDir = rtrim($this->localDir, DS);

            if (empty($this->localDir)) {
                $this->usingTempDir = true;

                $this->localDir = Mage::getBaseDir('var') . '/aoe_backup';

                $dirs = array(
                    $this->localDir,
                    $this->localDir . DS . self::DB_DIR,
                    $this->localDir . DS . self::FILES_DIR
                );

                foreach ($dirs as $dir) {
                    if (!is_dir($dir)) {
                        $res = mkdir($dir);
                        if ($res === false) {
                            Mage::throwException('Error creating local dir: ' . $dir);
                        }
                    }
                }
            }

            foreach (array($this->localDir, $this->localDir . DS . self::DB_DIR, $this->localDir . DS . self::FILES_DIR) as $dir) {
                if (!is_dir($dir)) {
                    Mage::throwException('Could not find local directory at ' . $dir);
                }
            }
        }
        return $this->localDir;
    }

} 