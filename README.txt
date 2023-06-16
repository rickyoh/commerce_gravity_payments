# Commerce Gravity Payments

Drupal Commerce payment gateway that uses Gravity Payments Checkout, PCI compliant hosted fields for the checkout form, and tokenized transactions for refunds.

https://dev.gravitypayments.com/docs/emergepay/checkout/

Card and ACH payment gateways are supported in the current release. Card Authorization and Capture as well as Authorization only methods are supported.

## Post-Installation
Install the module, go to "Configure Gravity Payments" under Commerce -> Configuration -> Payment. Add your OID and Auth Token here. Once this is configured, add the payment gateway.