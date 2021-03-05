<?php

namespace Experteam\ApiCrudBundle\Service\ViolationUtil;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

interface ViolationUtilInterface
{
    /**
     * @param ConstraintViolationListInterface $violations
     * @return array
     */
    public function build(ConstraintViolationListInterface $violations): array;

    /**
     * @param array $errors
     * @return array
     */
    public function buildMessages(array $errors): array;

    /**
     * @param FormInterface $form
     * @param $submittedData
     * @param string $entityClass
     * @param bool $throwException
     * @return array
     */
    public function validateDataTypes(FormInterface $form, $submittedData, string $entityClass, bool $throwException = true): array;
}