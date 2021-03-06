<?php

namespace App\Test\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class Category
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue()
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     */
    public $name;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     */
    public $description;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     */
    public $groupName;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean")
     */
    public $enabled = true;

    /**
     * @var ProductType
     *
     * @ORM\ManyToOne(targetEntity="ProductType")
     */
    public $type;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function __toString()
    {
        return $this->name;
    }
}
