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
use NilPortugues\Serializer\Serializer;
use NilPortugues\Api\JsonApi\Server\Actions\Traits\ResponseTrait;
use NilPortugues\Api\JsonApi\Server\Data\DataException;
use NilPortugues\Api\JsonApi\Server\Data\DataObject;
use NilPortugues\Api\JsonApi\Server\Errors\Error;
use NilPortugues\Api\JsonApi\Server\Errors\ErrorBag;

/**
 * Class CreateResource.
 */
class CreateResource
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
     * @param array    $data
     * @param          $className
     * @param callable $callable
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function get(array $data, $className, callable $callable)
    {
        try {
            DataObject::assertPost($data, $this->serializer, $className, $this->errorBag);

            $values = DataObject::getAttributes($data, $this->serializer);
            $model = $callable($data, $values, $this->errorBag);

            $response = $this->resourceCreated($this->serializer->serialize($model));
        } catch (Exception $e) {
            $response = $this->getErrorResponse($e, $this->errorBag);
        }

        return $response;
    }

    /**
     * @param Exception $e
     * @param ErrorBag  $errorBag
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function getErrorResponse(Exception $e, ErrorBag $errorBag)
    {
        switch (get_class($e)) {
            case DataException::class:
                $response = $this->unprocessableEntity($errorBag);
                break;

            default:
                $response = $this->errorResponse(
                    new ErrorBag([new Error('Bad Request', 'Request could not be served.')])
                );
        }

        return $response;
    }
}
