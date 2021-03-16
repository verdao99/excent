<?php

use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;

$app->group('/messages', function () use ($app) {

    $app->get('/', function (Request $request, Response $response) {
        return $this->MessageController->message($request, $response);
    });

    $app->post('/register/', function (Request $request, Response $response) {
        return $this->MessageController->saveMessage($request, $response);
    });

    $app->get('/delete/api/{id}/', function (Request $request, Response $response) {
        return $this->ApiController->messageDelete($request, $response);
    });

    $app->get('/api/[{id}/]', function (Request $request, Response $response) {
        return $this->ApiController->messagesTable($request, $response);
    });
});