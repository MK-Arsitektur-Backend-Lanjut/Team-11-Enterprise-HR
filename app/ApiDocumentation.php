<?php

namespace App;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    description: "API Documentation for Team 11 Enterprise HR System, covering Employee, Attendance, and Approval modules.",
    title: "Enterprise HR API Documentation",
    contact: new OA\Contact(email: "admin@enterprise.com")
)]
#[OA\Server(
    url: "/",
    description: "Localhost"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT"
)]
class ApiDocumentation
{
}
