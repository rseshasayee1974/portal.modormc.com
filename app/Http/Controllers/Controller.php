<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(title: "Mobile Dashboard API", version: "1.0.0")]
#[OA\Server(url: "/api")]
#[OA\SecurityScheme(securityScheme: "bearerAuth", type: "http", scheme: "bearer")]
abstract class Controller
{
    //
}
