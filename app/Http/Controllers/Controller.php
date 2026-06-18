<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

#[OA\Info(title: "Mobile Dashboard API", version: "1.0.0")]
#[OA\Server(url: "/api")]
#[OA\SecurityScheme(securityScheme: "bearerAuth", type: "http", scheme: "bearer")]
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
}
