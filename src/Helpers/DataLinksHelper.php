<?php

/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 7/25/15
 * Time: 9:54 PM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NilPortugues\Api\JsonApi\Helpers;

use NilPortugues\Api\JsonApi\JsonApiTransformer;
use NilPortugues\Api\Transformer\Helpers\RecursiveDeleteHelper;
use NilPortugues\Api\Transformer\Helpers\RecursiveFormatterHelper;
use NilPortugues\Serializer\Serializer;

/**
 * Class DataLinksHelper.
 */
class DataLinksHelper
{
    /**
     * @param \NilPortugues\Api\Mapping\Mapping[] $mappings
     * @param array                               $value
     *
     * @return array
     */
    public static function setResponseDataLinks(array &$mappings, array &$value)
    {
        $data = [];
        $type = $value[Serializer::CLASS_IDENTIFIER_KEY];

        if (\is_scalar($type) && !empty($mappings[$type])) {
            $copy = $value;
            RecursiveFormatterHelper::formatScalarValues($copy);
            if (!empty($copy[Serializer::CLASS_IDENTIFIER_KEY])) {
                unset($copy[Serializer::CLASS_IDENTIFIER_KEY]);
            }

            $idProperties = array_keys($copy);
            foreach ($idProperties as &$property) {
                $property = sprintf('{%s}', $property);
            }
            $idValues = array_values($copy);

            $selfLink = $mappings[$type]->getResourceUrl();

            if (!empty($selfLink)) {
                $url = self::buildUrl($mappings, $idProperties, $idValues, $selfLink, $type);

                if ($url !== $selfLink) {
                    $data[JsonApiTransformer::LINKS_KEY][JsonApiTransformer::SELF_LINK][JsonApiTransformer::LINKS_HREF] = $url;
                }
            }

            self::removeArraysFromKeyValueReplacement($idProperties, $idValues);

            foreach ($mappings[$type]->getUrls() as $name => $url) {
                if (is_array($url) && !empty($url['name'])) {
                    $url = $url['name'];
                }

                $newUrl = \str_replace($idProperties, $idValues, $url);

                if ($newUrl !== $url) {
                    $data[JsonApiTransformer::LINKS_KEY][$name][JsonApiTransformer::LINKS_HREF] = $newUrl;
                }
            }
        }

        return $data;
    }

