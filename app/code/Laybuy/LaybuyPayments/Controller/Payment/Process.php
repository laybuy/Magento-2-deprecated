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
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\OrderService;

use Laybuy\LaybuyPayments\Gateway\Config\Config;
use Laybuy\LaybuyPayments\Model\Helper;
use Laybuy\LaybuyPayments\Gateway\Http\Client\Laybuy;


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
     * @var CustomerSession
     */
    protected $customerSession;
    
    /**
     * @var CheckoutSession
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
     * @var EventManager
     */
    private $eventManager;
    
    /**
     * @var EventManager
     */
    private $orderFactory;
    
    
    private $orderManagement;
    
    protected $quoteRepository;
    
    protected $paymentHelper;
    
    protected $cartManagement;
    
    protected $checkout;
    
    protected $client;
    
    
    public function __construct(
        Context $context,
        Config $config,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        Helper\OrderPlace $orderPlace,
        \Magento\Framework\Event\Manager $eventManager,
        OrderFactory $order_factory,
        Logger $logger,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        OrderService $orderManagement,
        PaymentHelper $paymentHelper,
        CartManagementInterface $cartManagement,
        Onepage $checkout,
        Laybuy $client
    ) {
        parent::__construct($context);
        $this->config          = $config;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->orderPlace      = $orderPlace;
        $this->logger          = $logger;
        $this->eventManager    = $eventManager;
        $this->orderFactory    = $order_factory;
        $this->quoteRepository = $quoteRepository;
        $this->paymentHelper   = $paymentHelper;
        $this->cartManagement  = $cartManagement;
        $this->checkout        = $checkout;
        $this->client          = $client;
    }
    
    public function execute() {
        
        $this->logger->debug([__METHOD__ => 'start']);
        
        //status=CANCELLED | SUCCESS
        $status = strtoupper($this->getRequest()->getParam('status'));
        $token  = $this->getRequest()->getParam('token');
        
        /* @var $quote \Magento\Quote\Model\Quote */
        // try checkout session  -- look into fall backs?
        $quote = $this->checkoutSession->getQuote();
        
        
        try {
            if ($status == Laybuy::SUCCESS) {
                if ($this->client->getCheckoutMethod($quote) === Onepage::METHOD_GUEST) {
                
                }
                
                // move this higher
                $laybuy_order_id = $this->client->laybuyConfirm($token);
                
                if (!$laybuy_order_id) {
                    $this->messageManager->addNoticeMessage('Laybuy: There was an error' . ( ($this->client->last_error)?', '. $this->client->last_error : '' ) . '.' );
                    $this->client->laybuyCancel($token);
                    
                    // we are done
                    return $this->_redirect('checkout/cart', ['_secure' => TRUE]);
                }
                
                
                // setup order with the onepage helper
                $this->checkout->setQuote($quote);
                
                $paymentData = [
                    "method" => "laybuy_laybuypayments",
                ];
                
                $this->checkout->savePayment($paymentData);
                $this->checkout->saveOrder();
                
                
                /* @var $order \Magento\Sales\Model\Order */
                $order = $this->checkoutSession->getLastRealOrder();
                
                $invoices = $order->getInvoiceCollection();
                
                $this->logger->debug(['order_id' => $order->getId()]);
                $this->logger->debug(['invoices' => count($invoices)]);
                
                foreach ($invoices as $invoice) {
                    /* @var $invoice \Magento\Sales\Model\Order\Invoice */
                    /* $invoice->setState(2); */
                    $invoice->pay();
                    $invoice->save();
                }
                
                $txn_id = $laybuy_order_id . '_' . $token;
                
                //TODO look into assigning a Txn ID on the order
                //$order->getPayment()->setLastTransId($txn_id);
                //$order->setCustomerNote('Paid with Laybuy: ' . $txn_id);
                //$order->save();
                
                $this->logger->debug(['txn_id' => $txn_id]);
                
                // TODO: look into why this causes the success page to bounce back to the cart
                //$this->checkoutSession->clearQuote();
                
                return $this->_redirect('checkout/onepage/success', ['_secure' => TRUE]);
                
            }
            else {
                
                // the Neat this is that we done need to do anything
                // there isn't an order yet
                
                if ($status == Laybuy::CANCELLED) {
                    $this->messageManager->addNoticeMessage('Laybuy payment was Cancelled.');
                }
                else {
                    $this->messageManager->addNoticeMessage('Laybuy: There was an error, your payment failed.');
                }
                
                $this->client->laybuyCancel($token);
                
                // fall though to cart redirect
            }
            
            
        } catch (\Exception $e) {
            $this->logger->debug(['process error ' => $e->getTraceAsString()]);
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
            
        }
        
        return $this->_redirect('checkout/cart', ['_secure' => TRUE]);
        
    }
    
}