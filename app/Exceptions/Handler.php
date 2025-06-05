<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

public function render($request, Throwable $exception)
{
    // Token signature issue
    if ($exception instanceof UnauthorizedHttpException && $exception->getMessage() === 'Token Signature could not be verified.') {
        return response()->json([
            'status' => false,
            'message' => 'Token is invalid or tampered.'
        ], 401);
    }

    // Optional: handle all other JWT exceptions too
    if ($exception instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException) {
        return response()->json(['status' => false, 'message' => 'Token is invalid'], 401);
    }

    if ($exception instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException) {
        return response()->json(['status' => false, 'message' => 'Token has expired'], 401);
    }

    if ($exception instanceof \Tymon\JWTAuth\Exceptions\JWTException) {
        return response()->json(['status' => false, 'message' => 'Token not provided'], 401);
    }

    return parent::render($request, $exception);
}
}
