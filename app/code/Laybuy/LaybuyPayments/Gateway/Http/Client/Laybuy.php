<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Laybuy\LaybuyPayments\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use Laybuy\LaybuyPayments\Gateway\Config\Config;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Customer\Model\Group;

use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\OrderFactory;

use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Checkout\Helper\Data as CheckoutHelper;

use Magento\Payment\Gateway\Http\Client\Zend as httpClient;
use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Framework\HTTP\ZendClient;
use Magento\Payment\Gateway\Http\ConverterInterface;


class Laybuy implements ClientInterface
{
    const SUCCESS   = 'SUCCESS';
    const FAILURE   = 'ERROR';
    const CANCELLED = 'CANCELLED';
    
    const LAYBUY_LIVE_URL       = 'https://api.laybuy.com';
    const LAYBUY_SANDBOX_URL    = 'https://sandbox-api.laybuy.com';
    const LAYBUY_RETURN_SUCCESS = 'laybuypayments/payment/process?result=success';
    const LAYBUY_RETURN_FAIL    = 'laybuypayments/payment/process?result=fail';
    
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
     * @var array
     */
    private $results = [
        self::SUCCESS,
        self::FAILURE
    ];

    /**
     * @var Logger
     */
    private $logger;
    
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;
    
    /**
     * @var CustomerSession
     */
    protected $customerSession;
    
    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;
    
    /**
     * @var OrderFactory
     */
    protected $orderFactory;
    
    protected $token;
    
    protected $redirectUrl;
    
    private $paymentHelper;
    
    private $checkoutHelper;
    
    public $last_error;
    
