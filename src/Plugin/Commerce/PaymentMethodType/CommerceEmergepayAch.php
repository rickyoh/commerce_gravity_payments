<?php

namespace Drupal\commerce_gravity_payments\Plugin\Commerce\PaymentMethodType;

use Drupal\entity\BundleFieldDefinition;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\PaymentMethodTypeBase;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides the ACH payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "commerce_emergepay_ach",
 *   label = @Translation("ACH"),
 *   create_label = @Translation("ACH"),
 * )
 */
class CommerceEmergepayAch extends PaymentMethodTypeBase {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) {
    $args = [
      '@account_type' => isset($payment_method->account_type->value) ? static::accountTypes()[$payment_method->account_type->value] : '',
      '@account_number' => isset($payment_method->account_number->value) ? $payment_method->account_number->value : '',
      '@account_holder_name' => isset($payment_method->account_holder_name->value) ? $payment_method->account_holder_name->value : '',
    ];
    return $this->t('@type account @account', $args);
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['account_type'] = BundleFieldDefinition::create('list_string')
      ->setLabel($this->t('Account type'))
      ->setDescription($this->t('The account type.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', static::accountTypes());

    $fields['account_number'] = BundleFieldDefinition::create('string')
      ->setLabel($this->t('Account number'))
      ->setDescription($this->t('The masked account number.'))
      ->setRequired(TRUE);

    $fields['account_holder_name'] = BundleFieldDefinition::create('string')
      ->setLabel($this->t('Account holder name'))
      ->setDescription($this->t('The account holder name.'))
      ->setRequired(TRUE);   

    return $fields;
  }

  /**
   * Provides account types.
   *
   * @return array
   *   Account types, keyed by single character identifier.
   */
  public static function accountTypes() {

    return [
      'C' => t('Checking'),
      'S' => t('Savings'),
    ];
  }

}
