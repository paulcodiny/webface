<?php

namespace WebFace\Provider;

use Silex\ServiceProviderInterface;
use Silex\Application;

use WebFace\Form\Type;

class AdminServiceProvider implements ServiceProviderInterface
{
    protected $tables;

    public function __construct($tables)
    {
        $this->tables = $tables;
    }

    public function register(Application $app)
    {
        $navigation = $tableNames = array();
        $app->mount('/webface', new \WebFace\Controller\DashboardControllerProvider());
        foreach ($this->tables as $definition) {
            $table = $definition['controller']->getTable();
            if (!isset($definition['group'])) {
                $definition['group'] = 'Общее';
            }
            $app->mount('/webface/' . $table, $definition['controller']);

            if (!isset($navigation[$definition['group']])) {
                $navigation[$definition['group']] = array();
            }
            $navigation[$definition['group']][] = array('table' => $table, 'label' => $definition['label']);
            $tableNames[$table] = $definition['label'];
        }

        $app->extend('form.factory', function (\Symfony\Component\Form\FormFactory $formFactory) {
            $formFactory->addType(new Type\EditableImageType());
            $formFactory->addType(new Type\GroupedChoiceType());
            $formFactory->addType(new Type\TinyMCETextareaType());

            return $formFactory;
        });

        $app['webface.admin'] = $app->share(function() use($app, $navigation, $tableNames) {
           return new \WebFace\Admin($navigation, $tableNames);
        });
    }

    public function boot(Application $app)
    {
        $app['twig']->addGlobal('webface_navigation', $app['webface.admin']->getNavigation());
    }
}
