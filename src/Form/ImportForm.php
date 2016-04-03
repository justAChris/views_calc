<?php

/**
 * @file
 * Contains \Drupal\views_calc\Form\ImportForm.
 */

namespace Drupal\views_calc\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ImportForm.
 *
 * @package Drupal\better_formats\Form
 */
class ImportForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'views_calc_import_form';
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
    $form['#prefix'] = t('This form will import Views Calc custom fields.');
    $form['macro'] = array(
      '#type' => 'textarea',
      '#rows' => 20,
      '#title' => t('Import data'),
      '#required' => TRUE,
      '#description' => t('Paste the text created by a Views Calc export into this field.'),
    );
    // @todo Change button name to Import
    // Read in a file if there is one and set it as the default macro value.
    if (isset($_REQUEST['macro_file']) && $file = file_get_contents($_REQUEST['macro_file'])) {
      $form['macro']['#default_value'] = $file;
      if (isset($_REQUEST['type_name'])) {
        $form['type_name']['#default_value'] = $_REQUEST['type_name'];
      }
      $form['#prefix'] .= '<p class="error">' . t('A file has been pre-loaded for import.') . '</p>';
    }
    // @todo redirect not working
    $form_state->setRedirect('views_calc.config');

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $form_values = $form_state->getValues();
    $fields = NULL;

    // Use '@' to suppress errors about undefined constants in the macro.
    @eval($form_values['macro']);

    if (empty($fields) || !is_array($fields)) {
      return;
    }

    foreach ($fields as $delta => $field) {
      // Don't over-write existing fields, create new ones.
      $fields[$delta]['cid'] = NULL;
    }

    // Run the values thru drupal_execute() so they are properly validated.
    $form_state->setValues($fields);

    // @todo: use FieldsForm::Submit directly
    \Drupal::formBuilder()->submitForm('Drupal\views_calc\Form\FieldsForm', $form_state);


  }

}
