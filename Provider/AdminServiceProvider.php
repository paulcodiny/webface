<?php

namespace WebFace\Provider;

use Symfony\Component\Form\FormFactory;

use Silex\ServiceProviderInterface;
use Silex\Application;

use WebFace\Admin;
use WebFace\Controller\DashboardControllerProvider;
use WebFace\Form\Type;

class AdminServiceProvider implements ServiceProviderInterface
{
    protected $tables;
    protected $groupsRole;

    public function __construct($tables, $groupsRole = array())
    {
        $this->tables     = $tables;
        $this->groupsRole = $groupsRole;
    }

    public function register(Application $app)
    {
        $navigation = $tableNames = array();
        $app->mount('/webface', new DashboardControllerProvider());
        foreach ($this->tables as $definition) {
            $table = $definition['table'] = $definition['controller']->getTable();
            if (!isset($definition['group'])) {
                $definition['group'] = 'Общее';
            }
            $app->mount('/webface/' . $table, $definition['controller']);

            $group = $definition['group'];
            if (!isset($navigation[$group])) {
                $navigation[$group] = array();
            }

            $navigation[$group][] = $definition;
            $tableNames[$table]   = $definition['label'];
        }

        $app->extend('form.types', $app->share(function ($types) {
            $types[] = new Type\EditableImageType();
            $types[] = new Type\GroupedChoiceType();
            $types[] = new Type\TinyMCETextareaType();

            return $types;
        }));

        $app['webface.admin'] = $app->share(function() use($app, $navigation, $tableNames) {
           return new Admin($navigation, $tableNames);
        });

        $app['webface.groups_role'] = $this->groupsRole;
    }

    public function boot(Application $app)
    {
        $app['twig']->addGlobal('webface_navigation', $app['webface.admin']->getNavigation());
        $app['twig']->addGlobal('webface_groups_role', $app['webface.groups_role']);
    }
}
