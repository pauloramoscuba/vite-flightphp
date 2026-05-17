<?php

declare(strict_types=1);

use app\controllers\ApiExampleController;
use app\middlewares\SecurityHeadersMiddleware;
use flight\Engine;
use flight\net\Router;

/**
 * Route definitions and middleware registration.
 *
 * @var Router $router
 * @var Engine $app
 */

// This wraps all routes in the group with the SecurityHeadersMiddleware
$router->group(
    '',
    function (Router $router) use ($app) {
        $router->get('/', function () use ($app) {
            $app->get('page_cache')->render('welcome', [
                'app' => $app,
                'message' => 'You are gonna do great things!',
            ]);
        });

        $router->get('/hello-world/@name', function ($name) {
            echo '<h1>Hello world! Oh hey ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '!</h1>';
        });

        $router->get('/cached-page', function () use ($app) {
            $app->get('page_cache')->render('welcome', [
                'app' => $app,
                'message' => 'Automatically Cached page example!',
            ]);
        });

        $router->get('/cached-page-per-user', function () use ($app) {
            $app->get('page_cache')->render(
                'welcome',
                [
                    'app' => $app,
                    'message' => 'Per user cached page!',
                ],
                true,
            );
        });

        $router->get('/no-cache-page', function () use ($app) {
            $app->get('page_cache')->render(
                'welcome',
                [
                    'app' => $app,
                    'message' => 'No cache page!',
                ],
                false,
                true,
            );
        });

        $router->group('/api', function () use ($router) {
            $router->get('/users', [ApiExampleController::class, 'getUsers']);
            $router->get('/users/@id:[0-9]', [ApiExampleController::class, 'getUser']);
            $router->post('/users/@id:[0-9]', [ApiExampleController::class, 'updateUser']);
        });
    },
    [SecurityHeadersMiddleware::class],
);
