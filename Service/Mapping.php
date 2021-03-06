<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Elasticsearch\Client;
use EMS\CoreBundle\DependencyInjection\EMSCoreExtension;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Form\FieldType\FieldTypeType;

class Mapping
{
    const PUBLISHED_DATETIME_FIELD = '_published_datetime';
    const FINALIZATION_DATETIME_FIELD = '_finalization_datetime';
    const FINALIZED_BY_FIELD = '_finalized_by';
    const HASH_FIELD = '_sha1';
    const SIGNATURE_FIELD = '_signature';
    const CONTENT_TYPE_FIELD = '_contenttype';

    const MAPPING_INTERNAL_FIELDS = [
        Mapping::PUBLISHED_DATETIME_FIELD => Mapping::PUBLISHED_DATETIME_FIELD,
        Mapping::FINALIZATION_DATETIME_FIELD => Mapping::FINALIZATION_DATETIME_FIELD,
        Mapping::FINALIZED_BY_FIELD => Mapping::FINALIZED_BY_FIELD,
        Mapping::HASH_FIELD => Mapping::HASH_FIELD,
        Mapping::SIGNATURE_FIELD => Mapping::SIGNATURE_FIELD,
        Mapping::CONTENT_TYPE_FIELD => Mapping::CONTENT_TYPE_FIELD,
    ];

    const CONTENT_TYPE_META_FIELD = 'content_type';
    const GENERATOR_META_FIELD = 'generator';
    const GENERATOR_META_FIELD_VALUE = 'elasticms';
    const CORE_VERSION_META_FIELD = 'core_version';
    const INSTANCE_ID_META_FIELD = 'instance_id';

    /** @var Client */
    private $client;
    /** @var EnvironmentService */
    private $environmentService;

    /** @var FieldTypeType $fieldTypeType */
    private $fieldTypeType;
    
    /** @var ElasticsearchService $elasticsearchService */
    private $elasticsearchService;

    /**@var string*/
    private $coreVersion;

    /**@var string*/
    private $instanceId;
    
    /**
     * Constructor
     *
     * @param FieldTypeType $fieldTypeType
     * @param ElasticsearchService $elasticsearchService
     */
    public function __construct(Client $client, EnvironmentService $environmentService, FieldTypeType $fieldTypeType, ElasticsearchService $elasticsearchService, $coreVersion, $instanceId)
    {
        $this->client = $client;
        $this->environmentService = $environmentService;
        $this->fieldTypeType = $fieldTypeType;
        $this->elasticsearchService = $elasticsearchService;
        $this->coreVersion = $coreVersion;
        $this->instanceId = $instanceId;
    }
    
    public function generateMapping(ContentType $contentType, $withPipeline = false)
    {
        $out = [
            "properties" => [],
        ];

        if ($this->elasticsearchService->withAllMapping()) {
            $out['_all'] = [
                "store" => true,
                "enabled" => true,
            ];
        }
        
        if (null != $contentType->getFieldType()) {
            $out['properties'] = $this->fieldTypeType->generateMapping($contentType->getFieldType(), $withPipeline);
        }
        
        $out['properties'] = array_merge(
            [
                Mapping::HASH_FIELD => $this->elasticsearchService->getKeywordMapping(),
                Mapping::SIGNATURE_FIELD => $this->elasticsearchService->getNotIndexedStringMapping(),
                Mapping::FINALIZED_BY_FIELD => $this->elasticsearchService->getKeywordMapping(),
                Mapping::CONTENT_TYPE_FIELD => $this->elasticsearchService->getKeywordMapping(),
                Mapping::FINALIZATION_DATETIME_FIELD => $this->elasticsearchService->getDateTimeMapping(),
                Mapping::PUBLISHED_DATETIME_FIELD => $this->elasticsearchService->getDateTimeMapping(),
            ],
            $out['properties']
        );

        $out['_meta'] = [
            Mapping::CONTENT_TYPE_META_FIELD => $contentType->getName(),
            Mapping::GENERATOR_META_FIELD => Mapping::GENERATOR_META_FIELD_VALUE,
            Mapping::CORE_VERSION_META_FIELD => $this->coreVersion,
            Mapping::INSTANCE_ID_META_FIELD => $this->instanceId,
        ];
        
        
        return [ $contentType->getName() => $out ];
    }

    public function dataFieldToArray(DataField $dataField)
    {
        return $this->fieldTypeType->dataFieldToArray($dataField);
    }

    public function getMapping(array $environmentNames): ?array
    {
        $indices = $this->client->indices();
        $indexes = [];

        foreach ($environmentNames as $name) {
            $env = $this->environmentService->getByName($name);

            if ($env && $indices->exists(['index' => $env->getAlias()])) {
                $indexes[] = $env->getAlias();
            }
        }

        $result = [];

        if (empty($indexes)) {
            return $result;
        }

        $mappings = $indices->getMapping(['index' => $indexes]);

        foreach ($mappings as $index) {
            foreach ($index['mappings'] as $type => $mapping) {
                $result = \array_merge_recursive($mapping['properties'], $result);
            }
        }

        return $result;
    }
}
