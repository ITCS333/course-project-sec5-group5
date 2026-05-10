<?php
declare(strict_types=1);

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

try {
    // Handle unsupported HTTP methods
    if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'])) {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    // Route to comment endpoints
    if ($action === 'comments') {
        handleComments($method);
    } elseif ($action === 'comment') {
        handleComment($method);
    } elseif ($action === 'delete_comment') {
        handleDeleteComment($method);
    } else {
        // Route to assignment endpoints
        handleAssignments($method);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

function handleAssignments($method) {
    if ($method === 'GET') {
        handleGetAssignments();
    } elseif ($method === 'POST') {
        handleCreateAssignment();
    } elseif ($method === 'PUT') {
        handleUpdateAssignment();
    } elseif ($method === 'DELETE') {
        handleDeleteAssignment();
    }
}

function handleGetAssignments() {
    $id = $_GET['id'] ?? null;
    $search = $_GET['search'] ?? null;
    
    if ($id) {
        // Get single assignment
        $assignment = getAssignmentById($id);
        if (!$assignment) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Assignment not found']);
            exit;
        }
        echo json_encode(['success' => true, 'data' => $assignment]);
    } else {
        // Get all assignments, optionally filtered by search
        $assignments = getAllAssignments($search);
        echo json_encode(['success' => true, 'data' => $assignments]);
    }
}

function handleCreateAssignment() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($input['title']) || empty($input['title'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Title is required']);
        exit;
    }
    if (!isset($input['description']) || empty($input['description'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Description is required']);
        exit;
    }
    if (!isset($input['due_date']) || empty($input['due_date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Due date is required']);
        exit;
    }
    
    // Validate date format (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['due_date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid date format. Use YYYY-MM-DD']);
        exit;
    }
    
    $id = createAssignment(
        $input['title'],
        $input['description'],
        $input['due_date'],
        $input['files'] ?? []
    );
    
    http_response_code(201);
    echo json_encode(['success' => true, 'id' => $id]);
}

function handleUpdateAssignment() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID is required']);
        exit;
    }
    
    // Validate date format if provided
    if (isset($input['due_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['due_date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid date format. Use YYYY-MM-DD']);
        exit;
    }
    
    $updated = updateAssignment($input['id'], $input);
    if (!$updated) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Assignment not found']);
        exit;
    }
    
    echo json_encode(['success' => true, 'data' => $updated]);
}

function handleDeleteAssignment() {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID is required']);
        exit;
    }
    
    $deleted = deleteAssignment($id);
    if (!$deleted) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Assignment not found']);
        exit;
    }
    
    echo json_encode(['success' => true, 'message' => 'Assignment deleted']);
}

function handleComments($method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }
    
    $assignmentId = $_GET['assignment_id'] ?? null;
    if (!$assignmentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Assignment ID is required']);
        exit;
    }
    
    $comments = getCommentsByAssignmentId($assignmentId);
    echo json_encode(['success' => true, 'data' => $comments]);
}

function handleComment($method) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['assignment_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Assignment ID is required']);
        exit;
    }
    
    if (!isset($input['text']) || empty($input['text'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Comment text is required']);
        exit;
    }
    
    if (!assignmentExists($input['assignment_id'])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Assignment not found']);
        exit;
    }
    
    $commentId = createComment(
        $input['assignment_id'],
        $input['author'] ?? 'Anonymous',
        $input['text']
    );
    
    http_response_code(201);
    echo json_encode(['success' => true, 'id' => $commentId]);
}

function handleDeleteComment($method) {
    if ($method !== 'DELETE') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }
    
    $commentId = $_GET['comment_id'] ?? null;
    if (!$commentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Comment ID is required']);
        exit;
    }
    
    $deleted = deleteComment($commentId);
    if (!$deleted) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Comment not found']);
        exit;
    }
    
    echo json_encode(['success' => true, 'message' => 'Comment deleted']);
}

// Placeholder database functions - implement these based on your data storage
function getAllAssignments($search = null) { return []; }
function getAssignmentById($id) { return null; }
function createAssignment($title, $description, $dueDate, $files) { return 0; }
function updateAssignment($id, $data) { return null; }
function deleteAssignment($id) { return false; }
function getCommentsByAssignmentId($assignmentId) { return []; }
function createComment($assignmentId, $author, $text) { return 0; }
function deleteComment($commentId) { return false; }
function assignmentExists($id) { return false; }
