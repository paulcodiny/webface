<?php

namespace WebFace\Controller;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Silex\ControllerCollection;

class DashboardControllerProvider implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $t = clone $this;

        $controllers = $app['controllers_factory'];

        $controllers->get('/', function(Application $app) use ($t) {
            return $t->actionDashboard($app);
        })->bind('dashboard');

        $app['twig.loader.filesystem']->addPath(realpath(__DIR__ . '/../View'));
        $app['twig.loader.filesystem']->addPath(realpath(__DIR__ . '/../View/crud'));

        return $controllers;
    }

    public function actionDashboard($app)
    {
        return $app['twig']->render('dashboard.twig');
    }
}
