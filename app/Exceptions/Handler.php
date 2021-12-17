<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Contracts\Validation\Validator;


class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->renderable(function (UnauthorizedException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'You don`t have Correct role Authorization',
            ], 403);
        });
        $this->renderable(function (HttpResponseException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid',
            ], 422);
        });
        $this->renderable(function (ModelNotFoundException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'Source Page Not Found',
            ], 404);
        });

        $this->renderable(function (NotFoundHttpException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'Url Not Found',
            ], 404);
        });

        $this->renderable(function (MethodNotAllowedHttpException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'Method Not Allowed',
            ], 405);
        });

        $this->renderable(function (ValidationException $e, $request) {
            $validationErrors = $e->validator->errors()->getMessages();
            $validationErrors = array_map(function($error){
                return array_map(function($message){
                    return $message;
                }, $error);
            }, $validationErrors);
            return response()->json([
                'success' => false,
                'data' => $validationErrors,
                'message' => 'The given data was invalid',
            ], 422);
        });

        $this->renderable(function (QueryException $e, $request) {
            $debug = config('app.debug');
            if($debug){
                $message = $e->getMessage();
            }else{
                $message = "The Request failed execution";
            }
            $status_code = 500;

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        });
        $this->renderable(function (AuthenticationException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized, please login first!',
            ], 401);
        });
    }
}
