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
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Quote\Model\Quote\PaymentFactory;

use Laybuy\LaybuyPayments\Gateway\Config\Config;
use Laybuy\LaybuyPayments\Model\Helper;
use Laybuy\LaybuyPayments\Gateway\Http\Client\Laybuy;
use Laybuy\LaybuyPayments\Gateway\Request\CaptureRequest;
use Laybuy\LaybuyPayments\Gateway\Http\TransferFactory;


class Redirect extends Action {
    
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
     * @var CheckoutSession
     */
    protected $checkoutSession;
    
    /**
     * @var CheckoutSession
     */
    protected $customerSession;
    
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
     * @var $client Laybuy
     */
    private $client;
    
    /**
     * @var CaptureRequest
     */
    private $request_bulider;
    
    /**
     * @var TransferFactory
     */
    private $transferFactory;
    
    
    /**
     * @var PaymentFactory
     */
    private $paymentFactory;
    
    
    /**
     * Redirect constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Laybuy\LaybuyPayments\Gateway\Config\Config $config
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Laybuy\LaybuyPayments\Gateway\Http\Client\Laybuy $client
     * @param \Laybuy\LaybuyPayments\Gateway\Http\TransferFactory $transferFactory
     * @param \Magento\Quote\Model\Quote\PaymentFactory $paymentFactory
     */
    public function __construct(
        Context $context,
        Config $config,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        Logger $logger,
        Laybuy $client,
        TransferFactory $transferFactory,
        PaymentFactory $paymentFactory
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->logger          = $logger;
        $this->client          = $client;
        $this->transferFactory = $transferFactory;
        $this->paymentFactory  = $paymentFactory;
    }
    
    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute() {
        
        $this->logger->debug([__METHOD__ => 'start']);
        
        $guest_email = $this->getRequest()->getParam('guest');
        
        $quote = $this->checkoutSession->getQuote();
    
        try{
            
            $this->validateQuote($quote);
    
            if ($guest_email) {
                $quote->setCustomerEmail($guest_email);
                $quote->getBillingAddress()->setCustomerEmail($guest_email); // for the order create later
                $quote->save();
            }
    
            $laybuy_order = $this->client->makeLaybuyOrder($quote);
    
            $redirect_url = $this->client->getLaybuyRedirectUrl($laybuy_order);
    
            if ($redirect_url) {
                $this->logger->debug([__METHOD__ . '  LAYBUY REDIRECT URL ' => $redirect_url]);
        
                //return $this->resultRedirectFactory->create()->setUrl($action['RETURN_URL']);
                return $this->_redirect($redirect_url);
            }
            
            // fall though
            
        } catch (\Exception $e) {
            
            $this->logger->debug([__METHOD__ . ' ERROR LAYBUY REDIRECT '. $e->getMessage() . " " => $e->getTraceAsString()]);
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
            
        }
        
        return $this->_redirect('checkout/cart');
        
    }
    
    /**
     * @param CartInterface $quote
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function validateQuote(\Magento\Quote\Model\Quote $quote) {
        
        $this->logger->debug([ __METHOD__ . ' QUOTE IS: ' . get_class($quote)]);
        if (!$quote || !$quote->getItemsCount()) {
            throw new \InvalidArgumentException(__("We can't initialize checkout."));
        }
    }
    
    
}