<?php

namespace Experteam\ApiCrudBundle\Service\ModelLogger;

use Doctrine\ORM\UnitOfWork;
use Experteam\ApiBaseBundle\Service\ELKLogger\ELKLogger;
use ReflectionClass;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

class ModelLogger implements ModelLoggerInterface
{
    /**
     * @var ELKLogger
     */
    protected $elkLogger;

    public function __construct(ELKLogger $elkLogger)
    {
        $this->elkLogger = $elkLogger;
    }

    public function logEntity(UnitOfWork $uow): void
    {
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $changeSet = $uow->getEntityChangeSet($entity);

            $changedProps = array_keys($changeSet);

            $changes = array_map(function ($item) {
                return $item[1];
            }, $changeSet);

            $new = [];
            $old = [];
            foreach ($this->properties($entity) as $prop) {
                $getter = 'get' . ucfirst($prop);
                $current = $entity->$getter();

                $new[$prop] = $current;
                $old[$prop] = in_array($prop, $changedProps)
                    ? $changeSet[$prop][0]
                    : $current;
            }

            $model = (new ReflectionClass($entity))
                ->getShortName();

            $this->elkLogger
                ->warningLog(
                    "Model [$model] changed!",
                    compact('model', 'changes', 'new', 'old')
                );
        }
    }

    private function properties(object $entity): ?array
    {
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();

        $listExtractors = [$reflectionExtractor];
        $typeExtractors = [$phpDocExtractor, $reflectionExtractor];
        $descriptionExtractors = [$phpDocExtractor];
        $accessExtractors = [$reflectionExtractor];
        $propertyInitializableExtractors = [$reflectionExtractor];

        $propertyInfo = new PropertyInfoExtractor(
            $listExtractors,
            $typeExtractors,
            $descriptionExtractors,
            $accessExtractors,
            $propertyInitializableExtractors
        );
        $class = get_class($entity);
        return $propertyInfo->getProperties($class);
    }
}