<?php

namespace Experteam\ApiCrudBundle\MessageHandler;

use Experteam\ApiCrudBundle\Message\EntityChangeMessage;
use Experteam\ApiCrudBundle\Service\ModelLogger\ModelLoggerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class EntityChangeMessageHandler implements MessageHandlerInterface
{
    private $modelLogger;

    public function __construct(
        ModelLoggerInterface $modelLogger
    ) {
        $this->modelLogger = $modelLogger;
    }

    public function __invoke(EntityChangeMessage $message)
    {
        [
            'changes' => $changes,
            'current' => $current,
            'class_name' => $className,
            'user' => $user,
        ] = $message->getData();

        $this->modelLogger
            ->logChanges(
                json_decode($current, true),
                $changes,
                $className,
                $user
            );
    }
}
