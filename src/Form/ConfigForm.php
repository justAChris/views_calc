<?php

/**
 * @file
 * Contains \Drupal\views_calc\Form\ConfigForm.
 */

namespace Drupal\views_calc\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SettingsForm.
 *
 * @package Drupal\better_formats\Form
 */
class ConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'views_calc_config_form';
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
    $config = $this->config('veiws_calc.settings');

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('veiws_calc.settings');

    foreach ($form_state->getValues() as $key => $value) {
      $config->set($key, $value);
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