    /**
     * @param Config $config
     * @param Session $checkoutSession
     * @param QuoteFactory $quoteFactory
     * @param OrderFactory $orderFactory
     * @param Logger $logger
     */
    public function __construct(
        Config $config,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        QuoteFactory $quoteFactory,
        OrderFactory $orderFactory,
        Logger $logger,
        PaymentHelper $paymentHelper,
        CheckoutHelper $checkoutHelper
    ) {
        $this->logger          = $logger;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->quoteFactory    = $quoteFactory;
        $this->orderFactory    = $orderFactory;
        $this->config          = $config;
        $this->paymentHelper   = $paymentHelper;
        $this->checkoutHelper   = $checkoutHelper;
    
        $this->last_error = null;
    
        $this->logger->debug([__METHOD__ . ' TEST sandbox? ' => $this->config->getUseSandbox()]);
        $this->logger->debug([__METHOD__ . ' TEST sandbox_merchantid? ' => $this->config->getSandboxMerchantId()]);
        
    }
    
    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param Quote $quote
     *
     * @return array
     */
    public function makeLaybuyOrder(Quote $quote) {
    
        if ($this->getCheckoutMethod($quote) === Onepage::METHOD_GUEST) {
            $this->prepareGuestQuote($quote);
        }
        
        // get the base url of the site
        /* @var $urlInterface \Magento\Framework\UrlInterface */
        $urlInterface = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\UrlInterface');
        $base_url     = $urlInterface->getBaseUrl();
    
        //build address details
        $address = $quote->getBillingAddress();
    
        $laybuy = new \stdClass();
    
        $laybuy->amount   = number_format($quote->getGrandTotal(), 2, '.', ''); // laybuy likes the .00 to be included
        $laybuy->currency = $this->config->getCurrency(); //"NZD"; // support for new currency options from laybuy
    
        // check if this has been set, if not use NZD as this was the hardcoded value before
        if ($laybuy->currency === NULL) {
            $laybuy->currency = "NZD";
        }
    
        $laybuy->returnUrl = $base_url . 'laybuypayments/payment/process'; //, ['_secure' => TRUE]);
    
        // save the quote url adn add some unquie ness so reties are uniquw, but we have a way to reteive the
        // ID in the precess step
        $laybuy->merchantReference = $this->makeMerchantReference($quote->getId());
    
        $laybuy->customer            = new \stdClass();
        $laybuy->customer->firstName = $address->getFirstname();
        $laybuy->customer->lastName  = $address->getLastname();
        $laybuy->customer->email     = $address->getEmail(); // quest email is handled in prepareGuestQuote
    
        $phone = $address->getTelephone(); // this may not be compulsory
    
        if ($phone == '' || strlen(preg_replace('/[^0-9+]/i', '', $phone)) <= 6) {
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
        $laybuy->items[0]->description = "Purchase";//. //$store->getName();
        $laybuy->items[0]->quantity    = 1;
        $laybuy->items[0]->price       = number_format($quote->getGrandTotal(), 2, '.', ''); // laybuy likes the .00 to be included
    
        
        return (array) $laybuy;
        
    }
    
    /**
     * @param array $laybuy_order
     *
     * @return bool
     * @throws \Zend_Http_Client_Exception
     */
    public function getLaybuyRedirectUrl(array $laybuy_order){
    
        $client = $this->getRestClient();
    
        // use teh transfer object to creat ea new order at Lauybuy
        $response = $client->restPost('/order/create', json_encode($laybuy_order));
    
        $body = json_decode($response->getBody());
        $this->logger->debug(['redirect response body' => $body]);
    
        /* stdClass Object
                (
                    [result] => SUCCESS
                    [token] => a5MbmNdAU6WNFIT4smlr45BiuM4Nrb4CiFRPmj0f
                    [paymentUrl] => https://sandbox-payment.laybuy.com/pay/a5MbmNdAU6WNFIT4smlr45BiuM4Nrb4CiFRPmj0f
                )
         */
    
        if ($body->result == Laybuy::SUCCESS) {
        
            if (!$body->paymentUrl) {
                // $this->noLaybuyRedirectError($body);
                $this->logger->debug(['FAILED TO GET returnURL' => $body]);
                $this->last_error = $body->error;
                return FALSE;
            }
        
            $this->token       = $body->token;
            $this->redirectUrl = $body->paymentUrl;
            
            return $this->redirectUrl;
        }
        else {
        
            $this->logger->debug(['FAILED TO GET returnURL' => $body]);
            $this->last_error = $body->error;
            return FALSE;
            
        }
        
        
    }
    
    public function laybuyConfirm( $token ) {
        
        $client = $this->getRestClient();
        
        // use teh transfer object to creat ea new order at Lauybuy
        $response = $client->restPost('/order/confirm', json_encode(['token' => $token]));
        
        $body = json_decode($response->getBody());
        $this->logger->debug(['confirm response body' => $body]);
        
        /* stdClass Object
                (
                    [result] => SUCCESS
                    [token] => a5MbmNdAU6WNFIT4smlr45BiuM4Nrb4CiFRPmj0f
                    [paymentUrl] => https://sandbox-payment.laybuy.com/pay/a5MbmNdAU6WNFIT4smlr45BiuM4Nrb4CiFRPmj0f
                )
         */
        
        if ($body->result == Laybuy::SUCCESS) {
            
            
            if (!$body->orderId) {
                
                $this->logger->debug(['FAILED confirm order' => $body]);
                $this->last_error = $body->error;
                return FALSE;
            }
            
            $this->orderId = $body->orderId;
            
            return $this->orderId;
        }
        else {
            
            $this->logger->debug(['FAILED confirm order' => $body]);
            $this->last_error = $body->error;
            return FALSE;
            
        }
        
        
    }
    
    public function laybuyCancel($token) {
        
        $client = $this->getRestClient();
        
        // use teh transfer object to creat ea new order at Lauybuy
        $response = $client->restGet('/order/cancel/' . $token );
        
        $body = json_decode($response->getBody());
        $this->logger->debug(['cancel response body' => $body]);
        
        
        if ($body->result == Laybuy::SUCCESS) {
            return TRUE;
        }
        else {
            $this->logger->debug(['FAILED to cancel Laybuy Order' => $body]);
            return FALSE;
        }
        
    }
    
    
    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param TransferInterface $transferObject
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject)
    {
    
        $this->logger->debug(['transferObject'  => $transferObject->getBody()]);
        
        $client = $this->getRestClient();
      
        // check if we are returning
       
    
        /* @var $urlInterface \Magento\Framework\UrlInterface */
        $urlInterface = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\UrlInterface');
        
       
        $path = parse_url($urlInterface->getCurrentUrl(), PHP_URL_PATH);
    
        $this->logger->debug([' URL PATH ' => $path]);
        
        
        if($path === '/laybuypayments/payment/process') {
            $data = [];
            parse_str(parse_url($urlInterface->getCurrentUrl(), PHP_URL_QUERY), $data);
            
            $this->logger->debug(['PHP_URL_QUERY process payment ' => $data]);
            $this->logger->debug(['token form process payment ' => $data['token']]);
            
            $laybuy = new \stdClass();
            $laybuy->token = $data['token'];
            $response = $client->restPost('/order/confirm', json_encode($laybuy));
            
            

            $body = json_decode($response->getBody());
            $this->logger->debug(['confirm reposnse body' => $body]);
            
            
            if ($body->result == Laybuy::SUCCESS ) {
    
                $this->logger->debug(['reposnse body' => $body]);
                
                // get the order so we can get the correct
                $order_response = $client->restGet('/order/'. $body->orderId );
    
                $laybuy_order = json_decode($order_response->getBody());
                $this->logger->debug(['get Laybuy Order reposnse' => $laybuy_order]);
                
               
                return [
                    'ACTION'      => 'process',
                    'TXN_ID'       => $body->orderId,
                    'RESULT_CODE' => Laybuy::SUCCESS,
                ];
            }
            else {
    
                // $this->noLaybuyRedirectError($body);
                $this->logger->debug(['FAILED TO GET returnURL' => $body]);
                
                return [
                    'ACTION'      => 'process',
                    'TXN_ID'      => NULL,
                    'RESULT_CODE' => Laybuy::FAILURE,
                ];
    
            }
            
            
        }
        // this is the redirect to laybuy request
        else {
            
            // use teh transfer object to creat ea new order at Lauybuy
            $response = $client->restPost('/order/create', json_encode($transferObject->getBody()));
    
            $body = json_decode($response->getBody());
            $this->logger->debug(['redirect response body' => $body]);
    
            /* stdClass Object
                    (
                        [result] => SUCCESS
                        [token] => a5MbmNdAU6WNFIT4smlr45BiuM4Nrb4CiFRPmj0f
                        [paymentUrl] => https://sandbox-payment.laybuy.com/pay/a5MbmNdAU6WNFIT4smlr45BiuM4Nrb4CiFRPmj0f
                    )
             */
    
            if ($body->result == Laybuy::SUCCESS ) {
        
                $this->logger->debug(['reposnse body' => $body]);
        
                if (!$body->paymentUrl) {
                    // $this->noLaybuyRedirectError($body);
                    $this->logger->debug(['FAILED TO GET returnURL' => $body]);
                    
                }
                
                
                $this->checkoutSession->setLaybuyRedirectUrl($body->paymentUrl);
                $this->checkoutSession->setLaybuyPaymentToken($body->token);
                //$this->checkoutSession->setLaybuyTransfer($transferObject->getBody());
        
                return [
                    'ACTION'      => 'redirect',
                    'RESULT_CODE' => Laybuy::SUCCESS,
                    'TOKEN'       => $body->token,
                    'RETURN_URL'  => $body->paymentUrl,
                ];
            }
            else {
        
                // $this->noLaybuyRedirectError($body);
                $this->logger->debug(['FAILED TO GET returnURL' => $body]);
                
            }
            
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
        $this->logger->debug([__METHOD__ . ' sandbox? ' => $this->config->getUseSandbox() ]);
        $this->logger->debug([__METHOD__ . ' sandbox_merchantid? '  => $this->config->getSandboxMerchantId() ]);
        
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
        $this->logger->debug([__METHOD__ . ' INITIALISED' => '' ]);
    }
    
    
    /**
     *
     * @return array
     */
    public function getCurrencyList() {
        
        $client   = $this->getRestClient();
        $response = $client->restGet('/options/currencies');
        
        $result = json_decode($response->getBody());
        
        $this->logger->debug([__METHOD__ . ' RESULT' => $result]);
        
        $currencies = [];
        
        if (strtoupper($result->result) === "SUCCESS" && isset($result->currencies) && is_array($result->currencies)) {
            
            foreach ($result->currencies as $currency) {
                $currencies[strtoupper($currency)] = strtoupper($currency);
            }
            
            return $currencies;
        }
        
        return [];
    }
    
    /**
     * Make a Unique string with the quote ID embeded so we can get it later
     * @param int $quote_id
     * @return string
     */
    private function makeMerchantReference($quote_id){
        return $quote_id . '_' . uniqid();
    }
    
    /**
     * get the quote from $merchant_reference string with the quote ID embeded
     *
     * @param string $merchant_reference
     *
     * @return Quote | false
     */
    private function getQuoteFromMerchantReference($merchant_reference) {
    
        $quote_id = (int) preg_replace('/_.*$/', '', $merchant_reference);
        if(!$quote_id){
            return false;
        }
        
        return $this->quoteFactory->create()->loadActive($quote_id);
        
    }
    
    /**
     * Get checkout method
     *
     * @param Quote $quote
     *
     * @return string
     */
    public function getCheckoutMethod(Quote $quote) {
        if ($this->customerSession->isLoggedIn()) {
            return Onepage::METHOD_CUSTOMER;
        }
        if (!$quote->getCheckoutMethod()) {
            if ($this->checkoutHelper->isAllowedGuestCheckout($quote)) {
                $quote->setCheckoutMethod(Onepage::METHOD_GUEST);
            }
            else {
                $quote->setCheckoutMethod(Onepage::METHOD_REGISTER);
            }
        }
        
        return $quote->getCheckoutMethod();
    }
    
    /**
     * Prepare quote for guest checkout order submit
     *
     * @param Quote $quote
     *
     * @return void
     */
    private function prepareGuestQuote(Quote &$quote) {
        $quote->setCustomerId(NULL)
              //->setCustomerEmail($quote->getBillingAddress()->getEmail())
              ->setCustomerEmail($quote->getCustomerEmail())
              ->setCustomerIsGuest(TRUE)
              ->setCustomerGroupId(Group::NOT_LOGGED_IN_ID);
              //->save();
        
    }
    
    
}
