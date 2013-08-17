<?php

namespace WebFace;

use \Silex\Application;

/**
 *
 */
class Admin
{
    protected $app = null;
    protected $navigation = array();
    protected $tableNames = array();

    protected $currentTable = null;

    public function __construct(Application $app, $navigation, $tableNames)
    {
        $this->app        = $app;
        $this->navigation = $navigation;
        $this->tableNames = $tableNames;
    }

    public function getNavigation()
    {
        return $this->navigation;
    }

    public function setCurrentTable($table)
    {
        $this->app['webface.admin.current_service_container']->setCurrentTable($table);
        $this->currentTable = $table;
        foreach ($this->navigation as $group => &$pages) {
            foreach ($pages as &$page) {
                if ($table == $page['table']) {
                    $page['active'] = true;

                    break 2;
                }
            }
        }
    }

    public function getCurrentTable()
    {
        return $this->currentTable;
    }

    public function getCurrentTableLabel()
    {
        return $this->tableNames[$this->currentTable];
    }

    public function generateRandomString($length = 10)
    {
        $result = '';
        $alpha = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $alphaLength = strlen($alpha);
        for ($i = 0; $i < $length; $i++) {
            $result .= $alpha[rand(0, $alphaLength - 1)];
        }

        return $result;
    }
}