<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
   http_response_code(200);
   exit;
}

require_once '../../../includes/db.php';

try {
   $db = get_db_connection();
   $method = $_SERVER['REQUEST_METHOD'];
   $rawData = file_get_contents('php://input');
   $data = json_decode($rawData, true) ?? [];

   $action = $_GET['action'] ?? null;
   $id = $_GET['id'] ?? null;
   $assignmentId = $_GET['assignment_id'] ?? null;
   $commentId = $_GET['comment_id'] ?? null;

   if ($method === 'GET') {
       if ($action === 'comments') {
           $stmt = $db->prepare("SELECT * FROM comments_assignment WHERE assignment_id = ? ORDER BY created_at ASC");
           $stmt->execute([$assignmentId]);
           echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
       } elseif ($id) {
           $stmt = $db->prepare("SELECT * FROM assignments WHERE id = ?");
           $stmt->execute([$id]);
           $res = $stmt->fetch(PDO::FETCH_ASSOC);
           if ($res) { $res['files'] = json_decode($res['files'], true) ?? []; }
           echo json_encode(['success' => true, 'data' => $res]);
       } else {
           $stmt = $db->query("SELECT id, title, description, due_date, files FROM assignments ORDER BY due_date ASC");
           $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
           foreach ($rows as &$r) { $r['files'] = json_decode($r['files'], true) ?? []; }
           echo json_encode(['success' => true, 'data' => $rows]);
       }
   } elseif ($method === 'POST') {
       if ($action === 'comment') {
           $stmt = $db->prepare("INSERT INTO comments_assignment (assignment_id, author, text) VALUES (?, ?, ?)");
           $stmt->execute([$data['assignment_id'], $data['author'], $data['text']]);
           echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
       } else {
           $stmt = $db->prepare("INSERT INTO assignments (title, description, due_date, files) VALUES (?, ?, ?, ?)");
           $files = json_encode($data['files'] ?? []);
           $stmt->execute([$data['title'], $data['description'], $data['due_date'], $files]);
           echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
       }
   } elseif ($method === 'PUT') {
       $stmt = $db->prepare("UPDATE assignments SET title = ?, description = ?, due_date = ? WHERE id = ?");
       $stmt->execute([$data['title'], $data['description'], $data['due_date'], $data['id']]);
       echo json_encode(['success' => true]);
   } elseif ($method === 'DELETE') {
       if ($action === 'delete_comment') {
           $stmt = $db->prepare("DELETE FROM comments_assignment WHERE id = ?");
           $stmt->execute([$commentId]);
       } else {
           $stmt = $db->prepare("DELETE FROM assignments WHERE id = ?");
           $stmt->execute([$id]);
       }
       echo json_encode(['success' => true]);
   }
} catch (Exception $e) {
   http_response_code(500);
   echo json_encode(['success' => false, 'message' => 'Server Error']);
}
