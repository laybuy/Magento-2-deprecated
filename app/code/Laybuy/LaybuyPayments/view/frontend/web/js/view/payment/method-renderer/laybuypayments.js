/**
 * Note placeOrder is in the Magento_Checkout/js/view/payment/default
 * vendor/magento/module-checkout/view/frontend/web/js/view/payment/default.js
 */
/*browser:true*/
/*global define*/
define(
    [
      'Magento_Checkout/js/view/payment/default',
      'Magento_Checkout/js/action/redirect-on-success',
      'Magento_Checkout/js/model/quote',

    ],
    function (
        Component,
        redirectOnSuccessAction,
        quote)  {
      'use strict';

      return Component.extend({
        defaults: {
          template: 'Laybuy_LaybuyPayments/payment/laybuy',
          redirectAfterPlaceOrder: true
        },

        getCode: function () {
          return 'laybuy_laybuypayments';
        },

        getData: function () {
          return {
            'method': this.item.method
          };
        },

        afterPlaceOrder: function () {
          var self = this;

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
            this.isPlaceOrderActionAllowed(false);

            this.getPlaceOrderDeferredObject()
                .fail(
                    function () {
                      self.isPlaceOrderActionAllowed(true);
                    }
                ).done(
                function (json) {
                  console.log('done');
                  console.log(json);

                  self.afterPlaceOrder();

                  if (self.redirectAfterPlaceOrder) {
                    window.location.assign( JSON.parse( json ) );
                  }
                }
            );

            return true;
          }

          return false;
        }


      });
    }
);