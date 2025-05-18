<?php

namespace Experteam\ApiCrudBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Experteam\ApiBaseBundle\Service\Param\ParamInterface;
use Experteam\ApiBaseBundle\Service\RequestUtil\RequestUtilInterface;
use Experteam\ApiCrudBundle\Service\Paginator\PaginatorInterface;
use Experteam\ApiCrudBundle\Service\ViolationUtil\ViolationUtilInterface;
use Experteam\ApiRedisBundle\Service\RedisClient\RedisClientInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class BaseController extends \Experteam\ApiBaseBundle\Controller\BaseController
{
    public function __construct(
        ParamInterface                   $param,
        RequestUtilInterface             $requestUtil,
        HttpClientInterface              $httpClient,
        TranslatorInterface              $translator,
        protected ViolationUtilInterface $violationUtil,
        protected EntityManagerInterface $entityManager,
        protected PaginatorInterface     $paginator,
        protected RedisClientInterface   $redisClient
    )
    {
        parent::__construct($param, $requestUtil, $httpClient, $translator);
    }

    protected function save(string $type, mixed $data, mixed $submittedData, array $formOptions = [], bool $refreshData = false): array
    {
        $this->validate($type, $data, $submittedData, true, $formOptions);
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        if ($refreshData) {
            $this->entityManager->refresh($data);
        }

        $class = get_class($data);
        $key = str_replace('App\\Entity\\', '', $class);
        $key[0] = strtolower($key[0]);
        return [$key => $data];
    }

    protected function validate(string $type, mixed $data, mixed $submittedData, bool $throwException = true, array $formOptions = [], bool $validateDataTypes = true, bool $useErrorFormatV2 = false): array
    {
        $processedErrors = [];
        $form = $this->createForm($type, $data, $formOptions);

        if ($validateDataTypes) {
            $this->violationUtil->validateDataTypes($form, $submittedData, get_class($data));
        }

        $form->submit($submittedData);

        if (!$form->isValid()) {
            if ($useErrorFormatV2) {
                $processedErrors = $this->getErrorsFromForm($form);
            } else {
                $errors = $form->getErrors(true);
                $validationErrors = new ConstraintViolationList();

                foreach ($errors as $error) {
                    $validationErrors->add($error->getCause());
                }

                if ($validationErrors->count() > 0) {
                    $processedErrors = $this->violationUtil->build($validationErrors);
                }
            }

            if ($throwException && count($processedErrors) > 0) {
                throw new BadRequestHttpException(json_encode($processedErrors));
            }
        }

        return $processedErrors;
    }

    protected function getErrorsFromForm(FormInterface $form): array
    {
        $errors = [];

        foreach ($form->getErrors() as $error) {
            $errors[] = $error->getMessage();
        }

        foreach ($form->all() as $childForm) {
            if ($childForm instanceof FormInterface) {
                if ($childErrors = $this->getErrorsFromForm($childForm)) {
                    $errors[$childForm->getName()] = $childErrors;
                }
            }
        }

        return $errors;
    }
}
