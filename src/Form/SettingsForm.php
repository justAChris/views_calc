<?php

/**
 * @file
 * Contains \Drupal\views_calc\Form\SettingsForm.
 */

namespace Drupal\views_calc\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SettingsForm.
 *
 * @package Drupal\views_calc\Form
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'views_calc_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['views_calc.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('views_calc.settings');

    $operators = $config->get('operators');

    $form['operators'] = array(
      '#type' => 'textarea',
      '#default_value' => implode("\n", $operators),
      '#title' => t('Allowable functions and operators'),
      '#rows' => intval(sizeof($operators) + 2),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('views_calc.settings');
    $operators = explode("\n", $form_state->getValue('operators'));

    $config->set('operators', $operators)->save();

    parent::submitForm($form, $form_state);
  }

}
