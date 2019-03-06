<?php

namespace Yceruto\Bundle\RichFormBundle\Request;

use Yceruto\Bundle\RichFormBundle\Doctrine\Query\DynamicParameter;

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
     * @return DynamicParameter[]
     */
    public function getQueryBuilderDynamicParams(): array
    {
        return $this->options['qb_dynamic_params'] ?? [];
    }

    public function getQueryBuilderDynamicParamsValues(): array
    {
        return $this->options['dynamic_params_values'];
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
