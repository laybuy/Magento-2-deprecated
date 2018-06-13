<?php

namespace Laybuy\LaybuyPayments\Block\Catalog\Product;

class EasyPayments extends \Magento\Framework\View\Element\Template
{
    /** @var PriceCurrencyInterface $priceCurrency */
    protected $_priceCurrency;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $_registry;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        array $data = [],
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\Framework\Registry $registry
    ) {
        parent::__construct($context, $data);

        $this->_priceCurrency = $priceCurrency;
        $this->_registry = $registry;
    }

    public function getCurrentProduct()
    {
        return $this->_registry->registry('current_product');
    }

    /**
     * @return float $price
     */
    public function getLaybuyWeeklyCost() {
        return $this->getCurrentProduct()->getPriceInfo()->getPrice('final_price')->getValue() / 6;
    }

    /**
     * Function getLaybuyWeeklyCostFormatted
     *
     * @return string
     */
    public function getLaybuyWeeklyCostFormatted()
    {
        return $this->_priceCurrency->convertAndFormat($this->getLaybuyWeeklyCost());
    }

}
