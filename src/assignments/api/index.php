<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
   http_response_code(200);
   exit;
}

require_once __DIR__ . '/../../../includes/db.php';

$db = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'];
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true) ?? [];

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$assignmentId = $_GET['assignment_id'] ?? null;
$commentId = $_GET['comment_id'] ?? null;

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
} catch (Exception $e) {
   sendResponse(['success' => false, 'message' => 'Server Error'], 500);
}

// --- Functions ---

function getAllAssignments(PDO $db) {
   $search = $_GET['search'] ?? '';
   $sort = in_array($_GET['sort'] ?? '', ['title', 'due_date', 'created_at']) ? $_GET['sort'] : 'due_date';
   $order = strtoupper($_GET['order'] ?? '') === 'DESC' ? 'DESC' : 'ASC';

   $query = "SELECT id, title, description, due_date, files, created_at, updated_at FROM assignments";
   if ($search) {
       $query .= " WHERE title LIKE :search OR description LIKE :search";
   }
   $query .= " ORDER BY $sort $order";

   $stmt = $db->prepare($query);
   if ($search) {
       $stmt->bindValue(':search', '%' . $search . '%');
   }
   $stmt->execute();
   $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

   foreach ($assignments as &$row) {
       $row['files'] = json_decode($row['files'], true) ?? [];
   }
   sendResponse(['success' => true, 'data' => $assignments]);
}

function getAssignmentById(PDO $db, $id) {
   $stmt = $db->prepare("SELECT * FROM assignments WHERE id = ?");
   $stmt->execute([$id]);
   $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
   if ($assignment) {
       $assignment['files'] = json_decode($assignment['files'], true) ?? [];
       sendResponse(['success' => true, 'data' => $assignment]);
   } else {
       sendResponse(['success' => false, 'message' => 'Not Found'], 404);
   }
}

function createAssignment(PDO $db, array $data) {
   $title = sanitizeInput($data['title'] ?? '');
   $desc = sanitizeInput($data['description'] ?? '');
   $date = $data['due_date'] ?? '';
   $files = json_encode($data['files'] ?? []);

   if (!$title || !$desc || !validateDate($date)) {
       sendResponse(['success' => false, 'message' => 'Invalid data'], 400);
   }

   $stmt = $db->prepare("INSERT INTO assignments (title, description, due_date, files) VALUES (?, ?, ?, ?)");
   if ($stmt->execute([$title, $desc, $date, $files])) {
       sendResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
   }
}

function updateAssignment(PDO $db, array $data) {
   $id = $data['id'] ?? null;
   if (!$id) sendResponse(['success' => false, 'message' => 'Missing ID'], 400);

   $stmt = $db->prepare("UPDATE assignments SET title = ?, description = ?, due_date = ? WHERE id = ?");
   if ($stmt->execute([$data['title'], $data['description'], $data['due_date'], $id])) {
       sendResponse(['success' => true]);
   }
}

function deleteAssignment(PDO $db, $id) {
   $stmt = $db->prepare("DELETE FROM assignments WHERE id = ?");
   $stmt->execute([$id]);
   sendResponse(['success' => true]);
}

function getCommentsByAssignment(PDO $db, $assignmentId) {
   $stmt = $db->prepare("SELECT * FROM comments_assignment WHERE assignment_id = ? ORDER BY created_at ASC");
   $stmt->execute([$assignmentId]);
   sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function createComment(PDO $db, array $data) {
   $stmt = $db->prepare("INSERT INTO comments_assignment (assignment_id, author, text) VALUES (?, ?, ?)");
   if ($stmt->execute([$data['assignment_id'], $data['author'], $data['text']])) {
       sendResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
   }
}

function deleteComment(PDO $db, $commentId) {
   $stmt = $db->prepare("DELETE FROM comments_assignment WHERE id = ?");
   $stmt->execute([$commentId]);
   sendResponse(['success' => true]);
}

function sendResponse(array $data, int $statusCode = 200) {
   http_response_code($statusCode);
   echo json_encode($data);
   exit;
}

function validateDate($date) {
   $d = DateTime::createFromFormat('Y-m-d', $date);
   return $d && $d->format('Y-m-d') === $date;
}

function sanitizeInput($data) {
   return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
