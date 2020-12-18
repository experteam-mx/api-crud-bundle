<?php

namespace Experteam\ApiCrudBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;

class ExperteamApiCrudBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
    }
}