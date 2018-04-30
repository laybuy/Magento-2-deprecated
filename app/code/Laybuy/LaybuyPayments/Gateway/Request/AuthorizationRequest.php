<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Laybuy\LaybuyPayments\Gateway\Request;

use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\StoreManagerInterface;

class AuthorizationRequest implements BuilderInterface
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
     * @param StoreManagerInterface $storeManager
     * @param ConfigInterface $config
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ConfigInterface $config
    ) {
        $this->config = $config;
        $this->storeManager = $storeManager;
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

        /**  @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $buildSubject['payment'];
    
        $order = $paymentDO->getOrder();
        $store = $this->storeManager->getStore();
        //$payment = $paymentDO->getPayment();
   
        /* @var $urlInterface \Magento\Framework\UrlInterface */
        $urlInterface = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\UrlInterface');
        $base_url = $urlInterface->getBaseUrl();
        
        //$address = $mage_order->getShippingAddress();
        $address = $order->getBillingAddress();
        
        //$address->getFirstname()
     
        //$this->errors[] = 'This is another error.';
        $laybuy          = new \stdClass();
    
        $laybuy->amount    = number_format($order->getGrandTotalAmount(), 2, '.', ''); // laybuy likes the .00 to be included
        $laybuy->currency  = "NZD"; // New Zealand Dollars (NZD) is currently the only currency supported.
        $laybuy->returnUrl = $base_url . '/laybuypayments/payment/process'; //, ['_secure' => TRUE]);
        
        // BS $order->merchantReference = $quote->getId();
        $laybuy->merchantReference = $order->getOrderIncrementId();
    
        $laybuy->customer            = new \stdClass();
        $laybuy->customer->firstName = $address->getFirstname();
        $laybuy->customer->lastName  = $address->getLastname();
        $laybuy->customer->email     = $address->getEmail() ; // $quote->getCustomerEmail();
    
        $phone = $address->getTelephone();
    
        if ($phone == '' || strlen(preg_replace('/[^0-9+]/i', '', $phone)) <= 6) {
            // TODO: $this->errors[] = 'Please provide a valid New Zealand phone number.';
            $phone = "00 000 000";
        }
    
        $laybuy->customer->phone = $phone;
    
    
        $laybuy->items = [];
    
        $totalOrderValue = 0;
    
        // make the order more like a normal gateway txn, we just make
        // an item that match the total order rather than try to get the orderitem to match the grandtotal
        // as there is lot magento will let modules do to the total compared to a simple calc of
        // the cart items
    
        $laybuy->items[0]              = new \stdClass();
        $laybuy->items[0]->id          = 1;
        $laybuy->items[0]->description = "Purchase from " . $store->getName();
        $laybuy->items[0]->quantity    = 1;
        $laybuy->items[0]->price       =  number_format($order->getGrandTotalAmount(), 2, '.', ''); // laybuy likes the .00 to be included
        
        return (array) $laybuy;
        
        
        /*
         * {
                "amount":156.90,
                "currency":"NZD",
                "returnUrl":"https://www.merchantsite.com/confirm-payment?i=7437377",
                "merchantReference":"17125026",
                "customer": {
                    "firstName":"Jenny",
                    "lastName":"Smith",
                    "email":"jenny.smith@laybuy.com",
                    "phone":"0219876543"
                },
                "billingAddress": {
                    "address1":"123 Crown Street",
                    "city":"Auckland",
                    "postcode":"1010",
                    "country":"New Zealand",
                },
                "shippingAddress": {
                    "name":"Timmy Smith",
                    "address1":"Level 4, Goodsmiths Building",
                    "address2":"123 Crown Street",
                    "suburb":"Redvale",
                    "city":"Auckland",
                    "postcode":"1010",
                    "country":"New Zealand",
                    "phone":"097654321"
                },
                "items":[
                    {
                        "id":"4470356028717",
                        "description":"Blue Widget",
                        "quantity":2,
                        "price":76.95
                    },
                    {
                        "id":"SHIPPING",
                        "description":"Shipping",
                        "quantity":1,
                        "price":3.00
                    },
                ]
            }
            
         */
        
    }
    
}
