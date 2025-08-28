<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LocalController extends Controller  // for handling errors
{
    public function sendResponse($result, $data_name = "data")
    {
        $response = [
            'status' => 'success',
            $data_name => $result
        ];

        return response()->json($response, 200);
    }

    public function sendError($errorMessage = [], $code = 400)
    {
        return  response()->json(
            [
                'success' => false,
                'message' => $errorMessage
            ],
            $code
        );

        if (!empty($errorMessage)) {
            $response['error_message'] = $errorMessage;
        }

        return response()->json($response, $code);
    }
}
