<?php

namespace Experteam\ApiCrudBundle\Controller;

use Experteam\ApiBaseBundle\Schemas\ErrorResponse;
use Experteam\ApiBaseBundle\Schemas\FailResponse;
use Experteam\ApiBaseBundle\Schemas\SuccessResponse;
use Experteam\ApiRedisBundle\Service\RedisTransport\RedisTransportInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="Redis data")
 * @Rest\Route(path="/redis-data")
 */
class RedisDataController extends BaseController
{
    /**
     * Creates a Redis data resource.
     *
     * @Security(name="Bearer")
     *
     * @Rest\Post()
     *
     * @Rest\View()
     * @OA\Response(response=200, @Model(type=SuccessResponse::class), description="Client success response.")
     * @OA\Response(response=400, @Model(type=FailResponse::class), description="Client error response.")
     * @OA\Response(response=500, @Model(type=ErrorResponse::class), description="Server error response.")
     *
     * @param RedisTransportInterface $redisTransport
     * @return array
     */
    public function new(RedisTransportInterface $redisTransport): array
    {
        $redisTransport->restoreData();
        return [];
    }
}