<?php

namespace Experteam\ApiCrudBundle\Controller;

use Experteam\ApiCrudBundle\Service\Paginator\PaginatorInterface;
use Experteam\ApiBaseBundle\Service\Param\ParamInterface;
use Experteam\ApiRedisBundle\Service\RedisClient\RedisClientInterface;
use Experteam\ApiBaseBundle\Service\RequestUtil\RequestUtilInterface;
use Experteam\ApiCrudBundle\Service\ViolationUtil\ViolationUtilInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Validator\ConstraintViolationList;

class BaseController extends AbstractFOSRestController
{
    /**
     * @var PaginatorInterface
     */
    protected $paginator;

    /**
     * @var ParamInterface
     */
    protected $param;

    /**
     * @var RedisClientInterface
     */
    protected $redisClient;

    /**
     * @var RequestUtilInterface
     */
    protected $requestUtil;

    /**
     * @var ViolationUtilInterface
     */
    private $violator;

    /**
     * @param PaginatorInterface $paginator
     * @param ParamInterface $param
     * @param RedisClientInterface $redisClient
     * @param RequestUtilInterface $requestUtil
     * @param ViolationUtilInterface $violator
     */
    public function __construct(PaginatorInterface $paginator, ParamInterface $param, RedisClientInterface $redisClient, RequestUtilInterface $requestUtil, ViolationUtilInterface $violator)
    {
        $this->paginator = $paginator;
        $this->param = $param;
        $this->redisClient = $redisClient;
        $this->requestUtil = $requestUtil;
        $this->violator = $violator;
    }

    /**
     * @param string $data
     * @return mixed
     */
    protected function jsonDecode(string $data)
    {
        $jsonEncoder = new JsonEncoder();
        return $jsonEncoder->decode($data, 'json');
    }

    /**
     * @param string $type
     * @param mixed $data
     * @param mixed $submittedData
     * @param bool $throwException
     * @return array
     */
    protected function validate(string $type, $data, $submittedData, bool $throwException = true)
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
    protected function save(string $type, $data, $submittedData)
    {
        $this->validate($type, $data, $submittedData);
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($data);
        $entityManager->flush();
        $class = get_class($data);
        $key = str_replace('App\\Entity\\', '', $class);
        $key[0] = strtolower($key[0]);
        return [$key => $data];
    }
}