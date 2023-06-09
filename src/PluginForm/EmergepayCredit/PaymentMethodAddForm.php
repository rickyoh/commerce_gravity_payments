<?php

namespace Drupal\commerce_gravity_payments\PluginForm\EmergepayCredit;

use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;
use Drupal\Core\Form\FormStateInterface;

use Drupal\commerce_gravity_payments\EmergepayClient;

class PaymentMethodAddForm extends BasePaymentMethodAddForm {

  /**
   * {@inheritdoc}
   */
  protected function buildCreditCardForm(array $element, FormStateInterface $form_state) {
    $plugin = $this->plugin;

    $configuration = $plugin->getConfiguration();

    $emergepay_config =  \Drupal::config('commerce_gravity_payments.settings');

    $emergepay_client_configuration = [
      'mode' => $emergepay_config->get('mode'),
      'oid' => $emergepay_config->get('oid'),
      'auth_token' => $emergepay_config->get('auth_token'),
    ];

    $mode = $emergepay_config->get('mode');

    $element['#attached']['library'][] = 'commerce_gravity_payments/form';
    if($mode == 'live'){
      $element['#attached']['library'][] = 'commerce_gravity_payments/emergepay_production';
    }else{
      $element['#attached']['library'][] = 'commerce_gravity_payments/emergepay_sandbox';
    }

    $emergepay_client = new EmergepayClient($emergepay_client_configuration);

    $transaction_token = $emergepay_client->startCreditSale();

    if($transaction_token == false){
      $element['error'] = [
        '#type' => 'markup',
        '#markup' => '<div id="paymentGatewayError" class="form-text">
        '.$this->t('We are sorry, but the payment gateway is inoperable at the moment. Please contact us for assistance.').'
        </div>',
      ];
      return $element;
    }

    // field styles
    $field_styles = new \stdClass();
    $field_error_styles = new \stdClass();
    if(isset($configuration['field_styles']) && !empty($configuration['field_styles'])){
      $field_styles = json_decode($configuration['field_styles']);
    }
    if(isset($configuration['field_error_styles']) && !empty($configuration['field_error_styles'])){
      $field_error_styles = json_decode($configuration['field_error_styles']);
    }

    $element['#attached']['drupalSettings']['commerceGravityPayments'] = [
      'transactionToken' => $transaction_token,
      'fieldStyles' => $field_styles,
      'fieldErrorStyles' => $field_error_styles,
    ];
    $element['#attributes']['class'][] = 'emergepay-form';

    // To display validation errors.
    $element['payment_errors'] = [
      '#type' => 'markup',
      '#markup' => '<div id="paymentErrors"></div>',
      '#weight' => -200,
    ];

    $element['card_number'] = [
      '#type' => 'item',
      '#title' => t('Card number'),
      '#required' => TRUE,
      '#validated' => TRUE,
      '#markup' => '<div id="cardNumberContainer" class="form-text"></div>',
    ];

    $element['expiration'] = [
      '#type' => 'item',
      '#title' => t('Expiration date'),
      '#required' => TRUE,
      '#validated' => TRUE,
      '#markup' => '<div id="expirationDateContainer"></div>',
    ];

    $element['security_code'] = [
      '#type' => 'item',
      '#title' => t('CVC'),
      '#required' => TRUE,
      '#validated' => TRUE,
      '#markup' => '<div id="securityCodeContainer"></div>',
    ];



    $element['transaction_token'] = [
      '#type' => 'hidden',
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['emergepay-transaction-token']
      ]
    ];

    $element['last4'] = [
      '#type' => 'hidden',
      '#attributes' => ['class' => ['emergepay-last4']],
    ];
    $element['exp_month'] = [
      '#type' => 'hidden',
      '#attributes' => ['class' => ['emergepay-exp-month']],
    ];
    $element['exp_year'] = [
      '#type' => 'hidden',
      '#attributes' => ['class' => ['emergepay-exp-year']],
    ];

    $element['card_type'] = [
      '#type' => 'hidden',
      '#attributes' => ['class' => ['emergepay-card-type']],
    ];



    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function validateCreditCardForm(array &$element, FormStateInterface $form_state) {
    // The JS library performs its own validation.
    $values = $form_state->getValues();
  }

  /**
   * {@inheritdoc}
   */
  public function submitCreditCardForm(array $element, FormStateInterface $form_state) {
    $values = $form_state->getValues();
  }

}
