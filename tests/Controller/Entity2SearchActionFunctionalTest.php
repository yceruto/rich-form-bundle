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

        $form = $crawler->selectButton('Submit')->form();
        // Set product name
        $form['product_form[name]']->setValue('Product1');
        // Select product type
        $form['product_form[type]']->select('1');

        // Show first page
        $crawler->filter('.select2-selection--single')->click();
        $client->waitFor('.select2-results__option--highlighted');
        $liHtml = '<li class="select2-results__option select2-results__option--highlighted" role="treeitem" aria-selected="false" data-select2-id="5"><strong>Category 2</strong><br><small>Short description...</small></li>';
        $this->assertSame($liHtml, $crawler->filter('.select2-results__option--highlighted')->html());
        // Adding "Category 23"
        $searchInput = $crawler->filter('.select2-container--open .select2-search__field');
        $searchInput->sendKeys('24');
        usleep(500000);
        $liHtml = '<li class="select2-results__option select2-results__option--highlighted" role="treeitem" aria-selected="false" data-select2-id="17"><strong>Category 24</strong><br><small>Short description...</small></li>';
        $this->assertSame($liHtml, $crawler->filter('.select2-results__option--highlighted')->html());
        $searchInput->sendKeys(WebDriverKeys::ENTER);

        // Adding "Tag 11"
        $crawler->filter('.select2-selection--multiple')->click();
        $searchInput = $crawler->filter('.select2-container--open .select2-search__field');
        $searchInput->sendKeys('11');
        usleep(500000);
        $searchInput->sendKeys(WebDriverKeys::ENTER);

        // Adding "Tag 20"
        $crawler->filter('.select2-selection--multiple')->click();
        $searchInput = $crawler->filter('.select2-container--open .select2-search__field');
        $searchInput->sendKeys('20');
        usleep(500000);
        $searchInput->sendKeys(WebDriverKeys::ENTER);

        // Submit and redirect
        $crawler = $client->submit($crawler->filter('form')->form());

        // Check success
        $client->waitFor('.product-list');
        $this->assertCount(1, $crawler->filter('tbody > tr'));
        $this->assertSame('Category 24', $crawler->filter('tbody > tr > td')->getElement(2)->getText());
        $this->assertSame('Tag 11, Tag 20', $crawler->filter('tbody > tr > td')->getElement(3)->getText());
    }

    public function testEditProductWithSelect2SearchQuery(): void
    {
        $client = static::createPantherClient();

        $crawler = $client->request('GET', '/edit/1');

        // Change to "Category 10"
        $crawler->filter('.select2-selection--single')->click();
        $searchInput = $crawler->filter('.select2-container--open .select2-search__field');
        $searchInput->sendKeys('10');
        usleep(500000);
        $searchInput->sendKeys(WebDriverKeys::ENTER);

        // Adding "Tag 32"
        $crawler->filter('.select2-selection--multiple')->click();
        $searchInput = $crawler->filter('.select2-container--open .select2-search__field');
        $searchInput->sendKeys('32');
        usleep(500000);
        $searchInput->sendKeys(WebDriverKeys::ENTER);

        // Removing "Tag 11"
        $crawler->filter('.select2-selection__choice__remove')->first()->click();

        // Submit and redirect
        $crawler = $client->submit($crawler->filter('form')->form());

        // Check success
        $client->waitFor('.product-list');
        $this->assertCount(1, $crawler->filter('tbody > tr'));
        $this->assertSame('Category 10', $crawler->filter('tbody > tr > td')->getElement(2)->getText());
        $this->assertSame('Tag 20, Tag 32', $crawler->filter('tbody > tr > td')->getElement(3)->getText());
    }

    public function testMaxResultsAndInfinityScroll(): void
    {
        $client = static::createPantherClient();

        $crawler = $client->request('GET', '/new');

        $crawler->filter('.select2-selection--single')->click();

        usleep(500000);

        $results = $crawler->filter('.select2-results__options > li');
        $this->assertSame(11, $results->count());
        $this->assertSame('Loading more results…', $results->last()->text());

        $client->executeScript('document.getElementById("select2-product_form_category-results").scrollBy(0, 1000)');

        usleep(500000);

        $results = $crawler->filter('.select2-results__options > li');
        $this->assertSame(21, $results->count());
        $this->assertSame('Loading more results…', $results->last()->text());

        $client->executeScript('document.getElementById("select2-product_form_category-results").scrollBy(0, 1000)');

        usleep(500000);

        $results = $crawler->filter('.select2-results__options > li');
        $this->assertSame(31, $results->count());
        $this->assertSame('Loading more results…', $results->last()->text());

        $client->executeScript('document.getElementById("select2-product_form_category-results").scrollBy(0, 1000)');

        usleep(500000);

        $results = $crawler->filter('.select2-results__options > li');
        $this->assertSame(41, $results->count());
        $this->assertSame('Loading more results…', $results->last()->text());

        $client->executeScript('document.getElementById("select2-product_form_category-results").scrollBy(0, 1000)');

        usleep(500000);

        $results = $crawler->filter('.select2-results__options > li');
        $this->assertSame(49, $results->count());
        $this->assertNotSame('Loading more results…', $results->last()->text());
    }
}
