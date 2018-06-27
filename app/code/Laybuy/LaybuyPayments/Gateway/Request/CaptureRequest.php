<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Laybuy\LaybuyPayments\Gateway\Request;

//use Magento\Payment\Gateway\ConfigInterface;
use Laybuy\LaybuyPayments\Gateway\Config\Config;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Customer\Model\Session;
use Magento\Sales\Model\OrderFactory;

class CaptureRequest implements BuilderInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;
    
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    
    /**
     * @var StoreManagerInterface
     */
    protected $logger;
    
    /**
     * @var Session
     */
    protected $customerSession;
    
    /**
     * @var OrderFactory
     */
    protected $orderFactory;
    
    /**
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param Logger $logger
     * @param Session $customerSession
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Config $config,
        Logger $logger,
        Session $customerSession,
        OrderFactory $orderFactory
    ) {
        $this->config = $config;
        $this->storeManage = $storeManager;
        $this->logger = $logger;
        $this->customerSession = $customerSession;
        $this->orderFactory = $orderFactory;
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $buildSubject['payment'];
    
        /* @var $order   \Magento\Payment\Gateway\Data\Order\OrderAdapter */
        $order = $paymentDO->getOrder();

        
        $this->logger->debug([__METHOD__ . ' ORDER ID ' => $order->getId()]);
        $order_id = $order->getOrderIncrementId();
        //$this->session->setLaybuyOrder($order);
        $this->customerSession->setLaybuyOrderID( (int) $order_id); //just fro guest??
        

        /* @var $urlInterface \Magento\Framework\UrlInterface */
        $urlInterface = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\UrlInterface');
        $base_url     = $urlInterface->getBaseUrl();

        //build address details
        $address = $order->getBillingAddress();
    
        $laybuy = new \stdClass();
    
        $laybuy->amount    = number_format($order->getGrandTotalAmount(), 2, '.', ''); // laybuy likes the .00 to be included
        $laybuy->currency = $this->config->getCurrency(); //"NZD"; // support for new currency options from laybuy
    
        // check if this has been set, if not use NZD as this was the hardcoded value before
        if ($laybuy->currency === NULL) {
            $laybuy->currency = "NZD";
        }
        
        $laybuy->returnUrl = $base_url . 'laybuypayments/payment/process'; //, ['_secure' => TRUE]);
    
        // BS $order->merchantReference = $quote->getId();
        $laybuy->merchantReference = $order_id;
    
        $laybuy->customer            = new \stdClass();
        $laybuy->customer->firstName = $address->getFirstname();
        $laybuy->customer->lastName  = $address->getLastname();
        $laybuy->customer->email     = $address->getEmail(); // $quote->getCustomerEmail();
    
        $phone = $address->getTelephone();
    
        if ($phone == '' || strlen(preg_replace('/[^0-9+]/i', '', $phone)) <= 6) {
            $phone = "00 000 000";
        }
    
        $laybuy->customer->phone = $phone;
    
    
        $laybuy->items = [];

    
        // make the order more like a normal gateway txn, we just make
        // an item that match the total order rather than try to get the orderitem to match the grandtotal
        // as there is lot magento will let modules do to the total compared to a simple calc of
        // the cart items
    
        $laybuy->items[0]              = new \stdClass();
        $laybuy->items[0]->id          = 1;
        $laybuy->items[0]->description = "Purchase" ;//. //$store->getName();
        $laybuy->items[0]->quantity    = 1;
        $laybuy->items[0]->price       = number_format($order->getGrandTotalAmount(), 2, '.', ''); // laybuy likes the .00 to be included
    
       
        /*return [
            'TXN_TYPE' => 'S',
            'TXN_ID' => $payment->getLastTransId(),
            'MERCHANT_KEY' => $this->config->getValue(
                'merchant_gateway_key',
                $order->getStoreId()
            )
        ];*/
    
        return (array) $laybuy;
    }
}
