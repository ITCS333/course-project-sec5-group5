<?php
/**
* Assignment Management API
* (Keep the original comment block here as it was)
*/

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../common/db.php';
$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true) ?? [];

$action       = $_GET['action']       ?? null;
$id           = $_GET['id']           ?? null;
$assignmentId = $_GET['assignment_id'] ?? null;
$commentId    = $_GET['comment_id']    ?? null;

// ============================================================================
// ASSIGNMENT FUNCTIONS
// ============================================================================

function getAllAssignments(PDO $db): void
{
    $sql = "SELECT id, title, description, due_date, files, created_at, updated_at FROM assignments";
    $params = [];

    if (!empty($_GET['search'])) {
        $sql .= " WHERE title LIKE :search OR description LIKE :search";
        $params['search'] = '%' . $_GET['search'] . '%';
    }

    $sortWhitelist = ['title', 'due_date', 'created_at'];
    $sort = in_array($_GET['sort'] ?? '', $sortWhitelist) ? $_GET['sort'] : 'due_date';
    $order = (isset($_GET['order']) && strtolower($_GET['order']) === 'desc') ? 'DESC' : 'ASC';

    $sql .= " ORDER BY $sort $order";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($assignments as &$row) {
        $row['files'] = json_decode($row['files'], true) ?? [];
    }

    sendResponse(['success' => true, 'data' => $assignments]);
}

function getAssignmentById(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $stmt = $db->prepare("SELECT id, title, description, due_date, files, created_at, updated_at FROM assignments WHERE id = ?");
    $stmt->execute([$id]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($assignment) {
        $assignment['files'] = json_decode($assignment['files'], true) ?? [];
        sendResponse(['success' => true, 'data' => $assignment]);
    } else {
        sendResponse(['success' => false, 'message' => 'Not Found'], 404);
    }
}

function createAssignment(PDO $db, array $data): void
{
    if (empty($data['title']) || empty($data['description']) || empty($data['due_date'])) {
        sendResponse(['success' => false, 'message' => 'Missing fields'], 400);
    }

    if (!validateDate($data['due_date'])) {
        sendResponse(['success' => false, 'message' => 'Invalid date format'], 400);
    }

    $files = json_encode($data['files'] ?? []);
    $stmt = $db->prepare("INSERT INTO assignments (title, description, due_date, files) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        sanitizeInput($data['title']),
        sanitizeInput($data['description']),
        $data['due_date'],
        $files
    ]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'id' => (int)$db->lastInsertId()], 201);
    } else {
        sendResponse(['success' => false], 500);
    }
}

function updateAssignment(PDO $db, array $data): void
{
    if (empty($data['id'])) {
        sendResponse(['success' => false, 'message' => 'ID required'], 400);
    }

    $stmt = $db->prepare("SELECT id FROM assignments WHERE id = ?");
    $stmt->execute([$data['id']]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Not Found'], 404);
    }

    $fields = [];
    $params = [];
    $allowed = ['title', 'description', 'due_date', 'files'];

    foreach ($allowed as $field) {
        if (isset($data[$field])) {
            if ($field === 'due_date' && !validateDate($data[$field])) {
                sendResponse(['success' => false, 'message' => 'Invalid date'], 400);
            }
            $fields[] = "$field = ?";
            $params[] = ($field === 'files') ? json_encode($data[$field]) : sanitizeInput($data[$field]);
        }
    }

    if (empty($fields)) {
        sendResponse(['success' => false, 'message' => 'No fields to update'], 400);
    }

    $params[] = $data['id'];
    $sql = "UPDATE assignments SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    
    if ($stmt->execute($params)) {
        sendResponse(['success' => true]);
    } else {
        sendResponse(['success' => false], 500);
    }
}

function deleteAssignment(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false], 400);
    }

    $stmt = $db->prepare("DELETE FROM assignments WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true]);
    } else {
        sendResponse(['success' => false], 404);
    }
}

// ============================================================================
// COMMENTS FUNCTIONS
// ============================================================================

function getCommentsByAssignment(PDO $db, $assignmentId): void
{
    if (!$assignmentId || !is_numeric($assignmentId)) {
        sendResponse(['success' => false], 400);
    }

    $stmt = $db->prepare("SELECT id, assignment_id, author, text, created_at FROM comments_assignment WHERE assignment_id = ? ORDER BY created_at ASC");
    $stmt->execute([$assignmentId]);
    sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function createComment(PDO $db, array $data): void
{
    if (empty($data['assignment_id']) || empty($data['author']) || empty(trim($data['text'] ?? ''))) {
        sendResponse(['success' => false], 400);
    }

    $stmt = $db->prepare("SELECT id FROM assignments WHERE id = ?");
    $stmt->execute([$data['assignment_id']]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false], 404);
    }

    $stmt = $db->prepare("INSERT INTO comments_assignment (assignment_id, author, text) VALUES (?, ?, ?)");
    $stmt->execute([$data['assignment_id'], sanitizeInput($data['author']), sanitizeInput($data['text'])]);
    $newId = $db->lastInsertId();

    if ($newId) {
        $stmt = $db->prepare("SELECT * FROM comments_assignment WHERE id = ?");
        $stmt->execute([$newId]);
        sendResponse(['success' => true, 'id' => (int)$newId, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)], 201);
    } else {
        sendResponse(['success' => false], 500);
    }
}

function deleteComment(PDO $db, $commentId): void
{
    if (!$commentId || !is_numeric($commentId)) {
        sendResponse(['success' => false], 400);
    }

    $stmt = $db->prepare("DELETE FROM comments_assignment WHERE id = ?");
    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true]);
    } else {
        sendResponse(['success' => false], 404);
    }
}

// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    if ($method === 'GET') {
        if ($action === 'comments') {
            getCommentsByAssignment($db, $assignmentId);
        } elseif ($id) {
            getAssignmentById($db, $id);
        } else {
            getAllAssignments($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createAssignment($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateAssignment($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteAssignment($db, $id);
        }
    } else {
        sendResponse(['success' => false, 'message' => 'Method Not Allowed'], 405);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false], 500);
} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false], 500);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function validateDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
