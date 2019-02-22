<?php

namespace Yceruto\Bundle\RichFormBundle\Tests\Fixtures\Entity\Embeddable;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Embeddable()
 */
class Contact
{
    /**
     * @ORM\Column(type="string", nullable=true)
     */
    public $phone;
}
