<?php

namespace App\Controllers\Admin;

use CodeIgniter\RESTful\ResourceController;
use App\Models\ActivityLogModel;
use App\Services\AuthService;

class LogController extends ResourceController
{
    protected $modelName = 'App\Models\ActivityLogModel';
    protected $format    = 'json';
    protected $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    private function validateUser()
    {
        $token = $this->request->getHeaderLine('Authorization');
        return $this->authService->validateUser($token);
    }

    /**
     * Internal helper to build query with filters
     */
    private function buildQuery($search, $action, $startDate, $endDate)
    {
        $model = new ActivityLogModel();
        $model->orderBy('created_at', 'DESC');

        if ($search) {
            $model->groupStart()
                  ->like('user_name', $search)
                  ->orLike('details', $search)
                  ->groupEnd();
        }

        if ($action && $action !== 'ALL') {
            $model->where('action', $action);
        }

        if ($startDate) {
            $model->where('created_at >=', $startDate . ' 00:00:00');
        }

        if ($endDate) {
            $model->where('created_at <=', $endDate . ' 23:59:59');
        }
        
        return $model;
    }

    /**
     * List all activity logs (Data Table Source)
     */
    public function index()
    {
        $user = $this->validateUser();
        if (!$user || $user->role !== 'admin') {
            return $this->failForbidden('Access Denied: Admins only.');
        }

        $page = $this->request->getVar('page') ?? 1;
        $perPage = $this->request->getVar('limit') ?? 20;
        $search = $this->request->getVar('search');
        
        // Filters
        $action = $this->request->getVar('action');
        $startDate = $this->request->getVar('start_date');
        $endDate = $this->request->getVar('end_date');

        $model = $this->buildQuery($search, $action, $startDate, $endDate);
        
        $data = $model->paginate($perPage, 'default', $page);

        return $this->respond([
            'data' => $data,
            'pager' => $model->pager->getDetails()
        ]);
    }

    /**
     * Export Logs to CSV
     */
    public function export()
    {
        $user = $this->validateUser();
        if (!$user || $user->role !== 'admin') {
            return $this->failForbidden('Access Denied: Admins only.');
        }

        $search = $this->request->getVar('search');
        $action = $this->request->getVar('action');
        $startDate = $this->request->getVar('start_date');
        $endDate = $this->request->getVar('end_date');

        $model = $this->buildQuery($search, $action, $startDate, $endDate);
        
        // Fetch all matching records (no pagination)
        $data = $model->findAll();

        $filename = 'activity_logs_' . date('Ymd_His') . '.csv';

        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=\"$filename\"");

        $output = fopen("php://output", "w");

        // Header Row
        fputcsv($output, ['ID', 'Date', 'User', 'Role', 'Action', 'Details', 'IP Address']);

        foreach ($data as $row) {
            fputcsv($output, [
                $row['id'],
                $row['created_at'],
                $row['user_name'],
                $row['role'],
                $row['action'],
                $row['details'],
                $row['ip_address']
            ]);
        }

        fclose($output);
        exit;
    }

    public function show($id = null)
    {
        $user = $this->validateUser();
        if (!$user || $user->role !== 'admin') {
            return $this->failForbidden('Access Denied: Admins only.');
        }

        $model = new ActivityLogModel();
        $data = $model->find($id);
        if (!$data) return $this->failNotFound('Log not found');
        return $this->respond($data);
    }
}
