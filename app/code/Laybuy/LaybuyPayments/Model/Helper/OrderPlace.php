<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Laybuy\LaybuyPayments\Model\Helper;

use Magento\Quote\Model\Quote;
use Magento\Checkout\Helper\Data;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\Session;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Checkout\Api\AgreementsValidatorInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\Quote\PaymentFactory;
use Magento\Payment\Model\Method\Logger;
use Magento\Checkout\Model\Session as CheckoutSession;


/**
 * Class OrderPlace
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OrderPlace extends AbstractHelper
{
    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var AgreementsValidatorInterface
     */
    private $agreementsValidator;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var Data
     */
    private $checkoutHelper;
    
    /**
     * @var OrderFactory
     */
    protected $orderFactory;
    
    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;
    /**
     * @var PaymentFactory
     */
    protected $paymentFactory;
    
    /**
     * @var Logger
     */
    protected $logger;
    
    /**
     * @var CheckoutSession
     */
    protected $checkoutsession;
    
    
    
    /**
     * Constructor
     *
     * @param CartManagementInterface $cartManagement
     * @param AgreementsValidatorInterface $agreementsValidator
     * @param Session $customerSession
     * @param OrderFactory $orderFactory
     * @param Data $checkoutHelper
     * @param PaymentFactory $paymentFactory
     * @param Logger $logger
     */
    public function __construct(
        CartManagementInterface $cartManagement,
        AgreementsValidatorInterface $agreementsValidator,
        Session $customerSession,
        OrderFactory $orderFactory,
        Data $checkoutHelper,
        PaymentFactory $paymentFactory,
        Logger $logger,
        CheckoutSession $checkoutsession,
        QuoteFactory $quoteFactory
    ) {
        $this->cartManagement = $cartManagement;
        $this->agreementsValidator = $agreementsValidator;
        $this->customerSession = $customerSession;
        $this->orderFactory = $orderFactory;
        $this->checkoutHelper = $checkoutHelper;
        $this->paymentFactory = $paymentFactory;
        $this->logger = $logger;
        $this->checkoutsession = $checkoutsession;
        $this->quoteFactory = $quoteFactory;
    }

    /**
     * Execute operation
     *
     * @param Quote $quote
     * @param array $agreement
     * @return int $order_id
     * @throws LocalizedException
     */
    public function execute(Quote $quote) {
        
        $this->logger->debug([__METHOD__ . ' start ' => 'start']);
        //
        
    }

    /**
     * Get checkout method
     *
     * @param Quote $quote
     * @return string
     */
    private function getCheckoutMethod(Quote $quote)
    {
        if ($this->customerSession->isLoggedIn()) {
            return Onepage::METHOD_CUSTOMER;
        }
        if (!$quote->getCheckoutMethod()) {
            if ($this->checkoutHelper->isAllowedGuestCheckout($quote)) {
                $quote->setCheckoutMethod(Onepage::METHOD_GUEST);
            } else {
                $quote->setCheckoutMethod(Onepage::METHOD_REGISTER);
            }
        }

        return $quote->getCheckoutMethod();
    }

    /**
     * Prepare quote for guest checkout order submit
     *
     * @param Quote $quote
     * @return void
     */
    private function prepareGuestQuote(Quote $quote)
    {
        $quote->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(Group::NOT_LOGGED_IN_ID);
    }
}
