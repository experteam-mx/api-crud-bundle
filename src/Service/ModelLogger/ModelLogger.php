<?php

namespace Experteam\ApiCrudBundle\Service\ModelLogger;

use Experteam\ApiBaseBundle\Service\ELKLogger\ELKLogger;
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

    public function entityChanges(array $current, array $changes, string $className): void
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
}