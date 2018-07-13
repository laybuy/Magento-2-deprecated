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

use Magento\Sales\Model\Service\OrderService;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface as TransactionBuilder;

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
    
    private $transaction_builder;
    
    private $orderManagement;
    
    protected $quoteRepository;
    
    protected $paymentHelper;
    
    protected $cartManagement;
    
    protected $checkout;
    
    /**
     * @var InvoiceService
     */
    protected $invoice_management;
    
    protected $client;
    
    
    public function __construct(
        Context $context,
        Config $config,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        Helper\OrderPlace $orderPlace,
        \Magento\Framework\Event\Manager $eventManager,
        TransactionBuilder $transaction_builder,
        Logger $logger,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        OrderService $orderManagement,
        InvoiceService $invoice_management,
        PaymentHelper $paymentHelper,
        CartManagementInterface $cartManagement,
        Onepage $checkout,
        Laybuy $client
    ) {
        parent::__construct($context);
        $this->config              = $config;
        $this->customerSession     = $customerSession;
        $this->checkoutSession     = $checkoutSession;
        $this->orderPlace          = $orderPlace;
        $this->logger              = $logger;
        $this->eventManager        = $eventManager;
        $this->transaction_builder = $transaction_builder;
        $this->quoteRepository     = $quoteRepository;
        $this->paymentHelper       = $paymentHelper;
        $this->cartManagement      = $cartManagement;
        $this->checkout            = $checkout;
        $this->client              = $client;
        
        $this->invoice_management = $invoice_management;
        
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
                 // not needed
                }
                
                // move this higher
                $laybuy_order_id = $this->client->laybuyConfirm($token);
                
                if (!$laybuy_order_id) {
                    $this->messageManager->addNoticeMessage('Laybuy: There was an error' . (($this->client->last_error) ? ', ' . $this->client->last_error : '') . '.');
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
                $order->setTotalPaid($order->getTotalDue());
                $order->save();
                
                $invoices = $order->getInvoiceCollection();
                
                $this->logger->debug(['order_id' => $order->getId()]);
                $this->logger->debug(['invoices' => count($invoices)]);
                
                foreach ($invoices as $invoice) {
                    /* @var $invoice \Magento\Sales\Model\Order\Invoice */
                    /* $invoice->setState(2); */
                    
                    $this->invoice_management->setCapture($invoice->getId());
                    
                    if ($this->config->getAllowInvoiceNotify()) {
                        $this->invoice_management->notify($invoice->getId());
                    }
                    
                    /*$invoice->pay()*/
                    $invoice->pay();
                    $invoice->save();
                }
                
                // mark the order as paid
                $txn_id = $laybuy_order_id . '_' . $token;
                
                try {
                    // Prepare payment object
                    $payment = $order->getPayment();
                    $payment->setMethod(Config::CODE);
                    /*$payment->setLastTransId($payment->);
                    $payment->setTransactionId($paymentData['id']);
                    $payment->setAdditionalInformation([Transaction::RAW_DETAILS => (array) $paymentData]);*/
                    
                    // Formatted price
                    $formatedPrice = $order->getBaseCurrency()->formatTxt($order->getGrandTotal());
                    
                    // Prepare transaction
                    $transaction = $this->transaction_builder->setPayment($payment)->setOrder($order)->setTransactionId($txn_id)->setFailSafe(TRUE)->build(Transaction::TYPE_CAPTURE);
                    
                    // Add transaction to payment
                    $payment->addTransactionCommentsToOrder($transaction, __('The authorized amount is %1.', $formatedPrice));
                    $payment->setParentTransactionId(NULL);
                    
                    // Save payment, transaction and order
                    $payment->save();
                    $transaction->save();
                    $order->save();
                    
                    
                } catch (Exception $e) {
                    $this->messageManager->addExceptionMessage($e, $e->getMessage());
                }
                
                
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