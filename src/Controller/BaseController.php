<?php

namespace Experteam\ApiCrudBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Experteam\ApiBaseBundle\Service\Param\ParamInterface;
use Experteam\ApiBaseBundle\Service\RequestUtil\RequestUtilInterface;
use Experteam\ApiCrudBundle\Service\Paginator\PaginatorInterface;
use Experteam\ApiCrudBundle\Service\ViolationUtil\ViolationUtilInterface;
use Experteam\ApiRedisBundle\Service\RedisClient\RedisClientInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class BaseController extends \Experteam\ApiBaseBundle\Controller\BaseController
{
    protected PaginatorInterface $paginator;

    protected RedisClientInterface $redisClient;

    private ViolationUtilInterface $violator;

    protected EntityManagerInterface $entityManager;

    public function __construct(PaginatorInterface $paginator, ParamInterface $param, RedisClientInterface $redisClient, HttpClientInterface $httpClient, RequestUtilInterface $requestUtil, ViolationUtilInterface $violator, EntityManagerInterface $entityManager, TranslatorInterface $translator)
    {
        parent::__construct($param, $requestUtil, $httpClient, $translator);
        $this->paginator = $paginator;
        $this->redisClient = $redisClient;
        $this->violator = $violator;
        $this->entityManager = $entityManager;
    }

    protected function validate(string $type, mixed $data, mixed $submittedData, bool $throwException = true, array $formOptions = []): array
    {
        $processedErrors = [];
        $form = $this->createForm($type, $data, $formOptions);
        $this->violator->validateDataTypes($form, $submittedData, get_class($data));
        $form->submit($submittedData);

        if (!$form->isValid()) {
            $errors = $form->getErrors(true);
            $validationErrors = new ConstraintViolationList();

            foreach ($errors as $error) {
                $validationErrors->add($error->getCause());
            }

            if ($validationErrors->count() > 0) {
                $processedErrors = $this->violator->build($validationErrors);

                if ($throwException) {
                    throw new BadRequestHttpException(json_encode($processedErrors));
                }
            }
        }

        return $processedErrors;
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
}
