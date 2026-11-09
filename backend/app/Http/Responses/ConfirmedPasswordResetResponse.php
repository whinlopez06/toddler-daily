<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Http\Responses\PasswordResetResponse;

class ConfirmedPasswordResetResponse extends PasswordResetResponse
{
    public function toResponse($request)
    {
        $response = parent::toResponse($request);
        if ($response instanceof JsonResponse) {
            // Fortify resolves this response only after the broker saves the
            // password and consumes the reset token.
            $response->setData([...$response->getData(true), 'password_reset' => true]);
        }
        return $response;
    }
}
