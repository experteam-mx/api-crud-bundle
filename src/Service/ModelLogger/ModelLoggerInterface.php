<?php

namespace Experteam\ApiCrudBundle\Service\ModelLogger;

interface ModelLoggerInterface
{
    public function entityChanges(array $current, array $changes, string $className): void;
}