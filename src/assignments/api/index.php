
<?php
header("Content-Type: application/json");

require_once '../../common/db.php';

try {
   $pdo = getDBConnection();
} catch (Exception $e) {
   echo json_encode(["success" => false, "error" => "Connection failed"]);
   exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
   if ($method === 'GET') {
       if ($action === 'comments') {
           $stmt = $pdo->prepare("SELECT * FROM comments_assignment WHERE assignment_id = ? ORDER BY created_at ASC");
           $stmt->execute([(int)$_GET['assignment_id']]);
           echo json_encode(["success" => true, "data" => $stmt->fetchAll()]);
       } elseif ($id) {
           $stmt = $pdo->prepare("SELECT * FROM assignments WHERE id = ?");
           $stmt->execute([$id]);
           $res = $stmt->fetch();
           if ($res) {
               $res['files'] = json_decode($res['files'], true) ?: [];
               echo json_encode(["success" => true, "data" => $res]);
           } else {
               echo json_encode(["success" => false]);
           }
       } else {
           $stmt = $pdo->query("SELECT * FROM assignments ORDER BY due_date ASC");
           $data = $stmt->fetchAll();
           foreach ($data as &$r) {
               $r['files'] = json_decode($r['files'], true) ?: [];
           }
           echo json_encode(["success" => true, "data" => $data]);
       }
   } elseif ($method === 'POST') {
       $input = json_decode(file_get_contents("php://input"), true);
       if ($action === 'comment') {
           $stmt = $pdo->prepare("INSERT INTO comments_assignment (assignment_id, author, text) VALUES (?, ?, ?)");
           $stmt->execute([(int)$input['assignment_id'], $input['author'], $input['text']]);
           echo json_encode(["success" => true]);
       } else {
           $stmt = $pdo->prepare("INSERT INTO assignments (title, due_date, description, files) VALUES (?, ?, ?, ?)");
           $files = json_encode($input['files'] ?? []);
           $stmt->execute([$input['title'], $input['due_date'], $input['description'], $files]);
           echo json_encode(["success" => true, "id" => (int)$pdo->lastInsertId()]);
       }
   } elseif ($method === 'PUT') {
       $input = json_decode(file_get_contents("php://input"), true);
       $stmt = $pdo->prepare("UPDATE assignments SET title=?, due_date=?, description=?, files=? WHERE id=?");
       $stmt->execute([$input['title'], $input['due_date'], $input['description'], json_encode($input['files'] ?? []), (int)$input['id']]);
       echo json_encode(["success" => true]);
   } elseif ($method === 'DELETE') {
       if ($id) {
           $stmt = $pdo->prepare("DELETE FROM assignments WHERE id = ?");
           $stmt->execute([$id]);
           echo json_encode(["success" => true]);
       }
   }
} catch (Exception $e) {
   echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

