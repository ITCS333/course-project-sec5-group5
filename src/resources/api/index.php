<?php
/**
 * Course Resources API
 * 
 * 
 * Database Table Structures (for reference):
 * 
 * Table: resources
 * Columns:
 *   - id (INT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
 *   - title (VARCHAR(255), NOT NULL)
 *   - description (TEXT, nullable)
 *   - link (VARCHAR(500), NOT NULL)
 *   - created_at (TIMESTAMP)
 * 
 * Table: comments_resource
 * Columns:
 *   - id (INT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
 *   - resource_id (INT UNSIGNED, FOREIGN KEY references resources.id, CASCADE DELETE)
 *   - author (VARCHAR(100), NOT NULL)
 *   - text (TEXT, NOT NULL)
 *   - created_at (TIMESTAMP)
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

// Set headers for JSON response and CORS
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include the database connection file
require_once './config/Database.php';

// Get the PDO database connection
$database = new Database();
$db = $database->getConnection();

// Get the HTTP request method
$method = $_SERVER['REQUEST_METHOD'];

// Get the request body for POST and PUT requests
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

// Parse query parameters
$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$resource_id = $_GET['resource_id'] ?? null;
$comment_id = $_GET['comment_id'] ?? null;

// ============================================================================
// RESOURCE FUNCTIONS
// ============================================================================

function getAllResources($db) {
    $sql = "SELECT id, title, description, link, created_at FROM resources";

    $params = [];
    if (!empty($_GET['search'])) {
        $sql .= " WHERE title LIKE :search OR description LIKE :search";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $allowedSort = ['title', 'created_at'];
    $sort = in_array($_GET['sort'] ?? '', $allowedSort) ? $_GET['sort'] : 'created_at';

    $order = strtolower($_GET['order'] ?? 'desc');
    $order = in_array($order, ['asc', 'desc']) ? $order : 'desc';

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $resources]);
}

function getResourceById($db, $resourceId) {
    if (!is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $stmt = $db->prepare(
        "SELECT id, title, description, link, created_at FROM resources WHERE id = ?"
    );
    $stmt->execute([$resourceId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resource) {
        sendResponse(['success' => true, 'data' => $resource]);
    } else {
        sendResponse(['success' => false, 'message' => 'Resource not found'], 404);
    }
}

function createResource($db, $data) {
    $check = validateRequiredFields($data, ['title', 'link']);
    if (!$check['valid']) {
        sendResponse(['success' => false, 'message' => 'Missing fields', 'fields' => $check['missing']], 400);
    }

    $title = sanitizeInput($data['title']);
    $description = sanitizeInput($data['description'] ?? '');
    $link = sanitizeInput($data['link']);

    if (!validateUrl($link)) {
        sendResponse(['success' => false, 'message' => 'Invalid URL'], 400);
    }

    $stmt = $db->prepare(
        "INSERT INTO resources (title, description, link) VALUES (?, ?, ?)"
    );
    $stmt->execute([$title, $description, $link]);

    sendResponse([
        'success' => true,
        'message' => 'Resource created',
        'id' => $db->lastInsertId()
    ], 201);
}

function updateResource($db, $data) {
    if (empty($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $stmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $stmt->execute([$data['id']]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found'], 404);
    }

    $fields = [];
    $values = [];

    foreach (['title', 'description', 'link'] as $field) {
        if (!empty($data[$field])) {
            if ($field === 'link' && !validateUrl($data[$field])) {
                sendResponse(['success' => false, 'message' => 'Invalid URL'], 400);
            }
            $fields[] = "$field = ?";
            $values[] = sanitizeInput($data[$field]);
        }
    }

    if (empty($fields)) {
        sendResponse(['success' => false, 'message' => 'No fields to update'], 400);
    }

    $values[] = $data['id'];
    $sql = "UPDATE resources SET " . implode(', ', $fields) . " WHERE id = ?";

    $stmt = $db->prepare($sql);
    $stmt->execute($values);

    sendResponse(['success' => true, 'message' => 'Resource updated']);
}

function deleteResource($db, $resourceId) {
    if (!is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");
    $stmt->execute([$resourceId]);

    if ($stmt->rowCount()) {
        sendResponse(['success' => true, 'message' => 'Resource deleted']);
    } else {
        sendResponse(['success' => false, 'message' => 'Resource not found'], 404);
    }
}

// ============================================================================
// COMMENT FUNCTIONS
// ============================================================================

function getCommentsByResourceId($db, $resourceId) {
    if (!is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID'], 400);
    }

    $stmt = $db->prepare(
        "SELECT id, resource_id, author, text, created_at
         FROM comments_resource
         WHERE resource_id = ?
         ORDER BY created_at ASC"
    );
    $stmt->execute([$resourceId]);

    sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function createComment($db, $data) {
    $check = validateRequiredFields($data, ['resource_id', 'author', 'text']);
    if (!$check['valid']) {
        sendResponse(['success' => false, 'message' => 'Missing fields'], 400);
    }

    if (!is_numeric($data['resource_id'])) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID'], 400);
    }

    $stmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $stmt->execute([$data['resource_id']]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found'], 404);
    }

    $stmt = $db->prepare(
        "INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)"
    );
    $stmt->execute([
        $data['resource_id'],
        sanitizeInput($data['author']),
        sanitizeInput($data['text'])
    ]);

    sendResponse([
        'success' => true,
        'message' => 'Comment added',
        'id' => $db->lastInsertId()
    ], 201);
}

function deleteComment($db, $commentId) {
    if (!is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid comment ID'], 400);
    }

    $stmt = $db->prepare("DELETE FROM comments_resource WHERE id = ?");
    $stmt->execute([$commentId]);

    if ($stmt->rowCount()) {
        sendResponse(['success' => true, 'message' => 'Comment deleted']);
    } else {
        sendResponse(['success' => false, 'message' => 'Comment not found'], 404);
    }
}

// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {

    if ($method === 'GET') {

        if ($action === 'comments' && $resource_id) {
            getCommentsByResourceId($db, $resource_id);
        } elseif ($id) {
            getResourceById($db, $id);
        } else {
            getAllResources($db);
        }

    } elseif ($method === 'POST') {

        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createResource($db, $data);
        }

    } elseif ($method === 'PUT') {

        updateResource($db, $data);

    } elseif ($method === 'DELETE') {

        if ($action === 'delete_comment' && $comment_id) {
            deleteComment($db, $comment_id);
        } else {
            deleteResource($db, $id);
        }

    } else {
        sendResponse(['success' => false, 'message' => 'Method Not Allowed'], 405);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Server error'], 500);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validateRequiredFields($data, $requiredFields) {
    $missing = [];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            $missing[] = $field;
        }
    }
    return [
        'valid' => empty($missing),
        'missing' => $missing
    ];
}
?>
