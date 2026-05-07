<?php
/**
 * Course Resources API
 * 
 * This is a RESTful API that handles all CRUD operations for course resources 
 * and their associated comments/discussions.
 * It uses PDO to interact with a MySQL database.
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
 * 
 * HTTP Methods Supported:
 *   - GET:    Retrieve resource(s) or comment(s)
 *   - POST:   Create a new resource or comment
 *   - PUT:    Update an existing resource
 *   - DELETE: Delete a resource (associated comments in comments_resource are
 *             removed automatically by the ON DELETE CASCADE constraint)
 * 
 * Response Format: JSON
 * All responses follow the structure:
 *   { "success": true,  "data": ...    }  (on success)
 *   { "success": false, "message": ... }  (on error)
 * 
 * API Endpoints:
 * 
 *   Resources:
 *     GET    /resources/api/index.php                         - Get all resources
 *     GET    /resources/api/index.php?id={id}                 - Get single resource by ID
 *     POST   /resources/api/index.php                         - Create new resource
 *     PUT    /resources/api/index.php                         - Update resource
 *     DELETE /resources/api/index.php?id={id}                 - Delete resource
 * 
 *   Comments:
 *     GET    /resources/api/index.php?resource_id={id}&action=comments
 *                                                             - Get all comments for a resource
 *     POST   /resources/api/index.php?action=comment          - Create a new comment
 *     DELETE /resources/api/index.php?comment_id={id}&action=delete_comment
 *                                                             - Delete a single comment
 * 
 * Query Parameters for GET all resources:
 *   - search: Optional. Filter resources by title or description using LIKE.
 *   - sort:   Optional. Sort field — allowed values: title, created_at (default: created_at).
 *   - order:  Optional. Sort direction — allowed values: asc, desc (default: desc).
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

// Set headers for JSON response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
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

// Parse query parameters from $_GET
$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$resource_id = $_GET['resource_id'] ?? null;
$comment_id = $_GET['comment_id'] ?? null;


// ============================================================================
// RESOURCE FUNCTIONS
// ============================================================================

/**
 * Function: Get all resources
 * Method: GET (no id or action parameter)
 * 
 * Query Parameters:
 *   - search: Optional search term to filter by title or description
 *   - sort:   Optional field to sort by — allowed values: title, created_at
 *   - order:  Optional sort direction — allowed values: asc, desc (default: desc)
 * 
 * Response:
 *   { "success": true, "data": [ ...resource objects ] }
 */
function getAllResources($db) {
    // Initialize the base SQL query
    $sql = "SELECT id, title, description, link, created_at FROM resources";
    
    // Check if search parameter exists in $_GET
    $search = $_GET['search'] ?? null;
    if ($search) {
        $sql .= " WHERE (title LIKE :search OR description LIKE :search)";
    }
    
    // Validate the sort parameter
    $sort = $_GET['sort'] ?? 'created_at';
    $allowedSorts = ['title', 'created_at'];
    if (!in_array($sort, $allowedSorts)) {
        $sort = 'created_at';
    }
    
    // Validate the order parameter
    $order = $_GET['order'] ?? 'desc';
    $allowedOrders = ['asc', 'desc'];
    if (!in_array(strtolower($order), $allowedOrders)) {
        $order = 'desc';
    }
    
    // Add ORDER BY clause to the query
    $sql .= " ORDER BY " . $sort . " " . strtoupper($order);
    
    // Prepare the statement using PDO
    $stmt = $db->prepare($sql);
    
    // If a search parameter was used, bind it with % wildcards
    if ($search) {
        $stmt->bindValue(':search', '%' . $search . '%');
    }
    
    // Execute the query
    $stmt->execute();
    
    // Fetch all results as an associative array
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response using sendResponse()
    sendResponse(['success' => true, 'data' => $resources]);
}


/**
 * Function: Get a single resource by ID
 * Method: GET with ?id={id}
 * 
 * Parameters:
 *   - $resourceId: The resource's database ID (from $_GET['id'])
 * 
 * Response (success):
 *   { "success": true, "data": { id, title, description, link, created_at } }
 * Response (not found):
 *   HTTP 404 — { "success": false, "message": "Resource not found." }
 */
