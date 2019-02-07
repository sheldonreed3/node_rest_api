<?php

namespace Drupal\node_rest_api\Controller;

use Drupal\node_rest_api\FormatResults;

class NodeFormatResults extends FormatResults {
  protected function setFields() {
    $this->fields = [
      'title',
      'path',
    ];
  }
}