<?php

namespace Drupal\entity_rest_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node_rest_api\Controller\NodeFormatResults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class NodeRestAPI extends ControllerBase {
  private $headers = ['Access-Control-Allow-Origin' => 'bitbucket.org', 'Content-Type' => 'application/json'];

  /**
   * @var \Drupal\Core\Entity\Query\QueryInterface;
   */
  protected $query;
  protected $fields, $type;
  public $args;

  /**
   * @param Request $request
   * @return JsonResponse
   */
  protected function get(Request $request)
  {
    $this->setArgs($request->query);
    $response = $this->getFormattedNodes();
    return new JsonResponse($response, 200, $this->headers);
  }

  /**
   * @param Request $request
   * @return JsonResponse
   */
  protected function post(Request $request)
  {
    $this->setArgs($request->request);
    $response = $this->getFormattedNodes();
    return new JsonResponse($response, 200, $this->headers);
  }

  /**
   * @param $args
   */
  protected function setArgs($args)
  {
    $this->args = $args;
  }

  /**
   * @param $type
   */
  protected function query($type)
  {
    $query = \Drupal::entityQuery('node');
    $query->condition('type', $type)
      ->condition('status', 1)
      ->addMetaData('account', \Drupal\user\Entity\User::load(1)); // Run the query as user 1

    // TODO Add argument filters
    if ($this->args) {
      foreach ($this->args as $field => $value) {
        $query->condition($field, $value);
      }
    }

    $this->query = $query;
  }

  protected function alterQuery()
  {
  }

  /**
   * @return mixed
   */
  protected function getNodes()
  {
    return $this->query->execute();
  }

  /**
   * @return array
   */
  protected function getFormattedNodes()
  {
    $type = $this->type;

    $formatter = new NodeFormatResults($type);

    $this->query($type);
    $this->alterQuery();
    $nodes = $this->getNodes();

    return $formatter->processNodes($nodes);
  }
}