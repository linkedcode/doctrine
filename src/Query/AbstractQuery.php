<?php

namespace Linkedcode\Doctrine\Query;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Exception;

abstract class AbstractQuery
{
    protected QueryBuilder $qb;
    protected EntityManagerInterface $em;
    protected string $entityClass;
    protected string $alias;
    protected array $params = [];

    private int $page = 1;
    private int $limit = 10;

    protected const string AND = "AND";
    protected const string OR = "OR";

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;

        $this->qb = $this->em->createQueryBuilder();
        $this->qb->select($this->alias);
        $this->qb->from($this->entityClass, $this->alias);
    }

    abstract protected function parseParams();

    protected function addGreaterThan(string $table, string $column, mixed $value, $inclusive = true)
    {
        if ($inclusive) {
            $this->qb->andWhere("{$table}.{$column} >= :{$column}");
        } else {
            $this->qb->andWhere("{$table}.{$column} > :{$column}");
        }

        $this->qb->setParameter($column, $value);
    }

    protected function addLessThan(string $table, string $column, mixed $value, $inclusive = true)
    {
        if ($inclusive) {
            $this->qb->andWhere("{$table}.{$column} <= :{$column}");
        } else {
            $this->qb->andWhere("{$table}.{$column} < :{$column}");
        }

        $this->qb->setParameter($column, $value);
    }

    protected function orderBy(string $alias, string $column, string $order)
    {
        $this->qb->orderBy($alias . "." . $column, $order);
    }

    protected function addWhere(string $alias, string $column, mixed $value, $boolOperator = self::AND)
    {
        if (is_int($value) || is_numeric($value)) {
            $this->qb->andWhere("{$alias}.{$column} = :{$column}");
            $this->qb->setParameter($column, intval($value));
        } else if (is_null($value)) {
            $this->qb->andWhere("{$alias}.{$column} IS NULL");
        } else if (is_array($value)) {
            $expressions = [];
            foreach ($value as $idx => $v) {
                $expressions[] = $this->qb->expr()->eq("{$alias}.{$column}", ":{$column}{$idx}");
                $this->qb->setParameter($column . $idx, $v);
            }
            
            if ($boolOperator == self::AND) {
                $expr = $this->qb->expr()->andX(...$expressions);
            } else {
                $expr = $this->qb->expr()->orX(...$expressions);
            }
            
            $this->qb->andWhere($expr);
        }
    }

    protected function addLike(array $tableColumn, string $value, $boolOperator = "OR")
    {
        $columns = $this->countColumns($tableColumn);

        if ($columns === 1) {
            foreach ($tableColumn as $table => $columns) {
                $column = $columns[0];
            }

            $this->qb->andWhere("{$table}.{$column} LIKE :{$table}{$column}");
            $this->qb->setParameter($table . $column, $value);
        } else {
            $expressions = [];
            foreach ($tableColumn as $table => $columns) {
                foreach ($columns as $column) {
                    $expressions[] = $this->qb->expr()->like("{$table}.{$column}", ":{$table}{$column}");
                    $this->qb->setParameter($table . $column, $value);
                }
            }
            $expr = $this->qb->expr()->orX(...$expressions);
            $this->qb->andWhere($expr);
        }
    }

    protected function addWhereIn(string $table, string $column, mixed $value, array $params = [])
    {
        if ($value instanceof QueryBuilder) {
            $expr = $this->qb->expr()->in("{$table}.{$column}", $value->getDQL());
            if ($params) {
                foreach ($params as $k => $param) {
                    $this->qb->setParameter($k, $param);
                }
            }
        } else {
            throw new Exception("Falta programar");
        }

        $this->qb->andWhere($expr);
    }

    private function countColumns(array $tableColumn): int
    {
        $count = 0;

        foreach ($tableColumn as $columns) {
            $count += count($columns);
        }

        return $count;
    }

    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    public function execute(array $params = [])
    {
        $this->setParams($params);
        $this->parseParams();
        $this->qb->setFirstResult(($this->page - 1) * $this->limit);
        $this->qb->setMaxResults($this->limit);

        $dql = $this->qb->getDQL();
        //echo $dql;

        return $this->qb->getQuery()->getResult();
    }

    protected function setParams(array $params): void
    {
        $this->params = $params;
    }
}