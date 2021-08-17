<?php

namespace Experteam\ApiCrudBundle\Schemas;

use OpenApi\Annotations as OA;
use Symfony\Component\Validator\Constraints as Assert;

class MessageInput
{
    /**
     * @Assert\NotBlank()
     * @Assert\DateTime()
     * @OA\Property(type="string", maxLength=19, description="Date and time.", example="Y-m-d H:i:s")
     */
    public $dateTime;

    /**
     * @var string[]
     *
     * @OA\Property(type="array", @OA\Items(type="string"), description="Entities.")
     */
    public $entities = [];
}