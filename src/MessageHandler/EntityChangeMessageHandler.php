<?php

namespace Experteam\ApiCrudBundle\MessageHandler;

use Experteam\ApiCrudBundle\Message\EntityChangeMessage;
use Experteam\ApiCrudBundle\Service\ModelLogger\ModelLoggerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class EntityChangeMessageHandler implements MessageHandlerInterface
{
    /**
     * @var ModelLoggerInterface
     */
    private $modelLogger;

    public function __construct(ModelLoggerInterface $modelLogger)
    {
        $this->modelLogger = $modelLogger;
    }

    public function __invoke(EntityChangeMessage $message)
    {
        [
            'current' => $current,
            'changes' => $changes,
            'class_name' => $className,
        ] = $message->getData();

        $this->modelLogger
            ->entityChanges(
                $current,
                $changes,
                $className
            );
    }
}
