<?php

namespace WebFace;

use \Silex\Application;

use WebFace\Entity\EntityManager;
use WebFace\ListControl\ListBuilder;

class CurrentServiceContainer
{
    /** @var \Silex\Application */
    protected $app;

    /** @var string|null Name of the table  */
    protected $currentTable = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function setCurrentTable($currentTable)
    {
        $this->currentTable = $currentTable;
    }

    public function getCurrentTable()
    {
        return $this->currentTable;
    }

    public function checkCurrentTable()
    {
        if ($this->currentTable === null) {
            throw new CurrentServiceNotSetException();
        }
    }

    /**
     * @return ListBuilder
     */
    public function getListBuilder()
    {
        $this->checkCurrentTable();

        return $this->app['webface.' . $this->currentTable . '.list.builder'];
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager()
    {
        $this->checkCurrentTable();

        return $this->app['webface.' . $this->currentTable . '.entity.manager'];
    }
}