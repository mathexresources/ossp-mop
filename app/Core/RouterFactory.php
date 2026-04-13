<?php

declare(strict_types=1);

namespace App\Core;

use Nette\Application\Routers\RouteList;

final class RouterFactory
{
    public static function createRouter(): RouteList
    {
        $router = new RouteList;

        $front = new RouteList('Front');
        $front->addRoute('[<presenter>[/<action>[/<id \d+>]]]', 'Homepage:default');
        $router->add($front);

        $admin = new RouteList('Admin');
        $admin->addRoute('admin[/<presenter>[/<action>[/<id \d+>]]]', 'Dashboard:default');
        $router->add($admin);

        return $router;
    }
}
