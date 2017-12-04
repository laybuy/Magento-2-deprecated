<?php
/**
 * Created by PhpStorm.
 * User: carl
 * Date: 30/11/17
 * Time: 05:41
 */

namespace Laybuy\LaybuyPayments\Controller\Payment;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ObjectManager;
use Magento\Checkout\Model\Session;
use Magento\Framework\Controller\ResultFactory;
use Magento\Quote\Api\Data\CartInterface;

use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Webapi\Exception;
use Magento\Payment\Model\Method\Logger;

use Laybuy\LaybuyPayments\Gateway\Config\Config;
use Laybuy\LaybuyPayments\Model\Helper;

class Process extends Action {
    
    const LAYBUY_LIVE_URL = 'https://api.laybuy.com';
    
    const LAYBUY_SANDBOX_URL = 'https://sandbox-api.laybuy.com';
    
    const LAYBUY_RETURN = 'laybuypayments/payment/process';
    

    
    /**
     * @var bool
     */
    protected $laybuy_sandbox = TRUE;
    
    /**
     * @var string
     */
    protected $laybuy_merchantid;
    
    /**
     * @var string
     */
    protected $laybuy_apikey;
    
    /**
     * @var string
     */
    protected $endpoint;
    
    /**
     * @var \Zend_Rest_Client
     */
    protected $restClient;
    
    /**
     * @var Config
     */
    private $config;
    

    /**
     * @var Session
     */
    protected $checkoutSession;
    
    /**
     * @var Helper\OrderPlace
     */
    private $orderPlace;
    
    /**
     * Logger for exception details
     *
     * @var LoggerInterface
     */
    private $logger;
    
    /**
     * Constructor
     *
     * @param Context $context
     * @param Config $config
     * @param Session $checkoutSession
     * @param Helper\OrderPlace $orderPlace
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Context $context,
        Config $config,
        Session $checkoutSession,
        Helper\OrderPlace $orderPlace,
        Logger $logger
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->orderPlace = $orderPlace;
        $this->logger     = $logger;
    }
    
    public function execute() {
    
        $this->logger->debug([__METHOD__ => 'start']);
    
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT );
        
        $quote          = $this->checkoutSession->getQuote();
        
        /*
         *  $requestData = json_decode(
            $this->getRequest()->getPostValue('result', '{}'),
            true
        );
         */
        try {
            
            // get the curetn quote and match this wilt the session??
            
            // we need to complet the quote, thi sonly makes sense if we have an exisiting sesion up
            
            // need to check for success or fail
            
            //die();
            //
            //status=CANCELLED | SUCCESS
            $status = $this->getRequest()->getParam('status');
            
            if($status == 'SUCCESS'){
                
                $this->validateQuote($quote);
    
                $this->orderPlace->execute($quote);
    
                /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
                return $resultRedirect->setPath('checkout/onepage/success', ['_secure' => TRUE]);
                
            }
            
        } catch (\Exception $e) {
            //$this->logger->debug(['error' => $e]);??
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        }
        
        $this->messageManager->addNoticeMessage('Laybuy payment was Cancelled');
        
        return $resultRedirect->setPath('checkout/cart', ['_secure' => TRUE]);
        
        
    }
    
    /**
     * @param CartInterface $quote
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function validateQuote($quote) {
        if (!$quote || !$quote->getItemsCount()) {
            throw new \InvalidArgumentException(__('We can\'t initialize checkout.'));
        }
    }
    
    /**
     * Returns response fields for result code
     *
     * @param int $resultCode
     *
     * @return \Zend_Rest_Client
     */
    private function getRestClient() {
        
        if (is_null($this->laybuy_merchantid)) { // ?? just do it anyway?
            $this->setupLaybuy();
        }
        
        try {
            
            $this->restClient = new \Zend_Rest_Client($this->endpoint);
            $this->restClient->getHttpClient()->setAuth($this->laybuy_merchantid, $this->laybuy_apikey, \Zend_Http_Client::AUTH_BASIC);
            
            //$this->restClient->getHttpClient()->setAuth('100000' , 'Kaz1xe5WwpOvl3pJL4FqqX1vrnJGrcxghJRKQqZddKBLg23DxsQA2qRhK6QJcVus', \Zend_Http_Client::AUTH_BASIC);
            // 'auth' => ['100000', 'Kaz1xe5WwpOvl3pJL4FqqX1vrnJGrcxghJRKQqZddKBLg23DxsQA2qRhK6QJcVus'],
            
        } catch (\Exception $e) {
            
            // Mage::logException($e);
            // Mage::helper('checkout')->sendPaymentFailedEmail($this->getOnepage()->getQuote(), $e->getMessage());
            
            $this->logger->debug([__METHOD__ . ' CLIENT FAILED: ' => $this->laybuy_merchantid . ":" . $this->laybuy_apikey]);
            
            $result['success']        = FALSE;
            $result['error']          = TRUE;
            $result['error_messages'] = $this->__('[Laybuy connect] There was an error processing your order. Please contact us or try again later.');
            // TODOD this error needs to go back to the user
        }
        
        return $this->restClient;
        
    }
    
    
    private function setupLaybuy() {
        $this->logger->debug([__METHOD__ . ' sandbox? ' => $this->config->getUseSandbox()]);
        $this->logger->debug([__METHOD__ . ' sandbox_merchantid? ' => $this->config->getSandboxMerchantId()]);
        
        $this->laybuy_sandbox = $this->config->getUseSandbox() == 1;
        
        if ($this->laybuy_sandbox) {
            $this->endpoint          = self::LAYBUY_SANDBOX_URL;
            $this->laybuy_merchantid = $this->config->getSandboxMerchantId();
            $this->laybuy_apikey     = $this->config->getSandboxApiKey();
        }
        else {
            $this->endpoint          = self::LAYBUY_LIVE_URL;
            $this->laybuy_merchantid = $this->config->getMerchantId();
            $this->laybuy_apikey     = $this->config->getApiKey();
        }
        
        $this->logger->debug([__METHOD__ . ' CLIENT INIT: ' => $this->laybuy_merchantid . ":" . $this->laybuy_apikey]);
        $this->logger->debug([__METHOD__ . ' INITIALISED' => '']);
    }
    
}