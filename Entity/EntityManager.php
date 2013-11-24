<?php

namespace WebFace\Entity;

use Silex\Application;

class EntityManager
{
    const ENTITY_ID_TYPE_SCALAR = 'entity_id_type_scalar';
    const ENTITY_ID_TYPE_ASSOC  = 'entity_id_type_accos';
    const ENTITY_ID_TYPE_ARRAY  = 'entity_id_type_array';

    /** @var Application */
    protected $app;

    /** @var string */
    protected $table = null;

    public function __construct(Application $app, $table)
    {
        $this->app   = $app;
        $this->table = $table;
    }

    public function getTable()
    {
        return $this->table;
    }

    /**
     * @param $id
     *
     * @return mixed
     */
    public function getEntity($id)
    {
        $query = "SELECT * FROM `{$this->table}` WHERE id = ?";

        return $this->app['db']->fetchAssoc($query, array($id));
    }

    /**
     * @param array $entity
     * @param string $type ENTITY_ID_TYPE_* const
     *
     * @return array
     */
    public function getEntityId($entity, $type = self::ENTITY_ID_TYPE_ASSOC)
    {
        switch ($type) {
            case self::ENTITY_ID_TYPE_ASSOC:
                return array('id' => $entity['id']);

            case self::ENTITY_ID_TYPE_SCALAR:
                return $entity['id'];

            case self::ENTITY_ID_TYPE_ARRAY:
                return array($entity['id']);
        }
    }

    /**
     * @param string      $where
     * @param string      $orderBy
     * @param string      $offset
     * @param string      $limit
     *
     * @return array
     */
    public function getEntitiesList($where, $orderBy, $offset, $limit)
    {
        $query = "SELECT * FROM `{$this->table}` {$where} {$orderBy} LIMIT {$offset}, {$limit}";

        $entities = $this->app['db']->fetchAll($query);

        return $entities;
    }
}