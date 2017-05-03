<?php
/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 12/2/15
 * Time: 9:38 PM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NilPortugues\Api\JsonApi\Server\Actions;

use Exception;
use NilPortugues\Serializer\Serializer;
use NilPortugues\Api\JsonApi\Server\Actions\Traits\ResponseTrait;
use NilPortugues\Api\JsonApi\Server\Data\DataException;
use NilPortugues\Api\JsonApi\Server\Data\DataObject;
use NilPortugues\Api\JsonApi\Server\Errors\Error;
use NilPortugues\Api\JsonApi\Server\Errors\ErrorBag;
use NilPortugues\Api\JsonApi\Server\Errors\NotFoundError;
use NilPortugues\Api\JsonApi\Server\Actions\Exceptions\ForbiddenException;

/**
 * Class PatchResource.
 */
class PatchResource
{
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
     * @param Serializer $serializer
     */
    public function __construct(Serializer $serializer)
    {
        $this->serializer = $serializer;
        $this->errorBag = new ErrorBag();
    }

    /**
     * @param string   $id
     * @param array    $data
     * @param string   $className
     * @param callable $findOneCallable
     * @param callable $update
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function get($id, array $data, $className, callable $findOneCallable, callable $update)
    {
        try {
            DataObject::assertPatch($data, $this->serializer, $className, $this->errorBag);
            $model = $findOneCallable();

            if (empty($model)) {
                $mapping = $this->serializer->getTransformer()->getMappingByClassName($className);

                return $this->resourceNotFound(new ErrorBag([new NotFoundError($mapping->getClassAlias(), $id)]));
            }

            $values = DataObject::getAttributes($data, $this->serializer);
            $update($model, $data, $values, $this->errorBag);

            $response = $this->resourceUpdated($this->serializer->serialize($model));
        } catch (Exception $e) {
            $response = $this->getErrorResponse($e);
        }

        return $response;
    }

    /**
     * @param $e
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function getErrorResponse(Exception $e)
    {
        switch (get_class($e)) {
            case ForbiddenException::class:
                $response = $this->forbidden($this->errorBag);
                break;
            case DataException::class:
                $response = $this->unprocessableEntity($this->errorBag);
                break;

            default:
                $response = $this->errorResponse(
                    new ErrorBag([new Error('Bad Request', 'Request could not be served.')])
                );
        }

        return $response;
    }
}
