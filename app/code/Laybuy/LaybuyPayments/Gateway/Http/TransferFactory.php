<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Laybuy\LaybuyPayments\Gateway\Http;

use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;



class TransferFactory implements TransferFactoryInterface
{
    /**
     * @var TransferBuilder
     */
    private $transferBuilder;

    /**
     * @param TransferBuilder $transferBuilder
     */
    public function __construct(
        TransferBuilder $transferBuilder
    ) {
        $this->transferBuilder = $transferBuilder;
    }

    /**
     * Builds gateway transfer object
     *
     * @param array $request
     * @return TransferInterface
     */
    public function create(array $request) {
    
       // $authHeader = 'Authorization: Basic ' . base64_encode($merchantId . ':' . $apiKey);
        
        return $this->transferBuilder
            ->setBody($request)
            ->setMethod('POST')
            ->setUri('https://sandbox-api.laybuy.com/order/create') // ->setUri($this->getUrl())
            ->setHeaders(['Content-Type' => 'application/json'] )
            ->build();
    }
}
