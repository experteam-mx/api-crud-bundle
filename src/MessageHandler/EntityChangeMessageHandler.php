<?php

namespace Experteam\ApiCrudBundle\MessageHandler;

use Experteam\ApiCrudBundle\Message\EntityChangeMessage;
use Experteam\ApiCrudBundle\Service\ModelLogger\ModelLoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class EntityChangeMessageHandler implements MessageHandlerInterface
{
    /**
     * @var ModelLoggerInterface
     */
    private $modelLogger;

    /**
     * @var ParameterBagInterface
     */
    protected $parameterBag;

    public function __construct(
        ModelLoggerInterface $modelLogger,
        ParameterBagInterface $parameterBag
    ) {
        $this->modelLogger = $modelLogger;
        $this->parameterBag = $parameterBag;
    }

    public function __invoke(EntityChangeMessage $message)
    {
        [
            'current' => $current,
            'changes' => $changes,
            'class_name' => $className,
            'fqn' => $fqn,
        ] = $message->getData();

        $allowedEntities = $this->parameterBag
            ->get('experteam_api_crud.logged_entities');

        if (empty($allowedEntities)) {
            return;
        }

        $coincidences = array_filter($allowedEntities, function ($entity) use ($fqn) {
            return $entity['class'] === $fqn;
        });

        if (!empty($coincidences)) {
            $this->modelLogger
                ->logChanges(
                    $current,
                    $changes,
                    $className
                );
        }
    }
}
