<?php
declare(strict_types=1);

namespace Experteam\ApiCrudBundle\Service\ViolationUtil;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ViolationUtil implements ViolationUtilInterface
{
    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var FormFactoryInterface
     */
    protected $formFactory;

    public function __construct(ValidatorInterface $validator, EntityManagerInterface $entityManager, FormFactoryInterface $formFactory)
    {
        $this->validator = $validator;
        $this->entityManager = $entityManager;
        $this->formFactory = $formFactory;
    }

    /**
     * @param ConstraintViolationListInterface $violations
     * @return array
     */
    public function build(ConstraintViolationListInterface $violations): array
    {
        $errors = [];

        /** @var ConstraintViolation $violation */
        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }

        return $this->buildMessages($errors);
    }

    /**
     * @param array $errors
     * @return array
     */
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

    /**
     * @param FormInterface $form
     * @param $submittedData
     * @param string $entityClass
     * @param bool $throwException
     * @return array
     */
    public function validateDataTypes(FormInterface $form, $submittedData, string $entityClass, bool $throwException = true): array
    {
        $validationTypes = $this->getValidationTypes($form, $entityClass);
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

    /**
     * @param FormInterface $formType
     * @param string $entityClass
     * @return array
     */
    protected function getValidationTypes(FormInterface $formType, string $entityClass): array
    {
        $validationTypes = [];
        $metadata = $this->getClassMetadata($entityClass);

        foreach ($formType->all() as $fieldForm) {
            $fieldName = $fieldForm->getConfig()->getName();

            if (isset($metadata->fieldMappings[$fieldName])) {
                $validationTypes[$fieldName] = $this->getTypeFromDoctrine($metadata->fieldMappings[$fieldName]['type']);
            } elseif (isset($metadata->associationMappings[$fieldName])) {
                switch ($metadata->associationMappings[$fieldName]['type']) {
                    case 1: // OneToOne
                    case 2: // ManyToOne
                        $fieldName = property_exists($entityClass, $fieldName . 'Id') ? $fieldName . 'Id' : $fieldName;
                        $validationTypes[$fieldName] = new Assert\Type('integer');
                        break;
                    case 4:
                    case 8: // ManyToMany
                        $fieldFormType = $fieldForm->getConfig()->getType()->getInnerType();

                        if (get_class($fieldFormType) == CollectionType::class) {
                            $childTypeClass = $fieldForm->getConfig()->getOption('entry_type');
                            $childEntityClass = 'App\\Entity\\' . substr(basename(str_replace('\\', '/', $childTypeClass)), 0, -4);
                            $childForm = $this->formFactory->create($childTypeClass);

                            $_validationTypes = $this->getValidationTypes($childForm, $childEntityClass);
                            $_collection = new Assert\Collection($_validationTypes);
                            $_collection->allowMissingFields = true;
                            $_collection->allowExtraFields = true;
                            $validationTypes[$fieldName] = new Assert\All([$_collection]);
                        } else {
                            $validationTypes[$fieldName] = [
                                new Assert\Type('array'),
                                new Assert\All([
                                    new Assert\Type('integer')
                                ])
                            ];
                        }
                }
            }
        }

        return $validationTypes;
    }

    /**
     * @param string $type
     * @return Assert\Type
     */
    private function getTypeFromDoctrine(string $type): Assert\Type
    {
        $phpType = $type;

        switch ($type) {
            case 'decimal':
                $phpType = 'numeric';
                break;
            case 'bigint':
            case 'smallint':
                $phpType = 'integer';
                break;
            case 'date':
            case 'datetime':
            case 'time':
                $phpType = 'string';
                break;
        }

        return new Assert\Type($phpType);
    }

    /**
     * @param string $propertyPath
     * @return string
     */
    private function formatPropertyPath(string $propertyPath): string
    {
        $property = '';

        foreach (explode('][', substr($propertyPath, 1, -1)) as $prop) {
            $property .= preg_match('/^\d+$/', $prop) ? sprintf('[%s]', $prop) : sprintf('.%s', $prop);
        }

        return ltrim($property, '.');
    }

    /**
     * @param string $className
     * @return ClassMetadata
     */
    private function getClassMetadata(string $className): ClassMetadata
    {
        return $this->entityManager->getClassMetadata($className);
    }
}