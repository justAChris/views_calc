<?php

/**
 * @file
 * Contains \Drupal\views_calc\Plugin\views\style\ViewsCalcTable.
 */

namespace Drupal\views_calc\Plugin\views\style;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\Table;
use Drupal\views\Views;
/**
 * Style plugin to
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "views_calc_table",
 *   title = @Translation("Views Calc Table"),
 *   help = @Translation("Creates a table with column calculations."),
 *   theme = "views_view_calc_table",
 *   display_types = {"normal"}
 * )
 */
class ViewsCalcTable extends Table {
  /**
  * Does the style plugin allows to use style plugins.
  *
  * @var bool
  */
  protected $usesRowPlugin = FALSE;

  /**
   * Does the style plugin for itself support to add fields to it's output.
   *
   * @var bool
   */
  protected $usesFields = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['detailed_values'] = array('default' => 0);
    $options['precision'] = array('default' => 0);
    $options['decimal'] = array('default' => '.', 'translatable' => TRUE);
    $options['separator'] = array('default' => ',', 'translatable' => TRUE);
    $options['info'] = array('default' => array());

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['#theme'] = 'views_ui_style_plugin_calc_table';

    $form['detailed_values'] = array(
      '#title' => t('Show details'),
      '#type' => 'select',
      '#options' => array(
        0 => t('Yes'),
        1 => t('No'),
      ),
      '#default_value' => $this->options['detailed_values'],
      '#description' => t("Select 'Yes' to show detailed values followed by column calculations, 'No' to surpress details and show only calculated column totals."),
    );

    $columns = parent::sanitizeColumns($this->options['columns']);

    // @todo clean up or replace fieldset, takes up too much room in display currently
    foreach ($columns as $field => $column) {
      $form['info'][$field]['calc_group'] = array(
        '#type' => 'fieldset',
      );

      $form['info'][$field]['calc_group']['has_calc'] = array(
        '#type' => 'checkbox',
        '#title' => t('Display calculation'),
        '#default_value' => isset($this->options['info'][$field]['calc_group']['has_calc']) ? $this->options['info'][$field]['calc_group']['has_calc'] : 0,
      );

      $options = _views_calc_calc_options();
      $form['info'][$field]['calc_group']['calc'] = array(
        '#type' => 'select',
        '#options' => $options,
        '#multiple' => TRUE,
        '#default_value' => isset($this->options['info'][$field]['calc_group']['calc']) ? $this->options['info'][$field]['calc_group']['calc'] : array(),
        '#states' => array(
          'visible' => array(':checkbox[name="style_options[info][' . $field . '][calc_group][has_calc]"]' => array('checked' => TRUE),),  // condition
        ),
      );
    }

