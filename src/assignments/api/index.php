
<?php
header("Content-Type: application/json");

if (file_exists('db.php')) {
   require_once 'db.php';
} elseif (file_exists('../db.php')) {
   require_once '../db.php';
} else {
   echo json_encode(["success" => false, "message" => "Database file not found."]);
   exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
   switch ($method) {
       case 'GET':
           if ($action === 'comments') {
               $assignment_id = $_GET['assignment_id'];
               $stmt = $pdo->prepare("SELECT * FROM comments_assignment WHERE assignment_id = ? ORDER BY created_at ASC");
               $stmt->execute([$assignment_id]);
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
               $files = json_encode($data['files'] ?? []);
               $stmt->execute([$data['title'], $data['due_date'], $data['description'], $files]);
               echo json_encode(["success" => true, "id" => (int)$pdo->lastInsertId()]);
           }
           break;

       case 'PUT':
           $data = json_decode(file_get_contents("php://input"), true);
           $files = json_encode($data['files'] ?? []);
           $stmt = $pdo->prepare("UPDATE assignments SET title=?, due_date=?, description=?, files=? WHERE id=?");
           $stmt->execute([$data['title'], $data['due_date'], $data['description'], $files, $data['id']]);
           echo json_encode(["success" => true]);
           break;

       case 'DELETE':
           $id = $_GET['id'] ?? null;
           if ($id) {
               $stmt = $pdo->prepare("DELETE FROM assignments WHERE id = ?");
               $stmt->execute([$id]);
               echo json_encode(["success" => true]);
           }
           break;
   }
} catch (Exception $e) {
   http_response_code(500);
   echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

