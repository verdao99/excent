<?php

use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/', function (Request $request, Response $response) {
    return $this->AdminController->index($request, $response);
});

$app->post('/task/register/', function (Request $request, Response $response) {
    return $this->AdminController->saveTask($request, $response);
});

$app->get('/dashboard/messages/api/[{id}/]', function (Request $request, Response $response) {
    return $this->ApiController->messagesDashboard($request, $response);
});
