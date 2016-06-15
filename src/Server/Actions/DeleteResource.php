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
use NilPortugues\Api\JsonApi\Server\Errors\Error;
use NilPortugues\Api\JsonApi\Server\Errors\ErrorBag;
use NilPortugues\Api\JsonApi\Server\Errors\NotFoundError;

/**
 * Class DeleteResource.
 */
class DeleteResource
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
     * @param          $id
     * @param          $className
     * @param callable $findOneCallable
     * @param callable $deleteCallable
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function get($id, $className, callable $findOneCallable, callable $deleteCallable)
    {
        try {
            $data = $findOneCallable();
            if (empty($data)) {
                $mapping = $this->serializer->getTransformer()->getMappingByClassName($className);

                return $this->resourceNotFound(new ErrorBag([new NotFoundError($mapping->getClassAlias(), $id)]));
            }

            $deleteCallable();

            return $this->resourceDeleted();
        } catch (Exception $e) {
            return $this->errorResponse(new ErrorBag([new Error('Bad Request', 'Request could not be served.')]));
        }
    }
}
