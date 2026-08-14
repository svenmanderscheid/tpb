<?php
declare(strict_types=1);

// Front Controller – einziger Einstieg (§2, Sollzustand M0 §4).
require dirname(__DIR__) . '/vendor/autoload.php';

\Tpb\Core\Env::load(dirname(__DIR__) . '/.env');
\Tpb\Core\ErrorHandler::register();
\Tpb\Core\View::base(dirname(__DIR__) . '/app/Views');
\Tpb\Core\Router::dispatch(require dirname(__DIR__) . '/app/Http/routes.php');
