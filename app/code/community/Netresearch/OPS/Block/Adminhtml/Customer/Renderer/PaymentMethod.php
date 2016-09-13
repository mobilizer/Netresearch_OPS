<?php

/**
 * PaymentMethod.php
 *
 * @author Paul Siedler <paul.siedler@netresearch.de>
 */
class Netresearch_OPS_Block_Adminhtml_Customer_Renderer_PaymentMethod
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{

    public function render(Varien_Object $row)
    {
        $methodCode = $row->getData($this->getColumn()->getIndex());
        $instance = Mage::helper('payment')->getMethodInstance($methodCode);
        if ($instance) {
            return $instance->getTitle();
        }
    }
}