    /**
     * @param \NilPortugues\Api\Mapping\Mapping[] $mappings
     * @param array                               $array
     * @param array                               $parent
     *
     * @return array
     */
    public static function setResponseDataRelationship(array &$mappings, array &$array, array $parent, string $attributesCase)
    {
        $data = [JsonApiTransformer::RELATIONSHIPS_KEY => []];

        foreach ($array as $propertyName => $value) {
            if (\is_array($value)) {
                self::addRelationshipData($mappings, $parent, $value, $propertyName, $data);
                if (\array_key_exists(Serializer::MAP_TYPE, $value)) {
                    $newData = [];

                    foreach ($value[Serializer::SCALAR_VALUE] as $d) {
                        self::addRelationshipData($mappings, $parent, $d, $propertyName, $newData);

                        if (!empty($d[Serializer::CLASS_IDENTIFIER_KEY])) {
                            $type = $d[Serializer::CLASS_IDENTIFIER_KEY];

                            if (!in_array($propertyName, RecursiveFormatterHelper::getIdProperties($mappings, $type), true)) {
                                $parentType = $d[Serializer::CLASS_IDENTIFIER_KEY];

                                list($idValues, $idProperties) = RecursiveFormatterHelper::getIdPropertyAndValues(
                                    $mappings,
                                    $d,
                                    $parentType
                                );

                                if (!empty($mappings[$parentType]) && !empty($selfLink = $mappings[$parentType]->getRelationshipSelfUrl($propertyName))) {
                                    $href = \str_replace($idProperties, $idValues, $selfLink);
                                    if ($selfLink != $href) {
                                        $propertyNameKey = DataAttributesHelper::transformToValidMemberName($propertyName);

                                        if ($attributesCase == 'snake_case') {
                                            $propertyNameKey = RecursiveFormatterHelper::camelCaseToUnderscore($propertyNameKey);
                                        }

                                        $newData[JsonApiTransformer::RELATIONSHIPS_KEY][$propertyNameKey][JsonApiTransformer::LINKS_KEY][JsonApiTransformer::SELF_LINK][JsonApiTransformer::LINKS_HREF] = $href;
                                    }
                                }
                            }
                        }

                        if (!empty($newData[JsonApiTransformer::RELATIONSHIPS_KEY][$propertyName])) {
                            $propertyNameKey = DataAttributesHelper::transformToValidMemberName($propertyName);
                            if ($attributesCase == 'snake_case') {
                                $propertyNameKey = RecursiveFormatterHelper::camelCaseToUnderscore($propertyNameKey);
                            }

                            if (!empty($d[Serializer::CLASS_IDENTIFIER_KEY])) {
                                $type = $d[Serializer::CLASS_IDENTIFIER_KEY];
                                $parentType = $parent[Serializer::CLASS_IDENTIFIER_KEY];

                                //Removes relationships related to the current resource if filtering include resources has been set.
                                if (!empty($mappings[$parentType]) && !empty($mappings[$parentType]->isFilteringIncludedResources())) {
                                    foreach ($newData[JsonApiTransformer::RELATIONSHIPS_KEY][$propertyName] as $position => $includedResource) {
                                        if (count($mappings[$parentType]->getIncludedResources()) > 0 &&
                                            false === in_array($type, $mappings[$parentType]->getIncludedResources(), true)
                                        ) {
                                            unset($newData[JsonApiTransformer::RELATIONSHIPS_KEY][$propertyName][$position]);
                                        }
                                    }
                                }

                                $newData[JsonApiTransformer::RELATIONSHIPS_KEY][$propertyName] = array_filter($newData[JsonApiTransformer::RELATIONSHIPS_KEY][$propertyName]);
                                if (!empty($newData[JsonApiTransformer::RELATIONSHIPS_KEY][$propertyName][JsonApiTransformer::DATA_KEY])) {
                                    $data[JsonApiTransformer::RELATIONSHIPS_KEY][$propertyNameKey][JsonApiTransformer::DATA_KEY][] = $newData[JsonApiTransformer::RELATIONSHIPS_KEY][$propertyName][JsonApiTransformer::DATA_KEY];
                                }
                            }
                        }
                    }
                }
            }
        }

        return (array) \array_filter($data);
    }

    /**
     * @param array $mappings
     * @param array $parent
     * @param       $value
     * @param       $propertyName
     * @param       $data
     */
    protected static function addRelationshipData(
        array &$mappings,
        array &$parent,
        &$value,
        &$propertyName,
        &$data
    ) {
        if (\array_key_exists(Serializer::CLASS_IDENTIFIER_KEY, $value)) {
            $propertyName = DataAttributesHelper::transformToValidMemberName($propertyName);
            $type = $value[Serializer::CLASS_IDENTIFIER_KEY];
            self::relationshipLinksSelf($mappings, $parent, $propertyName, $type, $data, $value);
            self::relationshipLinksRelated($propertyName, $mappings, $parent, $data);
        }
    }

    /**
     * @param \NilPortugues\Api\Mapping\Mapping[] $mappings
     * @param array                               $parent
     * @param string                              $propertyName
     * @param string                              $type
     * @param array                               $data
     * @param array                               $value
     */
    protected static function relationshipLinksSelf(
        array &$mappings,
        array &$parent,
        $propertyName,
        $type,
        array &$data,
        array &$value
    ) {
        if (!in_array($propertyName, RecursiveFormatterHelper::getIdProperties($mappings, $type), true)) {
            $data[JsonApiTransformer::RELATIONSHIPS_KEY][$propertyName] = array_merge(
                array_filter(
                    [
                        JsonApiTransformer::LINKS_KEY => self::setResponseDataRelationshipSelfLinks(
                                $propertyName,
                                $mappings,
                                $parent
                            ),
                    ]
                ),
                array_filter(
                    [JsonApiTransformer::DATA_KEY => PropertyHelper::setResponseDataTypeAndId($mappings, $value)]
                )
            );

            if (count($data[JsonApiTransformer::RELATIONSHIPS_KEY][$propertyName]) === 0) {
                unset($data[JsonApiTransformer::RELATIONSHIPS_KEY][$propertyName]);
            }
        }
    }

