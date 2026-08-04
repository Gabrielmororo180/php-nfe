<?php

namespace App\Infrastructure\Primary\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'API RESTful for NFe issuance and cancellation using Hexagonal Architecture & NFePHP',
    title: 'PHP-NFe API'
)]
#[OA\Server(
    url: 'http://localhost:8000',
    description: 'Local Development Server'
)]
abstract class Controller
{
}
