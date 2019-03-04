<?php

namespace Yceruto\Bundle\RichFormBundle\Doctrine\Query;

use Doctrine\ORM\Query\Parameter;

class DynamicParameter extends Parameter
{
    /**
     * @var array
     */
    private $where = [];

    /**
     * @var bool
     */
    private $optional = false;

    public function __construct(string $name, $default = null, $type = null)
    {
        parent::__construct($name, $default, $type);
    }

    public function getWhere(): array
    {
        return $this->where;
    }

    /**
     * Specifies one or more restrictions to the query result.
     * Replaces any previously specified restrictions, if any.
     *
     * <code>
     *     $param = new DynamicParameter('id');
     *     $param->where('group.id = :id');
     * </code>
     *
     * @param string $where The WHERE statement.
     *
     * @return $this
     */
    public function where(string $where)
    {
        $this->where = [['AND' => $where]];

        return $this;
    }

    /**
     * Adds one or more restrictions to the query results, forming a logical
     * conjunction with any previously specified restrictions.
     *
     * <code>
     *     $param = new DynamicParameter('username');
     *     $param->where('user.username LIKE :username')
     *     $param->andWhere('user.is_active = 1');
     * </code>
     *
     * @param string $where The WHERE statement.
     *
     * @return $this
     *
     * @see where()
     */
    public function andWhere(string $where)
    {
        $this->where[] = ['AND' => $where];

        return $this;
    }

    /**
     * Adds one or more restrictions to the query results, forming a logical
     * disjunction with any previously specified restrictions.
     *
     * <code>
     *     $param = new DynamicParameter('name');
     *     $param->where('user.username LIKE :name');
     *     $param->orWhere('group.name LIKE :name');
     * </code>
     *
     * @param mixed $where The WHERE statement.
     *
     * @return $this
     *
     * @see where()
     */
    public function orWhere(string $where)
    {
        $this->where[] = ['OR' => $where];

        return $this;
    }

    public function isOptional(): bool
    {
        return $this->optional;
    }

    /**
     * @return $this
     */
    public function optional(bool $optional)
    {
        $this->optional = $optional;

        return $this;
    }
}
