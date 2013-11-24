<?php

namespace WebFace\ListControl;

use \Silex\Application;

class ListBuilder
{
    /** @var Application */
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @return array
     */
    public function getFieldNames()
    {
        return array();
    }

    /**
     * @return string
     */
    public function getOrder()
    {
        return 'id DESC';
    }

    /**
     * @return int
     */
    public function getPerPageCount()
    {
        return 20;
    }

    /**
     * Actions available in list page
     *
     * @return array
     */
    public function getActions()
    {
        return array('_add' => 'Добавить');
    }

    /**
     * @param array $entity
     *
     * @return array
     */
    public function getEntityActions($entity)
    {
        return array(
            new EntityAction('Редактировать', '_edit'),
            new EntityAction('Удалить', '_delete'),
        );
    }
}