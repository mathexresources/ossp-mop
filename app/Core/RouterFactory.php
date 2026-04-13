<?php

declare(strict_types=1);

namespace App\Core;

use Nette\Application\Routers\RouteList;

final class RouterFactory
{
    public static function createRouter(): RouteList
    {
        $router = new RouteList;

        // Admin must be registered before the Front catch-all.
        $admin = new RouteList('Admin');
        $admin->addRoute('admin[/<presenter>[/<action>[/<id \d+>]]]', 'Dashboard:default');
        $router->add($admin);

        $front = new RouteList('Front');
        $front->addRoute('[<presenter>[/<action>[/<id \d+>]]]', 'Homepage:default');
        $router->add($front);

        return $router;
    }
}
