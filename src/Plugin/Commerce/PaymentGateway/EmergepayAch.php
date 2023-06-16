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
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsCreatingPaymentMethodsInterface;
use Drupal\commerce_price\MinorUnitsConverterInterface;
use Drupal\commerce_price\Price;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_gravity_payments\Exception\PaymentFailedException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;

use Drupal\Core\Url;

use Drupal\Core\Link;
use Drupal\commerce_gravity_payments\EmergepayClient;

/**
 * Provides the On-site payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "emergepay_ach",
 *   label = "Gravity Payments Emergepay ACH",
 *   display_label = "emergepay ach",
 *   forms = {
 *     "add-payment-method" = "Drupal\commerce_gravity_payments\PluginForm\EmergepayAch\PaymentMethodAddForm",
 *     "edit-payment-method" = "Drupal\commerce_payment\PluginForm\PaymentMethodEditForm",
 *   },
 *   payment_method_types = {"commerce_emergepay_ach"},
 *   requires_billing_information = FALSE,
 * )
 */
class EmergepayAch extends OnsitePaymentGatewayBase implements SupportsCreatingPaymentMethodsInterface, SupportsRefundsInterface {

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

    $amount = $payment->getAmount();
    $payment_method_token = $payment_method->getRemoteId();

    $amount = $amount ?: $payment->getAmount();
    $number = $amount->getNumber();
   
    $remote_id = $payment->getRemoteId();

    $transactionData =  [
      'amount' => $number,
      'externalTransactionId' => $this->emergepay_client->GUID(), // @todo is this the order id?
      'cashierId' => 'Cornish Plus',
      'transactionReference' => sprintf("%03d", $payment->getOrderId()), // emergepay requires 3 characters
      // "checkNumber" => "445",
      // "accountType" => "Checking"
    ];

    $response = $this->emergepay_client->processAchSale($payment_method_token, $transactionData);

    if(!isset($response->transactionResponse)){
      throw new PaymentGatewayException('Unable to perform transaction.');
    }

    $resultMessage = $response->transactionResponse->resultMessage;
    $resultStatus = $response->transactionResponse->resultStatus;

    if($resultMessage != 'Approved'){
      throw new PaymentGatewayException('Unable to perform transaction.');
    }

    $accountExpiryDate = $response->transactionResponse->accountExpiryDate;
    $maskedAccount = $response->transactionResponse->maskedAccount;
    $resultMessage = $response->transactionResponse->resultMessage;
    $uniqueTransId = $response->transactionResponse->uniqueTransId;


    $payment_method->set('account_number', substr($maskedAccount, -4));
    $payment_method->save();

    $payment->setState('completed');
    $payment->setAmount($amount);
    $payment->save();

    $payment->setRemoteId($uniqueTransId);

    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) {
    $this->assertPaymentState($payment, ['authorization']);

    $remote_id = $payment->getRemoteId();
    $transaction_data = [
      'uniqueTransId' => $remote_id,
      'externalTransactionId' => $this->emergepay_client->GUID(),
    ];

    try{
      $response = $this->emergepay_client->processVoid($transaction_data);
      if(isset($response->transactionResponse->resultStatus) && ($response->transactionResponse->resultStatus == 'true')){
        $payment->setState('authorization_voided');
        $payment->save();
      }else{
        throw new PaymentGatewayException('Unable to perform transaction.');
      }
    } catch(exception $e){
      return new InvalidResponseException($exception->getMessage(), $exception->getCode(), $exception);
    }
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

      if(isset($response->transactionResponse->resultStatus) && ($response->transactionResponse->resultStatus == 'true')){
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
      }else{
        throw new PaymentGatewayException('Unable to perform transaction.');
      }
    } catch(exception $e){
      return new InvalidResponseException($exception->getMessage(), $exception->getCode(), $exception);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
    $required_keys = [
      'transaction_token',
    ];

    $payment_method->setReusable(FALSE);
    $remote_id = $payment_details['transaction_token'];
    $payment_method->setRemoteId($remote_id);

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

}
