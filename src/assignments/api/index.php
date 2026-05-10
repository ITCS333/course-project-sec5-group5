<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once '../../common/db.php';

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    if ($method === 'GET') {
        if ($action === 'comments') {
            $assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : null;
            if (!$assignment_id) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "assignment_id is required"]);
                exit;
            }
            $stmt = $pdo->prepare("SELECT * FROM comments_assignment WHERE assignment_id = ? ORDER BY created_at ASC");
            $stmt->execute([$assignment_id]);
            http_response_code(200);
            echo json_encode(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } elseif ($id) {
            $stmt = $pdo->prepare("SELECT * FROM assignments WHERE id = ?");
            $stmt->execute([$id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res) {
                $res['files'] = json_decode($res['files'], true) ?: [];
                http_response_code(200);
                echo json_encode(["success" => true, "data" => $res]);
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "error" => "Assignment not found"]);
            }
        } else {
            $stmt = $pdo->query("SELECT * FROM assignments ORDER BY due_date ASC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($data as &$r) {
                $r['files'] = json_decode($r['files'], true) ?: [];
            }
            http_response_code(200);
            echo json_encode(["success" => true, "data" => $data]);
        }
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents("php://input"), true);
        if (!$input) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Invalid JSON input"]);
            exit;
        }

        if ($action === 'comment') {
            // Validate required fields
            if (empty($input['assignment_id']) || empty($input['author']) || empty($input['text'])) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "assignment_id, author, and text are required"]);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO comments_assignment (assignment_id, author, text, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([(int)$input['assignment_id'], $input['author'], $input['text']]);
            http_response_code(201);
            echo json_encode(["success" => true, "id" => (int)$pdo->lastInsertId()]);
        } else {
            // Validate required fields
            if (empty($input['title']) || empty($input['due_date']) || empty($input['description'])) {
                http_response_code(400);
                echo json_encode(["success" => false, "error" => "title, due_date, and description are required"]);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO assignments (title, due_date, description, files, created_at) VALUES (?, ?, ?, ?, NOW())");
            $files = json_encode($input['files'] ?? []);
            $stmt->execute([$input['title'], $input['due_date'], $input['description'], $files]);
            http_response_code(201);
            echo json_encode(["success" => true, "id" => (int)$pdo->lastInsertId()]);
        }
    } elseif ($method === 'PUT') {
        // Validate required fields
        $input = json_decode(file_get_contents("php://input"), true);
        if (!$input) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Invalid JSON input"]);
            exit;
        }
        if (empty($input['id']) || empty($input['title']) || empty($input['due_date']) || empty($input['description'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "id, title, due_date, and description are required"]);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE assignments SET title=?, due_date=?, description=?, files=? WHERE id=?");
        $stmt->execute([$input['title'], $input['due_date'], $input['description'], json_encode($input['files'] ?? []), (int)$input['id']]);
        http_response_code(200);
        echo json_encode(["success" => true]);
    } elseif ($method === 'DELETE') {
        if (!$id) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "id is required"]);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM assignments WHERE id = ?");
        $stmt->execute([$id]);
        http_response_code(200);
        echo json_encode(["success" => true]);
    } else {
        http_response_code(405);
        echo json_encode(["success" => false, "error" => "Method not allowed"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