function getResourceById($db, $resourceId) {
    // Validate that $resourceId is provided and is numeric
    if (!$resourceId || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID.'], 400);
    }
    
    // Prepare SQL query
    $sql = "SELECT id, title, description, link, created_at FROM resources WHERE id = ?";
    $stmt = $db->prepare($sql);
    
    // Bind $resourceId and execute
    $stmt->execute([$resourceId]);
    
    // Fetch the result as an associative array
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If found, return success response with resource data
    if ($resource) {
        sendResponse(['success' => true, 'data' => $resource]);
    } else {
        // If not found, return error response with HTTP 404
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }
}


/**
 * Function: Create a new resource
 * Method: POST (no action parameter)
 * 
 * Required JSON Body:
 *   - title:       Resource title (required)
 *   - description: Resource description (optional, defaults to empty string)
 *   - link:        URL to the resource (required, must be a valid URL)
 * 
 * Response (success):
 *   HTTP 201 — { "success": true, "message": "...", "id": <new resource id> }
 * Response (validation error):
 *   HTTP 400 — { "success": false, "message": "..." }
 */
function createResource($db, $data) {
    // Validate required fields — title and link must not be empty
    if (!$data || !isset($data['title']) || empty(trim($data['title'])) || 
        !isset($data['link']) || empty(trim($data['link']))) {
        sendResponse(['success' => false, 'message' => 'Title and link are required.'], 400);
    }
    
    // Sanitize input — trim whitespace from all fields
    $title = sanitizeInput($data['title']);
    $description = isset($data['description']) ? sanitizeInput($data['description']) : '';
    $link = sanitizeInput($data['link']);
    
    // Validate the link using filter_var with FILTER_VALIDATE_URL
    if (!validateUrl($link)) {
        sendResponse(['success' => false, 'message' => 'Invalid URL format.'], 400);
    }
    
    // Prepare INSERT query
    $sql = "INSERT INTO resources (title, description, link) VALUES (?, ?, ?)";
    $stmt = $db->prepare($sql);
    
    // Bind title, description, and link; then execute
    if ($stmt->execute([$title, $description, $link])) {
        $newId = $db->lastInsertId();
        sendResponse(['success' => true, 'message' => 'Resource created successfully.', 'id' => $newId], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create resource.'], 500);
    }
}


/**
 * Function: Update an existing resource
 * Method: PUT
 * 
 * Required JSON Body:
 *   - id:          The resource's database ID (required)
 *   - title:       Updated title (optional)
 *   - description: Updated description (optional)
 *   - link:        Updated URL (optional, must be a valid URL if provided)
 * 
 * Response (success):
 *   HTTP 200 — { "success": true, "message": "Resource updated successfully." }
 * Response (not found):
 *   HTTP 404 — { "success": false, "message": "Resource not found." }
 * Response (validation error):
 *   HTTP 400 — { "success": false, "message": "..." }
 */
function updateResource($db, $data) {
    // Validate that id is provided in $data
    if (!$data || !isset($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Resource ID is required.'], 400);
    }
    
    $resourceId = $data['id'];
    
    // Check if the resource exists — SELECT by id
    $checkSql = "SELECT id FROM resources WHERE id = ?";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([$resourceId]);
    
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }
    
    // Build UPDATE query dynamically for only the fields provided
    $updateFields = [];
    $updateValues = [];
    
    if (isset($data['title']) && !empty(trim($data['title']))) {
        $updateFields[] = "title = ?";
        $updateValues[] = sanitizeInput($data['title']);
    }
    
    if (isset($data['description'])) {
        $updateFields[] = "description = ?";
        $updateValues[] = sanitizeInput($data['description']);
    }
    
    if (isset($data['link']) && !empty(trim($data['link']))) {
        // Validate link if being updated
        if (!validateUrl($data['link'])) {
            sendResponse(['success' => false, 'message' => 'Invalid URL format.'], 400);
        }
        $updateFields[] = "link = ?";
        $updateValues[] = sanitizeInput($data['link']);
    }
    
    // If no fields to update, return error response
    if (empty($updateFields)) {
        sendResponse(['success' => false, 'message' => 'No fields to update.'], 400);
    }
    
    // Build the final SQL
    $sql = "UPDATE resources SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $updateValues[] = $resourceId;
    
    // Prepare, bind all update values then bind id, and execute
    $stmt = $db->prepare($sql);
    
    if ($stmt->execute($updateValues)) {
        sendResponse(['success' => true, 'message' => 'Resource updated successfully.'], 200);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to update resource.'], 500);
    }
}


/**
 * Function: Delete a resource
 * Method: DELETE with ?id={id}
 * 
 * Parameters:
 *   - $resourceId: The resource's database ID (from $_GET['id'])
 * 
 * Response (success):
 *   HTTP 200 — { "success": true, "message": "Resource deleted successfully." }
 * Response (not found):
 *   HTTP 404 — { "success": false, "message": "Resource not found." }
 * 
 * Note: All associated comments in comments_resource are deleted automatically
 *       by the ON DELETE CASCADE foreign key constraint — no manual deletion
 *       of comments is needed.
 */
function deleteResource($db, $resourceId) {
    // Validate that $resourceId is provided and is numeric
    if (!$resourceId || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID.'], 400);
    }
    
    // Check if the resource exists — SELECT by id
    $checkSql = "SELECT id FROM resources WHERE id = ?";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([$resourceId]);
    
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }
    
    // Prepare DELETE query
    $sql = "DELETE FROM resources WHERE id = ?";
    $stmt = $db->prepare($sql);
    
    // Bind $resourceId and execute
    if ($stmt->execute([$resourceId])) {
        sendResponse(['success' => true, 'message' => 'Resource deleted successfully.'], 200);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete resource.'], 500);
    }
}


