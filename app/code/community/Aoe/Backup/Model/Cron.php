<?php
/**
 * Class Aoe_Backup_Model_Cron
 *
 * @author Fabrizio Branca
 * @since 2014-10-06
 */
class Aoe_Backup_Model_Cron {

    public function backup() {
        // if not enabled return $this (status skipped)
        $this->createDatabaseBackup();
        $this->createMediaBackup();
        $this->upload();
        // delete tmp directory if it was created
        // return some statistics (duration, filesize)
    }

    protected function createMediaBackup() {

        // rsync files to local directory

        // optionally minify files first

        // date +%s > ${SYSTEMSTORAGE_LOCAL}files/created.txt

    }

    protected function createDatabaseBackup() {

        // create /var/db_dump_in_progress.lock

        // create dump using n98-magerun

        // date +%s > ${SYSTEMSTORAGE_LOCAL}database/created.txt

        // delete /var/db_dump_in_progress.lock

    }

    protected function upload() {
        // configure aws cli (through environmen variables)
        // upload files
    }

    protected function getLocalDirectory() {
        // if configuration is empty create tmp directory and store that information
    }

} 