<?php

namespace Yceruto\Bundle\RichFormBundle\Tests\Controller;

use Facebook\WebDriver\WebDriverKeys;
use Symfony\Component\Panther\PantherTestCase;

class Entity2SearchActionFunctionalTest extends PantherTestCase
{
    public function testNewProductWithSelect2SearchQuery(): void
    {
        $client = static::createPantherClient();

        $crawler = $client->request('GET', '/new');

        $this->assertCount(1, $crawler->filter('h1'));
        $this->assertSame('New Product', $crawler->filter('h1')->text());

        // Fill form
        $crawler->filter('#form_name')->sendKeys('Product1');
        $crawler->filter('.select2-container')->click();
        $searchInput = $crawler->filter('.select2-search__field');
        // Looking for "Category 23"
        $searchInput->sendKeys('23');
        $client->waitFor('.select2-results__option--highlighted');
        $searchInput->sendKeys(WebDriverKeys::ENTER);

        // Submit and redirect
        $crawler = $client->submit($crawler->filter('form')->form());

        // Check success
        $client->waitFor('.product-list');
        $this->assertCount(1, $crawler->filter('tbody > tr'));
        $this->assertSame('Category 23', $crawler->filter('tbody > tr > td')->getElement(1)->getText());
    }

    public function testEditProductWithSelect2SearchQuery(): void
    {
        $client = static::createPantherClient();

        $crawler = $client->request('GET', '/edit/1');

        // Fill form
        $crawler->filter('.select2-container')->click();
        $searchInput = $crawler->filter('.select2-search__field');
        // Looking for "Category 11"
        $searchInput->sendKeys('11');
        $client->waitFor('.select2-results__option--highlighted');
        $searchInput->sendKeys(WebDriverKeys::ENTER);

        // Submit and redirect
        $crawler = $client->submit($crawler->filter('form')->form());

        // Check success
        $client->waitFor('.product-list');
        $this->assertCount(1, $crawler->filter('tbody > tr'));
        $this->assertSame('Category 11', $crawler->filter('tbody > tr > td')->getElement(1)->getText());
    }
}
