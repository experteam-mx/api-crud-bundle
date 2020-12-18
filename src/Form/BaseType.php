<?php

namespace Experteam\ApiCrudBundle\Form;

use Symfony\Component\Form\AbstractType;

class BaseType extends AbstractType
{
    private $defaults = [
        'allow_extra_fields' => true
    ];

    /**
     * @return array
     */
    public function getDefaults(): array
    {
        return $this->defaults;
    }

    /**
     * @param array $defaults
     * @return BaseType
     */
    public function addDefaults(array $defaults): self
    {
        $this->defaults = array_merge($this->defaults, $defaults);
        return $this;
    }
}