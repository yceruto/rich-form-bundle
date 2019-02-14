<?php

namespace Yceruto\Bundle\RichFormBundle\Form\ChoiceList\Loader;

use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

class Entity2LoaderDecorator implements ChoiceLoaderInterface
{
    private $decoratedLoader;
    private $choices = [];

    /**
     * @var ChoiceListInterface
     */
    private $choiceList;

    function __construct(ChoiceLoaderInterface $decoratedLoader)
    {
        $this->decoratedLoader = $decoratedLoader;
    }

    public function loadChoiceList($value = null)
    {
        if (null !== $this->choiceList) {
            return $this->choiceList;
        }

        return $this->choiceList = new ArrayChoiceList($this->choices, $value);
    }

    public function loadChoicesForValues(array $values, $value = null)
    {
        return $this->choices = $this->decoratedLoader->loadChoicesForValues($values, $value);
    }

    public function loadValuesForChoices(array $choices, $value = null)
    {
        if ([] === $values = $this->decoratedLoader->loadValuesForChoices($choices, $value)) {
            $this->choices = [];
        } else {
            $this->choices = $choices;
        }

        return $values;
    }
}
