<?php

/**
* @file
* Contains \Drupal\views_calc\Plugin\views\sort\ViewsCalc.
*/

namespace Drupal\views_calc\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\SortPluginBase;
/**
* Default implementation of the base sort plugin.
*
* @ingroup views_sort_handlers
*
* @ViewsSort("views_calc")
*/
class ViewsCalc extends SortPluginBase {

    function query() {
        $this->ensureMyTable();
        $result = _views_calc_fields();
        foreach ($result as $calc_field) {
            if ($this->field == "cid" . $calc_field->cid) {
                foreach (explode(',', $calc_field->tablelist) as $table) {
                    $this->query->addTable($table);
                }
            }
            $this->query->addOrderBy(NULL, "({$calc_field->calc})", $this->options['order'], "cid" . $calc_field->cid);
            return;
        }
    }
}
