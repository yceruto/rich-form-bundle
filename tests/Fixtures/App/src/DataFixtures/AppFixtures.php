<?php

namespace App\Test\DataFixtures;

use App\Test\Entity\Category;
use App\Test\Entity\Tag;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        for ($i = 1; $i <= 50; ++$i) {
            $category = new Category();
            $category->name = 'Category '.$i;
            $category->description = 'Short description...';
            $category->enabled = 13 !== $i;
            $manager->persist($category);

            $tag = new Tag();
            $tag->name = 'Tag '.$i;
            $manager->persist($tag);
        }
        $manager->flush();
    }
}