// ============================================================================
// COMMENT FUNCTIONS
// ============================================================================

/**
 * Function: Get all comments for a specific resource
 * Method: GET with ?resource_id={id}&action=comments
 * 
 * Query Parameters:
 *   - resource_id: The resource's database ID (required)
 * 
 * Response:
 *   { "success": true, "data": [ ...comment objects ] }
 *   Returns an empty data array if no comments exist (not an error).
 *
 * Each comment object: { id, resource_id, author, text, created_at }
 */
function getCommentsByResourceId($db, $resourceId) {
    // Validate that $resourceId is provided and is numeric
    if (!$resourceId || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID.'], 400);
    }
    
    // Prepare SQL query
    $sql = "SELECT id, resource_id, author, text, created_at
            FROM comments_resource
            WHERE resource_id = ?
            ORDER BY created_at ASC";
    
    $stmt = $db->prepare($sql);
    
    // Bind $resourceId and execute
    $stmt->execute([$resourceId]);
    
    // Fetch all results as an associative array
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return success response — always return an array, even if empty
    sendResponse(['success' => true, 'data' => $comments]);
}


/**
 * Function: Create a new comment
 * Method: POST with ?action=comment
 * 
 * Required JSON Body:
 *   - resource_id: The resource's database ID (required, must be numeric)
 *   - author:      Name of the comment author (required)
 *   - text:        Comment text content (required)
 * 
 * Response (success):
 *   HTTP 201 — { "success": true, "message": "...", "id": <new comment id> }
 * Response (resource not found):
 *   HTTP 404 — { "success": false, "message": "Resource not found." }
 * Response (validation error):
 *   HTTP 400 — { "success": false, "message": "..." }
 */
function createComment($db, $data) {
    // Validate required fields — resource_id, author, and text
    if (!$data || !isset($data['resource_id']) || !isset($data['author']) || !isset($data['text'])) {
        sendResponse(['success' => false, 'message' => 'Resource ID, author, and text are required.'], 400);
    }
    
    // Validate that resource_id is numeric
    if (!is_numeric($data['resource_id'])) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID.'], 400);
    }
    
    // Validate that author and text are not empty after trimming
    if (empty(trim((string)$data['author'])) || empty(trim((string)$data['text']))) {
        sendResponse(['success' => false, 'message' => 'Resource ID, author, and text are required.'], 400);
    }
    
    $resourceId = $data['resource_id'];
    
    // Check that the resource exists in the resources table
    $checkSql = "SELECT id FROM resources WHERE id = ?";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([$resourceId]);
    
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }
    
    // Sanitize author and text — trim whitespace
    $author = sanitizeInput($data['author']);
    $text = sanitizeInput($data['text']);
    
    // Prepare INSERT query
    $sql = "INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)";
    $stmt = $db->prepare($sql);
    
    // Bind resource_id, author, and text; then execute
    if ($stmt->execute([$resourceId, $author, $text])) {
        $newId = $db->lastInsertId();
        sendResponse(['success' => true, 'message' => 'Comment created successfully.', 'id' => $newId], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create comment.'], 500);
    }
}


/**
 * Function: Delete a comment
 * Method: DELETE with ?comment_id={id}&action=delete_comment
 * 
 * Query Parameters:
 *   - comment_id: The comment's database ID (required)
 * 
 * Response (success):
 *   HTTP 200 — { "success": true, "message": "Comment deleted successfully." }
 * Response (not found):
 *   HTTP 404 — { "success": false, "message": "Comment not found." }
 */
