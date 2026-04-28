<?php

namespace App\Controllers;

use App\Models\AssistantSearchLogModel;
use App\Services\AuthService;
use CodeIgniter\API\ResponseTrait;

class AssistantController extends BaseController
{
    use ResponseTrait;

    private AssistantSearchLogModel $logModel;
    private AuthService $authService;

    public function __construct()
    {
        $this->logModel = new AssistantSearchLogModel();
        $this->authService = new AuthService();
    }

    private function getCurrentUser()
    {
        $token = $this->request->getHeaderLine('Authorization');
        return $this->authService->validateUser($token);
    }

    public function logSearch()
    {
        $input = $this->request->getJSON(true);
        if (!is_array($input)) {
            $input = $this->request->getPost();
        }

        $query = trim((string) ($input['query'] ?? ''));
        if ($query === '') {
            return $this->fail('query is required', 400);
        }

        if (mb_strlen($query) > 500) {
            return $this->fail('query is too long (max 500 chars)', 400);
        }

        $effectiveQuery = trim((string) ($input['effective_query'] ?? $query));
        $mode = strtolower(trim((string) ($input['mode'] ?? 'broad')));
        if (!in_array($mode, ['specific', 'broad'], true)) {
            $mode = 'broad';
        }

        $resultCount = max(0, (int) ($input['result_count'] ?? 0));
        $latencyMs = max(0, (int) ($input['latency_ms'] ?? 0));

        $confidence = null;
        if (isset($input['confidence']) && $input['confidence'] !== '') {
            $confidence = (float) $input['confidence'];
            if ($confidence < 0) {
                $confidence = 0.0;
            }
            if ($confidence > 100) {
                $confidence = 100.0;
            }
        }

        $isStrongMatch = !empty($input['is_strong_match']) ? 1 : 0;

        $topIds = $input['top_research_ids'] ?? [];
        if (!is_array($topIds)) {
            $topIds = [];
        }
        $topIds = array_values(array_unique(array_map('intval', $topIds)));
        $topIds = array_values(array_filter($topIds, static fn (int $id): bool => $id > 0));
        $topIds = array_slice($topIds, 0, 20);

        $user = $this->getCurrentUser();
        $record = [
            'user_id' => $user ? (int) $user->id : null,
            'query' => $query,
            'effective_query' => mb_substr($effectiveQuery, 0, 500),
            'mode' => $mode,
            'result_count' => $resultCount,
            'top_research_ids' => empty($topIds) ? null : json_encode($topIds),
            'latency_ms' => $latencyMs,
            'confidence' => $confidence,
            'is_strong_match' => $isStrongMatch,
            'ip_address' => $this->request->getIPAddress(),
            'user_agent' => mb_substr((string) $this->request->getUserAgent(), 0, 255),
        ];

        $id = $this->logModel->insert($record, true);
        if (!$id) {
            return $this->failServerError('Failed to create assistant log');
        }

        return $this->respondCreated([
            'status' => 'success',
            'log_id' => (int) $id,
        ]);
    }

    public function feedback()
    {
        $input = $this->request->getJSON(true);
        if (!is_array($input)) {
            $input = $this->request->getPost();
        }

        $logId = (int) ($input['log_id'] ?? 0);
        if ($logId <= 0) {
            return $this->fail('log_id is required', 400);
        }

        $feedback = strtolower(trim((string) ($input['feedback'] ?? '')));
        if (!in_array($feedback, ['helpful', 'not_helpful'], true)) {
            return $this->fail('feedback must be helpful or not_helpful', 400);
        }

        $note = trim((string) ($input['note'] ?? ''));
        if (mb_strlen($note) > 255) {
            return $this->fail('note is too long (max 255 chars)', 400);
        }

        $row = $this->logModel->find($logId);
        if (!$row) {
            return $this->failNotFound('Assistant log not found');
        }

        $user = $this->getCurrentUser();
        $ip = $this->request->getIPAddress();
        $isOwner = false;
        if ($user && !empty($row['user_id'])) {
            $isOwner = ((int) $row['user_id'] === (int) $user->id);
        } elseif (!$user && !empty($row['ip_address'])) {
            $isOwner = ((string) $row['ip_address'] === (string) $ip);
        }

        $isAdmin = $user && ($user->role === 'admin');
        if (!$isOwner && !$isAdmin) {
            return $this->failForbidden('You cannot update feedback for this log');
        }

        $updated = $this->logModel->update($logId, [
            'feedback' => $feedback,
            'feedback_note' => $note !== '' ? $note : null,
        ]);

        if (!$updated) {
            return $this->failServerError('Failed to save feedback');
        }

        return $this->respond([
            'status' => 'success',
            'message' => 'Feedback saved',
        ]);
    }

    public function analytics()
    {
        $user = $this->getCurrentUser();
        if (!$user || $user->role !== 'admin') {
            return $this->failForbidden('Access Denied');
        }

        $slowThreshold = 1500;
        $db = $this->logModel->db;

        $totals = $db->table('assistant_search_logs')
            ->select('COUNT(*) AS total_queries, SUM(CASE WHEN result_count = 0 THEN 1 ELSE 0 END) AS zero_results, AVG(latency_ms) AS avg_latency, SUM(CASE WHEN latency_ms >= ' . $slowThreshold . ' THEN 1 ELSE 0 END) AS slow_queries')
            ->get()
            ->getRowArray();

        $topQueries = $db->table('assistant_search_logs')
            ->select('effective_query, COUNT(*) AS count')
            ->where('effective_query IS NOT NULL')
            ->where('effective_query !=', '')
            ->groupBy('effective_query')
            ->orderBy('count', 'DESC')
            ->limit(10)
            ->get()
            ->getResultArray();

        $zeroResultQueries = $db->table('assistant_search_logs')
            ->select('effective_query, COUNT(*) AS count')
            ->where('result_count', 0)
            ->where('effective_query IS NOT NULL')
            ->where('effective_query !=', '')
            ->groupBy('effective_query')
            ->orderBy('count', 'DESC')
            ->limit(10)
            ->get()
            ->getResultArray();

        $feedback = $db->table('assistant_search_logs')
            ->select('feedback, COUNT(*) AS count')
            ->where('feedback IS NOT NULL')
            ->groupBy('feedback')
            ->get()
            ->getResultArray();

        $slowQueries = $db->table('assistant_search_logs')
            ->select('id, effective_query, latency_ms, created_at')
            ->where('latency_ms >=', $slowThreshold)
            ->orderBy('latency_ms', 'DESC')
            ->limit(10)
            ->get()
            ->getResultArray();

        return $this->respond([
            'status' => 'success',
            'summary' => [
                'total_queries' => (int) ($totals['total_queries'] ?? 0),
                'zero_results' => (int) ($totals['zero_results'] ?? 0),
                'avg_latency_ms' => round((float) ($totals['avg_latency'] ?? 0), 2),
                'slow_queries' => (int) ($totals['slow_queries'] ?? 0),
                'slow_threshold_ms' => $slowThreshold,
            ],
            'top_queries' => $topQueries,
            'zero_result_queries' => $zeroResultQueries,
            'feedback' => $feedback,
            'slowest_queries' => $slowQueries,
        ]);
    }
}
