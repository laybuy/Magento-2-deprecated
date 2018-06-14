<?php

namespace Laybuy\LaybuyPayments\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Laybuy\LaybuyPayments\Gateway\Config\Config;

final class ConfigProvider implements ConfigProviderInterface
{
    const CODE  = 'laybuy_laybuypayments';

    /**
     * @var Config
     */
    private $config;

    /**
     * @param Config $config
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    /**
     * Retrieve checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    // Config options can be exposed to the UI here.
                    // Example: 'use_sandbox' => $this->config->getUseSandbox()
                ],
            ]
        ];
    }
}
