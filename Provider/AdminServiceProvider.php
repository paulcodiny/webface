<?php

namespace WebFace\Provider;

use Symfony\Component\Form\FormFactory;

use Silex\ServiceProviderInterface;
use Silex\Application;

use WebFace\Admin;
use WebFace\Controller\DashboardControllerProvider;
use WebFace\CurrentServiceContainer;
use WebFace\Definition;
use WebFace\Entity\Entity;
use WebFace\Entity\EntityManager;
use WebFace\Form\Type;
use WebFace\Form\FormBuilder;
use WebFace\ListControl\FilterBuilder;
use WebFace\ListControl\ListBuilder;

class AdminServiceProvider implements ServiceProviderInterface
{
    protected $pages;
    protected $groupsRole;

    public function __construct($pages, $groupsRole = array())
    {
        $this->pages      = $pages;
        $this->groupsRole = $groupsRole;
    }

    public function register(Application $app)
    {
        $navigation = $tableNames = array();
        $app->mount('/webface', new DashboardControllerProvider());
        foreach ($this->pages as $definition) {
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

            /** @var Definition $tableDefinition */
            $tableDefinition = new $definition['definition']($app, $table);
            $tableDefinition->defineDependencies();
            $app['webface.admin.definition.' . $table] = $tableDefinition;
        }

        $app->extend('form.types', $app->share(function ($types) {
            $types[] = new Type\EditableImageType();
            $types[] = new Type\GroupedChoiceType();
            $types[] = new Type\TinyMCETextareaType();

            return $types;
        }));

        $app['webface.admin'] = $app->share(function() use ($app, $navigation, $tableNames) {
           return new Admin($app, $navigation, $tableNames);
        });

        $app['webface.admin.current_service_container'] = $app->share(function() use ($app) {
            return new CurrentServiceContainer($app);
        });

        $app['webface.groups_role'] = $this->groupsRole;
    }

    public function boot(Application $app)
    {
        $app['twig']->addGlobal('webface_navigation', $app['webface.admin']->getNavigation());
        $app['twig']->addGlobal('webface_groups_role', $app['webface.groups_role']);
    }
}
