<?php

namespace Drupal\commerce_gravity_payments\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Exception\InvalidResponseException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayBase;
use Drupal\commerce_price\MinorUnitsConverterInterface;
use Drupal\commerce_price\Price;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;

use Drupal\Core\Url;

use Drupal\Core\Link;
use Drupal\commerce_gravity_payments\EmergepayClient;



/**
 * Provides the On-site payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "emergepay_credit",
 *   label = "Gravity Payments Emergepay Credit",
 *   display_label = "emergepay credit",
 *   forms = {
 *     "add-payment-method" = "Drupal\commerce_gravity_payments\PluginForm\EmergepayCredit\PaymentMethodAddForm",
 *     "edit-payment-method" = "Drupal\commerce_payment\PluginForm\PaymentMethodEditForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
 *   },
 *   requires_billing_information = TRUE,
 * )
 */
class EmergepayCredit extends OnsitePaymentGatewayBase implements EmergepayCreditInterface {

  protected $emergepay_config = [
    'mode' => null,
    'oid' => null,
    'auth_token' => null,
  ];
  protected $emergepay_client = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time, MinorUnitsConverterInterface $minor_units_converter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time, $minor_units_converter);


    $emergepay_config =  \Drupal::config('commerce_gravity_payments.settings');

    $this->emergepay_config = [
      'mode' => $emergepay_config->get('mode'),
      'oid' => $emergepay_config->get('oid'),
      'auth_token' => $emergepay_config->get('auth_token'),
    ];
    $this->emergepay_client = new EmergepayClient($this->emergepay_config);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'field_styles' => '',
      'field_error_styles' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $config =  \Drupal::config('commerce_gravity_payments.settings');

    $mode = $config->get('mode');

    $url = Url::fromRoute('commerce_gravity_payments.settings', [], ['absolute' => TRUE]);

    $link = Link::fromTextAndUrl($this->t('Configure Gravity Payments here'), $url);

    if(empty($mode)){
      $form['gravity_payments_warning'] = [
        '#type' => 'inline_template',
        '#template' => '<div class="messages-list__item messages messages--warning">{{text}}</div>',
        '#context' => [
          'text' => $this->t('Gravity Payments must be configured before you can set up the payment gateway. @link.' , ['@link' => $link->toString()])
        ]
      ];
    }


    $form['field_styles'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Field Styles'),
      '#default_value' => $this->configuration['field_styles'],
      '#description' => $this->t('A JSON string such as: { "border": "2px solid #000000", "border-radius": "4px", "padding" : "12px 20px" }')
    ];

    $form['field_error_styles'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Field Error Styles'),
      '#default_value' => $this->configuration['field_error_styles'],
      '#description' => $this->t('A JSON string such as: { "border": "2px solid red" }')
    ];



    if($mode && (isset($form['mode']))){
      $form['mode']['#default_value'] = $mode;
      $form['mode']['#disabled'] = true;
    }

    return $form;
  }

    /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $config =  \Drupal::config('commerce_gravity_payments.settings');
    $url = Url::fromRoute('commerce_gravity_payments.settings', [], ['absolute' => TRUE]);
    $link = Link::fromTextAndUrl($this->t('Configure Gravity Payments here'), $url);
    $mode = $config->get('mode');
    if(empty($mode)){
      $form_state->setErrorByName('submit', $this->t('Gravity Payments must be configured before you can set up the payment gateway. @link.' , ['@link' => $link->toString()]));
    }
   }


  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);

    $this->configuration['field_styles'] = $values['field_styles'];
    $this->configuration['field_error_styles'] = $values['field_error_styles'];
  }

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {
    $this->assertPaymentState($payment, ['new']);
    $payment_method = $payment->getPaymentMethod();
    $this->assertPaymentMethod($payment_method);


    // Perform the create payment request here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    // @todo take into account $capture when performing the request.
    $amount = $payment->getAmount();
    $payment_method_token = $payment_method->getRemoteId();

    $amount = $amount ?: $payment->getAmount();
    $number = $amount->getNumber();
   
    $remote_id = $payment->getRemoteId();
 
    $billing = $payment_method->getBillingProfile();

    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
    $address = $billing->get('address')->first();

    try{
     
      $billing_name = $address->getGivenName().' '.$address->getFamilyName();

      $transactionData =  [
        'amount' => $number,
        'externalTransactionId' => $this->emergepay_client->GUID(), // @todo is this the order id?
        // Optional
        'billingAddress' => $address->getAddressLine1().' '.$address->getAddressLine2(),
        'billingName' =>  $billing_name,
        'billingPostalCode' => $address->getPostalCode(),
        'cashierId' => 'Cornish Plus',
        'transactionReference' => sprintf("%03d", $payment->getOrderId()), // emergepay requires 3 characters
      ];

      $response = $this->emergepay_client->processCreditSale($payment_method_token, $transactionData);

      if( $response != false){
        $accountExpiryDate = $response->transactionResponse->accountExpiryDate;
        $maskedAccount = $response->transactionResponse->maskedAccount;
        $avsResponseCode = $response->transactionResponse->avsResponseCode;
        $uniqueTransId = $response->transactionResponse->uniqueTransId;

        $accountExpiryDate = $response->transactionResponse->accountExpiryDate;

        if($maskedAccount){
          $payment_method->set('card_number', substr($maskedAccount, -4));
          $payment_method->save();
        }

        $payment->setState('completed');
        $payment->setAmount($amount);
        $payment->save();

        $next_state = $capture ? 'completed' : 'authorization';

        $payment->setState($next_state);

        $payment->setRemoteId($uniqueTransId);
        $payment->setAvsResponseCode($avsResponseCode);
        $payment->save();
      }else{
        return new InvalidResponseException($exception->getMessage(), $exception->getCode(), $exception);
      }


    } catch(exception $e){
      \Drupal::logger('commerce_gravity_payments')->error($e->getMessage());
      return new InvalidResponseException($exception->getMessage(), $exception->getCode(), $exception);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function capturePayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['authorization']);

    $amount = $amount ?: $payment->getAmount();
    $number = $amount->getNumber();
   
    $remote_id = $payment->getRemoteId();
 
    $billing = $payment_method->getBillingProfile();

    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
    $address = $billing->get('address')->first();

    try{
     
      $billing_name = $address->getGivenName().' '.$address->getFamilyName();

      $transactionData =  [
        'amount' => $number,
        'externalTransactionId' => $this->emergepay_client->GUID(), // @todo is this the order id?
        // Optional
        'billingAddress' => $address->getAddressLine1().' '.$address->getAddressLine2(),
        'billingName' =>  $billing_name,
        'billingPostalCode' => $address->getPostalCode(),
        'cashierId' => 'Cornish Plus',
        'transactionReference' => sprintf("%03d", $payment->getOrderId()), // emergepay requires 3 characters
      ];

      $response = $this->emergepay_client->processCreditSale($payment_method_token, $transactionData);

      if( $response != false){
        $accountExpiryDate = $response->transactionResponse->accountExpiryDate;
        $maskedAccount = $response->transactionResponse->maskedAccount;
        $avsResponseCode = $response->transactionResponse->avsResponseCode;
        $uniqueTransId = $response->transactionResponse->uniqueTransId;

        $accountExpiryDate = $response->transactionResponse->accountExpiryDate;

        if($maskedAccount){
          $payment_method->set('card_number', substr($maskedAccount, -4));
          $payment_method->save();
        }

        $payment->setState('completed');
        $payment->setAmount($amount);
        $payment->save();

        $next_state = $capture ? 'completed' : 'authorization';

        $payment->setState($next_state);

        $payment->setRemoteId($uniqueTransId);
        $payment->setAvsResponseCode($avsResponseCode);
        $payment->save();
      }else{
        return new InvalidResponseException($exception->getMessage(), $exception->getCode(), $exception);
      }

    } catch(exception $e){
      \Drupal::logger('commerce_gravity_payments')->error($e->getMessage());
      return new InvalidResponseException($exception->getMessage(), $exception->getCode(), $exception);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) {
    $this->assertPaymentState($payment, ['authorization']);
    // Perform the void request here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.

    // @todo handle voiding payment such as in cancellation right after an order

    $remote_id = $payment->getRemoteId();

    $payment->setState('authorization_voided');
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    // If not specified, refund the entire amount.
    $amount = $amount ?: $payment->getAmount();
    $this->assertRefundAmount($payment, $amount);

    $payment_method = $payment->getPaymentMethod();
    $payment_method_token = $payment_method->getRemoteId();
    $remote_id = $payment->getRemoteId();
    $number = $amount->getNumber();
 
    $transaction_data = [
      'uniqueTransId' => $remote_id,
      'externalTransactionId' => $this->emergepay_client->GUID(),
      'amount' => $number,
    ];

    try{
      $response = $this->emergepay_client->processTokenizedRefund($transaction_data);

      if(isset($response->transactionResponse->amount)){
        $old_refunded_amount = $payment->getRefundedAmount();
        $new_refunded_amount = $old_refunded_amount->add($amount);
        if ($new_refunded_amount->lessThan($payment->getAmount())) {
          $payment->setState('partially_refunded');
        }
        else {
          $payment->setState('refunded');
        }

        $payment->setRefundedAmount($new_refunded_amount);
        $payment->save();
      }
    } catch(exception $e){
      \Drupal::logger('commerce_gravity_payments')->error($e->getMessage());
      return new InvalidResponseException($exception->getMessage(), $exception->getCode(), $exception);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
    $required_keys = [
      'transaction_token', 'card_type',
    ];
    foreach ($required_keys as $required_key) {
      if (empty($payment_details[$required_key])) {
        throw new \InvalidArgumentException(sprintf('$payment_details must contain the %s key.', $required_key));
      }
    }

    // @todo Make payment methods reusable. Currently they represent 24hr nonce.
    // @see https://docs.connect.squareup.com/articles/processing-recurring-payments-ruby
    // Meet specific requirements for reusable, permanent methods.
    $payment_method->setReusable(FALSE);
    $payment_method->card_type = $payment_details['card_type'];
    // $payment_method->card_number = $payment_details['last4'];
    // $payment_method->card_exp_month = $payment_details['exp_month'];
    $payment_method->card_exp_year = $payment_details['exp_year'];
    $remote_id = $payment_details['transaction_token'];
    $payment_method->setRemoteId($remote_id);

    // Nonces expire after 24h. We reduce that time by 5s to account for the
    // time it took to do the server request after the JS tokenization.
    $expires = $this->time->getRequestTime() + (3600 * 24) - 5;
    $payment_method->setExpiresTime($expires);
    $payment_method->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
    // Delete the remote record here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    // Delete the local entity.
    $payment_method->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function updatePaymentMethod(PaymentMethodInterface $payment_method) {
    // Perform the update request here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
  }

  /**
   * {@inheritdoc}
   */
  public function buildAvsResponseCodeLabel($avs_response_code, $card_type) {
    if ($card_type == 'dinersclub' || $card_type == 'jcb') {
      if ($avs_response_code == 'A') {
        return $this->t('Approved.');
      }
      return NULL;
    }
    return parent::buildAvsResponseCodeLabel($avs_response_code, $card_type);
  }

}
