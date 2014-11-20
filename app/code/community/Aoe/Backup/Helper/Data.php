<?php
/**
 * Class ${NAME}
 *
 * @author Fabrizio Branca
 * @since 2014-10-07
 */
class Aoe_Backup_Helper_Data extends Mage_Core_Helper_Abstract {

    /**
     * Checks if n98-magerun is present and returns the version number
     */
    public function checkN98Magerun() {

        $output = $this->runN98Magerun(array('--version'));
        if (!isset($output[0]) || strpos($output[0], 'n98-magerun version') === false) {
            Mage::throwException('No valid n98-magerun found');
        }

        $matches = array();
        preg_match('/(\d+\.\d+\.\d)/', $output[0], $matches);

        return $matches[1];
    }

    /**
     * Get n98-magerun path
     *
     * @return string
     * @throws Mage_Core_Exception
     */
    public function getN98MagerunPath() {
        $pathN98 = Mage::getStoreConfig('system/aoe_backup/path_n98');
        $baseDir = Mage::getBaseDir();
        $path = $pathN98;
        if (!is_file($path)) {
            Mage::throwException('Could not find n98-magerun at ' . $path);
        }
        return $path;
    }

    public function runN98Magerun($options=array()) {
        array_unshift($options, '--root-dir='.Mage::getBaseDir());
        array_unshift($options, '--no-interaction');
        array_unshift($options, '--no-ansi');
        $output = array();
        // TODO: extract php path?
        // echo '/usr/bin/php -d apc.enable_cli=0 ' . $this->getN98MagerunPath() . ' ' . implode(' ', $options);
        exec('/usr/bin/php -d apc.enable_cli=0 ' . $this->getN98MagerunPath() . ' ' . implode(' ', $options), $output);
        return $output;
    }

    /**
     * Explodes a string and trims all values for whitespace in the ends.
     * If $onlyNonEmptyValues is set, then all blank ('') values are removed.
     *
     * @see t3lib_div::trimExplode() in TYPO3
     * @param $delim
     * @param string $string
     * @param bool $removeEmptyValues If set, all empty values will be removed in output
     * @return array Exploded values
     */
    public function pregExplode($delim, $string, $removeEmptyValues = true)
    {
        $explodedValues = preg_split($delim, $string);

        $result = array_map('trim', $explodedValues);

        if ($removeEmptyValues) {
            $temp = array();
            foreach ($result as $value) {
                if ($value !== '') {
                    $temp[] = $value;
                }
            }
            $result = $temp;
        }

        return $result;
    }

} 