<?php

namespace Drupal\notice_data_feed\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Pager\PagerParametersInterface;
use Drupal\Core\Render\RendererInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Notice data feed routes.
 */
class NoticeDataFeedController extends ControllerBase {
  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * The pager parameters.
   *
   * @var \Drupal\Core\Pager\PagerParametersInterface
   */
  protected $pagerParameters;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Constructs a new NoticeDataFeedController.
   */
  public function __construct(
    ClientInterface $http_client,
    RendererInterface $renderer,
    PagerManagerInterface $pager_manager,
    PagerParametersInterface $pager_parameters,
    DateFormatterInterface $date_formatter,
    CacheBackendInterface $cache,
  ) {
    $this->httpClient = $http_client;
    $this->renderer = $renderer;
    $this->pagerManager = $pager_manager;
    $this->pagerParameters = $pager_parameters;
    $this->dateFormatter = $date_formatter;
    $this->cache = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('renderer'),
      $container->get('pager.manager'),
      $container->get('pager.parameters'),
      $container->get('date.formatter'),
      $container->get('cache.default')
    );
  }

  /**
   * Builds the response.
   */
  public function content(): array {

    $page = $this->pagerParameters->findPage();
    $per_page = 10;
    $offset = $page * $per_page;
    try {
      // Use Drupal cache to cache API responses per page.
      $cache_tags = ['notice_data_feed'];
      $cid = 'notice_data_feed:page:' . $page;

      if ($cache = $this->cache->get($cid)) {
        $data = $cache->data;
      }
      else {
        $response = $this->httpClient->get('https://www.thegazette.co.uk/all-notices/notice/data.json', [
          'query' => [
            'results-page' => $page + 1,
          ],
        ]);
        $response_data = $response->getBody()->getContents();
        $data = json_decode($response_data, TRUE);
      }

      if (isset($data['entry'])) {
        $this->cache->set($cid, $data, time() + 3600, $cache_tags);
      }
      if (empty($data['entry'])) {
        return ['#markup' => $this->t('No notices found.')];
      }

      $build = [];
      foreach ($data['entry'] as $entry) {
        $notice_url = $entry['link'][1]['@href'] ?? $entry['link'][0]['@href'];
        $build[] = [
          '#theme' => 'notice_data_item',
          '#title' => $entry['title'] ?? '',
          '#url' => $notice_url,
          '#date' => $this->dateFormatter->format(strtotime($entry['published']), 'custom', 'j F Y') ?? '',
          '#content' => $entry['content'] ?? '',
          '#category' => $entry['category']['@term'] ?? '',
        ];
      }

      // Add pager using the total from the API response.
      $total = $data['f:total'] ?? 0;
      $this->pagerManager->createPager($total, $per_page);

      $build['pager'] = [
        '#type' => 'pager',
      ];

      return $build;
    }
    catch (\Exception $e) {
      $this->getLogger('notice_data_feed')->error($e->getMessage());
      return ['#markup' => $this->t('Failed to retrieve notices. Please try again later.')];
    }
  }

}
