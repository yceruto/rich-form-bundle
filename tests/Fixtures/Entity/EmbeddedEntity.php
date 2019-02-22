<?php

namespace Yceruto\Bundle\RichFormBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Yceruto\Bundle\RichFormBundle\Tests\Fixtures\Entity\Embeddable\Contact;

/**
 * @ORM\Entity
 */
class EmbeddedEntity
{
    /**
     * @var int
     * @ORM\Id @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     */
    public $name;

    /**
     * @ORM\Embedded(class="Yceruto\Bundle\RichFormBundle\Tests\Fixtures\Entity\Embeddable\Contact")
     */
    public $contact;

    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
        $this->contact = new Contact();
    }
}
