define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';

        rendererList.push(
            {
                type: 'laybuy_laybuypayments',
                component: 'Laybuy_LaybuyPayments/js/view/payment/method-renderer/laybuypayments'
            }
        );

        return Component.extend({});
    }
);
