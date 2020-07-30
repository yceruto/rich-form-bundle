<?php

namespace App\Test\DataFixtures;

use App\Test\Entity\Category;
use App\Test\Entity\ProductType;
use App\Test\Entity\Tag;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $type1 = new ProductType();
        $type1->name = 'Inventory';
        $manager->persist($type1);

        $type2 = new ProductType();
        $type2->name = 'Non-Inventory';
        $manager->persist($type2);

        for ($i = 1; $i <= 50; ++$i) {
            $category = new Category();
            $category->name = 'Category '.$i;
            $category->description = 'Short description...';
            $category->groupName = $i % 2 === 0 ? 'odd' : 'even';
            $category->type = $i % 2 === 0 ? $type1 : $type2;
            $category->enabled = 13 !== $i;
            $manager->persist($category);

            $tag = new Tag();
            $tag->name = 'Tag '.$i;
            $manager->persist($tag);
        }
        $manager->flush();
    }
}
