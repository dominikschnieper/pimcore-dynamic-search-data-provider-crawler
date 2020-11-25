<?php

namespace DsWebCrawlerBundle\Provider;

use DsWebCrawlerBundle\DsWebCrawlerBundle;
use DsWebCrawlerBundle\Service\CrawlerServiceInterface;
use DsWebCrawlerBundle\Service\FileWatcherServiceInterface;
use DynamicSearchBundle\Context\ContextDefinitionInterface;
use DynamicSearchBundle\Exception\ProviderException;
use DynamicSearchBundle\Normalizer\Resource\ResourceMetaInterface;
use DynamicSearchBundle\Provider\DataProviderInterface;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CrawlerDataProvider implements DataProviderInterface
{
    /**
     * @var CrawlerServiceInterface
     */
    protected $crawlerService;

    /**
     * @var FileWatcherServiceInterface
     */
    protected $fileWatcherService;

    /**
     * @var array
     */
    protected $configuration;

    /**
     * @param CrawlerServiceInterface     $crawlerService
     * @param FileWatcherServiceInterface $fileWatcherService
     */
    public function __construct(CrawlerServiceInterface $crawlerService, FileWatcherServiceInterface $fileWatcherService)
    {
        $this->crawlerService = $crawlerService;
        $this->fileWatcherService = $fileWatcherService;
    }

    /**
     * {@inheritdoc}
     */
    public static function configureOptions(OptionsResolver $resolver)
    {
        $options = [
            'always'                                 => function (OptionsResolver $spoolResolver) {

                $options = [
                    'own_host_only'      => false,
                    'allow_subdomains'   => false,
                    'allow_query_in_url' => false,
                    'allow_hash_in_url'  => false,
                    'allowed_mime_types' => ['text/html', 'application/pdf'],
                    'allowed_schemes'    => ['http'],
                    'content_max_size'   => 0,
                    'core_invalid_links' => '@.*\.(js|JS|gif|GIF|jpg|JPG|png|PNG|ico|ICO|eps|jpeg|JPEG|bmp|BMP|css|CSS|sit|wmf|zip|ppt|mpg|xls|gz|rpm|tgz|mov|MOV|exe|mp3|MP3|kmz|gpx|kml|swf|SWF)$@'
                ];

                $spoolResolver->setDefaults($options);
                $spoolResolver->setRequired(array_keys($options));
                $spoolResolver->setAllowedTypes('own_host_only', ['bool']);
                $spoolResolver->setAllowedTypes('allow_subdomains', ['bool']);
                $spoolResolver->setAllowedTypes('allow_query_in_url', ['bool']);
                $spoolResolver->setAllowedTypes('allow_hash_in_url', ['bool']);
                $spoolResolver->setAllowedTypes('allowed_mime_types', ['string[]']);
                $spoolResolver->setAllowedTypes('allowed_schemes', ['string[]']);
                $spoolResolver->setAllowedTypes('content_max_size', ['int']);
                $spoolResolver->setAllowedTypes('core_invalid_links', ['string']);
            },
            self::PROVIDER_BEHAVIOUR_FULL_DISPATCH   => function (OptionsResolver $spoolResolver) {

                $options = [
                    'seed'               => null,
                    'valid_links'        => [],
                    'user_invalid_links' => [],
                    'max_link_depth'     => 15,
                    'max_crawl_limit'    => 0,
                ];

                $spoolResolver->setDefaults($options);
                $spoolResolver->setRequired(array_keys($options));
                $spoolResolver->setAllowedTypes('seed', ['string']);
                $spoolResolver->setAllowedTypes('valid_links', ['string[]']);
                $spoolResolver->setAllowedTypes('user_invalid_links', ['string[]']);
                $spoolResolver->setAllowedTypes('max_link_depth', ['int']);
                $spoolResolver->setAllowedTypes('max_crawl_limit', ['int']);
            },
            self::PROVIDER_BEHAVIOUR_SINGLE_DISPATCH => function (OptionsResolver $spoolResolver) {

                $spoolResolver->setDefaults([]);
                $spoolResolver->setRequired(['host']);
                $spoolResolver->setAllowedTypes('host', ['string']);

            }
        ];

        $resolver->setDefaults($options);
        $resolver->setRequired(array_keys($options));
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options)
    {
        $this->configuration = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function warmUp(ContextDefinitionInterface $contextDefinition)
    {
        $this->fileWatcherService->resetPersistenceStore();
    }

    /**
     * {@inheritdoc}
     */
    public function coolDown(ContextDefinitionInterface $contextDefinition)
    {
        $this->fileWatcherService->resetPersistenceStore();
        $this->fileWatcherService->resetUriFilterPersistenceStore();
    }

    /**
     * {@inheritdoc}
     */
    public function cancelledShutdown(ContextDefinitionInterface $contextDefinition)
    {
        $this->fileWatcherService->resetPersistenceStore();
        $this->fileWatcherService->resetUriFilterPersistenceStore();
    }

    /**
     * {@inheritdoc}
     */
    public function emergencyShutdown(ContextDefinitionInterface $contextDefinition)
    {
        $this->fileWatcherService->resetPersistenceStore();
        $this->fileWatcherService->resetUriFilterPersistenceStore();
    }

    /**
     * {@inheritdoc}
     */
    public function checkUntrustedResourceProxy(ContextDefinitionInterface $contextDefinition, $resource)
    {
        // not required / implemented in crawler provider!

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function validateUntrustedResource(ContextDefinitionInterface $contextDefinition, $resource)
    {
        if ($resource instanceof Asset\Document) {
            return true;
        } elseif ($resource instanceof Document) {
            return true;
        } elseif ($resource instanceof DataObject\Concrete) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function provideAll(ContextDefinitionInterface $contextDefinition)
    {
        $this->crawlerService->initFullCrawl($contextDefinition->getName(), $contextDefinition->getContextDispatchType(), $this->configuration);
        $this->crawlerService->process();
    }

    /**
     * {@inheritdoc}
     */
    public function provideSingle(ContextDefinitionInterface $contextDefinition, ResourceMetaInterface $resourceMeta)
    {
        $options = $resourceMeta->getResourceOptions();

        if (!is_string($options['path'])) {
            throw new ProviderException('resource option "path" must be set to provide single data.', DsWebCrawlerBundle::PROVIDER_NAME);
        }

        $this->configuration['path'] = $options['path'];

        $this->crawlerService->initSingleCrawl($resourceMeta, $contextDefinition->getName(), $contextDefinition->getContextDispatchType(), $this->configuration);
        $this->crawlerService->process();
    }
}
