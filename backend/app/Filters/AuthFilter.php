<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Services\AuthService;
use CodeIgniter\API\ResponseTrait;

class AuthFilter implements FilterInterface
{
    use ResponseTrait;

    public function before(RequestInterface $request, $arguments = null)
    {
        // For CLI or non-HTTP requests
        if (!$request instanceof \CodeIgniter\HTTP\IncomingRequest) {
            return;
        }

        $authService = new AuthService();
        
        // 1. Check Session or Header Token
        $token = $request->getHeaderLine('Authorization');
        
        $user = $authService->validateUser($token);
        
        if (!$user) {
            $response = service('response');
            return $response->setJSON([
                'status' => 'error',
                'message' => 'Unauthorized Access. Please login.'
            ])->setStatusCode(401);
        }

        // User is already validated by authService, filter will pass.
        // Controller will re-validate via getUser() helper.
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}
