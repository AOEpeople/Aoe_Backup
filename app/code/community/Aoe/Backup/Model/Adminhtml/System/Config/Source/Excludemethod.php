<?php

/**
 * Used in selecting what exclude method should be used for table dumps
 *
 */
class Aoe_Backup_Model_Adminhtml_System_Config_Source_Excludemethod
{

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => '--strip=', 'label' => Mage::helper('adminhtml')->__('Strip')),
            array('value' => '--exclude=', 'label' => Mage::helper('adminhtml')->__('Exclude')),
        );
    }
}
