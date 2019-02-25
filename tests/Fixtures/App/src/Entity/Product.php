<?php

namespace App\Test\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class Product
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
     * @var Category
     *
     * @ORM\ManyToOne(targetEntity="Category")
     */
    public $category;

    /**
     * @var Tag[]
     *
     * @ORM\ManyToMany(targetEntity="Tag")
     */
    public $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
