<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';

use App\Auth;
use App\Database;
use App\Env;

Env::load(__DIR__ . '/../.env');
$pdo = Database::connection();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];

if ($uri === '/' && $method === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/app.html');
    exit;
}

if ($uri === '/api/login' && $method === 'POST') {
    $token = Auth::login($pdo, (string) ($input['email'] ?? ''), (string) ($input['password'] ?? ''));
    if (!$token) {
        http_response_code(401);
        echo json_encode(['message' => 'Invalid credentials']);
        exit;
    }

    echo json_encode(['token' => $token]);
    exit;
}

$userId = Auth::userIdFromToken($_SERVER['HTTP_AUTHORIZATION'] ?? null);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['message' => 'Unauthorized']);
    exit;
}

if ($uri === '/api/tasks' && $method === 'POST') {
    $stmt = $pdo->prepare('INSERT INTO tasks(user_id, title, description, status, due_date, created_at, updated_at, deleted_at)
        VALUES(:user_id, :title, :description, :status, :due_date, :created_at, :updated_at, NULL)');
    $now = date('Y-m-d H:i:s');
    $stmt->execute([
        'user_id' => $userId,
        'title' => (string) ($input['title'] ?? ''),
        'description' => (string) ($input['description'] ?? ''),
        'status' => in_array(($input['status'] ?? 'todo'), ['todo', 'in_progress', 'done'], true) ? $input['status'] : 'todo',
        'due_date' => $input['due_date'] ?? null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    http_response_code(201);
    echo json_encode(['id' => (int) $pdo->lastInsertId()]);
    exit;
}

if ($uri === '/api/tasks' && $method === 'GET') {
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(50, max(1, (int) ($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    $filters = ['user_id = :user_id', 'deleted_at IS NULL'];
    $params = ['user_id' => $userId];

    if (!empty($_GET['status'])) {
        $filters[] = 'status = :status';
        $params['status'] = $_GET['status'];
    }

    if (!empty($_GET['q'])) {
        $filters[] = '(title LIKE :q OR description LIKE :q)';
        $params['q'] = '%' . $_GET['q'] . '%';
    }

    $where = implode(' AND ', $filters);
    $count = $pdo->prepare("SELECT COUNT(*) as total FROM tasks WHERE {$where}");
    $count->execute($params);
    $total = (int) $count->fetch()['total'];

    $sql = "SELECT id, title, description, status, due_date, created_at, updated_at
            FROM tasks WHERE {$where} ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode([
        'data' => $stmt->fetchAll(),
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ],
    ]);
    exit;
}

if (preg_match('#^/api/tasks/(\d+)$#', $uri, $matches)) {
    $taskId = (int) $matches[1];

    if ($method === 'PUT') {
        $stmt = $pdo->prepare('UPDATE tasks
            SET title = :title, description = :description, status = :status, due_date = :due_date, updated_at = :updated_at
            WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL');
        $stmt->execute([
            'title' => (string) ($input['title'] ?? ''),
            'description' => (string) ($input['description'] ?? ''),
            'status' => in_array(($input['status'] ?? 'todo'), ['todo', 'in_progress', 'done'], true) ? $input['status'] : 'todo',
            'due_date' => $input['due_date'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
            'id' => $taskId,
            'user_id' => $userId,
        ]);

        echo json_encode(['updated' => $stmt->rowCount() > 0]);
        exit;
    }

    if ($method === 'DELETE') {
        $stmt = $pdo->prepare('UPDATE tasks SET deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL');
        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            'deleted_at' => $now,
            'updated_at' => $now,
            'id' => $taskId,
            'user_id' => $userId,
        ]);

        echo json_encode(['deleted' => $stmt->rowCount() > 0]);
        exit;
    }
}

http_response_code(404);
echo json_encode(['message' => 'Not found']);
