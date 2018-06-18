<?php

namespace Laybuy\LaybuyPayments\Model\Adminhtml\Source;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Framework\Option\ArrayInterface;
use\Laybuy\LaybuyPayments\Gateway\Http\Client\Laybuy;

class CurrencyList implements ArrayInterface {
    
    protected $client;
    
    /**
     * @param Config $config
     *
     */
    public function __construct(
        Laybuy $client
    ) {
        
        $this->client = $client;
        
    }
    
    
    /**
     * Possible actions on order place
     *
     * @codeCoverageIgnore
     *
     * @return array
     */
    public function toOptionArray() {
        
        return $this->client->getCurrencyList();
        
    }
    
    
}