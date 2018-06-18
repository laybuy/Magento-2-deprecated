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
use Magento\Checkout\Model\Session;

use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\OrderFactory;

use Magento\Payment\Gateway\Http\Client\Zend as httpClient;
use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Framework\HTTP\ZendClient;
use Magento\Payment\Gateway\Http\ConverterInterface;
use GuzzleHttp\Client;

class Laybuy implements ClientInterface
{
    const SUCCESS = 'SUCCESS';
    const FAILURE = 'ERROR';
    
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
     * @var Session
     */
    protected $checkoutSession;
    
    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;
    
    /**
     * @var OrderFactory
     */
    protected $orderFactory;
    
    
    /**
     * @param Config $config
     * @param Session $checkoutSession
     * @param QuoteFactory $quoteFactory
     * @param OrderFactory $orderFactory
     * @param Logger $logger
     */
    public function __construct(
        Config $config,
        Session $checkoutSession,
        QuoteFactory $quoteFactory,
        OrderFactory $orderFactory,
        Logger $logger
    ) {
        $this->logger          = $logger;
        $this->checkoutSession = $checkoutSession;
        $this->quoteFactory    = $quoteFactory;
        $this->orderFactory    = $orderFactory;
        $this->config          = $config;
    
        $this->logger->debug([__METHOD__ . ' TEST sandbox? ' => $this->config->getUseSandbox()]);
        $this->logger->debug([__METHOD__ . ' TEST sandbox_merchantid? ' => $this->config->getSandboxMerchantId()]);
        
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
        // TODO: find better way to get teh redirect url to KO frontend, rather than shortcurcit
    
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
        else {
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
}