    $form['precision'] = array(
      '#type' => 'textfield',
      '#title' => t('Default precision'),
      '#default_value' => $this->options['precision'],
      '#description' => t('Specify how many digits to print after the decimal point in calculated values, if not specified in the field settings.'),
      '#dependency' => array('edit-options-set-precision' => array(TRUE)),
      '#size' => 2,
    );
    $form['decimal'] = array(
      '#type' => 'textfield',
      '#title' => t('Default decimal point'),
      '#default_value' => $this->options['decimal'],
      '#description' => t('What single character to use as a decimal point in calculated values, if not specified in the field settings.'),
      '#size' => 2,
    );
    $form['separator'] = array(
      '#type' => 'select',
      '#title' => t('Default thousands marker'),
      '#options' => array(
        '' => t('- None -'),
        ',' => t('Comma'),
        ' ' => t('Space'),
        '.' => t('Decimal'),
        '\'' => t('Apostrophe'),
      ),
      '#default_value' => $this->options['separator'],
      '#description' => t('What single character to use as the thousands separator in calculated values, if not specified in the field settings.'),
      '#size' => 2,
    );
  }

   /**
   * {@inheritdoc}
   *
   * TODO
   * figure out what changes are needed so Views field groups will work.
   */
  function preRender($results) {
    parent::preRender($results);

    // If there are no calc fields, do nothing.
    if (!$calc_fields = $this->get_calc_fields()) {
      return;
    }

    // If we're not getting a summary row, do nothing.
    if (!empty($this->view->views_calc_calculation)) {
      return;
    }

    $this->view->totals = array();
    $this->view->sub_totals = array();
    $this->view->views_calc_fields = $calc_fields;
    $this->view->views_calc_calculation = FALSE;

    $maxitems = $this->view->getItemsPerPage();

    if (!empty($maxitems)
     && ($this->view->total_rows > $maxitems)) {
      $ids = array();
      foreach ($this->view->result as $delta => $value) {
        //@todo return this logic
        //dpm($value->{$this->view->storage->get('base_field')});
        $ids[] = $value->{$this->view->storage->get('base_field')};
      }
      // Add sub_total rows to the results.
      // We need one query per aggregation because theming needs unrenamed views field alias.
      // TODO Looks like we have problems unless we
      // force a non-page display, need to keep an eye on this.
      $this->execute_summary_view($ids);

    }
    // Add grand totals to the results.
    $this->execute_summary_view();
  }

  function execute_summary_view($ids = array()) {
    // Clone view for local subquery.

    //$summary_view = $this->view->createDuplicate();
    $summary_view = Views::executableFactory()->get($this->view->storage);

    $summary_view->setDisplay($this->view->current_display);
    // copy the query object by value not by reference!
    $summary_view->query = clone $this->view->query;
    $summary_view->setDisplay($this->view->current_display);

    // Make sure the view is completely valid.
    $errors = $summary_view->validate();
    if (!empty($errors)) {
      foreach ($errors as $error) {
        drupal_set_message($error, 'error');
      }
      return NULL;
    }

    // intialize summary view
    $is_subtotal = !empty($ids);
    $summary_view->preview = TRUE;
    $summary_view->is_cacheable = FALSE;
    $summary_view->views_calc_calculation = TRUE;
    $summary_view->views_calc_sub_total = $is_subtotal;
    // If it's a subtotal calc, we're filtering the page elements by $ids.
    $summary_view->views_calc_ids = $ids;
    $summary_view->views_calc_fields = $this->view->views_calc_fields;
    $summary_view->build_info = $this->view->build_info;
    $summary_view->field = $this->view->field;

    // Call the method which adds aggregation fields before rendering
    // In an earlier version this was done in the overridden method query() of this class
    // which is not called for some reason here.
    $this->add_aggregation_fields($summary_view);

    // We don't need any offset in the calculation queries because
    // the statement does only return the records with the passed ids
    $summary_view->query->offset = 0;

    // Execute and render the view in preview mode. Note that we only store
    // the result if the executed view query returns any result.
    $summary_view->preExecute($this->view->args);

    // All results of the calculation queries are used, so we don't page them.
    // Else we will loose the global pager from the page view.
    $summary_view->setItemsPerPage(0);
    $summary_view->execute();
    $summary_view->postExecute();
//    dpq($summary_view->build_info['query']);

    if (!empty($summary_view->result)) {
      if ($is_subtotal) {
        $this->view->sub_totals = array_shift($summary_view->result);
      }
      else {
        $this->view->totals = array_shift($summary_view->result);
      }
    }
  }

  /**
   * Query grand total.
   */
  function add_aggregation_fields(&$view) {
    // Create summary rows.
    // Empty out any fields that have been added to the query,
    // we don't need them for the summary totals.
    $view->query->clearFields();
    // Clear out any sorting and grouping, it can create unexpected results
    // when Views adds aggregation values for the sorts.
    $view->query->where = array();
    $view->query->orderby = array();
    $view->query->groupby = array();

    // See if this view has any calculations.
    $has_calcs = TRUE;

    $calc_fields = $view->views_calc_fields;

    foreach ($calc_fields as $calc => $fields) {
      foreach ($view->field as $field) {

        // Is this a field or a property?
        if (!empty($field->definition['entity_type'])) {
          $query_field = substr($field->field, 0, 3) == 'cid' ? $field->definition['calc'] : $field->table . '.' . $field->realField;
        }
        else {
          //$query_field = substr($field->field, 0, 3) == 'cid' ? $field->definition['calc'] : $field->table . '.' . $field->field_alias . '.' . $field->realField;
          $query_field = substr($field->field, 0, 3) == 'cid' ? $field->definition['calc'] : $field->tableAlias . '.' . $field->realField;
        }
        // @todo in the D7 version, this uses $field->aliases['entity_type'] if
        // available. Review cases were similar logic may be needed.
        $query_alias = $field->field;

        // Bail if we have a broken handler.
        if ($query_alias == 'unknown') {
          continue;
        }
        $view->query->addTable($field->table, $field->relationship, NULL, $field->table);
        // aggregation functions
        $ext_alias = views_calc_shorten($calc . '__' . $query_alias);
        if (in_array($field->options['id'], $fields)) {
          // Calculated fields.
          $view->query->addField(NULL, $query_field, $ext_alias, array('function' =>  strtolower($calc)));
          // TODO We may need to ask which field to group by.
          // Some databases are going to complain if we don't have a groupby field with using aggregation.
          // @todo need to ensure alias is quoted, does this work in all databases?
          if ($has_calcs) {
            //$view->query->addGroupBy($view->base_table . '.' . $view->base_field);
            $view->query->addGroupBy("'" . $ext_alias . "'");
          }
        }
      }
    }
    // TODO This won't work right with relationships, need a fix here.
    // Limit to specific primary ids. This is for subtotals.
    if (!empty($view->views_calc_ids)) {
      //$view->query->add_where(NULL, $view->base_table . "." . $view->base_field . " IN (%s)", implode(',', $view->views_calc_ids));
      $view->query->addWhere(NULL, $view->storage->get('base_table') . '.' . $view->storage->get('base_field'), $view->views_calc_ids, 'IN');
    }
//    dpm($view->query);
  }

  /**
   * Get views_calc fields
   */
  function get_calc_fields() {
    // TODO on preview this returns the wrong list.
    $options = $this->view->style_plugin->options;
    $handler = $this->view->style_plugin;
    $fields = $this->view->field;
    $columns = $handler->sanitizeColumns($options['columns'], $fields);
    $calcs = array_keys(_views_calc_calc_options());

    $calc_fields = array();
    foreach ($columns as $field => $column) {
      if ($field == $column && empty($fields[$field]->options['exclude'])) {
        if ($options['info'][$field]['calc_group']['has_calc']) {
          foreach ($calcs as $calc) {
            if (isset($this->options['info'][$field]['calc_group']['calc'][$calc])) {
              $calc_fields[$calc][] = $field;
            }
          }
        }
      }
    }
    return $calc_fields;
  }
}
