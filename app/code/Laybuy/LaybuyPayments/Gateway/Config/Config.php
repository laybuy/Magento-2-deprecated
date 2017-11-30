<?php

namespace Laybuy\LaybuyPayments\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\Encryptor;

class Config extends \Magento\Payment\Gateway\Config\Config
{
    
    const CODE = 'laybuy_laybuypayments';
    
    // keys from the admin form
    const KEY_ACTIVE = 'active';
    const KEY_MERCHANT_ID = 'merchant_id';
    const KEY_API_KEY = 'api_key';
    const USE_SANDBOX = 'use_sandbox';
    const KEY_SANDBOX_MERCHANT_ID = 'sandbox_merchant_id';
    const KEY_SANDBOX_API_KEY = 'sandbox_api_key';
    const KEY_SDK_URL = 'sdk_url';

    
    
    /**
     * @var Encryptor
     */
    protected $encryptor;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Encryptor $encryptor
     * @param string|null $methodCode
     * @param string $pathPattern
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Encryptor $encryptor,
        $methodCode = null,
        $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
        $this->encryptor = $encryptor;
    }

    /**
     * Get Payment configuration status
     * 
     * @return bool
     */
    public function isActive()
    {
        return (bool) $this->getValue(self::KEY_ACTIVE );
    }
    
    /**
     * Get The Laybuy Merchant ID
     * 
     * @return string
     */
    public function getMerchantId()
    {
        return $this->getValue(self::KEY_MERCHANT_ID);
    }
    
    /**
     * Get teh laybuy API key (secret)
     * 
     * @return string
     */
    public function getApiKey()
    {
        $value = $this->getValue(self::KEY_API_KEY);
        return $value ? $this->encryptor->decrypt($value) : $value;
    }
    
    /**
     * Get sdk url
     * 
     * @return string
     */
    public function getSdkUrl()
    {
        return $this->getValue(self::KEY_SDK_URL);
    }
    
    
    /**
     * Get use sanbox flag
     *
     * @return string
     */
    public function getUseSandbox() {
        return $this->getValue(self::USE_SANDBOX);
    }
    
    /**
     * Get The Laybuy Merchant ID
     *
     * @return string
     */
    public function getSandboxMerchantId() {
        return $this->getValue(self::KEY_SANDBOX_MERCHANT_ID);
    }
    
    /**
     * Get teh laybuy API key (secret)
     *
     * @return string
     */
    public function getSandboxApiKey() {
        $value = $this->getValue(self::KEY_SANDBOX_API_KEY);
        return $value ? $this->encryptor->decrypt($value) : $value;
    }
}
