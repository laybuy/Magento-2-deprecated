<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Laybuy\LaybuyPayments\Gateway\Http;

use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Model\Method\Logger;

class TransferFactory implements TransferFactoryInterface
{
    /**
     * @var TransferBuilder
     */
    private $transferBuilder;
    
    private $logger;

    /**
     * @param TransferBuilder $transferBuilder
     */
    public function __construct(
        TransferBuilder $transferBuilder,
        Logger $logger
    ) {
        $this->transferBuilder = $transferBuilder;
        $this->logger = $logger;
    }
    
    /**
     * @param array $request
     *
     * @return \Magento\Payment\Gateway\Http\TransferInterface|void
     */
    public function create(array $request) {
        //
    }
}
