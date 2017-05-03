<?php
/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 11/27/15
 * Time: 9:58 PM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NilPortugues\Api\JsonApi\Server\Data;

use NilPortugues\Serializer\Serializer;
use NilPortugues\Api\JsonApi\JsonApiTransformer;
use NilPortugues\Api\JsonApi\Server\Errors\ErrorBag;
use NilPortugues\Api\JsonApi\Server\Errors\InvalidAttributeError;
use NilPortugues\Api\JsonApi\Server\Errors\InvalidTypeError;
use NilPortugues\Api\JsonApi\Server\Errors\MissingAttributeError;
use NilPortugues\Api\JsonApi\Server\Errors\MissingDataError;
use NilPortugues\Api\JsonApi\Server\Errors\MissingTypeError;

/**
 * Class DataObject.
 */
class DataObject
{
    /**
     * @param array             $data
     * @param Serializer $serializer
     * @param string            $className
     * @param ErrorBag          $errorBag
     */
    public static function assertPatch($data, Serializer $serializer, $className, ErrorBag $errorBag)
    {
        DataAssertions::assert($data, $serializer, $className, $errorBag);
    }

    /**
     * @param array             $data
     * @param Serializer $serializer
     * @param string            $className
     * @param ErrorBag          $errorBag
     *
     * @throws DataException
     */
    public static function assertPost($data, Serializer $serializer, $className, ErrorBag $errorBag)
    {
        try {
            DataAssertions::assert($data, $serializer, $className, $errorBag);
            self::assertRelationshipData($data, $serializer, $errorBag);
        } catch (DataException $e) {
        }

        $missing = self::missingCreationAttributes($data, $serializer);
        if (false === empty($missing)) {
            foreach ($missing as $attribute) {
                $errorBag->offsetSet(null, new MissingAttributeError($attribute));
            }
        }

        if ($errorBag->count() > 0) {
            throw new DataException('An error with the provided data occured.', $errorBag);
        }
    }

    /**
     * @param array             $data
     * @param Serializer $serializer
     * @param string            $className
     * @param ErrorBag          $errorBag
     *
     * @throws DataException
     */
    public static function assertPut($data, Serializer $serializer, $className, ErrorBag $errorBag)
    {
        self::assertPost($data, $serializer, $className, $errorBag);
    }

    /**
     * @param array             $data
     * @param Serializer $serializer
     *
     * @return array
     */
    protected static function missingCreationAttributes(array $data, Serializer $serializer)
    {
        $inputAttributes = array_keys($data[ApiTransformer::ATTRIBUTES_KEY]);

        $mapping = $serializer->getTransformer()->getMappingByAlias($data[JsonApiTransformer::TYPE_KEY]);

        $diff = [];
        if (null !== $mapping) {
            $required = $mapping->getRequiredProperties();
            $properties = str_replace(
                array_keys($mapping->getAliasedProperties()),
                array_values($mapping->getAliasedProperties()),
                !empty($required) ? $required : $mapping->getProperties()
            );
            $properties = array_diff($properties, $mapping->getIdProperties());

            $diff = (array) array_diff($properties, $inputAttributes);
        }

        return $diff;
    }

    /**
     * @param array             $data
     * @param Serializer $serializer
     *
     * @return array
     */
    public static function getAttributes(array $data, Serializer $serializer)
    {
        $mapping = $serializer->getTransformer()->getMappingByAlias($data[JsonApiTransformer::TYPE_KEY]);
        $aliases = $mapping->getAliasedProperties();
        $keys = str_replace(
            array_values($aliases),
            array_keys($aliases),
            array_keys($data[JsonApiTransformer::ATTRIBUTES_KEY])
        );

        return array_combine($keys, array_values($data[JsonApiTransformer::ATTRIBUTES_KEY]));
    }

    /**
     * @param array             $data
     * @param Serializer $serializer
     * @param ErrorBag          $errorBag
     *
     * @throws DataException
     */
    protected static function assertRelationshipData(array $data, Serializer $serializer, ErrorBag $errorBag)
    {
        if (!empty($data[JsonApiTransformer::RELATIONSHIPS_KEY])) {
            foreach ($data[JsonApiTransformer::RELATIONSHIPS_KEY] as $relationshipData) {
                if (empty($relationshipData[JsonApiTransformer::DATA_KEY]) || !is_array(
                        $relationshipData[JsonApiTransformer::DATA_KEY]
                    )
                ) {
                    $errorBag->offsetSet(null, new MissingDataError());
                    break;
                }

                $firstKey = key($relationshipData[JsonApiTransformer::DATA_KEY]);
                if (is_numeric($firstKey)) {
                    foreach ($relationshipData[JsonApiTransformer::DATA_KEY] as $inArrayRelationshipData) {
                        self::relationshipDataAssert($inArrayRelationshipData, $serializer, $errorBag);
                    }
                    break;
                }

                self::relationshipDataAssert($relationshipData[JsonApiTransformer::DATA_KEY], $serializer, $errorBag);
            }
        }
    }

    /**
     * @param array             $relationshipData
     * @param Serializer $serializer
     * @param ErrorBag          $errorBag
     */
    protected static function relationshipDataAssert($relationshipData, Serializer $serializer, ErrorBag $errorBag)
    {
        //Has type member.
        if (empty($relationshipData[JsonApiTransformer::TYPE_KEY]) || !is_string(
                $relationshipData[JsonApiTransformer::TYPE_KEY]
            )
        ) {
            $errorBag->offsetSet(null, new MissingTypeError());

            return;
        }

        //Provided type value is supported.
        if (null === $serializer->getTransformer()->getMappingByAlias(
                $relationshipData[JsonApiTransformer::TYPE_KEY]
            )
        ) {
            $errorBag->offsetSet(null, new InvalidTypeError($relationshipData[JsonApiTransformer::TYPE_KEY]));

            return;
        }

        //Validate if attributes passed in make sense.
        if (!empty($relationshipData[JsonApiTransformer::ATTRIBUTES_KEY])) {
            $mapping = $serializer->getTransformer()->getMappingByAlias(
                $relationshipData[JsonApiTransformer::TYPE_KEY]
            );

            $properties = str_replace(
                array_keys($mapping->getAliasedProperties()),
                array_values($mapping->getAliasedProperties()),
                $mapping->getProperties()
            );

            foreach (array_keys($relationshipData[JsonApiTransformer::ATTRIBUTES_KEY]) as $property) {
                if (false === in_array($property, $properties, true)) {
                    $errorBag->offsetSet(null, new InvalidAttributeError($property, $relationshipData[JsonApiTransformer::TYPE_KEY]));
                }
            }
        }
    }
}
