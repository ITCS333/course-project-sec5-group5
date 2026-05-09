
<?php
header("Content-Type: application/json");

if (file_exists('db.php')) {
   require_once 'db.php';
} elseif (file_exists('../db.php')) {
   require_once '../db.php';
} elseif (file_exists('../../db.php')) {
   require_once '../../db.php';
}

if (!isset($pdo) && isset($conn)) {
   $pdo = $conn;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
   switch ($method) {
       case 'GET':
           if ($action === 'comments') {
               $stmt = $pdo->prepare("SELECT * FROM comments_assignment WHERE assignment_id = ? ORDER BY created_at ASC");
               $stmt->execute([$_GET['assignment_id']]);
               echo json_encode(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
           } elseif (isset($_GET['id'])) {
               $stmt = $pdo->prepare("SELECT * FROM assignments WHERE id = ?");
               $stmt->execute([$_GET['id']]);
               $item = $stmt->fetch(PDO::FETCH_ASSOC);
               if ($item) {
                   $item['files'] = json_decode($item['files'], true) ?: [];
                   echo json_encode(["success" => true, "data" => $item]);
               } else {
                   echo json_encode(["success" => false]);
               }
           } else {
               $stmt = $pdo->query("SELECT * FROM assignments ORDER BY due_date ASC");
               $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
               foreach ($items as &$item) {
                   $item['files'] = json_decode($item['files'], true) ?: [];
               }
               echo json_encode(["success" => true, "data" => $items]);
           }
           break;

       case 'POST':
           $data = json_decode(file_get_contents("php://input"), true);
           if ($action === 'comment') {
               $stmt = $pdo->prepare("INSERT INTO comments_assignment (assignment_id, author, text) VALUES (?, ?, ?)");
               $stmt->execute([$data['assignment_id'], $data['author'], $data['text']]);
               $id = $pdo->lastInsertId();
               $stmt = $pdo->prepare("SELECT * FROM comments_assignment WHERE id = ?");
               $stmt->execute([$id]);
               echo json_encode(["success" => true, "data" => $stmt->fetch(PDO::FETCH_ASSOC)]);
           } else {
               $stmt = $pdo->prepare("INSERT INTO assignments (title, due_date, description, files) VALUES (?, ?, ?, ?)");
               $stmt->execute([$data['title'], $data['due_date'], $data['description'], json_encode($data['files'] ?? [])]);
               echo json_encode(["success" => true, "id" => (int)$pdo->lastInsertId()]);
           }
           break;

       case 'PUT':
           $data = json_decode(file_get_contents("php://input"), true);
           $stmt = $pdo->prepare("UPDATE assignments SET title=?, due_date=?, description=?, files=? WHERE id=?");
           $stmt->execute([$data['title'], $data['due_date'], $data['description'], json_encode($data['files'] ?? []), $data['id']]);
           echo json_encode(["success" => true]);
           break;

       case 'DELETE':
           if (isset($_GET['id'])) {
               $stmt = $pdo->prepare("DELETE FROM assignments WHERE id = ?");
               $stmt->execute([$_GET['id']]);
               echo json_encode(["success" => true]);
           }
           break;
   }
} catch (Exception $e) {
   http_response_code(500);
   echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

