<?php
declare(strict_types=1);

namespace Experteam\ApiCrudBundle\Service\ViolationUtil;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ViolationUtil implements ViolationUtilInterface
{
    public function __construct(
        private readonly ValidatorInterface     $validator,
        private readonly EntityManagerInterface $entityManager,
        private readonly FormFactoryInterface   $formFactory
    )
    {
    }

    public function build(ConstraintViolationListInterface $violations): array
    {
        $errors = [];

        /** @var ConstraintViolation $violation */
        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }

        return $this->buildMessages($errors);
    }

    public function buildMessages(array $errors): array
    {
        $result = [];

        foreach ($errors as $path => $message) {
            $temp = &$result;
            $path = str_replace(['children', '[', ']'], '', $path);

            foreach (explode('.', $path) as $key) {
                preg_match('/(.*)(\[.*?\])/', $key, $matches);

                if ($matches) {
                    $temp = &$temp[$matches[1]][$matches[2]];
                } else {
                    $temp = &$temp[$key];
                }
            }

            $temp = $message;
        }

        if (isset($result['data'])) {
            $result = $result['data'];
        }

        return $result;
    }

    public function validateDataTypes(FormInterface $form, $submittedData, string $entityClass, bool $throwException = true): array
    {
        $validationTypes = $this->getValidationTypes($form, $entityClass);

        if (empty($validationTypes)) {
            return [];
        }

        $constraints = new Assert\Collection($validationTypes);
        $constraints->allowExtraFields = true;
        $constraints->allowMissingFields = true;

        $validationErrors = $this->validator->validate($submittedData, $constraints);
        $processedErrors = [];

        if ($validationErrors->count() > 0) {
            $errors = [];

            foreach ($validationErrors as $violation) {
                $errors[$this->formatPropertyPath($violation->getPropertyPath())] = $violation->getMessage();
            }

            $processedErrors = $this->buildMessages($errors);

            if ($throwException) {
                throw new BadRequestHttpException(json_encode($processedErrors));
            }
        }

        return $processedErrors;
    }

    protected function getValidationTypes(FormInterface $formType, string $entityClass): array
    {
        $validationTypes = [];
        $metadata = $this->getClassMetadata($entityClass);

        foreach ($formType->all() as $fieldForm) {
            $fieldName = $fieldForm->getConfig()->getName();

            if (isset($metadata->fieldMappings[$fieldName])) {
                $type = $this->getTypeFromDoctrine($metadata->fieldMappings[$fieldName]['type']);

                if (!is_null($type)) {
                    $validationTypes[$fieldName] = $type;
                }
            } elseif (isset($metadata->associationMappings[$fieldName])) {
                switch ($metadata->associationMappings[$fieldName]['type']) {
                    case 1: // OneToOne
                    case 2: // ManyToOne
                        if (property_exists($entityClass, $fieldName . 'Id')) {
                            $validationTypes[$fieldName . 'Id'] = new Assert\Type('integer');
                        } else {
                            $childTypeClass = get_class($fieldForm->getConfig()->getType()->getInnerType());
                            $childEntityClass = 'App\\Entity\\' . substr(basename(str_replace('\\', '/', $childTypeClass)), 0, -4);
                            $childForm = $this->formFactory->create($childTypeClass);
                            $_validationTypes = $this->getValidationTypes($childForm, $childEntityClass);

                            if (!empty($_validationTypes)) {
                                $_collection = new Assert\Collection($_validationTypes);
                                $_collection->allowMissingFields = true;
                                $_collection->allowExtraFields = true;
                                $validationTypes[$fieldName] = $_collection;
                            }
                        }

                        break;
                    case 4:
                    case 8: // ManyToMany
                        $fieldFormType = $fieldForm->getConfig()->getType()->getInnerType();

                        if (get_class($fieldFormType) == CollectionType::class) {
                            $childTypeClass = $fieldForm->getConfig()->getOption('entry_type');
                            $childEntityClass = 'App\\Entity\\' . substr(basename(str_replace('\\', '/', $childTypeClass)), 0, -4);
                            $childForm = $this->formFactory->create($childTypeClass);
                            $_validationTypes = $this->getValidationTypes($childForm, $childEntityClass);

                            if (!empty($_validationTypes)) {
                                $_collection = new Assert\Collection($_validationTypes);
                                $_collection->allowMissingFields = true;
                                $_collection->allowExtraFields = true;
                                $validationTypes[$fieldName] = new Assert\All([$_collection]);
                            }
                        } else {
                            $validationTypes[$fieldName] = [
                                new Assert\Type('array'),
                                new Assert\All([
                                    new Assert\Type('integer')
                                ])
                            ];
                        }

                        break;
                }
            }
        }

        return $validationTypes;
    }

    private function getClassMetadata(string $className): ClassMetadata
    {
        return $this->entityManager->getClassMetadata($className);
    }

    private function getTypeFromDoctrine(string $type): ?Assert\Type
    {
        return match ($type) {
            'decimal', 'float' => new Assert\Type('numeric'),
            'bigint', 'integer', 'smallint' => new Assert\Type('integer'),
            'date', 'datetime', 'time', 'text', 'string' => new Assert\Type('string'),
            'json' => new Assert\Type('array'),
            'boolean' => new Assert\Type('bool'),
            default => null
        };
    }

    public function formatPropertyPath(string $propertyPath): string
    {
        $property = '';

        foreach (explode('][', substr($propertyPath, 1, -1)) as $prop) {
            $property .= preg_match('/^\d+$/', $prop) ? sprintf('[%s]', $prop) : sprintf('.%s', $prop);
        }

        return ltrim($property, '.');
    }
}