function deleteComment($db, $commentId) {
    // Validate that $commentId is provided and is numeric
    if (!$commentId || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid comment ID.'], 400);
    }
    
    // Check if the comment exists in comments_resource — SELECT by id
    $checkSql = "SELECT id FROM comments_resource WHERE id = ?";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([$commentId]);
    
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found.'], 404);
    }
    
    // Prepare DELETE query
    $sql = "DELETE FROM comments_resource WHERE id = ?";
    $stmt = $db->prepare($sql);
    
    // Bind $commentId and execute
    if ($stmt->execute([$commentId])) {
        sendResponse(['success' => true, 'message' => 'Comment deleted successfully.'], 200);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete comment.'], 500);
    }
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    // Route the request based on $method and $action

    if ($method === 'GET') {

        // If action === 'comments', return all comments for a resource
        if ($action === 'comments') {
            getCommentsByResourceId($db, $resource_id);
        }
        // If 'id' is present in $_GET, return a single resource
        elseif ($id) {
            getResourceById($db, $id);
        }
        // Otherwise, return all resources (supports ?search=, ?sort=, ?order=)
        else {
            getAllResources($db);
        }

    } elseif ($method === 'POST') {

        // If action === 'comment', create a new comment
        if ($action === 'comment') {
            createComment($db, $data);
        }
        // Otherwise, create a new resource
        else {
            createResource($db, $data);
        }

    } elseif ($method === 'PUT') {

        // Update an existing resource
        updateResource($db, $data);

    } elseif ($method === 'DELETE') {

        // If action === 'delete_comment', delete a single comment
        if ($action === 'delete_comment') {
            deleteComment($db, $comment_id);
        }
        // Otherwise, delete a resource
        else {
            deleteResource($db, $id);
        }

    } else {
        // Return HTTP 405 Method Not Allowed for unsupported methods
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }

} catch (PDOException $e) {
    // Log the error with error_log()
    error_log('Database error: ' . $e->getMessage());
    // Return a generic HTTP 500 error — do NOT expose $e->getMessage() to the client
    sendResponse(['success' => false, 'message' => 'Database error occurred.'], 500);

} catch (Exception $e) {
    // Log the error with error_log()
    error_log('Error: ' . $e->getMessage());
    // Return HTTP 500 error response using sendResponse()
    sendResponse(['success' => false, 'message' => 'An error occurred.'], 500);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Helper: Send a JSON response and stop execution.
 * 
 * @param array $data        Response payload. Must include a 'success' key.
 * @param int   $statusCode  HTTP status code (default: 200).
 */
function sendResponse($data, $statusCode = 200) {
    // Set the HTTP status code using http_response_code()
    http_response_code($statusCode);
    
    // Ensure $data is an array; if not, wrap it
    if (!is_array($data)) {
        $data = ['data' => $data];
    }
    
    // Echo json_encode($data) and call exit
    echo json_encode($data);
    exit;
}


/**
 * Helper: Validate a URL string.
 * 
 * @param  string $url
 * @return bool  True if the URL passes FILTER_VALIDATE_URL, false otherwise.
 */
function validateUrl($url) {
    // Use filter_var($url, FILTER_VALIDATE_URL)
    // Return true if valid, false otherwise
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}


/**
 * Helper: Sanitize a single input string.
 * 
 * @param  string $data
 * @return string  Trimmed, tag-stripped, and HTML-encoded string.
 */
function sanitizeInput($data) {
    // trim() → strip_tags() → htmlspecialchars(ENT_QUOTES, 'UTF-8')
    // Return the sanitized string
    return htmlspecialchars(strip_tags(trim((string)$data)), ENT_QUOTES, 'UTF-8');
}


/**
 * Helper: Check that all required fields exist and are non-empty in $data.
 * 
 * @param  array $data            Associative array of input data.
 * @param  array $requiredFields  List of field names that must be present.
 * @return array  ['valid' => bool, 'missing' => string[]]
 */
function validateRequiredFields($data, $requiredFields) {
    // Loop through $requiredFields
    // Collect any that are absent or empty in $data into a $missing array
    $missing = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim((string)$data[$field]))) {
            $missing[] = $field;
        }
    }
    
    // Return ['valid' => (count($missing) === 0), 'missing' => $missing]
    return [
        'valid' => count($missing) === 0,
        'missing' => $missing
    ];
}

?>
