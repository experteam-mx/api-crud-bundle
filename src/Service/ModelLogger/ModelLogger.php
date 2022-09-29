<?php

namespace Experteam\ApiCrudBundle\Service\ModelLogger;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Experteam\ApiBaseBundle\Service\ELKLogger\ELKLogger;
use Experteam\ApiCrudBundle\Message\EntityChangeMessage;
use ReflectionClass;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ModelLogger implements ModelLoggerInterface
{
    /**
     * @var ELKLogger
     */
    protected $elkLogger;

    /**
     * @var MessageBusInterface
     */
    private $messageBus;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(
        ELKLogger $elkLogger,
        MessageBusInterface $messageBus,
        SerializerInterface $serializer
    ) {
        $this->elkLogger = $elkLogger;
        $this->messageBus = $messageBus;
        $this->serializer = $serializer;
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
                ? $changes[$prop][0]
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

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $changeSet = $uow->getEntityChangeSet($entity);

            if (empty($changeSet)) {
                continue;
            }

            $this->messageBus
                ->dispatch(
                    new EntityChangeMessage([
                        'changes' => $changeSet,
                        'current' => $this->serializer
                            ->serialize($entity, 'json', [
                                'groups' => ['read'], // TODO: configure in YML
                            ]),
                        'class_name' => (new ReflectionClass($entity))
                            ->getShortName(),
                        'fqn' => get_class($entity),
                    ])
                );
        }
    }
}