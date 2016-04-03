<?php

/**
 * @file
 * Contains \Drupal\views_calc\Form\FieldsForm.
 */

namespace Drupal\views_calc\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Views;

/**
 * Class FieldsForm.
 *
 * @package Drupal\views_calc\Form
 */
class FieldsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'views_calc.fields'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'views_calc_fields_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config('views_calc.fields');

    $i = 0;
    $substitutions = array();
    $help = t('<p>The specific fields that are available in any view depend on the base table used for that view.</p>');
    $views_data = Views::viewsData();
    $base_tables = $views_data->fetchBaseTables();

    foreach ($base_tables as $base => $data) {


      $base_subs = _views_calc_substitutions($base);
      $substitutions += $base_subs;

      $list = array(
        '#theme' => 'item_list',
        '#items' => $base_subs,
      );

      $fieldset = array(
        '#type' => 'details',
        '#title' => t('Base table: @name', array('@name' => $data['title'])),
        '#markup' => drupal_render($list),
        '#open' => FALSE,
      );

      $help .=  drupal_render($fieldset);

    }

    // display current views calcs fields
    $fields = _views_calc_fields();
    foreach ($fields as $field) {
      $form[] = $this->views_calc_field_form_item($i, $field, $substitutions);
      $i++;
    }
    // add blank fields for more calcs
    for ($x = $i + 1; $x < $i + 2; $x++) {
      $field = array();
      $form[] = $this->views_calc_field_form_item($i, $field, $substitutions);
    }

    $form['token_info'] = array(
      '#markup' => '<div class="views-calc-field-names"><strong>Field Substitutions</strong><div class="form-item">' . $help . '</div></div>',
      '#weight' => 101
    );

    $form['actions']['submit']['weight'] = 100;
    $form['#attached']['library'][] = 'views_calc/views_calc_admin';

    return $form;
  }

  /**
   * A form element for an individual calculated field.
   */
  public function views_calc_field_form_item($i, $field, $substitutions) {

    if (empty($field)) {
      $field = new \stdClass();
      $field->cid = 0;
      $field->label = '';
      $field->tablelist = '';
      $field->fieldlist = '';
      $field->calc = '';
      $field->format = '';
      $field->custom = '';
      $field->base = '';
    }

    $options = array();
    $views_data = Views::viewsData();
    $base_tables = $views_data->fetchBaseTables();


    foreach ($base_tables as $base => $data) {
      $options[$base] = $data['title'];
    }
    $form['group'][$i] = array(
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#title' => t('Field: ') . !empty($field->label) ? $field->label : t('New'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    );
    $form['group'][$i]['cid'] = array(
      '#type' => 'hidden',
      '#value' => intval($field->cid),
    );
    $form['group'][$i]['tablelist'] = array(
      '#type' => 'hidden',
      '#value' => $field->tablelist,
    );
    $form['group'][$i]['base'] = array(
      '#type' => 'select',
      '#title' => t('Base table'),
      '#options' => $options,
      '#default_value' => !empty($field->base) && array_key_exists($field->base, $options) ? $field->base : 'node',
      '#description' => t('The base table for this field.'),
    );
    $form['group'][$i]['label'] = array(
      '#type' => 'textfield',
      '#title' => t('Label'),
      '#field_prefix' => 'ViewsCalc: ',
      '#default_value' => str_replace('ViewsCalc: ', '', $field->label),
      '#description' => t('The views field name for this field (i.e. Views Calc: My Calculation).'),
    );
    $form['group'][$i]['calc'] = array(
      '#type' => 'textarea',
      '#title' => t('Calculation'),
      '#default_value' => strtr($field->calc, $substitutions),
      '#description' => t("<p>The query operation to be performed, using numbers, field substitutions, and " . implode(' ', _views_calc_operators()) . ". Leave spaces between parentheses and field names, i.e. 'CONCAT( %field1, ' ', %field2 )'. <strong>" . t('Note that all fields must be from the base table selected above! You cannot combine fields from different base tables.') . "</strong></p>"),
    );
    $form['group'][$i]['format'] = array(
      '#type' => 'select',
      '#title' => t('Format'),
      '#default_value' => $field->format,
      '#options' => array_combine(array_keys(_views_calc_format_options()),array_keys(_views_calc_format_options())),
      '#description' => t('The format of the result of this calculation.'),
    );
    $form['group'][$i]['custom'] = array(
      '#type' => 'textfield',
      '#title' => t('Custom function'),
      '#default_value' => $field->custom,
      '#description' => t('The function to call for a custom format.'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);


    foreach ($form_state->getValues() as $delta => $item) {
      if (!is_numeric($delta) || $item['calc'] == '') {
        // remove blank fields, don't save them
        continue;
      }
      else {
        // Remove all valid values from calc, if anything is left over, it is invalid.

        // First, remove all field names.
        $repl = array();
        $patterns = array();
        $base = $item['base'];
        foreach (_views_calc_substitutions($base) as $key => $value) {
          $key = trim($value);
          $count = strlen($value);
          $replace = preg_quote($value);
          $patterns[] = "`(^|[^\\\\\\\\])" . $replace . "`";
          $repl[] = '${1}';
        }
        $remaining = trim(preg_replace($patterns, $repl, $item['calc']));

        // Next, remove functions and numbers.
        $repl = array();
        $patterns = array();
        foreach (_views_calc_replacements() as $value) {
          $patterns[] = "`(^|[^\\\\\\\\])" . preg_quote(trim($value)) . "`";
          $repl[] = '${1}';
        }
        $remaining = trim(preg_replace($patterns, $repl, $remaining));
        if (!empty($remaining)) {
          // @todo update elemnt idebtifier
          $form_state->setError($form[$delta]['group'][$delta]['calc'], t('The values %remaining in %field are not allowed.', array('%remaining' => $remaining, '%field' => $item['label'])));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $form_values = array();
    foreach ($form_state->getValues() as $delta => $value) {
      // If this is some form item we don't care about, skip it.
      if (!is_numeric($delta) || !is_array($value)) {
        continue;
      }
      $value['calc'] = trim($value['calc']);
      if (empty($value['calc'])) {
        // remove blank fields, don't save them
        if (!empty($value['cid'])) {
          db_delete('views_calc_fields')
            ->condition('cid', $value['cid'])
            ->execute();
        }
      }
      else {
        $tables = array();
        $form_values[$delta]['cid']  = $value['cid'];
        $form_values[$delta]['label']  = $value['label'];
        $form_values[$delta]['format'] = $value['format'];
        $form_values[$delta]['custom'] = $value['custom'];
        $form_values[$delta]['calc']   = $value['calc'];
        $form_values[$delta]['base']   = $value['base'];

        // Substitute field names back into the calculation.
        $matches = array();
        $base = $value['base'];
        foreach (_views_calc_substitutions($base) as $key => $value) {
          $label_patterns[] = "`(^|[^\\\\\\\\])" . preg_quote($value) . "`";
          $value_patterns[] = "`(^|[^\\\\\\\\])" . preg_quote($key) . "`";
          $repl[] = '${1}' . $key;
        }
        $form_values[$delta]['calc'] = preg_replace($label_patterns, $repl, $form_values[$delta]['calc']);
        // Extract the fields and table names from the calculation.
        $tables = array();
        $fields = array();
        foreach ($value_patterns as $pattern) {
          if (preg_match($pattern, $form_values[$delta]['calc'], $results)) {
            $fields[trim($results[0])] = trim($results[0]);
            $tmp = explode('.', trim($results[0]));
            if (trim($tmp[0])) {
              $tables[trim($tmp[0])] = trim($tmp[0]);
            }
          }
        }
        $form_values[$delta]['tablelist'] = implode(',', $tables);
        $form_values[$delta]['fieldlist'] = implode(',', $fields);
      }
    }
    // @todo storage alternates, key/value
    foreach ((array) $form_values as $delta => $value) {
      \Drupal::database()->merge('views_calc_fields')
        ->key(array('cid' => $value['cid']))
        ->fields($value)
        ->execute();
    }
    views_invalidate_cache();

    drupal_set_message(t('Views Calc fields were updated.'));


  }

}
