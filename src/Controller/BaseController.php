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
    /**
     * @var PaginatorInterface
     */
    protected PaginatorInterface $paginator;

    /**
     * @var RedisClientInterface
     */
    protected RedisClientInterface $redisClient;

    /**
     * @var ViolationUtilInterface
     */
    private ViolationUtilInterface $violator;

    /**
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $entityManager;

    /**
     * @param PaginatorInterface $paginator
     * @param ParamInterface $param
     * @param RedisClientInterface $redisClient
     * @param HttpClientInterface $httpClient
     * @param RequestUtilInterface $requestUtil
     * @param ViolationUtilInterface $violator
     * @param EntityManagerInterface $entityManager
     * @param TranslatorInterface $translator
     */
    public function __construct(PaginatorInterface $paginator, ParamInterface $param, RedisClientInterface $redisClient, HttpClientInterface $httpClient, RequestUtilInterface $requestUtil, ViolationUtilInterface $violator, EntityManagerInterface $entityManager, TranslatorInterface $translator)
    {
        parent::__construct($param, $requestUtil, $httpClient, $translator);
        $this->paginator = $paginator;
        $this->redisClient = $redisClient;
        $this->violator = $violator;
        $this->entityManager = $entityManager;
    }

    /**
     * @param string $type
     * @param mixed $data
     * @param mixed $submittedData
     * @param bool $throwException
     * @return array
     */
    protected function validate(string $type, mixed $data, mixed $submittedData, bool $throwException = true): array
    {
        $processedErrors = [];
        $form = $this->createForm($type, $data);
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

    /**
     * @param string $type
     * @param mixed $data
     * @param mixed $submittedData
     * @return mixed
     */
    protected function save(string $type, mixed $data, mixed $submittedData): array
    {
        $this->validate($type, $data, $submittedData);
        $this->entityManager->persist($data);
        $this->entityManager->flush();
        $class = get_class($data);
        $key = str_replace('App\\Entity\\', '', $class);
        $key[0] = strtolower($key[0]);
        return [$key => $data];
    }
}
