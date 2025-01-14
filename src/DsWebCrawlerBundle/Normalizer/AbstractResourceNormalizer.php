<?php

namespace DsWebCrawlerBundle\Normalizer;

use DynamicSearchBundle\Context\ContextDefinitionInterface;
use DynamicSearchBundle\Exception\NormalizerException;
use DynamicSearchBundle\Manager\DataManagerInterface;
use DynamicSearchBundle\Manager\TransformerManagerInterface;
use DynamicSearchBundle\Normalizer\Resource\NormalizedDataResource;
use DynamicSearchBundle\Normalizer\Resource\ResourceMetaInterface;
use DynamicSearchBundle\Normalizer\ResourceNormalizerInterface;
use DynamicSearchBundle\Resource\Container\ResourceContainerInterface;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document\Page;
use Pimcore\Model\Element\ElementInterface;
use VDB\Spider\Resource as SpiderResource;

abstract class AbstractResourceNormalizer implements ResourceNormalizerInterface
{
    protected TransformerManagerInterface $transformerManager;
    protected DataManagerInterface $dataManager;

    public function __construct(
        TransformerManagerInterface $transformerManager,
        DataManagerInterface $dataManager
    ) {
        $this->transformerManager = $transformerManager;
        $this->dataManager = $dataManager;
    }

    public function normalizeToResourceStack(ContextDefinitionInterface $contextDefinition, ResourceContainerInterface $resourceContainer): array
    {
        if ($resourceContainer->getResource() instanceof SpiderResource) {
            return $this->normalizeSpiderResource($contextDefinition, $resourceContainer);
        }

        return $this->normalizePimcoreResource($contextDefinition, $resourceContainer);
    }

    /**
     * @throws NormalizerException
     */
    protected function normalizeSpiderResource(ContextDefinitionInterface $contextDefinition, ResourceContainerInterface $resourceContainer): array
    {
        $resourceMeta = null;
        if ($resourceContainer->hasAttribute('html')) {
            $resourceMeta = $this->generateResourceMetaFromHtmlResource($resourceContainer->getResource());
        } elseif ($resourceContainer->hasAttribute('pdf_content')) {
            $resourceMeta = $this->generateResourceMetaFromPdfResource($resourceContainer->getAttributes());
        }

        if ($resourceMeta === null) {
            return [];
        }

        return [new NormalizedDataResource($resourceContainer, $resourceMeta)];
    }

    /**
     * @throws NormalizerException
     */
    protected function normalizePimcoreResource(ContextDefinitionInterface $contextDefinition, ResourceContainerInterface $resourceContainer): array
    {
        $resource = $resourceContainer->getResource();
        if (!$resource instanceof ElementInterface) {
            return [];
        }

        if ($resource instanceof Page) {
            return $this->normalizePage($contextDefinition, $resourceContainer);
        }

        if ($resource instanceof Asset) {
            return $this->normalizeAsset($contextDefinition, $resourceContainer);
        }

        if ($resource instanceof DataObject) {
            return $this->normalizeDataObject($contextDefinition, $resourceContainer);
        }

        return [];
    }

    /**
     * @throws NormalizerException
     */
    abstract protected function normalizePage(ContextDefinitionInterface $contextDefinition, ResourceContainerInterface $resourceContainer): array;

    /**
     * @throws NormalizerException
     */
    abstract protected function normalizeAsset(ContextDefinitionInterface $contextDefinition, ResourceContainerInterface $resourceContainer): array;

    /**
     * @throws NormalizerException
     */
    abstract protected function normalizeDataObject(ContextDefinitionInterface $contextDefinition, ResourceContainerInterface $resourceContainer): array;

    /**
     * @throws NormalizerException
     */
    abstract protected function generateResourceMetaFromHtmlResource(SpiderResource $resource): ?ResourceMetaInterface;

    /**
     * @throws NormalizerException
     */
    abstract protected function generateResourceMetaFromPdfResource(array $resourceAttributes): ?ResourceMetaInterface;
}
