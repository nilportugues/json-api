<?php

/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 7/25/15
 * Time: 5:05 PM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NilPortugues\Api\JsonApi\Helpers;

use NilPortugues\Api\JsonApi\JsonApiTransformer;
use NilPortugues\Api\Transformer\Helpers\RecursiveFormatterHelper;
use NilPortugues\Serializer\Serializer;

/**
 * Class DataAttributesHelper.
 */
class DataAttributesHelper
{
    /**
     * @link http://jsonapi.org/format/#document-member-names-reserved-characters
     *
     * @var array
     */
    protected static $forbiddenMemberNameCharacters = [
        '+',
        ',',
        '.',
        '[',
        ']',
        '!',
        '"',
        '#',
        '$',
        '%',
        '&',
        '\'',
        '(',
        ')',
        '*',
        '/',
        ':',
        ';',
        '<',
        '=',
        '>',
        '?',
        '@',
        '\\',
        '^',
        '`',
        '{',
        '|',
        '}',
        '~',
    ];

    /**
     * @link http://jsonapi.org/format/#document-member-names-allowed-characters
     *
     * @var array
     */
    protected static $forbiddenAsFirstOrLastCharacter = [
        '-',
        '_',
        ' ',
    ];

    /**
     * @param \NilPortugues\Api\Mapping\Mapping[] $mappings
     * @param array                               $array
     *
     * @return array
     */
    public static function setResponseDataAttributes(array &$mappings, array &$array)
    {
        $attributes = [];
        $type = $array[Serializer::CLASS_IDENTIFIER_KEY];
        $idProperties = RecursiveFormatterHelper::getIdProperties($mappings, $type);

        foreach ($array as $propertyName => $value) {
            $keyName = self::transformToValidMemberName(RecursiveFormatterHelper::camelCaseToUnderscore($propertyName));

            if (\in_array($propertyName, $idProperties, true)) {
                self::addIdPropertiesInAttribute($mappings, $type, $keyName, $value, $attributes);
                continue;
            }

            if (!empty($value[Serializer::CLASS_IDENTIFIER_KEY])
                && empty($mappings[$value[Serializer::CLASS_IDENTIFIER_KEY]])
            ) {
                $copy = $value;

                if ($attributesCase == 'snake_case') {
                    self::recursiveSetKeysToUnderScore($copy);
                }
                
                $attributes[$keyName] = $copy;
                continue;
            }

            if (self::isScalarValue($value) && empty($mappings[$value[Serializer::SCALAR_TYPE]])) {
                $attributes[$keyName] = $value;
                continue;
            }

            if (\is_array($value) && !array_key_exists(Serializer::CLASS_IDENTIFIER_KEY, $value)) {
                if (false === self::hasClassIdentifierKey($value)) {
                    $attributes[$keyName] = $value;
                }
            }
        }

        //Guarantee it always returns the same attribute order. No matter what.
        ksort($attributes, SORT_STRING);

        return [JsonApiTransformer::ATTRIBUTES_KEY => $attributes];
    }

    /**
     * @param string $attributeName
     *
     * @return string
     */
    public static function transformToValidMemberName($attributeName)
    {
        $attributeName = \str_replace(self::$forbiddenMemberNameCharacters, '', $attributeName);

        $attributeName = \ltrim($attributeName, \implode('', self::$forbiddenAsFirstOrLastCharacter));
        $attributeName = \rtrim($attributeName, \implode('', self::$forbiddenAsFirstOrLastCharacter));

        return $attributeName;
    }

    /**
     * @param \NilPortugues\Api\Mapping\Mapping[] $mappings
     * @param string                              $type
     * @param string                              $keyName
     * @param array                               $value
     * @param array                               $attributes
     */
    protected static function addIdPropertiesInAttribute(array &$mappings, $type, $keyName, array $value, array &$attributes)
    {
        $keepKeys = str_replace(
            array_values($mappings[$type]->getAliasedProperties()),
            array_keys($mappings[$type]->getAliasedProperties()),
            $mappings[$type]->getFilterKeys()
        );

        $keepIdKeys = (0 === count($keepKeys));
        if (false !== array_search($keyName, $keepKeys, true)) {
            $keepIdKeys = false;
        }

        if ($keepIdKeys) {
            $ids = PropertyHelper::getIdValues($mappings, $value, $type);

            if (count($ids) > 0) {
                if (1 === count($ids)) {
                    $ids = array_pop($ids);
                }
                $attributes[$keyName] = $ids;
            } else {
                RecursiveFormatterHelper::formatScalarValues($value);
                $attributes[$keyName] = $value;
            }
        }
    }

    /**
     * Changes all array keys to under_score format using recursion.
     *
     * @param array $array
     */
    protected static function recursiveSetKeysToUnderScore(array &$array)
    {
        $newArray = [];
        foreach ($array as $key => &$value) {
            $underscoreKey = RecursiveFormatterHelper::camelCaseToUnderscore($key);
            $newArray[$underscoreKey] = $value;

            if (\is_array($value)) {
                self::recursiveSetKeysToUnderScore($newArray[$underscoreKey]);
            }
        }
        $array = $newArray;
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    protected static function isScalarValue($value)
    {
        return \is_array($value)
        && \array_key_exists(Serializer::SCALAR_TYPE, $value)
        && \array_key_exists(Serializer::SCALAR_VALUE, $value);
    }

    /**
     * @param array $input
     *
     * @return bool
     */
    protected static function hasClassIdentifierKey(array $input)
    {
        if (!empty($input[Serializer::CLASS_IDENTIFIER_KEY])) {
            return true;
        }

        $foundIdentifierKey = false;
        if (!empty($input[Serializer::SCALAR_VALUE]) && !empty($input[Serializer::MAP_TYPE])) {
            $input = $input[Serializer::SCALAR_VALUE];
            if (\is_array($input)) {
                foreach ($input as $value) {
                    if (\is_array($value)) {
                        $foundIdentifierKey = $foundIdentifierKey || self::hasClassIdentifierKey($value);
                    }
                }
            }
        }

        return $foundIdentifierKey;
    }
}
