<?php

/**
 * @file
 * Contains \Drupal\views_calc\Form\ExportForm.
 */

namespace Drupal\views_calc\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ExportForm.
 *
 * @package Drupal\better_formats\Form
 */
class ExportForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'views_calc_export_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['veiws_calc.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $fields = _views_calc_fields();
    $string = '';
    foreach ($fields as $field) {
      $base = $field->base;
      $substitutions = _views_calc_substitutions($base);
      $field->calc = strtr($field->calc, $substitutions);
      $string .= "\$fields[] = " . var_export((array) $field, TRUE) . ";\n";
    }

    $form['#prefix'] = t('This form will export Views Calc custom fields.');
    $form['macro'] = array(
      '#type' => 'textarea',
      '#rows' => 20,
      '#title' => t('Export data'),
      '#default_value' => $string,
      '#description' => t('This is an export of the custom Views Calc fields. Paste this text into a Views Calc import box to import these fields into another installation. This will only work if the other installation uses the same base tables required by these fields.'),
    );

    //@todo: remove submit button, submitfunction
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    parent::submitForm($form, $form_state);
  }

}