    /**
     * @param string                              $propertyName
     * @param \NilPortugues\Api\Mapping\Mapping[] $mappings
     * @param array                               $parent
     *
     * @return array
     */
    public static function setResponseDataRelationshipSelfLinks($propertyName, array &$mappings, array &$parent)
    {
        $data = [];
        $parentType = $parent[Serializer::CLASS_IDENTIFIER_KEY];

        if (\is_scalar($parentType) && !empty($mappings[$parentType])) {
            $copy = $parent;
            RecursiveFormatterHelper::formatScalarValues($copy);
            if (!empty($copy[Serializer::CLASS_IDENTIFIER_KEY])) {
                unset($copy[Serializer::CLASS_IDENTIFIER_KEY]);
            }

            $idProperties = array_keys($copy);
            foreach ($idProperties as &$property) {
                $property = sprintf('{%s}', $property);
            }
            $idValues = array_values($copy);

            if (!empty($mappings[$parentType]) && !empty($selfLink = $mappings[$parentType]->getRelationshipSelfUrl($propertyName))) {
                $url = self::buildUrl($mappings, $idProperties, $idValues, $selfLink, $parentType);

                if ($url !== $selfLink) {
                    $data[JsonApiTransformer::SELF_LINK][JsonApiTransformer::LINKS_HREF] = $url;
                }
            }
        }

        return $data;
    }

    /**
     * @param string                              $propertyName
     * @param \NilPortugues\Api\Mapping\Mapping[] $mappings
     * @param array                               $parent
     * @param array                               $data
     */
    protected static function relationshipLinksRelated($propertyName, array &$mappings, array &$parent, array &$data)
    {
        if (!empty($parent[Serializer::CLASS_IDENTIFIER_KEY]) && !empty($data[JsonApiTransformer::RELATIONSHIPS_KEY][$propertyName])) {
            $parentType = $parent[Serializer::CLASS_IDENTIFIER_KEY];

            if (\is_scalar($parentType)) {
                if (!empty($mappings[$parentType]) && !empty($relatedUrl = $mappings[$parentType]->getRelatedUrl($propertyName))) {
                    $copy = $parent;
                    RecursiveFormatterHelper::formatScalarValues($copy);
                    if (!empty($copy[Serializer::CLASS_IDENTIFIER_KEY])) {
                        unset($copy[Serializer::CLASS_IDENTIFIER_KEY]);
                    }

                    $idProperties = array_keys($copy);
                    foreach ($idProperties as &$property) {
                        $property = sprintf('{%s}', $property);
                    }
                    $idValues = array_values($copy);

                    $url = self::buildUrl($mappings, $idProperties, $idValues, $relatedUrl, $parentType);

                    if ($url !== $relatedUrl) {
                        $data[JsonApiTransformer::RELATIONSHIPS_KEY][$propertyName][JsonApiTransformer::LINKS_KEY][JsonApiTransformer::RELATED_LINK][JsonApiTransformer::LINKS_HREF] = $url;
                    }
                }
            }
        }
    }

