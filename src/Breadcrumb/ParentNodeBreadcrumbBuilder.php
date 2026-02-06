<?php

namespace Drupal\ssu_breadcrumbs\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Provides a breadcrumb builder for nodes with a parent reference.
 */
class ParentNodeBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ParentNodeBreadcrumbBuilder.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    
    // Применяем только к полным страницам нод.
    $route_name = $route_match->getRouteName();
    if ($route_name !== 'entity.node.canonical') {
      return FALSE;
    }

    $node = $route_match->getParameter('node');
    if (!$node instanceof NodeInterface) {
      return FALSE;
    }

    // Применяем только к типу материала news.
    if ($node->bundle() !== 'news') {
      return FALSE;
    }

    // Проверяем, есть ли у ноды поле field_news_link и оно не пустое.
    if (!$node->hasField('field_news_link') || $node->get('field_news_link')->isEmpty()) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $node = $route_match->getParameter('node');

    // Проверяем, что это нода.
    if (!$node instanceof NodeInterface) {
      $breadcrumb = new Breadcrumb();
      return $breadcrumb;
    }

    // Проверяем, что тип материала - news.
    if ($node->bundle() !== 'news') {
      $breadcrumb = new Breadcrumb();
      return $breadcrumb;
    }

    // Проверяем, что существует поле field_news_link и оно не пустое.
    if (!$node->hasField('field_news_link') || $node->get('field_news_link')->isEmpty()) {
      $breadcrumb = new Breadcrumb();
      return $breadcrumb;
    }

    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(['url.path']);

    // Главная страница всегда в начале.
    $breadcrumb->addLink(Link::createFromRoute(t('Главная'), '<front>'));

    // Добавляем ссылку на ноду "Структура" (ID: 759).
    // $structure_node = $this->entityTypeManager->getStorage('node')->load(759);
    // if ($structure_node) {
    //   $breadcrumb->addLink(Link::createFromRoute($structure_node->label(), 'entity.node.canonical', ['node' => 759]));
    // }

    // Получаем связанную сущность из поля field_news_link.
    $linked_entity = $node->get('field_news_link')->entity;

    if ($linked_entity) {
      // Рекурсивно обрабатываем связанную сущность.
      $this->processLinkedEntity($linked_entity, $breadcrumb);
    }

    // Получаем последнюю добавленную ссылку для формирования URL новостей.
    $links = $breadcrumb->getLinks();
    if (!empty($links)) {
      $last_link = end($links);
      $newsUrl = $last_link->getUrl();
      $newsUrl->setOption('absolute', false);
      $newsPath = $newsUrl->toString() . '/news';
      $newsUrlObject = Url::fromUserInput($newsPath);
      $breadcrumb->addLink(Link::fromTextAndUrl(t('Новости'), $newsUrlObject));
    }

    // Добавляем заголовок текущей ноды в конец breadcrumb (текстом, без ссылки).
    $breadcrumb->addLink(Link::fromTextAndUrl($node->getTitle(), Url::fromRoute('<none>')));

    return $breadcrumb;
  }

  /**
   * Рекурсивно обрабатывает связанную сущность для breadcrumb.
   * Порядок вывода: первый вошел, последний вышел (LIFO).
   *
   * @param \Drupal\Core\Entity\EntityInterface $linked_entity
   *   Связанная сущность для обработки.
   * @param \Drupal\Core\Breadcrumb\Breadcrumb $breadcrumb
   *   Объект breadcrumb для добавления ссылок.
   */
  protected function processLinkedEntity($linked_entity, Breadcrumb $breadcrumb) {
    // Проверяем, является ли связанная сущность нодой с ID 759.
    if ($linked_entity instanceof NodeInterface && $linked_entity->id() == 759) {
      // Если это нода 759, добавляем ссылку и прекращаем рекурсию.
      $breadcrumb->addLink(Link::fromTextAndUrl($linked_entity->label(), $linked_entity->toUrl()));
      return;
    }

    // Проверяем, есть ли у связанной сущности поле field_parent_link.
    if ($linked_entity->hasField('field_parent_link') && !$linked_entity->get('field_parent_link')->isEmpty()) {
      // Получаем следующую связанную сущность.
      $next_linked_entity = $linked_entity->get('field_parent_link')->entity;
      if ($next_linked_entity) {
        // Сначала рекурсивно обрабатываем следующую связанную сущность (идем вглубь).
        $this->processLinkedEntity($next_linked_entity, $breadcrumb);
      }
    }

    // После рекурсии добавляем текущую ссылку (LIFO: последний вошел - первый вышел).
    $breadcrumb->addLink(Link::fromTextAndUrl($linked_entity->label(), $linked_entity->toUrl()));
  }

}