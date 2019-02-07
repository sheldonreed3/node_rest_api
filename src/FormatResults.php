<?php

namespace Drupal\node_rest_api;

use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Drupal\Component\Serialization\Json;

/**
 * Class FormatResults
 *
 * Provide a base class for node export formatting. This is meant to handle the
 * issues with paragraphs, metatags and other entities that do not process
 * correctly with Views while still allowing for rendered data and field
 * selection through a REST endpoint or View.
 *
 * @package Drupal\node_rest_api\Controller
 */
abstract class FormatResults {

  /**
   * @var \Drupal\metatag\MetatagManager
   */
  private $metatag_manager;

  private $serializer;
  protected $fields, $field_defs;

  public function __construct($type) {
    $this->serializer = \Drupal::service('serializer');
    $this->metatag_manager = \Drupal::service('metatag.manager');
    $this->field_defs = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('node', $type);
  }

  public function processNodes($nids) {
    $formatted_nodes = [];
    foreach ($nids as $nid) {
      $formatted_nodes[] = $this->formatNode($nid);
    }
    return $formatted_nodes;
  }

  /**
   * Abstract function to allow for field selection.
   */
  abstract protected function setFields();

  /**
   * Get the Metatag information for this node.
   *
   * @param $node
   * @param $output
   */
  protected function getMetaInfo($node, &$output) {
    $metatags = metatag_get_default_tags($node);
    $tags = $this->metatag_manager->generateRawElements($metatags, $node);
    foreach ($tags as $name => $tag) {
      if ($name === 'canonical_url') {
        $val_name = 'href';
      }
      else {
        $val_name = 'content';
      }

      if (isset($tags['#attributes'][$val_name])) {
        $output['meta_' . $name] = $tag['#attributes'][$val_name];
      }
    }
  }

  /**
   * Reformat Media module tags in content to a regular image tag.
   *
   * @param $content
   *
   * @return mixed
   */
  private function parseMediaImage($content) {
    if (stripos($content, '[[{')) {
      preg_match_all('/\[\[{(.*?)}\]\]/s', $content, $matches);

      foreach ($matches[0] as $match) {
        $json = Json::decode($match);
        $json = reset($json);

        if ($img = File::load(reset($json)['fid'])) {
          $url = file_create_url($img->getFileUri());
          $alt = $img->get('field_file_image_alt_text')->value;
          $alt = $alt ? $alt : $img->get('field_image_alt_text')->value;
          $alt = $alt ? $alt : 'Entity image';
          $img_str = "<img src=\"$url\" alt=\"$alt\">";
          $content = str_ireplace($match, $img_str, $content);
        }
      }
    }

    return $content;
  }

  /**
   * Overridable function to optionally provide special formatting for taxonomy
   * terms
   *
   * @param $term
   */
  protected function formatTaxonomyTerm($term) {}

  /**
   * Handle EntityReference field formatting
   *
   * @param $field
   * @param $node
   *
   * @return array|bool|string
   */
  protected function formatEntityReference($field, Node $node) {
    $str = '';
    $arr = [];

    // We are going to loop through each field on the EntityReference and handle
    // each in turn
    foreach ($node->get($field) as $item) {
      $entity = $item->entity;

      if ($entity->getEntityType()->id() === 'taxonomy_term') {
        // Allow this process to be overridden.

        // For taxonomy terms we are just going to provide comma delimited
        // string by default.
        $str .= "{$entity->get('name')->value},";
      }
      else {
        $entity = $item->entity;
        $entity_type = $entity->getEntityType()->id();
        $view_mode = 'full';
        $view_builder = \Drupal::entityTypeManager()
          ->getViewBuilder($entity_type);
        $pre_render = $view_builder->view($entity, $view_mode);
        $render_output = render($pre_render);
        $render_output = $this->parseMediaImage($render_output);
        $arr[] = str_ireplace([
          "\r\n",
          '<p>&nbsp;</p>',
          '<p></p>',
        ], '', $render_output);
      }
    }

    $str = strlen($str) > 0 ? substr($str, 0, -1) : $str;
    return !empty($arr) ? $arr : $str;
  }

  /**
   * @param $nid
   *
   * @return array
   */
  protected function formatNode($nid) {
    $node = Node::load($nid);
    $output = [];
    $output['nid'] = $nid;
    $path = urlencode(\Drupal::service('path.alias_manager')
      ->getAliasByPath('/node/' . $nid));
    $output['path'] = str_ireplace('%2F', '/', $path);

    foreach ($this->fields as $field) {
      // Get the type of field i.e. string, integer etc.
      $type = $this->field_defs[$field]->getType();

      switch ($type) {
        case 'entity_reference_revisions':
        case 'entity_reference':
          $value = $this->formatEntityReference($field, $node);
          break;
        case 'image':
          if ($file = $node->get($field)->entity) {
            $uri = file_create_url($file->getFileUri());
            $value = $uri ? $uri : '';
          }
          break;
        case 'link':
          $value = $node->get($field)->getValue();
          break;
        default:
          $value = $node->get($field)->value;
          $value = $this->parseMediaImage($value);
          $value = str_ireplace([
            "\r\n",
            '<p>&nbsp;</p>',
            '<p></p>',
          ], '', $value);
      }

      $output[$field] = $value;
    }
    $this->getMetaInfo($node, $output);

    return $output;
  }
}