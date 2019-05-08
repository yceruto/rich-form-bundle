<?php

namespace Yceruto\Bundle\RichFormBundle\Form\ChoiceList\Loader;

use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

class Entity2LoaderDecorator implements ChoiceLoaderInterface
{
    private $decoratedLoader;
    /**
     * @var ChoiceListInterface
     */
    private $choiceList;
    private $choices = [];
    private $newChoices = false;

    public function __construct(ChoiceLoaderInterface $decoratedLoader)
    {
        $this->decoratedLoader = $decoratedLoader;
    }

    public function loadChoiceList($value = null)
    {
        if (null !== $this->choiceList && !$this->newChoices) {
            return $this->choiceList;
        }

        $this->newChoices = false;

        return $this->choiceList = new ArrayChoiceList($this->choices, $value);
    }

    public function loadChoicesForValues(array $values, $value = null): array
    {
        if ($this->choices !== $choices = $this->decoratedLoader->loadChoicesForValues($values, $value)) {
            $this->newChoices = true;
        }

        return $this->choices = $choices;
    }

    public function loadValuesForChoices(array $choices, $value = null): array
    {
        if ([] === $values = $this->decoratedLoader->loadValuesForChoices($choices, $value)) {
            $newChoices = [];
        } else {
            $newChoices = $choices;
        }

        if ($this->choices !== $newChoices) {
            $this->choices = $newChoices;
            $this->newChoices = true;
        }

        return $values;
    }
}
