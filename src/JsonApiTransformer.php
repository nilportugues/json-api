<?php

namespace NilPortugues\Api\JsonApi;

use NilPortugues\Api\JsonApi\Helpers\DataAttributesHelper;
use NilPortugues\Api\JsonApi\Helpers\DataIncludedHelper;
use NilPortugues\Api\JsonApi\Helpers\DataLinksHelper;
use NilPortugues\Api\JsonApi\Helpers\PropertyHelper;
use NilPortugues\Api\Transformer\Helpers\RecursiveDeleteHelper;
use NilPortugues\Api\Transformer\Helpers\RecursiveFilterHelper;
use NilPortugues\Api\Transformer\Helpers\RecursiveFormatterHelper;
use NilPortugues\Api\Transformer\Helpers\RecursiveRenamerHelper;
use NilPortugues\Api\Transformer\Transformer;
use NilPortugues\Serializer\Serializer;

/**
 * This Transformer follows the http://jsonapi.org specification.
 *
 * @link http://jsonapi.org/format/#document-structure
 */
class JsonApiTransformer extends Transformer
{
    const TITLE = 'title';
    const RELATIONSHIPS_KEY = 'relationships';
    const LINKS_KEY = 'links';
    const TYPE_KEY = 'type';
    const DATA_KEY = 'data';
    const JSON_API_KEY = 'jsonapi';
    const META_KEY = 'meta';
    const INCLUDED_KEY = 'included';
    const VERSION_KEY = 'version';
    const ATTRIBUTES_KEY = 'attributes';
    const ID_KEY = 'id';
    const ID_SEPARATOR = '.';
    const RELATED_LINK = 'related';

