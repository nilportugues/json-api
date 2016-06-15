<?php
/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 11/28/15
 * Time: 12:12 PM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace NilPortugues\Api\JsonApi\Server\Query;

use NilPortugues\Api\JsonApi\Http\Request\Parameters\Fields;
use NilPortugues\Api\JsonApi\Http\Request\Parameters\Included;
use NilPortugues\Api\JsonApi\Http\Request\Parameters\Sorting;
use NilPortugues\Serializer\Serializer;
use NilPortugues\Api\JsonApi\Server\Errors\ErrorBag;
use NilPortugues\Api\JsonApi\Server\Errors\InvalidParameterError;
use NilPortugues\Api\JsonApi\Server\Errors\InvalidParameterMemberError;
use NilPortugues\Api\JsonApi\Server\Errors\InvalidSortError;

/**
 * Class QueryObject.
 */
class QueryObject
{
    /**
     * @param Serializer $serializer
     * @param Fields            $fields
     * @param Included          $included
     * @param Sorting           $sort
     * @param ErrorBag          $errorBag
     * @param string            $className
     *
     * @throws QueryException
     */
    public static function assert(
        Serializer $serializer,
        Fields $fields,
        Included $included,
        Sorting $sort,
        ErrorBag $errorBag,
        $className
    ) {
        self::validateQueryParamsTypes($serializer, $fields, 'Fields', $errorBag);
        self::validateIncludeParams($serializer, $included, 'include', $errorBag);

        if (!empty($className) && false === $sort->isEmpty()) {
            self::validateSortParams($serializer, $className, $sort, $errorBag);
        }

        if ($errorBag->count() > 0) {
            throw new QueryException();
        }
    }

    /**
     * @param Serializer $serializer
     * @param Fields            $fields
     * @param                   $paramName
     * @param ErrorBag          $errorBag
     */
    protected static function validateQueryParamsTypes(
        Serializer $serializer,
        Fields $fields,
        $paramName,
        ErrorBag $errorBag
    ) {
        if (false === $fields->isEmpty()) {
            $transformer = $serializer->getTransformer();
            $validateFields = $fields->types();

            foreach ($validateFields as $key => $type) {
                $mapping = $transformer->getMappingByAlias($type);
                if (null !== $mapping) {
                    $members = array_merge(
                        array_combine($mapping->getProperties(), $mapping->getProperties()),
                        $mapping->getAliasedProperties()
                    );

                    $invalidMembers = array_diff($fields->members($type), $members);
                    foreach ($invalidMembers as $extraField) {
                        $errorBag->offsetSet(null, new InvalidParameterMemberError($extraField, $type, strtolower($paramName)));
                    }
                    unset($validateFields[$key]);
                }
            }

            if (false === empty($validateFields)) {
                foreach ($validateFields as $type) {
                    $errorBag->offsetSet(null, new InvalidParameterError($type, strtolower($paramName)));
                }
            }
        }
    }

    /**
     * @param Serializer $serializer
     * @param Included          $included
     * @param string            $paramName
     * @param ErrorBag          $errorBag
     */
    protected static function validateIncludeParams(
        Serializer $serializer,
        Included $included,
        $paramName,
        ErrorBag $errorBag
    ) {
        $transformer = $serializer->getTransformer();

        foreach ($included->get() as $resource => $data) {
            if (null === $transformer->getMappingByAlias($resource)) {
                $errorBag->offsetSet(null, new InvalidParameterError($resource, strtolower($paramName)));
                continue;
            }

            if (is_array($data)) {
                foreach ($data as $subResource) {
                    if (null === $transformer->getMappingByAlias($subResource)) {
                        $errorBag->offsetSet(null, new InvalidParameterError($subResource, strtolower($paramName)));
                    }
                }
            }
        }
    }

    /**
     * @param Serializer $serializer
     * @param string            $className
     * @param Sorting           $sorting
     * @param ErrorBag          $errorBag
     */
    protected static function validateSortParams(
        Serializer $serializer,
        $className,
        Sorting $sorting,
        ErrorBag $errorBag
    ) {
        if (false === $sorting->isEmpty()) {
            if ($mapping = $serializer->getTransformer()->getMappingByClassName($className)) {
                $aliased = (array) $mapping->getAliasedProperties();
                $sortsFields = str_replace(array_values($aliased), array_keys($aliased), $sorting->fields());

                $invalidProperties = array_diff($sortsFields, $mapping->getProperties());

                foreach ($invalidProperties as $extraField) {
                    $errorBag->offsetSet(null, new InvalidSortError($extraField));
                }
            }
        }
    }
}
