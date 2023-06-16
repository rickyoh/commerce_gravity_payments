<?php

namespace Drupal\commerce_gravity_payments\PluginForm\EmergepayCredit;

use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_gravity_payments\PluginForm\PaymentMethodAddFormBase;

class PaymentMethodAddForm extends PaymentMethodAddFormBase {

  /**
   * {@inheritdoc}
   */
  protected function buildCreditCardForm(array $element, FormStateInterface $form_state) {
    $plugin = $this->plugin;
    $configuration = $plugin->getConfiguration();

    $capture = true;
    $form = $form_state->getFormObject();
    $panes = $form->getPanes();

    if(isset($panes['payment_process'])){
      $payment_process_configuration = $panes['payment_process']->getConfiguration();
      $capture = (isset($payment_process_configuration['capture'])) ? $payment_process_configuration['capture'] : true;
    }


    $element['#attached']['library'][] = $this->getLibrary();
    $element['#attached']['library'][] = 'commerce_gravity_payments/form';


    $transaction_type = 'CreditSale';
    if($capture != true){
      $transaction_type = 'CreditAuth';
    }

    $emergepay_client = $this->getEmergepayClient();
    $transaction_token = $emergepay_client->startTransaction($transaction_type);

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
      'transactionType' => $transaction_type,
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
      '#title' => $this->t('Card number'),
      '#required' => TRUE,
      '#validated' => TRUE,
      '#markup' => '<div id="cardNumberContainer" class="form-text"></div>',
    ];

    $element['expiration'] = [
      '#type' => 'item',
      '#title' => $this->t('Expiration date'),
      '#required' => TRUE,
      '#validated' => TRUE,
      '#markup' => '<div id="expirationDateContainer"></div>',
    ];

    $element['security_code'] = [
      '#type' => 'item',
      '#title' => $this->t('CVC'),
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
