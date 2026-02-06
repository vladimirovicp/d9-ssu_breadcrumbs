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
    $structure_node = $this->entityTypeManager->getStorage('node')->load(759);
    if ($structure_node) {
      $breadcrumb->addLink(Link::createFromRoute($structure_node->label(), 'entity.node.canonical', ['node' => 759]));
    }

    // Получаем связанную сущность из поля field_news_link.
    $linked_entity = $node->get('field_news_link')->entity;


    if ($linked_entity) {
      // Добавляем ссылку на связанную сущность.
      $breadcrumb->addLink(Link::fromTextAndUrl($linked_entity->label(), $linked_entity->toUrl()));

      $newsUrl = $linked_entity->toUrl();
      
      $newsUrl->setOption('absolute', false);
      $newsPath = $newsUrl->toString() . '/news';
      $newsUrlObject = Url::fromUserInput($newsPath);
      $breadcrumb->addLink(Link::fromTextAndUrl(t('Новости'), $newsUrlObject));
    }



    // Добавляем заголовок текущей ноды в конец breadcrumb (текстом, без ссылки).
    $breadcrumb->addLink(Link::fromTextAndUrl($node->getTitle(), Url::fromRoute('<none>')));

    return $breadcrumb;
  }

}