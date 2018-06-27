<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Laybuy\LaybuyPayments\Gateway\Response;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\Method\Logger;

class TxnIdHandler implements HandlerInterface
{
    const TXN_ID = 'TXN_ID';
    
    private $logger;
    
    /**
     * Handles transaction id
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response)
    {
        $this->logger = \Magento\Framework\App\ObjectManager::getInstance()->get(Logger::class);
        
        $this->logger->debug([__METHOD__ => 'start']);
        
        if (!isset($handlingSubject['payment'])
            || !$handlingSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $handlingSubject['payment'];
    
        /** @var $payment \Magento\Sales\Model\Order\Payment */
        $payment = $paymentDO->getPayment();
        
        
        $this->logger->debug([__METHOD__ . " ACTION " => $response['ACTION']]);
       
        $payment->setTransactionId($response['TXN_ID']);
        $payment->setIsTransactionClosed(TRUE);
        
       
    }
}
