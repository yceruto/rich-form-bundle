<?php

namespace Yceruto\Bundle\RichFormBundle\Form\ChoiceList\Loader;

use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

/**
 * Loads the selected choices only.
 */
class Entity2LoaderDecorator implements ChoiceLoaderInterface
{
    private $decoratedLoader;
    private $choiceList;
    private $choices = [];
    private $cached = false;

    public function __construct(ChoiceLoaderInterface $decoratedLoader)
    {
        $this->decoratedLoader = $decoratedLoader;
    }

    public function loadChoiceList(callable $value = null)
    {
        if (null !== $this->choiceList && $this->cached) {
            return $this->choiceList;
        }

        $this->cached = true;

        return $this->choiceList = new ArrayChoiceList($this->choices, $value);
    }

    public function loadChoicesForValues(array $values, callable $value = null): array
    {
        if ($this->choices !== $choices = $this->decoratedLoader->loadChoicesForValues($values, $value)) {
            $this->cached = false;
        }

        return $this->choices = $choices;
    }

    public function loadValuesForChoices(array $choices, callable $value = null): array
    {
        $values = $this->decoratedLoader->loadValuesForChoices($choices, $value);

        if ([] === $values || [''] === $values) {
            $newChoices = [];
        } else {
            $newChoices = $choices;
        }

        if ($this->choices !== $newChoices) {
            $this->choices = $newChoices;
            $this->cached = false;
        }

        return $values;
    }
}
