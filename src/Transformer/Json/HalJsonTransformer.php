<?php

namespace NilPortugues\Api\Transformer\Json;

use NilPortugues\Api\Transformer\Helpers\RecursiveDeleteHelper;
use NilPortugues\Api\Transformer\Helpers\RecursiveFormatterHelper;
use NilPortugues\Api\Transformer\Helpers\RecursiveRenamerHelper;
use NilPortugues\Api\Transformer\Json\Helpers\JsonApi\DataLinksHelper;
use NilPortugues\Api\Transformer\Transformer;
use NilPortugues\Serializer\Serializer;

/**
 * This Transformer follows the JSON+HAL specification.
 *
 * @link http://stateless.co/hal_specification.html
 */
class HalJsonTransformer extends Transformer
{
    const EMBEDDED_KEY = '_embedded';
    const META_KEY = '_meta';

    const LINKS_KEY = '_links';
    const LINKS_TEMPLATED_KEY = 'templated';
    const LINKS_DEPRECATION_KEY = 'deprecation';
    const LINKS_TYPE_KEY = 'type';
    const LINKS_NAME_KEY = 'name';
    const LINKS_PROFILE_KEY = 'profile';
    const LINKS_TITLE_KEY = 'title';
    const LINKS_HREF_LANG_KEY = 'hreflang';
    const LINKS_HREF = 'href';

    const MEDIA_PROFILE_KEY = 'profile';

    const SELF_LINK = 'self';
    const FIRST_LINK = 'first';
    const LAST_LINK = 'last';
    const PREV_LINK = 'prev';
    const NEXT_LINK = 'next';

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

