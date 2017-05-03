<?php
/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 12/2/15
 * Time: 9:37 PM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NilPortugues\Api\JsonApi\Server\Actions;

use Exception;
use NilPortugues\Api\JsonApi\Http\Request\Parameters\Fields;
use NilPortugues\Api\JsonApi\Http\Request\Parameters\Included;
use NilPortugues\Api\JsonApi\Http\Request\Parameters\Sorting;
use NilPortugues\Serializer\Serializer;
use NilPortugues\Api\JsonApi\Server\Actions\Traits\RequestTrait;
use NilPortugues\Api\JsonApi\Server\Actions\Traits\ResponseTrait;
use NilPortugues\Api\JsonApi\Server\Errors\Error;
use NilPortugues\Api\JsonApi\Server\Errors\ErrorBag;
use NilPortugues\Api\JsonApi\Server\Errors\NotFoundError;
use NilPortugues\Api\JsonApi\Server\Query\QueryException;
use NilPortugues\Api\JsonApi\Server\Query\QueryObject;
use NilPortugues\Api\JsonApi\Server\Actions\Exceptions\ForbiddenException;

/**
 * Class GetResource.
 */
class GetResource
{
    use RequestTrait;
    use ResponseTrait;

    /**
     * @var \NilPortugues\Api\JsonApi\Server\Errors\ErrorBag
     */
    protected $errorBag;

    /**
     * @var Serializer
     */
    protected $serializer;

    /**
     * @var Fields
     */
    protected $fields;

    /**
     * @var Included
     */
    protected $included;

    /**
     * @param Serializer $serializer
     * @param Fields            $fields
     * @param Included          $included
     */
    public function __construct(
        Serializer $serializer,
        Fields $fields,
        Included $included
    ) {
        $this->serializer = $serializer;
        $this->errorBag = new ErrorBag();
        $this->fields = $fields;
        $this->included = $included;
    }

    /**
     * @param string|int $id
     * @param string     $className
     * @param callable   $callable
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function get($id, $className, callable $callable)
    {
        try {
            QueryObject::assert($this->serializer, $this->fields, $this->included, new Sorting(), $this->errorBag, $className);
            $data = $callable();

            if (empty($data)) {
                $mapping = $this->serializer->getTransformer()->getMappingByClassName($className);

                return $this->resourceNotFound(new ErrorBag([new NotFoundError($mapping->getClassAlias(), $id)]));
            }

            $response = $this->response($this->serializer->serialize($data, $this->fields, $this->included));
        } catch (Exception $e) {
            $response = $this->getErrorResponse($e);
        }

        return $response;
    }

    /**
     * @param Exception $e
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function getErrorResponse(Exception $e)
    {
        switch (get_class($e)) {
            case ForbiddenException::class:
                $response = $this->forbidden($this->errorBag);
                break;
            case QueryException::class:
                $response = $this->errorResponse($this->errorBag);
                break;

            default:
                $response = $this->errorResponse(
                    new ErrorBag([new Error('Bad Request', 'Request could not be served.')])
                );
        }

        return $response;
    }
}
