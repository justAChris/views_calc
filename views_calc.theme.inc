<?php

/**
 * @file theme.inc
 *
 * An array of preprocessors to fill variables for templates and helper
 * functions to make theming easier.
 */

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Element;
use Drupal\Core\Template\Attribute;

/**
 * Theme the form for the views calc table style plugin
 */
function template_preprocess_views_ui_style_plugin_calc_table(&$variables) {
  $form = $variables['form'];

  $header = array(
    t('Field'),
    t('Column calculations'),
    t('Column'),
    t('Align'),
    t('Separator'),
    array(
      'data' => t('Sortable'),
      'align' => 'center',
    ),
    array(
      'data' => t('Default order'),
      'align' => 'center',
    ),
    array(
      'data' => t('Default sort'),
      'align' => 'center',
    ),
    array(
      'data' => t('Hide empty column'),
      'align' => 'center',
    ),
    array(
      'data' => t('Responsive'),
      'align' => 'center',
    ),
  );
  $rows = array();
  foreach (Element::children($form['columns']) as $id) {
    $row = array();
    $row[]['data'] = $form['info'][$id]['name'];
    $row[]['data'] = $form['info'][$id]['calc_group'];
    $row[]['data'] = $form['columns'][$id];
    $row[]['data'] = $form['info'][$id]['align'];
    $row[]['data'] = $form['info'][$id]['separator'];

    if (!empty($form['info'][$id]['sortable'])) {
      $row[] = array(
        'data' => $form['info'][$id]['sortable'],
        'align' => 'center',
      );
      $row[] = array(
        'data' => $form['info'][$id]['default_sort_order'],
        'align' => 'center',
      );
      $row[] = array(
        'data' => $form['default'][$id],
        'align' => 'center',
      );
    }
    else {
      $row[] = '';
      $row[] = '';
      $row[] = '';
    }
    $row[] = array(
      'data' => $form['info'][$id]['empty_column'],
      'align' => 'center',
    );
    $row[] = array(
      'data' => $form['info'][$id]['responsive'],
      'align' => 'center',
    );
    $rows[] = $row;
  }

  // Add the special 'None' row.
  $rows[] = array(array('data' => t('None'), 'colspan' => 6), array('align' => 'center', 'data' => $form['default'][-1]), array('colspan' => 2));

  // Unset elements from the form array that are used to build the table so that
  // they are not rendered twice.
  unset($form['default']);
  unset($form['info']);
  unset($form['columns']);

  $variables['table'] = array(
    '#type' => 'table',
    '#theme' => 'table__views_ui_style_plugin_table',
    '#header' => $header,
    '#rows' => $rows,
  );

  $variables['form'] = $form;
}

/**
 * Prepares variables for views calc template.
 *
 * Default template: views-view-calc-table.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - view: A ViewExecutable object.
 *   - rows: The raw row data.
 */
