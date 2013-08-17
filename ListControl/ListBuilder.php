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

    public function getFieldNames()
    {
        return array();
    }

    public function getEntityActions($entity)
    {
        return array(
            new EntityAction('Редактировать', '_edit'),
            new EntityAction('Удалить', '_delete'),
        );
    }
}