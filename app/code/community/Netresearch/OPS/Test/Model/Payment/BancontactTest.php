<?php


class Netresearch_OPS_Test_Model_Payment_BancontactTest extends EcomDev_PHPUnit_Test_Case
{

    protected $model = null;

    public function setUp()
    {
        parent::setUp();
        $this->model = Mage::getModel('ops/payment_bancontact');
        $this->model->setInfoInstance(Mage::getModel('payment/info'));
    }

    public function testCanCapturePartial()
    {
        $this->assertTrue($this->model->canCapturePartial());
    }

    public function testGetOpsCode()
    {
        $this->assertEquals('CreditCard', $this->model->getOpsCode());
    }

    public function testGetOpsBrand()
    {
        $this->assertEquals('BCMC', $this->model->getOpsBrand());
    }

}
