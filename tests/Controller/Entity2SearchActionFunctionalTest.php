<?php

namespace Yceruto\Bundle\RichFormBundle\Tests\Controller;

use Symfony\Component\Panther\PantherTestCase;

class Entity2SearchActionFunctionalTest extends PantherTestCase
{
    public function testSelect2SearchQuery(): void
    {
        $client = static::createPantherClient();

        $crawler = $client->request('GET', '/new');

        $this->assertCount(1, $crawler->filter('h1'));
        $this->assertSame('New Product', $crawler->filter('h1')->text());
    }
}
