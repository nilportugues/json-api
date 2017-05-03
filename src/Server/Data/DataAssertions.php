<?php
/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 11/27/15
 * Time: 11:40 PM.
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
use NilPortugues\Api\JsonApi\Server\Errors\MissingAttributesError;
use NilPortugues\Api\JsonApi\Server\Errors\MissingDataError;
use NilPortugues\Api\JsonApi\Server\Errors\MissingTypeError;

/**
 * Class DataAssertions.
 */
class DataAssertions
{
    /**
     * @param array             $data
     * @param Serializer $serializer
     * @param string            $className
     * @param ErrorBag          $errorBag
     */
    public static function assert($data, Serializer $serializer, $className, ErrorBag $errorBag)
    {
        self::assertItIsArray($data, $errorBag);
        self::assertItHasTypeMember($data, $errorBag);
        self::assertItTypeMemberIsExpectedValue($data, $serializer, $className, $errorBag);
        self::assertItHasAttributeMember($data, $errorBag);
        self::assertAttributesExists($data, $serializer, $errorBag);
    }

    /**
     * @param          $data
     * @param ErrorBag $errorBag
     *
     * @throws DataException
     */
    protected static function assertItIsArray($data, ErrorBag $errorBag)
    {
        if (empty($data) || !is_array($data)) {
            $errorBag->offsetSet(null, new MissingDataError());
            throw new DataException();
        }
    }

    /**
     * @param array    $data
     * @param ErrorBag $errorBag
     *
     * @throws DataException
     */
    protected static function assertItHasTypeMember(array $data, ErrorBag $errorBag)
    {
        if (empty($data[JsonApiTransformer::TYPE_KEY]) || !is_string($data[JsonApiTransformer::TYPE_KEY])) {
            $errorBag->offsetSet(null, new MissingTypeError());
            throw new DataException();
        }
    }

    /**
     * @param array             $data
     * @param Serializer $serializer
     * @param                   $className
     * @param ErrorBag          $errorBag
     *
     * @throws DataException
     */
    protected static function assertItTypeMemberIsExpectedValue(
        array $data,
        Serializer $serializer,
        $className,
        ErrorBag $errorBag
    ) {
        $mapping = $serializer->getTransformer()->getMappingByAlias($data[JsonApiTransformer::TYPE_KEY]);

        if (null === $mapping || $mapping->getClassName() !== $className) {
            $errorBag->offsetSet(null, new InvalidTypeError($data[JsonApiTransformer::TYPE_KEY]));
            throw new DataException();
        }
    }

    /**
     * @param          $data
     * @param ErrorBag $errorBag
     *
     * @throws DataException
     */
    protected static function assertItHasAttributeMember($data, ErrorBag $errorBag)
    {
        if (empty($data[JsonApiTransformer::ATTRIBUTES_KEY]) || !is_array($data[JsonApiTransformer::ATTRIBUTES_KEY])) {
            $errorBag->offsetSet(null, new MissingAttributesError());
            throw new DataException();
        }
    }

    /**
     * @param array             $data
     * @param Serializer $serializer
     * @param ErrorBag          $errorBag
     *
     * @throws DataException
     */
    protected static function assertAttributesExists(array $data, Serializer $serializer, ErrorBag $errorBag)
    {
        $inputAttributes = array_keys($data[JsonApiTransformer::ATTRIBUTES_KEY]);

        $mapping = $serializer->getTransformer()->getMappingByAlias($data[JsonApiTransformer::TYPE_KEY]);

        $properties = str_replace(
            array_keys($mapping->getAliasedProperties()),
            array_values($mapping->getAliasedProperties()),
            $mapping->getProperties()
        );
        $properties = array_diff($properties, $mapping->getIdProperties());
        $properties = array_merge($properties, $mapping->getHiddenProperties());

        $hasErrors = false;
        foreach ($inputAttributes as $property) {
            if (false === in_array($property, $properties)) {
                $hasErrors = true;
                $errorBag->offsetSet(null, new InvalidAttributeError($property, $data[JsonApiTransformer::TYPE_KEY]));
            }
        }

        if ($hasErrors) {
            throw new DataException();
        }
    }
}
