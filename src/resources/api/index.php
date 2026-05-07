<?php
/**
 * Course Resources API
 *
 * (comments kept exactly as requested)
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

// TODO: Set headers for JSON response and CORS
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// TODO: Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// TODO: Include the database connection file
require_once './config/Database.php';

// TODO: Get the PDO database connection
$database = new Database();
$db = $database->getConnection();

// TODO: Get the HTTP request method
$method = $_SERVER['REQUEST_METHOD'];

// TODO: Get the request body for POST and PUT requests
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true) ?? [];

// TODO: Parse query parameters from $_GET
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

    $sort = $_GET['sort'] ?? 'created_at';
    $order = strtolower($_GET['order'] ?? 'desc');

    $allowedSort = ['title', 'created_at'];
    $allowedOrder = ['asc', 'desc'];

    if (!in_array($sort, $allowedSort)) $sort = 'created_at';
    if (!in_array($order, $allowedOrder)) $order = 'desc';

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }

    $stmt->execute();
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $resources]);
}

function getResourceById($db, $resourceId) {
    if (!is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource id'], 400);
    }

    $stmt = $db->prepare(
        "SELECT id, title, description, link, created_at FROM resources WHERE id = ?"
    );
    $stmt->execute([$resourceId]);

    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resource) {
        sendResponse(['success' => true, 'data' => $resource]);
    } else {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }
}

function createResource($db, $data) {
    $check = validateRequiredFields($data, ['title', 'link']);
    if (!$check['valid']) {
        sendResponse(['success' => false, 'message' => 'Missing fields'], 400);
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

    sendResponse(
        ['success' => true, 'id' => (int)$db->lastInsertId()],
        201
    );
}

function updateResource($db, $data) {
    if (empty($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Missing id'], 400);
    }

    $id = $data['id'];

    $stmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }

    $fields = [];
    $values = [];

    if (isset($data['title'])) {
        $fields[] = "title = ?";
        $values[] = sanitizeInput($data['title']);
    }
    if (isset($data['description'])) {
        $fields[] = "description = ?";
        $values[] = sanitizeInput($data['description']);
    }
    if (isset($data['link'])) {
        if (!validateUrl($data['link'])) {
            sendResponse(['success' => false, 'message' => 'Invalid URL'], 400);
        }
        $fields[] = "link = ?";
        $values[] = sanitizeInput($data['link']);
    }

    if (empty($fields)) {
        sendResponse(['success' => false, 'message' => 'Nothing to update'], 400);
    }

    $values[] = $id;
    $sql = "UPDATE resources SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($values);

    sendResponse(['success' => true, 'message' => 'Resource updated successfully.']);
}

function deleteResource($db, $resourceId) {
    if (!is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid id'], 400);
    }

    $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");
    $stmt->execute([$resourceId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Resource deleted successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }
}


// ============================================================================
// COMMENT FUNCTIONS
// ============================================================================

function getCommentsByResourceId($db, $resourceId) {
    if (!is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource id'], 400);
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

    $stmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $stmt->execute([$data['resource_id']]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }

    $stmt = $db->prepare(
        "INSERT INTO comments_resource (resource_id, author, text)
         VALUES (?, ?, ?)"
    );
    $stmt->execute([
        $data['resource_id'],
        sanitizeInput($data['author']),
        sanitizeInput($data['text'])
    ]);

    sendResponse(
        ['success' => true, 'id' => (int)$db->lastInsertId()],
        201
    );
}

function deleteComment($db, $commentId) {
    if (!is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid comment id'], 400);
    }

    $stmt = $db->prepare("DELETE FROM comments_resource WHERE id = ?");
    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Comment deleted successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Comment not found.'], 404);
    }
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    if ($method === 'GET') {
        if ($action === 'comments') {
            getCommentsByResourceId($db, $resource_id);
        } elseif ($id !== null) {
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
        if ($action === 'delete_comment') {
            deleteComment($db, $comment_id);
        } else {
            deleteResource($db, $id);
        }

    } else {
        sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
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
    return ['valid' => empty($missing), 'missing' => $missing];
}
?>
