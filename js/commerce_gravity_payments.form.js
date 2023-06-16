/**
 * @file
 * Javascript to handle Gravity Payments payment checkout in PCI-compliant way.
 */

 (function ($, Drupal, drupalSettings) {

  'use strict';

  /**
   * Attaches the commerceGravityPaymentsForm behavior.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the commerceGravityPaymentsForm behavior.
   *
   * @see Drupal.commerceSquare
   */
  Drupal.behaviors.commerceGravityPaymentsForm = {
    attach: function (context) {

      const settings = drupalSettings.commerceGravityPayments;
      const transactionToken = settings.transactionToken;

      const fieldStyles = settings.fieldStyles;
      const fieldErrorStyles = settings.fieldErrorStyles;

      const transactionType = settings.transactionType;

      console.log(transactionType);

      $(once('emergepay-processed', '.emergepay-form', context)).each(function () {

        // Initialize the hosted fields
        var hosted = emergepayFormFields.init({
          // (required) Used to set up each field
          transactionToken: transactionToken,
          // (required) The type of transaction to run
          transactionType: transactionType,
          // (optional) Configure which fields to use and the id's of the elements to append each field to
          fieldSetUp: {
              // These fields are valid for credit card transactions
              cardNumber: {
                  appendToSelector: "cardNumberContainer",
                  useField: true,
                  // optional, automatically sets the height of the iframe to the height of the
                  // contents within the iframe. Useful when using the styles object
                  autoIframeHeight: true,
                  // optional, see styles section above for more information
                  styles: fieldStyles
              },
              cardExpirationDate: {
                  appendToSelector: "expirationDateContainer",
                  useField: true,
                  // optional, automatically sets the height of the iframe to the height of the
                  // contents within the iframe. Useful when using the styles object
                  autoIframeHeight: true,
                  // optional, see styles section above for more information
                  styles: fieldStyles
              },
              cardSecurityCode: {
                  appendToSelector: "securityCodeContainer",
                  useField: true,
                  // optional, automatically sets the height of the iframe to the height of the
                  // contents within the iframe. Useful when using the styles object
                  autoIframeHeight: true,
                  // optional, see styles section above for more information
                  styles: fieldStyles
              },
              // These fields are valid for ACH transactions
              accountNumber: {
                  appendToSelector: "accountNumberContainer",
                  useField: true,
                  // optional, automatically sets the height of the iframe to the height of the
                  // contents within the iframe. Useful when using the styles object
                  autoIframeHeight: true,
                  // optional, see styles section above for more information
                  styles: fieldStyles
              },
              routingNumber: {
                  appendToSelector: "routingNumberContainer",
                  useField: true,
                  // optional, automatically sets the height of the iframe to the height of the
                  // contents within the iframe. Useful when using the styles object
                  autoIframeHeight: true,
                  // optional, see styles section above for more information
                  styles: fieldStyles
              },
              accountHolderName: {
                  appendToSelector: "accountHolderNameContainer",
                  useField: true,
                  // optional, automatically sets the height of the iframe to the height of the
                  // contents within the iframe. Useful when using the styles object
                  autoIframeHeight: true,
                  // optional, see styles section above for more information
                  styles: fieldStyles
              },
              // These fields are valid for all transaction types
              totalAmount: {
                  useField: false
              },
              externalTranId: {
                  useField: false
              }
          },
          // (optional) If there is a validation error for a field, the styles set in this object will be applied to the field
          fieldErrorStyles: fieldErrorStyles,
          // (optional) This callback function will be called when there is a validation error for a field.
          onFieldError: function (data) {
              console.log(data);
              $('.commerce-checkout-flow').find('input[type=submit]').removeAttr('disabled')
          },
          // (optional) This callback function will be called when a field validation error has been cleared.
          onFieldErrorCleared: function (data) {
              console.log(data);
              $('.commerce-checkout-flow').find('input[type=submit]').removeAttr('disabled')
          },
          // (optional) This callback function will be called when all of the requested fields have loaded
          // and are ready to accept user input. This can be useful for things like toggling the status
          // of loading indicators or ignoring clicks on a button until all of the fields are fully loaded
          onFieldsLoaded: function() {
              console.log('All fields loaded');
          },
          // (required) Callback function that gets called after user successfully enters their information into the form fields and triggers the execution of the `process` function
          onUserAuthorized: function (transactionToken) {
              var el = $('.emergepay-form input.emergepay-transaction-token');
              if(transactionToken){
                el.val(transactionToken)
                var form = el.closest('form');
                form.submit();  
              }
          },
          // (optional) Callback function that gets called after the user enters the first 6 digits of their card number.
          // This can be useful for showing a card brand icon to the user, or for determining if the card entered
          // is a credit card or a debit card.
          onBinChange: function(binData) {
            console.log('bin', binData.bin);
            console.log('card type', binData.cardType);

            $('.emergepay-form input.emergepay-card-type').val(binData.cardType);
          }
        });

        $('.commerce-checkout-flow').on('submit', function( event ) {
          var form = $(this)
          var transactionToken = form.find('input.emergepay-transaction-token').val();

          if(transactionToken.length == 0){
            event.preventDefault();
            $('.commerce-checkout-flow').find('input[type=submit]').attr('disabled', 'disabled')
            hosted.process();
          }
        });

      })



    }
  }

})(jQuery, Drupal, drupalSettings);