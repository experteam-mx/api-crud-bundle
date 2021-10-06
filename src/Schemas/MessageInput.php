<?php

namespace Experteam\ApiCrudBundle\Schemas;

use OpenApi\Annotations as OA;
use Symfony\Component\Validator\Constraints as Assert;

class MessageInput
{
    /**
     * @Assert\NotBlank()
     * @Assert\DateTime()
     * @OA\Property(type="string", maxLength=19, description="Date From.", example="Y-m-d H:i:s")
     */
    public $dateTimeFrom;

    /**
     * @Assert\DateTime()
     * @OA\Property(type="string", maxLength=19, description="Date To.", example="Y-m-d H:i:s")
     */
    public $dateTimeTo;

    /**
     * @var string[]
     *
     * @OA\Property(type="array", @OA\Items(type="string"), description="Entities.")
     */
    public $entities = [];

    /**
     * @var int[]
     *
     * @OA\Property(type="array", @OA\Items(type="number"), description="Entity Ids")
     */
    public $entityIds = [];
}