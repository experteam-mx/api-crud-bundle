<?php

namespace Experteam\ApiCrudBundle\Controller;

use Experteam\ApiBaseBundle\Schemas\ErrorResponse;
use Experteam\ApiBaseBundle\Schemas\FailResponse;
use Experteam\ApiBaseBundle\Schemas\SuccessResponse;
use Experteam\ApiCrudBundle\Schemas\MessageInput;
use Experteam\ApiRedisBundle\Service\RedisTransport\RedisTransportInterface;
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

        $redisTransport->restoreMessages($messageInput->dateTime, $messageInput->entities);
        return [];
    }
}