    /**
     * @param array $value
     *
     * @throws \NilPortugues\Api\Transformer\TransformerException
     *
     * @return string
     */
    public function serialize($value)
    {
        $this->noMappingGuard();

        if (\is_array($value) && !empty($value[Serializer::MAP_TYPE])) {
            $data = $this->serializedArray($value);
        } else {
            $data = $this->serializeObject($value);
        }

        return \json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param array $value
     *
     * @return array
     */
    protected function serializeObject(array $value)
    {
        $value = $this->transformUnmappedObjectsToArray($value);
        $value = $this->preSerialization($value);
        $data = $this->serialization($value);

        return $this->postSerialization($data);
    }

    /**
     * @param array $value
     *
     * @return array
     */
    protected function preSerialization(array $value)
    {
        /** @var \NilPortugues\Api\Mapping\Mapping $mapping */
        foreach ($this->mappings as $class => $mapping) {
            RecursiveFilterHelper::deletePropertiesNotInFilter($this->mappings, $value, $class);
            RecursiveDeleteHelper::deleteProperties($this->mappings, $value, $class);
            $this->buildValidPropertyAlias($mapping);
            RecursiveRenamerHelper::renameKeyValue($this->mappings, $value, $class);
        }

        return $value;
    }

    /**
     * @param array $value
     *
     * @return array
     */
    protected function serialization(array &$value)
    {
        $data = [
            self::DATA_KEY => \array_merge(
                PropertyHelper::setResponseDataTypeAndId($this->mappings, $value),
                DataAttributesHelper::setResponseDataAttributes($this->mappings, $value),
                DataLinksHelper::setResponseDataLinks($this->mappings, $value),
                DataLinksHelper::setResponseDataRelationship($this->mappings, $value, $value, $this->attributesCase)
            ),
        ];

        DataIncludedHelper::setResponseDataIncluded($this->mappings, $value, $data);

        $this->setResponseLinks($value, $data);
        $this->setResponseMeta($data);
        $this->setResponseVersion($data);

        return $data;
    }

    /**
     * @param array $value
     * @param array $data
     */
    protected function setResponseLinks(array $value, array &$data)
    {
        $data[self::LINKS_KEY] = \array_filter(
            \array_merge(
                $this->addHrefToLinks($this->buildLinks()),
                (!empty($data[self::LINKS_KEY])) ? $data[self::LINKS_KEY] : []
            )
        );

        if (!empty($value[Serializer::CLASS_IDENTIFIER_KEY])) {
            $type = $value[Serializer::CLASS_IDENTIFIER_KEY];

            if (\is_scalar($type) && !empty($this->mappings[$type])) {
                $urls = $this->mappings[$type]->getUrls();

                $data[self::LINKS_KEY] = \array_filter(
                    \array_merge(
                        (empty($data[self::LINKS_KEY])) ? [] : $data[self::LINKS_KEY],
                        (!empty($urls)) ? $this->addHrefToLinks($this->getResponseAdditionalLinks($value, $type)) : []
                    )
                );

                /*
                 * Adds the _links:self:href link to the response.
                 */
                list($idValues, $idProperties) = RecursiveFormatterHelper::getIdPropertyAndValues(
                    $this->mappings,
                    $value,
                    $type
                );
                $href = DataLinksHelper::buildUrl(
                    $this->mappings,
                    $idProperties,
                    $idValues,
                    $this->mappings[$type]->getResourceUrl(),
                    $type
                );
                if ($href != $this->mappings[$type]->getResourceUrl()) {
                    $data[self::LINKS_KEY][self::SELF_LINK][self::LINKS_HREF] = $href;
                }
            }
        }

        if (empty($data[self::LINKS_KEY])) {
            unset($data[self::LINKS_KEY]);
        }
    }

    /**
     * @param array $response
     */
    protected function setResponseMeta(array &$response)
    {
        if (!empty($this->meta)) {
            $response[self::META_KEY] = $this->meta;
        }
    }

    /**
     * @param array $response
     */
    protected function setResponseVersion(array &$response)
    {
        $response[self::JSON_API_KEY][self::VERSION_KEY] = '1.0';
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function postSerialization(array $data)
    {
        $this->formatScalarValues($data);
        RecursiveDeleteHelper::deleteKeys($data, [Serializer::CLASS_IDENTIFIER_KEY]);

        return $data;
    }

    /**
     * @param \NilPortugues\Api\Mapping\Mapping $mapping
     *
     * @link http://jsonapi.org/format/#document-member-names-allowed-characters
     */
    protected function buildValidPropertyAlias($mapping)
    {
        $aliases = $mapping->getAliasedProperties();
        foreach ($aliases as &$alias) {
            $alias = DataAttributesHelper::transformToValidMemberName($alias);
        }
        $mapping->setPropertyNameAliases($aliases);
    }

    /**
     * @param array $value
     *
     * @return array
     */
    protected function serializedArray(array $value)
    {
        unset($value[Serializer::MAP_TYPE]);

        $dataValues = [];
        $includedValues = [];

        foreach ($value[Serializer::SCALAR_VALUE] as $v) {
            $v = $this->serializeObject($v);
            $dataValues[] = $v[self::DATA_KEY];
            if (!empty($v[self::INCLUDED_KEY])) {
                $includedValues = \array_merge($includedValues, $v[self::INCLUDED_KEY]);
            }
        }
        $includedValues = \array_unique($includedValues, SORT_REGULAR);

        $data[self::DATA_KEY] = $dataValues;
        $data[self::INCLUDED_KEY] = \array_values($includedValues);
        $data = array_filter($data);

        $this->setResponseLinks($value, $data);
        $this->setResponseMeta($data);
        $this->setResponseVersion($data);
        $this->setResponseMeta($data);

        return (empty($data['data'])) ? array_merge(['data' => []], $data) : $data;
    }

    /**
     * @param array $value
     *
     * @return array
     */
    protected function transformUnmappedObjectsToArray(array $value)
    {
        return RecursiveRenamerHelper::serializedObjectToArray($value, $this->mappings);
    }
}
