/**
 * Note placeOrder is in the Magento_Checkout/js/view/payment/default
 * vendor/magento/module-checkout/view/frontend/web/js/view/payment/default.js
 */
/*browser:true*/
/*global define*/
define(
    [
      'ko',
      'jquery',
      'Magento_Checkout/js/view/payment/default',
      'Laybuy_LaybuyPayments/js/action/set-payment-method-action',
      'Magento_Checkout/js/checkout-data',
      'Magento_Checkout/js/model/quote',

      //'Magento_Checkout/js/action/redirect-on-success',
      //'Magento_Checkout/js/model/quote',

    ],
    function (
        ko,
        $,
        Component,
        setPaymentMethodAction
    ) {
      'use strict';

      return Component.extend({
        defaults: {
          //template: 'Laybuy_LaybuyPayments/payment/laybuy',
          //redirectAfterPlaceOrder: true
          redirectAfterPlaceOrder: false,
          template: 'Laybuy_LaybuyPayments/payment/laybuy'

        },

        getCode: function () {
          return 'laybuy_laybuypayments';
        },

        getData: function () {
           return {
             'method': this.item.method
           };
         },

        /**
         * Place order.
         */
        placeOrder: function (data, event) {
          var self = this;

          if (event) {
            event.preventDefault();
          }

          if (this.validate()) {

            self.selectPaymentMethod();
            setPaymentMethodAction(self.messageContainer);

            return true;

          }

          return false;
        }

      });
    }
);