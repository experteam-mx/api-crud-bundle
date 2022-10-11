<?php

namespace Experteam\ApiCrudBundle\Service\ModelLogger;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Experteam\ApiBaseBundle\Service\ELKLogger\ELKLogger;
use Experteam\ApiCrudBundle\Message\EntityChangeMessage;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ModelLogger implements ModelLoggerInterface
{
    private $elkLogger;

    private $messageBus;

    private $serializer;

    private $parameterBag;

    public function __construct(
        ELKLogger $elkLogger,
        MessageBusInterface $messageBus,
        SerializerInterface $serializer,
        ParameterBagInterface $parameterBag
    ) {
        $this->elkLogger = $elkLogger;
        $this->messageBus = $messageBus;
        $this->serializer = $serializer;
        $this->parameterBag = $parameterBag;
    }

    public function logChanges(array $current, array $changes, string $className): void
    {
        $changedProps = array_keys($changes);

        $changes = array_map(function ($item) {
            return $item[1];
        }, $changes);

        $old = [];
        foreach ($current as $prop => $value) {
            $old[$prop] = in_array($prop, $changedProps)
                ? $changes[$prop][0] ?? null
                : $value;
        }

        $this->elkLogger
            ->noticeLog("Model [$className] changed!", [
                'model' => $className,
                'changes' => $changes,
                'new' => $current,
                'old' => $old,
            ]);
    }

    public function dispatchChanges(OnFlushEventArgs $eventArgs): void
    {
        $uow = $eventArgs->getEntityManager()
            ->getUnitOfWork();

        $entities = array_merge(
            $uow->getScheduledEntityDeletions(),
            $uow->getScheduledEntityInsertions(),
            $uow->getScheduledEntityUpdates()
        );

        foreach ($entities as $e) {
            $fqn = get_class($e);

            if (!$this->modelIsConfig($fqn)) {
                continue;
            }

            $changeSet = $uow->getEntityChangeSet($e);

            if (empty($changeSet)) {
                continue;
            }

            $this->messageBus
                ->dispatch(
                    new EntityChangeMessage([
                        'changes' => $changeSet,
                        'current' => $this->serializer
                            ->serialize($e, 'json', [
                                'groups' => ['read'], // TODO: configure in YML
                            ]),
                        'class_name' => (new ReflectionClass($e))
                            ->getShortName(),
                    ])
                );
        }
    }

    private function modelIsConfig($fqn): bool
    {
        $allowedEntities = $this->parameterBag
            ->get('experteam_api_crud.logged_entities');

        if (empty($allowedEntities)) {
            return false;
        }

        $coincidences = array_filter(
            $allowedEntities,
            function ($entity) use ($fqn) {
                return $entity['class'] === $fqn;
            }
        );

        return !empty($coincidences);
    }
}