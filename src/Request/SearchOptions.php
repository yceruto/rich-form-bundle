<?php

namespace Yceruto\Bundle\RichFormBundle\Request;

class SearchOptions
{
    private $options;

    public function __construct(array $options)
    {
        $this->options = $options;
    }

    public function getEntityClass(): string
    {
        return $this->options['class'];
    }

    public function getQueryBuilderParts(): ?array
    {
        return $this->options['qb_parts'] ?? null;
    }

    /**
     * @return string[]
     */
    public function getQueryBuilderDynamicParams(): array
    {
        return $this->options['qb_dynamic_params'] ?? [];
    }

    public function getQueryBuilderDynamicParamsValues(): array
    {
        return $this->options['dynamic_params_values'];
    }

    public function getQueryBuilderDynamicParamValue(string $paramName)
    {
        return $this->options['dynamic_params_values'][$paramName] ?? null;
    }

    public function getSearchCallback(): ?callable
    {
        return $this->options['search_callback'];
    }

    public function getMaxResults(): int
    {
        return $this->options['max_results'];
    }

    public function getText(): ?string
    {
        return $this->options['text'];
    }

    public function getOrderBy(): ?array
    {
        return $this->options['order_by'];
    }

    public function getSearchBy(): array
    {
        return (array) $this->options['search_by'];
    }

    public function getGroupBy(): ?string
    {
        return $this->options['group_by'];
    }

    public function getResultFields(): array
    {
        return (array) $this->options['result_fields'];
    }

    public function getEntityManagerName(): ?string
    {
        return $this->options['em'];
    }
}
