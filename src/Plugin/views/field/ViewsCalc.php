<?php
 
/**
 * @file
 * Definition of Drupal\views_calc\Plugin\views\field\ViewsCalc
 */
 
namespace Drupal\views_calc\Plugin\views\field;
 
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\Core\Url;
 
/**
 * Field handler to flag the node type.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("views_calc")
 */
class ViewsCalc extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->additional_fields['nid'] = 'nid';
  }
 
  /**
   * @{inheritdoc}
   */
  public function query() {
    $results = _views_calc_fields();
    foreach ($results as $calc_field) {
      if ($this->definition['cid'] == $calc_field->cid) {
        // Ensure that the expected tables and fields have been joined in.
        foreach (explode(',', $calc_field->fieldlist) as $field) {
          $parts = explode('.', $field);
          //$this->view->query->add_field($parts[0], $parts[1]);
          $this->view->query->addTable($parts[0]);
        }
        $this->view->query->addField(NULL, "({$calc_field->calc})", "cid" . $calc_field->cid);
      }
    }
  }
 
  /**
   * Define the available options
   * @return array
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['link_to_node'] = array('default' => FALSE);
    
    return $options;
  }
  
  /**
   * Provide the options form.
   *
   * Provide link to node option.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    
    $form['link_to_node'] = array(
      '#title' => t('Link this field to its node'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->options['link_to_node']),
    );
  }

  /**
   * @{inheritdoc}
   */
  public function preQuery() {
    parent::preQuery();

    $this->field_alias = "cid{$this->definition['cid']}";
  }
 
  /**
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    // Use the requested format function to render the raw alias value.
    $field_alias = "cid{$this->definition['cid']}";

    $value = $values->$field_alias;
    $formats = _views_calc_format_options();
    $format = $formats[$this->definition['format']];
    $tmp = explode(':', $format);
    $function = trim($tmp[0]);
    $vars     = count($tmp) == 2 ? $tmp[1] : null;
    if ($function == 'custom') {
      $tmp = explode(':', $this->definition['custom']);
      $function = trim($tmp[0]);
      $vars     = count($tmp) == 2 ? $tmp[1] : null;
    }
    if (empty($function) || $function == 'none') {
      // @todo this sanitization is probably not right, review
      $raw = $this->sanitizeValue($value);
    }
    else {
      $raw = $function($value, $vars);
    }

    // This needs to be set for the $this->render_link() to work. It would
    // have been set in the query, if we hadn't bypassed the normal query.
    // TODO there may be a better way to do this.
    $this->aliases['nid'] = 'nid';

    return  $this->render_link($raw, $values);
  }

  /**
   * Render whatever the data is as a link to the node.
   *
   * Data should be made XSS safe prior to calling this function.
   */
  protected function render_link($data, $values) {
    // @todo see \Drupal\node\Plugin\views\field\Node::renderLink(), do we need to rewrite?
    if (!empty($this->options['link_to_node'])) {
      $url = Url::fromRoute('entity.node.canonical', ['node' => $values->{$this->aliases['nid']}]);
      return \Drupal::l($data, $url);
    }
    else {
      return $data;
    }
  }
}
