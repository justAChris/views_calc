<?php
/**
 * Implements hook_views_data_alter().
 *
 * Add calc fields to views field list.
 */
function views_calc_views_data_alter(&$data) {
  $results = _views_calc_fields();
  foreach ($results as $field) {
    $data[$field->base]['cid' . $field->cid] = array(
      //'group' => t('Views Calc'),
      'title' => t($field->label),
      'field' => array(
        'id' => 'views_calc',
        'title' => t($field->label),
        'help' => $field->calc,
        'click sortable' => TRUE,
        'allow empty' => TRUE,
        'cid' => $field->cid,
        'format' => $field->format,
        'custom' => $field->custom,
        'calc' => $field->calc,
      ),
      'sort' => array(
        'id' => 'views_calc',
      ),
    );
  }
}