        if (is_array($value) && !empty($value[Serializer::MAP_TYPE])) {
            $data = [];
            unset($value[Serializer::MAP_TYPE]);
            foreach ($value[Serializer::SCALAR_VALUE] as $v) {
                $data[] = $this->serializeObject($v);
            }
        } else {
            $data = $this->serializeObject($value);
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param array $value
     *
     * @return array
     */
    private function serializeObject(array $value)
    {
        $value = $this->preSerialization($value);
        $data = $this->serialization($value);

        return $this->postSerialization($data);
    }

    /**
     * @param array $value
     *
     * @return array
     */
    private function preSerialization(array $value)
    {
        /** @var \NilPortugues\Api\Mapping\Mapping $mapping */
        foreach ($this->mappings as $class => $mapping) {
            RecursiveDeleteHelper::deleteProperties($this->mappings, $value, $class);
            RecursiveRenamerHelper::renameKeyValue($this->mappings, $value, $class);
        }

        return $value;
    }

    /**
     * @param array $data
     *d
     *
     * @return array
     */
    private function serialization(array $data)
    {
        $this->setEmbeddedResources($data);
        $this->setResponseLinks($data);

        return $data;
    }

    private function setEmbeddedResources(array &$data)
    {
        foreach ($data as $propertyName => &$value) {
            if (is_array($value)) {
                if (!empty($value[Serializer::CLASS_IDENTIFIER_KEY])) {
                    $type = $value[Serializer::CLASS_IDENTIFIER_KEY];
                    $idProperties = $this->mappings[$type]->getIdProperties();

                    if (false === in_array($propertyName, $idProperties)) {
                        $data[self::EMBEDDED_KEY][$propertyName] = $value;

                        list($idValues, $idProperties) = DataLinksHelper::getPropertyAndValues(
                            $this->mappings,
                            $value,
                            $type
                        );

                        $data[self::EMBEDDED_KEY][$propertyName][self::LINKS_KEY][self::SELF_LINK][self::LINKS_HREF] = str_replace(
                            $idProperties,
                            $idValues,
                            $this->mappings[$type]->getResourceUrl()
                        );

                        $data[self::EMBEDDED_KEY][$propertyName][self::LINKS_KEY] = array_merge(
                            $data[self::EMBEDDED_KEY][$propertyName][self::LINKS_KEY],
                            $this->addHrefToLinks($this->getResponseAdditionalLinks($value, $type))
                        );

                        $data[self::LINKS_KEY][$propertyName][self::LINKS_HREF] = str_replace(
                            $idProperties,
                            $idValues,
                            $this->mappings[$type]->getResourceUrl()
                        );

                        unset($data[$propertyName]);
                    }
                }

                if (!empty($value[Serializer::MAP_TYPE])) {
                    foreach ($value as &$arrayValue) {
                        if (is_array($arrayValue)) {
                            foreach ($arrayValue as $inArrayProperty => &$inArrayValue) {
                                if (is_array(
                                        $inArrayValue
                                    ) && !empty($inArrayValue[Serializer::CLASS_IDENTIFIER_KEY])
                                ) {
                                    $this->setEmbeddedResources($inArrayValue);

                                    $data[self::EMBEDDED_KEY][$propertyName][$inArrayProperty] = $inArrayValue;
                                    $type = $inArrayValue[Serializer::CLASS_IDENTIFIER_KEY];

                                    list($idValues, $idProperties) = DataLinksHelper::getPropertyAndValues(
                                        $this->mappings,
                                        $inArrayValue,
                                        $type
                                    );

                                    $data[self::EMBEDDED_KEY][$propertyName][$inArrayProperty][self::LINKS_KEY][self::SELF_LINK][self::LINKS_HREF] = str_replace(
                                        $idProperties,
                                        $idValues,
                                        $this->mappings[$type]->getResourceUrl()
                                    );

                                    unset($data[$propertyName]);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @param array  $copy
     * @param string $type
     *
     * @return mixed
     */
    protected function getResponseAdditionalLinks(array $copy, $type)
    {
        $otherUrls = $this->mappings[$type]->getUrls();
        list($idValues, $idProperties) = DataLinksHelper::getPropertyAndValues(
            $this->mappings,
            $copy,
            $type
        );

        return str_replace($idProperties, $idValues, $otherUrls);
    }

    /**
     * @param array $data
     */
    protected function setResponseLinks(array &$data)
    {
        if (!empty($data[Serializer::CLASS_IDENTIFIER_KEY])) {
            $data[self::LINKS_KEY] = array_merge(
                $this->addHrefToLinks($this->buildLinks()),
                (!empty($data[self::LINKS_KEY])) ? $data[self::LINKS_KEY] : [],
                $this->addHrefToLinks($this->getResponseAdditionalLinks($data, $data[Serializer::CLASS_IDENTIFIER_KEY]))
            );

            if (empty($data[self::LINKS_KEY])) {
                unset($data[self::LINKS_KEY]);
            }
        }
    }

    /**
     * @param array $data
     *
     * @return array
     */
    private function postSerialization(array &$data)
    {
        RecursiveDeleteHelper::deleteKeys($data, [Serializer::CLASS_IDENTIFIER_KEY]);
        RecursiveDeleteHelper::deleteKeys($data, [Serializer::MAP_TYPE]);

        RecursiveFormatterHelper::formatScalarValues($data);

        self::flattenObjectsWithSingleKeyScalars($data);

        $this->recursiveSetKeysToUnderScore($data);
        $this->setResponseMeta($data);

        return $data;
    }

    /**
     * Simplifies the data structure by removing an array level if data is scalar and has one element in array.
     *
     * @param array $array
     */
    public static function flattenObjectsWithSingleKeyScalars(array &$array)
    {
        if (1 === count($array) && is_scalar(end($array))) {
            $array = array_pop($array);
        }

        if (is_array($array)) {
            self::loopScalarValues($array, 'flattenObjectsWithSingleKeyScalars');
        }
    }

    /**
     * @param array  $array
     * @param string $method
     */
    private static function loopScalarValues(array &$array, $method)
    {
        foreach ($array as $propertyName => &$value) {
            if (is_array($value) && self::LINKS_KEY !== $propertyName) {
                self::$method($value);
            }
        }
    }

    /**
     * @param array $response
     */
    private function setResponseMeta(array &$response)
    {
        if (!empty($this->meta)) {
            $response[self::META_KEY] = $this->meta;
        }
    }
}
