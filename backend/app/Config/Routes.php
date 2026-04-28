<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// APP ROUTES
// --------------------------------------------------------------------

$routes->group('OJT2', function($routes) {
    $routes->get('/', 'Home::index');

    // --- CORS PRE-FLIGHT ---
    $routes->options('(:any)', static function () {
        return service('response')->setStatusCode(204);
    });

    // --- AUTH ROUTES ---
    $routes->post('auth/login', 'AuthController::login');
    $routes->get('auth/verify', 'AuthController::verify');
    $routes->post('auth/logout', 'AuthController::logout', ['filter' => 'auth']);
    $routes->post('auth/update-profile', 'AuthController::updateProfile', ['filter' => 'auth']);
    $routes->post('auth/register', 'AuthController::register', ['filter' => 'auth']);

    // --- ADMIN ROUTES ---
    $routes->group('admin', ['filter' => 'auth'], function ($routes) {
        $routes->get('users', 'AdminController::index');
        $routes->match(['patch', 'post'], 'users/(:num)/status', 'AdminController::updateStatus/$1');
        $routes->post('reset-password', 'AdminController::resetPassword');
    });

    // --- API ROUTES ---
    $routes->group('api', function ($routes) {
        $routes->get('notifications', 'NotificationController::index', ['filter' => 'auth']);
        $routes->post('notifications/read', 'NotificationController::markAsRead', ['filter' => 'auth']);
        $routes->post('comments', 'ResearchController::addComment', ['filter' => 'auth']);
        $routes->post('assistant/log', 'AssistantController::logSearch', ['filter' => 'auth']);
        $routes->post('assistant/feedback', 'AssistantController::feedback', ['filter' => 'auth']);
        $routes->get('assistant/analytics', 'AssistantController::analytics', ['filter' => 'auth']);

        // --- ADMIN LOGS ---
        $routes->group('logs', ['filter' => 'auth'], function ($routes) {
            $routes->get('export', 'Admin\LogController::export');
            $routes->get('/', 'Admin\LogController::index');
            $routes->get('(:segment)', 'Admin\LogController::show/$1');
        });
    });

    // --- RESEARCH ROUTES ---
    $routes->group('research', function ($routes) {
        $routes->get('user-stats/(:num)', 'ResearchController::userStats/$1', ['filter' => 'auth']);
        $routes->get('stats', 'ResearchController::stats');
        $routes->get('masterlist', 'ResearchController::masterlist', ['filter' => 'auth']);
        $routes->get('view-pdf/(:num)', 'ResearchController::viewPdf/$1');

        $routes->get('/', 'ResearchController::index');
        $routes->get('top-viewed', 'ResearchController::topViewed');
        $routes->get('similar-titles', 'ResearchController::similarTitles', ['filter' => 'auth']);
        $routes->get('(:num)', 'ResearchController::show/$1', ['filter' => 'auth']);
        $routes->get('archived', 'ResearchController::archived', ['filter' => 'auth']);
        $routes->get('my-submissions', 'ResearchController::mySubmissions', ['filter' => 'auth']);
        $routes->get('my-archived', 'ResearchController::myArchived', ['filter' => 'auth']);
        $routes->get('pending', 'ResearchController::pending', ['filter' => 'auth']);
        $routes->get('rejected', 'ResearchController::rejectedList', ['filter' => 'auth']);

        $routes->get('comments/(:num)', 'ResearchController::getComments/$1', ['filter' => 'auth']);

        $routes->post('/', 'ResearchController::create', ['filter' => 'auth']);
        $routes->put('(:num)', 'ResearchController::update/$1', ['filter' => 'auth']);
        $routes->post('(:num)', 'ResearchController::update/$1', ['filter' => 'auth']);
        
        $routes->match(['patch', 'post'], '(:num)/approve', 'ResearchController::approve/$1', ['filter' => 'auth']);
        $routes->match(['patch', 'post'], '(:num)/reject', 'ResearchController::reject/$1', ['filter' => 'auth']);
        $routes->match(['patch', 'post'], '(:num)/archive', 'ResearchController::archive/$1', ['filter' => 'auth']);
        $routes->match(['patch', 'post'], '(:num)/restore', 'ResearchController::restore/$1', ['filter' => 'auth']);
        $routes->match(['delete', 'post'], '(:num)/delete', 'ResearchController::delete/$1', ['filter' => 'auth']);
        $routes->match(['patch', 'post'], '(:num)/extend-deadline', 'ResearchController::extendDeadline/$1', ['filter' => 'auth']);
        $routes->post('(:num)/view', 'ResearchController::trackView/$1');
        
        $routes->post('bulk-access-level', 'ResearchController::bulkAccessLevel', ['filter' => 'auth']);
        $routes->post('import-csv', 'ResearchController::importCsv', ['filter' => 'auth']);
        $routes->post('import-single', 'ResearchController::importSingle', ['filter' => 'auth']);
        $routes->post('bulk-upload-pdfs', 'ResearchController::uploadBulkPdfs', ['filter' => 'auth']);
        $routes->post('preview-bulk-pdfs', 'ResearchController::previewBulkPdfs', ['filter' => 'auth']);
        $routes->post('preview-csv', 'ResearchController::previewCsv', ['filter' => 'auth']);
    });
});
