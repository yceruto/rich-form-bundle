<?php

namespace Form\ChoiceList\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Yceruto\Bundle\RichFormBundle\Form\ChoiceList\Loader\Entity2LoaderDecorator;

class Entity2LoaderDecoratorTest extends TestCase
{
    private $choices;
    private $decoratedLoader;

    protected function setUp()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');
        $this->choices = [$entity1, $entity2, $entity3];

        $this->decoratedLoader = new CallbackChoiceLoader(function () {
            return $this->choices;
        });
    }

    protected function tearDown()
    {
        $this->choices = [];
        $this->decoratedLoader = null;
    }

    public function testLoadEmptyChoicesForEmptyValues()
    {
        $loader = new Entity2LoaderDecorator($this->decoratedLoader);

        $this->assertSame([], $loader->loadChoicesForValues([]));
    }

    public function testLoadChoicesForValues()
    {
        $loader = new Entity2LoaderDecorator($this->decoratedLoader);

        $this->assertSame([$this->choices[0]], $loader->loadChoicesForValues(['0']));
    }

    public function testLoadEmptyValuesForEmptyChoices()
    {
        $loader = new Entity2LoaderDecorator($this->decoratedLoader);

        $this->assertSame([], $loader->loadValuesForChoices([]));
    }

    public function testLoadEmptyValuesForNullChoices()
    {
        $loader = new Entity2LoaderDecorator($this->decoratedLoader);

        $this->assertSame([], $loader->loadValuesForChoices([null]));
    }

    public function testLoadValuesForChoices()
    {
        $loader = new Entity2LoaderDecorator($this->decoratedLoader);

        $this->assertSame(['1'], $loader->loadValuesForChoices([$this->choices[1]]));
    }

    public function testEmptyChoiceListIfNotCallToLoadChoicesOrValues()
    {
        $loader = new Entity2LoaderDecorator($this->decoratedLoader);

        $this->assertSame([], $loader->loadChoiceList()->getChoices());
    }

    public function testChoiceListSameLoadedChoices()
    {
        $loader = new Entity2LoaderDecorator($this->decoratedLoader);

        $this->assertSame($loader->loadChoicesForValues(['0']), $loader->loadChoiceList()->getChoices());
    }

    public function testChoiceListSameLoadedFromValues()
    {
        $loader = new Entity2LoaderDecorator($this->decoratedLoader);

        $choices = [$this->choices[1]];
        $loader->loadValuesForChoices($choices);

        $this->assertSame($choices, $loader->loadChoiceList()->getChoices());
    }

    public function testLoadCachedChoiceList()
    {
        $loader = new Entity2LoaderDecorator($this->decoratedLoader);

        $loader->loadChoicesForValues(['0']);

        $this->assertSame($loader->loadChoiceList(), $loader->loadChoiceList());
    }

    public function testLatestChoiceList()
    {
        $loader = new Entity2LoaderDecorator($this->decoratedLoader);

        $loader->loadValuesForChoices([$this->choices[2]]);
        $loader->loadChoicesForValues(['0']);

        $this->assertSame([$this->choices[0]], $loader->loadChoiceList()->getChoices());
    }

    public function testNewChoiceListPerLoadChoicesOrValuesCall()
    {
        $loader = new Entity2LoaderDecorator($this->decoratedLoader);

        $loader->loadValuesForChoices([$this->choices[2]]);
        $choiceList1 = $loader->loadChoiceList();

        $this->assertSame([$this->choices[2]], $choiceList1->getChoices());

        $loader->loadChoicesForValues(['0']);
        $choiceList2 = $loader->loadChoiceList();

        $this->assertSame([$this->choices[0]], $choiceList2->getChoices());

        $this->assertNotSame($choiceList1, $choiceList2);
    }
}
