<?php

namespace Experteam\ApiCrudBundle\Service\ViolationUtil;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

interface ViolationUtilInterface
{
    public function build(ConstraintViolationListInterface $violations): array;

    public function buildMessages(array $errors): array;

    public function validateDataTypes(FormInterface $form, $submittedData, string $entityClass, bool $throwException = true): array;

    public function formatPropertyPath(string $propertyPath): string;
}
