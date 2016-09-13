<?php

class Netresearch_OPS_Test_Block_FormTest
    extends EcomDev_PHPUnit_Test_Case_Controller
{
    private $_block;

    public function setUp()
    {
        parent::setup();
        $this->_block = Mage::app()->getLayout()->getBlockSingleton('ops/form');
    }

    public function testIsUserRegistering()
    {
        $dataHelperMock = $this->getHelperMock('ops/data', array('checkIfUserIsRegistering'));
        $dataHelperMock->expects($this->any())
            ->method('checkIfUserIsRegistering')
            ->will($this->returnValue(false));
        $this->replaceByMock('helper', 'ops/data', $dataHelperMock);
        
        $block = new Netresearch_OPS_Block_Form();
        $this->assertFalse($block->isUserRegistering());
    }

    public function testIsUserNotRegistering()
    {
        $dataHelperMock = $this->getHelperMock('ops/data', array('checkIfUserIsNotRegistering'));
        $dataHelperMock->expects($this->any())
            ->method('checkIfUserIsNotRegistering')
            ->will($this->returnValue(false));
        $this->replaceByMock('helper', 'ops/data', $dataHelperMock);
        
        $block = new Netresearch_OPS_Block_Form();
        $this->assertFalse($block->isUserNotRegistering());
    }


    public function testGetPmLogo()
    {
        $this->assertEquals(null, $this->_block->getPmLogo());
    }

    public function testGetFrontendValidatorsAreEmtpyWhenNoExtraParamtersAreSubmitted()
    {
        $quoteMock = Mage::getModel('sales/quote');
        $quoteMock->setStoreId(0);
        $sessionMock = $this->getModelMockBuilder('checkout/session')
            ->disableOriginalConstructor()// This one removes session_start and other methods usage
            ->setMethods(array('getQuote'))
            ->getMock();
        $sessionMock->expects($this->any())
            ->method('getQuote')
            ->will($this->returnValue($quoteMock));
        $this->replaceByMock('singleton', 'checkout/session', $sessionMock);

        $configMock = $this->getModelMock('ops/config', array('canSubmitExtraParameter'));
        $configMock->expects($this->once())
            ->method('canSubmitExtraParameter')
            ->will($this->returnValue(false));
        $this->_block->setConfig($configMock);
        $this->_block->setQuote($quoteMock);
        $this->assertEquals(Mage::helper('core/data')->jsonEncode(array()), $this->_block->getFrontendValidators());
    }

    public function testGetFrontendValidatorsAreEmptyDueToEmptyValidators()
    {
        $configMock = $this->getModelMock('ops/config', array('canSubmitExtraParameter', 'getParameterLengths'));
        $configMock->expects($this->once())
            ->method('canSubmitExtraParameter')
            ->will($this->returnValue(true));
        $configMock->expects($this->once())
            ->method('getParameterLengths')
            ->will($this->returnValue(array()));

        $quote = Mage::getModel('sales/quote');
        $blockMock = $this->getBlockMock('ops/form', array('getQuote'));
        $blockMock->expects($this->any())
            ->method('getQuote')
            ->will($this->returnValue($quote));
        $blockMock->setConfig($configMock);
        $this->assertEquals(Mage::helper('core/data')->jsonEncode(array()), $blockMock->getFrontendValidators());
    }

    public function testGetFrontendValidatorsAreEmptyDueToUnmappedValidators()
    {
        $configMock = $this->getModelMock('ops/config', array('canSubmitExtraParameter', 'getParameterLengths'));
        $configMock->expects($this->once())
            ->method('canSubmitExtraParameter')
            ->will($this->returnValue(true));
        $configMock->expects($this->once())
            ->method('getParameterLengths')
            ->will($this->returnValue(array('Foo' => 50)));

        $quote = Mage::getModel('sales/quote');
        $blockMock = $this->getBlockMock('ops/form', array('getQuote'));
        $blockMock->expects($this->any())
            ->method('getQuote')
            ->will($this->returnValue($quote));
        $blockMock->setConfig($configMock);
        $this->assertEquals(Mage::helper('core/data')->jsonEncode(array()), $blockMock->getFrontendValidators());
    }


    public function testGetFrontendValidatorsAreNotEmpty()
    {

        $configValues = array('CN' => 50, 'ECOM_BILLTO_POSTAL_POSTALCODE' => 10, 'ECOM_SHIPTO_POSTAL_POSTALCODE' => 10);

        $configMock = $this->getModelMock('ops/config', array('canSubmitExtraParameter', 'getParameterLengths'));
        $configMock->expects($this->once())
            ->method('canSubmitExtraParameter')
            ->will($this->returnValue(true));
        $configMock->expects($this->once())
            ->method('getParameterLengths')
            ->will($this->returnValue($configValues));

        $quote = Mage::getModel('sales/quote');
        $blockMock = $this->getBlockMock('ops/form', array('getQuote'));
        $blockMock->expects($this->any())
            ->method('getQuote')
            ->will($this->returnValue($quote));
        $blockMock->setConfig($configMock);
        $this->assertEquals(
            Mage::helper('core/data')->jsonEncode(
                array(
                    'billing:firstname' => 50, 'billing:lastname' => 50, 'billing:postcode' => 10,
                    'shipping:postcode' => 10
                )
            ), $blockMock->getFrontendValidators()
        );
    }
}