    /**
     * @param \NilPortugues\Api\Mapping\Mapping[] $mappings
     * @param                                     $idProperties
     * @param                                     $idValues
     * @param                                     $url
     * @param                                     $type
     *
     * @return mixed
     */
    public static function buildUrl(array &$mappings, $idProperties, $idValues, $url, $type)
    {
        if (!is_array($idValues)) {
            $idValues = [$idValues];
        }

        self::removeArraysFromKeyValueReplacement($idProperties, $idValues);

        if (is_array($url) && !empty($url['name'])) {
            $url = $url['name'];
        }

        $outputUrl = \str_replace($idProperties, $idValues, $url);
        if ($outputUrl !== $url) {
            return $outputUrl;
        }

        $outputUrl = self::secondPassBuildUrl($mappings[$type]->getClassAlias(), $idValues, $url);
        if ($outputUrl !== $url) {
            return $outputUrl;
        }

        $className = $mappings[$type]->getClassName();
        $className = \explode('\\', $className);
        $className = \array_pop($className);

        $outputUrl = self::secondPassBuildUrl($className, $idValues, $url);
        if ($outputUrl !== $url) {
            return $outputUrl;
        }

        return $url;
    }

    /**
     * @param $idPropertyName
     * @param $idValues
     * @param $url
     *
     * @return mixed
     */
    protected static function secondPassBuildUrl($idPropertyName, $idValues, $url)
    {
        if (is_array($url) && !empty($url['name'])) {
            $url = $url['name'];
        }

        if (is_array($idValues)) {
            $idValues = array_shift($idValues);
        }

        if (!empty($idPropertyName)) {
            $outputUrl = self::toCamelCase($idPropertyName, $idValues, $url);
            if ($url !== $outputUrl) {
                return $outputUrl;
            }

            $outputUrl = self::toLowerFirstCamelCase($idPropertyName, $idValues, $url);
            if ($url !== $outputUrl) {
                return $outputUrl;
            }

            $outputUrl = self::toUnderScore($idPropertyName, $idValues, $url);
            if ($url !== $outputUrl) {
                return $outputUrl;
            }
        }

        return $url;
    }

    /**
     * @param $original
     * @param $idValues
     * @param $url
     *
     * @return mixed
     */
    protected static function toCamelCase($original, $idValues, $url)
    {
        $className = self::underscoreToCamelCase(self::camelCaseToUnderscore($original));

        return \str_replace('{'.$className.'}', $idValues, $url);
    }

    /**
     * @param $original
     * @param $idValues
     * @param $url
     *
     * @return mixed
     */
    protected static function toLowerFirstCamelCase($original, $idValues, $url)
    {
        $className = self::underscoreToCamelCase(self::camelCaseToUnderscore($original));
        $className[0] = \strtolower($className[0]);

        return \str_replace('{'.$className.'}', $idValues, $url);
    }

    /**
     * @param $original
     * @param $idValues
     * @param $url
     *
     * @return mixed
     */
    protected static function toUnderScore($original, $idValues, $url)
    {
        $className = self::camelCaseToUnderscore($original);

        return \str_replace('{'.$className.'}', $idValues, $url);
    }

    /**
     * Transforms a given string from camelCase to under_score style.
     *
     * @param string $camel
     * @param string $splitter
     *
     * @return string
     */
    public static function camelCaseToUnderscore($camel, $splitter = '_')
    {
        $camel = \preg_replace(
            '/(?!^)[[:upper:]][[:lower:]]/',
            '$0',
            \preg_replace('/(?!^)[[:upper:]]+/', $splitter.'$0', $camel)
        );

        return \strtolower($camel);
    }

    /**
     * Converts a underscore string to camelCase.
     *
     * @param string $string
     *
     * @return string
     */
    protected static function underscoreToCamelCase($string)
    {
        return \str_replace(' ', '', \ucwords(\strtolower(\str_replace(['_', '-'], ' ', $string))));
    }

    /**
     * @param array $idProperties
     * @param array $idValues
     */
    protected static function removeArraysFromKeyValueReplacement(array &$idProperties, array &$idValues)
    {
        RecursiveDeleteHelper::deleteKeys($idValues, [Serializer::CLASS_IDENTIFIER_KEY]);
        RecursiveFormatterHelper::flattenObjectsWithSingleKeyScalars($idValues);

        if (is_array($idValues)) {
            foreach ($idValues as $key => $value) {
                if (is_array($value)) {
                    unset($idProperties[$key]);
                    unset($idValues[$key]);
                }
            }
        }
    }
}