function _views_calc_preprocess_views_view_calc_table(&$variables) {
  $view = $variables['view'];

  // We need the raw data for this grouping, which is passed in
  // as $variables['rows'].
  // However, the template also needs to use for the rendered fields.  We
  // therefore swap the raw data out to a new variable and reset $variables['rows']
  // so that it can get rebuilt.
  // Store rows so that they may be used by further preprocess functions.
  $result = $variables['result'] = $variables['rows'];
  $variables['rows'] = array();
  $variables['header'] = array();

  $options = $view->style_plugin->options;
  $handler = $view->style_plugin;

  $fields = &$view->field;
  $columns = $handler->sanitizeColumns($options['columns'], $fields);

  $active = !empty($handler->active) ? $handler->active : '';
  $order = !empty($handler->order) ? $handler->order : 'asc';

  // A boolean variable which stores whether the table has a responsive class.
  $responsive = FALSE;

  $query = tablesort_get_query_parameters();
  if (isset($view->exposed_raw_input)) {
    $query += $view->exposed_raw_input;
  }

  // A boolean to store whether the table's header has any labels.
  $has_header_labels = FALSE;
  foreach ($columns as $field => $column) {
    // Create a second variable so we can easily find what fields we have and
    // what the CSS classes should be.
    $variables['fields'][$field] = Html::cleanCssIdentifier($field);
    if ($active == $field) {
      $variables['fields'][$field] .= ' is-active';
    }

    // Render the header labels.
    if ($field == $column && empty($fields[$field]->options['exclude'])) {
      $label = !empty($fields[$field]) ? $fields[$field]->label() : '';
      if (empty($options['info'][$field]['sortable']) || !$fields[$field]->clickSortable()) {
        $variables['header'][$field]['content'] = $label;
      }
      else {
        $initial = !empty($options['info'][$field]['default_sort_order']) ? $options['info'][$field]['default_sort_order'] : 'asc';

        if ($active == $field) {
          $initial = ($order == 'asc') ? 'desc' : 'asc';
        }

        $title = t('sort by @s', array('@s' => $label));
        if ($active == $field) {
          $variables['header'][$field]['sort_indicator'] = array(
            '#theme' => 'tablesort_indicator',
            '#style' => $initial,
          );
        }

        $query['order'] = $field;
        $query['sort'] = $initial;
        $link_options = array(
          'query' => $query,
        );
        // It is ok to specify no URL path here as we will always reload the
        // current page.
        $url = new Url('<none>', [], $link_options);
        $variables['header'][$field]['url'] = $url->toString();
        $variables['header'][$field]['content'] = $label;
        $variables['header'][$field]['title'] = $title;
      }

      $variables['header'][$field]['default_classes'] = $fields[$field]->options['element_default_classes'];
      // Set up the header label class.
      $variables['header'][$field]['attributes'] = array();
      $class = $fields[$field]->elementLabelClasses(0);
      if ($class) {
        $variables['header'][$field]['attributes']['class'][] = $class;
      }
      // Add responsive header classes.
      if (!empty($options['info'][$field]['responsive'])) {
        $variables['header'][$field]['attributes']['class'][] = $options['info'][$field]['responsive'];
        $responsive = TRUE;
      }
      // Add a CSS align class to each field if one was set.
      if (!empty($options['info'][$field]['align'])) {
        $variables['header'][$field]['attributes']['class'][] = Html::cleanCssIdentifier($options['info'][$field]['align']);
      }
      // Add a header label wrapper if one was selected.
      if ($variables['header'][$field]['content']) {
        $element_label_type = $fields[$field]->elementLabelType(TRUE, TRUE);
        if ($element_label_type) {
          $variables['header'][$field]['wrapper_element'] = $element_label_type;
        }
        // Improves accessibility of complex tables.
        $variables['header'][$field]['attributes']['id'] = Html::getUniqueId('view-' . $field . '-table-column');
      }
      // Check if header label is not empty.
      if (!empty($variables['header'][$field]['content'])) {
        $has_header_labels = TRUE;
      }

      $variables['header'][$field]['attributes'] = new Attribute($variables['header'][$field]['attributes']);
    }

    // Add a CSS align class to each field if one was set.
    if (!empty($options['info'][$field]['align'])) {
      $variables['fields'][$field] .= ' ' . Html::cleanCssIdentifier($options['info'][$field]['align']);
    }

    // Render each field into its appropriate column.
    foreach ($result as $num => $row) {

      // Skip building the attributes and content if the field is to be excluded
      // from the display.
      if (!empty($fields[$field]->options['exclude'])) {
        continue;
      }

      // Reference to the column in the loop to make the code easier to read.
      $column_reference =& $variables['rows'][$num]['columns'][$column];

      $column_reference['default_classes'] = $fields[$field]->options['element_default_classes'];

      // Set the field key to the column so it can be used for adding classes
      // in a template.
      $column_reference['fields'][] = $variables['fields'][$field];

      // Add field classes.
      if (!isset($column_reference['attributes'])) {
        $column_reference['attributes'] = array();
      }

      if ($classes = $fields[$field]->elementClasses($num)) {
        $column_reference['attributes']['class'][] = $classes;
      }

      // Add responsive header classes.
      if (!empty($options['info'][$field]['responsive'])) {
        $column_reference['attributes']['class'][] = $options['info'][$field]['responsive'];
      }

      // Improves accessibility of complex tables.
      if (isset($variables['header'][$field]['attributes']['id'])) {
        $column_reference['attributes']['headers'] = array($variables['header'][$field]['attributes']['id']);
      }

      if (!empty($fields[$field])) {
        $field_output = $handler->getField($num, $field);
        $column_reference['wrapper_element'] = $fields[$field]->elementType(TRUE, TRUE);
        if (!isset($column_reference['content'])) {
          $column_reference['content'] = [];
        }

        // Only bother with separators and stuff if the field shows up.
        // Place the field into the column, along with an optional separator.
        if (trim($field_output) != '') {
          if (!empty($column_reference['content']) && !empty($options['info'][$column]['separator'])) {
            $column_reference['content'][] = [
              'separator' => ['#markup' => $options['info'][$column]['separator']],
              'field_output' => ['#markup' => $field_output]
            ];
          }
          else {
            $column_reference['content'][] = [
              'field_output' => ['#markup' => $field_output]
            ];
          }
        }
      }
      $column_reference['attributes'] = new Attribute($column_reference['attributes']);
    }

    // Remove columns if the "empty_column" option is checked and the
    // field is empty.
    if (!empty($options['info'][$field]['empty_column'])) {
      $empty = TRUE;
      foreach ($variables['rows'] as $columns) {
        $empty &= empty($columns['columns'][$column]['content']);
      }
      if ($empty) {
        foreach ($variables['rows'] as &$column_items) {
          unset($column_items['columns'][$column]);
        }
        unset($variables['header'][$column]);
      }
    }
  }

  // Hide table header if all labels are empty.
  if (!$has_header_labels) {
    $variables['header'] = array();
  }

  foreach ($variables['rows'] as $num => $row) {
    $variables['rows'][$num]['attributes'] = array();
    if ($row_class = $handler->getRowClass($num)) {
      $variables['rows'][$num]['attributes']['class'][] = $row_class;
    }
    $variables['rows'][$num]['attributes'] = new Attribute($variables['rows'][$num]['attributes']);
  }

  if (empty($variables['rows']) && !empty($options['empty_table'])) {
    $build = $view->display_handler->renderArea('empty');
    $variables['rows'][0]['columns'][0]['content'][0]['field_output'] = $build;
    $variables['rows'][0]['attributes'] = new Attribute(array('class' => 'odd'));
    // Calculate the amounts of rows with output.
    $variables['rows'][0]['columns'][0]['attributes'] = new Attribute(array(
      'colspan' => count($variables['header']),
      'class' => 'views-empty',
    ));
  }

  $variables['sticky'] = FALSE;
  if (!empty($options['sticky'])) {
    $variables['view']->element['#attached']['library'][] = 'core/drupal.tableheader';
    $variables['sticky'] = TRUE;
  }

  // Add the caption to the list if set.
  if (!empty($handler->options['caption'])) {
    $variables['caption'] = ['#markup' => $handler->options['caption']];
    $variables['caption_needed'] = TRUE;
  }
  else {
    $variables['caption'] = '';
    $variables['caption_needed'] = FALSE;
  }

  $variables['summary'] = $handler->options['summary'];
  $variables['description'] = $handler->options['description'];
  $variables['caption_needed'] |= !empty($variables['summary']) || !empty($variables['description']);

  $variables['responsive'] = FALSE;
  // If the table has headers and it should react responsively to columns hidden
  // with the classes represented by the constants RESPONSIVE_PRIORITY_MEDIUM
  // and RESPONSIVE_PRIORITY_LOW, add the tableresponsive behaviors.
  if (isset($variables['header']) && $responsive) {
    $variables['view']->element['#attached']['library'][] = 'core/drupal.tableresponsive';
    // Add 'responsive-enabled' class to the table to identify it for JS.
    // This is needed to target tables constructed by this function.
    $variables['responsive'] = TRUE;
  }

    // Add totals.
  $variables['totals'] = array();
  $variables['sub_totals'] = array();
  if ($view->total_rows > $view->getItemsPerPage()
      && isset($view->sub_totals)) {
    views_calc_table_total($variables, 'sub_totals', $view->sub_totals);
  }
  if (isset($view->totals)) {
    views_calc_table_total($variables, 'totals', $view->totals);
  }

  $variables['#attached']['library'][] = 'views_calc/views_calc';
}

