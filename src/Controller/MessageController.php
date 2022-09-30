<?php

namespace Experteam\ApiCrudBundle\Controller;

use Experteam\ApiBaseBundle\Schemas\ErrorResponse;
use Experteam\ApiBaseBundle\Schemas\FailResponse;
use Experteam\ApiBaseBundle\Schemas\SuccessResponse;
use Experteam\ApiCrudBundle\Schemas\MessageInput;
use Experteam\ApiRedisBundle\Service\RedisTransport\RedisTransportInterface;
use Experteam\ApiRedisBundle\Service\RedisTransportV2\RedisTransportV2Interface;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Request;

/**
 * @OA\Tag(name="Message")
 */
class MessageController extends BaseController
{
    /**
     * Creates a Message resource.
     *
     * @Security(name="Bearer")
     *
     * @OA\RequestBody(@Model(type=MessageInput::class), description="The new Message resource.")
     *
     * @Rest\View()
     * @OA\Response(response=200, @Model(type=SuccessResponse::class), description="Success response.")
     * @OA\Response(response=400, @Model(type=FailResponse::class), description="Client error response.")
     * @OA\Response(response=500, @Model(type=ErrorResponse::class), description="Server error response.")
     *
     * @param Request $request
     * @param RedisTransportInterface $redisTransport
     * @return array
     */
    public function new(Request $request, RedisTransportInterface $redisTransport): array
    {
        /** @var MessageInput $messageInput */
        $messageInput = $this->requestUtil->validate($request->getContent(), MessageInput::class);

        if (count($messageInput->entities) > 0) {
            $namespace = "App\\Entity\\";

            foreach ($messageInput->entities as &$entity) {
                if (strpos($entity, $namespace) === false) {
                    $entity = $namespace . $entity;
                }
            }
        }

        $redisTransport->restoreMessages($messageInput->dateTimeFrom, $messageInput->dateTimeTo, $messageInput->entities, $messageInput->entityIds);
        return [];
    }

    /**
     * Creates a Message resource.
     *
     * @Security(name="Bearer")
     *
     * @OA\RequestBody(@Model(type=MessageInput::class), description="The new Message resource.")
     *
     * @Rest\View()
     * @OA\Response(response=200, @Model(type=SuccessResponse::class), description="Success response.")
     * @OA\Response(response=400, @Model(type=FailResponse::class), description="Client error response.")
     * @OA\Response(response=500, @Model(type=ErrorResponse::class), description="Server error response.")
     *
     * @param Request $request
     * @param RedisTransportV2Interface $redisTransport
     * @return array
     */
    public function newV2(Request $request, RedisTransportV2Interface $redisTransport): array
    {
        /** @var MessageInput $messageInput */
        $messageInput = $this->requestUtil->validate($request->getContent(), MessageInput::class);

        if (count($messageInput->entities) > 0) {
            $namespace = "App\\Entity\\";

            foreach ($messageInput->entities as &$entity) {
                if (strpos($entity, $namespace) === false) {
                    $entity = $namespace . $entity;
                }
            }
        }

        $redisTransport->restoreMessages($messageInput->dateTimeFrom, $messageInput->dateTimeTo, $messageInput->entities, $messageInput->entityIds);

        if ($messageInput->streamCompute)
            $redisTransport->restoreStreamCompute($messageInput->dateTimeFrom, $messageInput->dateTimeTo, $messageInput->entities, $messageInput->entityIds);

        return [];
    }
}