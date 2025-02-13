<?php

namespace Drupal\nidirect_gp\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for GP entities.
 */
class GpViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Additional information for Views integration, such as table joins, can be
    // put here.

    return $data;
  }

}