/**
 * Build total var line.
 */
function views_calc_table_total(&$vars, $key, $totals) {

  $view = $vars['view'];

  $options = $view->style_plugin->options;
  $handler = $view->style_plugin;
  $fields = &$view->field;
  $columns = $handler->sanitizeColumns($options['columns'], $fields);

  $vars[$key] = array();
  $added_label = array();
  // Build a data baserow with the default fields of the view
  $baserow = new StdClass();
  foreach ($view->field as $field) {
    // @todo in the D7 version, this uses $field->aliases['entity_type'] if
    // available. Review cases were similar logic may be needed.
    $query_alias = $field->field;
    $query_alias = views_calc_adj_full_alias($query_alias, $field);
    if (!empty($totals->{$query_alias})) {
      $baserow->{$query_alias} = $totals->{$query_alias};
    }
  }
  // Build aggregation rows, one per function.
  foreach ($view->views_calc_fields as $calc => $calc_fields) {
    // Rebuild row as if it where single queries.
    $row = clone $baserow;

    foreach ($view->field as $field) {
      // @todo in the D7 version, this uses $field->aliases['entity_type'] if
      // available. Review cases were similar logic may be needed.
      $query_alias = $field->field;
      $ext_alias = strtolower($calc) . '__' . $query_alias;

      // Limit the length of the alias up to 60 characters, because
      // stored in $total alias has been truncated in views add_field() function.
      $ext_alias = views_calc_shorten($ext_alias);
      if (in_array($field->options['id'], $calc_fields)) {
        if (!empty($totals->{$ext_alias})) {
          $row->{$query_alias} = $totals->{$ext_alias};
        }
      }
    }
    // Build row output data.
    foreach ($columns as $field => $column) {
      // @todo in the D7 version, this uses $field->aliases['entity_type'] if
      // available. Review cases were similar logic may be needed.
      $field_alias = $fields[$field]->field;
      if ($field == $column && empty($fields[$field]->options['exclude'])) {
        // Process only calculated, non-excluded fields.
        if (in_array($field, $calc_fields)) {

          if ($calc == 'COUNT') {

            // COUNT is always a numeric value, no matter what kind of field it is.

            $vars[$key][$calc][$column] =  number_format($row->$field_alias, 0, 0, ',');
          }
          else {

            // Calculations other than COUNT should run the value through the field's theme.
            // This will allow dates and numeric values to apply the right formatting to the result.
            // Unfortunately, there seems to be no easy way to push an arbitrary value through
            // the field theme. The theme may be retrieving its value from the cached entity.
            // We would like to do $fields[$field]->theme($row), but that won't work.

            // If this is a numeric field, get its options. We can at least use that in the aggregation.
            // The settings we need are in ->options['settings'] for fields created by the field module,
            // and in ->options for other fields that are based on the numeric field handler.
            if (isset($fields[$field]->field_info)) {
              $separator = !empty($fields[$field]->options['settings']['thousands_separator']) ? $fields[$field]->options['settings']['thousands_separator'] : $options['separator'];
              $decimal = !empty($fields[$field]->options['settings']['decimal_separator']) ? $fields[$field]->options['settings']['decimal_separator'] : $options['decimal'];
              $precision = !empty($fields[$field]->options['settings']['scale']) ? $fields[$field]->options['settings']['scale'] : $options['precision'];
            }
            else {
              $separator = !empty($fields[$field]->options['separator']) ? $fields[$field]->options['separator'] : $options['separator'];
              $decimal = !empty($fields[$field]->options['decimal']) ? $fields[$field]->options['decimal'] : $options['decimal'];
              $precision = !empty($fields[$field]->options['precision']) ? $fields[$field]->options['precision'] : $options['precision'];
            }
            // Make sure this is a number before formatting it.
            if (isset($row->$field_alias) && is_numeric($row->$field)) {
              $vars[$key][$calc][$column] = number_format($row->$field, $precision, $decimal, $separator);
           // if (isset($row->$field_alias) && is_numeric($row->$field_alias)) {
             // $vars[$key][$calc][$column] = number_format($row->$field_alias, $precision, $decimal, $separator);
            }
            else {
              $vars[$key][$calc][$column] = number_format(0, $precision, $decimal, $separator);
            }
          }
        }
        else {
          // Add the calc type label into the first empty column.
          // Identify which is the sub total and which the grand total
          // when both are provided.
          if (empty($added_label[$calc])) {
            if ($key == 'sub_totals') {
              $label = t("Page @Calculation", array("@Calculation" => $calc));
            }
            else {
              $label = t("Total @Calculation", array("@Calculation" => $calc));
            }
            $vars[$key][$calc][$column] = $label;
            $added_label[$calc] = TRUE;
          }
          else {
            $vars[$key][$calc][$column] = '';
          }
        }
      }
    }
  }
}


/**
  * Edge case if the same field has been used more than once,
  * most likely with relationships since you could show title
  * for each relationship. Results in a field like title becoming
  * title_1.
  */
function views_calc_adj_full_alias($alias, $field) {
  if ($field->field != $field->options['id']) {
    $addition = str_replace($field->field, '', $field->options['id']) .  '_';
    $ext_alias = $field->relationship . $addition . $field->real_field;
    $ext_alias = views_calc_shorten($ext_alias);
    return $ext_alias;
  }
  return $alias;
}

