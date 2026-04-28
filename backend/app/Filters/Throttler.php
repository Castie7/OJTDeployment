<?php namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Throttler implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $throttler = \Config\Services::throttler();

        // Limit to 60 requests per minute per IP address
        if ($throttler->check(md5($request->getIPAddress()), 60, 60) === false) {
            return \Config\Services::response()->setStatusCode(429)->setBody('Too Many Requests');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}