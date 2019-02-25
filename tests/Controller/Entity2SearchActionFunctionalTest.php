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

        // Fill product name
        $crawler->filter('#product_form_name')->sendKeys('Product1');

        // Adding "Category 23"
        $crawler->filter('.select2-selection--single')->click();
        $searchInput = $crawler->filter('.select2-container--open .select2-search__field');
        $searchInput->sendKeys('23');
        $client->waitFor('.select2-results__option--highlighted');
        $liHtml = '<li class="select2-results__option select2-results__option--highlighted" role="treeitem" aria-selected="false" data-select2-id="7"><strong>Category 23</strong><br><small>Short description...</small></li>';
        $this->assertSame($liHtml, $crawler->filter('.select2-results__option--highlighted')->html());
        $searchInput->sendKeys(WebDriverKeys::ENTER);

        // Adding "Tag 11"
        $crawler->filter('.select2-selection--multiple')->click();
        $searchInput = $crawler->filter('.select2-container--open .select2-search__field');
        $searchInput->sendKeys('11');
        $client->waitFor('.select2-results__option--highlighted');
        $searchInput->sendKeys(WebDriverKeys::ENTER);

        // Adding "Tag 20"
        $crawler->filter('.select2-selection--multiple')->click();
        $searchInput = $crawler->filter('.select2-container--open .select2-search__field');
        $searchInput->sendKeys('20');
        $client->waitFor('.select2-results__option--highlighted');
        $searchInput->sendKeys(WebDriverKeys::ENTER);

        // Submit and redirect
        $crawler = $client->submit($crawler->filter('form')->form());

        // Check success
        $client->waitFor('.product-list');
        $this->assertCount(1, $crawler->filter('tbody > tr'));
        $this->assertSame('Category 23', $crawler->filter('tbody > tr > td')->getElement(1)->getText());
        $this->assertSame('Tag 11, Tag 20', $crawler->filter('tbody > tr > td')->getElement(2)->getText());
    }

    public function testEditProductWithSelect2SearchQuery(): void
    {
        $client = static::createPantherClient();

        $crawler = $client->request('GET', '/edit/1');

        // Change to "Category 11"
        $crawler->filter('.select2-selection--single')->click();
        $searchInput = $crawler->filter('.select2-container--open .select2-search__field');
        $searchInput->sendKeys('11');
        $client->waitFor('.select2-results__option--highlighted');
        $searchInput->sendKeys(WebDriverKeys::ENTER);

        // Adding "Tag 32"
        $crawler->filter('.select2-selection--multiple')->click();
        $searchInput = $crawler->filter('.select2-container--open .select2-search__field');
        $searchInput->sendKeys('32');
        $client->waitFor('.select2-results__option--highlighted');
        $searchInput->sendKeys(WebDriverKeys::ENTER);

        // Removing "Tag 11"
        $crawler->filter('.select2-selection__choice__remove')->first()->click();

        // Submit and redirect
        $crawler = $client->submit($crawler->filter('form')->form());

        // Check success
        $client->waitFor('.product-list');
        $this->assertCount(1, $crawler->filter('tbody > tr'));
        $this->assertSame('Category 11', $crawler->filter('tbody > tr > td')->getElement(1)->getText());
        $this->assertSame('Tag 20, Tag 32', $crawler->filter('tbody > tr > td')->getElement(2)->getText());
    }

    public function testMaxResultsAndInfinityScroll(): void
    {
        $client = static::createPantherClient();

        $crawler = $client->request('GET', '/new');

        $crawler->filter('.select2-selection--single')->click();
        $searchInput = $crawler->filter('.select2-container--open .select2-search__field');
        $searchInput->sendKeys('a');
        $client->waitFor('.select2-results__option--highlighted');

        $results = $crawler->filter('.select2-results__options > li');
        $this->assertSame(11, $results->count());
        $this->assertSame('Loading more results…', $results->last()->text());

        $client->executeScript('document.getElementById("select2-product_form_category-results").scrollBy(0, 1000)');

        sleep(1);

        $results = $crawler->filter('.select2-results__options > li');
        $this->assertSame(21, $results->count());
        $this->assertSame('Loading more results…', $results->last()->text());

        $client->executeScript('document.getElementById("select2-product_form_category-results").scrollBy(0, 1000)');

        sleep(1);

        $results = $crawler->filter('.select2-results__options > li');
        $this->assertSame(31, $results->count());
        $this->assertSame('Loading more results…', $results->last()->text());

        $client->executeScript('document.getElementById("select2-product_form_category-results").scrollBy(0, 1000)');

        sleep(1);

        $results = $crawler->filter('.select2-results__options > li');
        $this->assertSame(41, $results->count());
        $this->assertSame('Loading more results…', $results->last()->text());

        $client->executeScript('document.getElementById("select2-product_form_category-results").scrollBy(0, 1000)');

        sleep(1);

        $results = $crawler->filter('.select2-results__options > li');
        $this->assertSame(49, $results->count());
        $this->assertNotSame('Loading more results…', $results->last()->text());
    }
}
