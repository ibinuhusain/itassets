<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *"); 

$host = "localhost";
$db   = "anomakio_collection";
$user = "anomakio_collection_adm";
$pass = "@GE3cn093tkl@";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (\PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['username']) || !isset($data['full_name'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data payload.']);
    exit;
}

try {
    // Hash the temporary password
    $hashedPassword = password_hash($data['temp_password'], PASSWORD_DEFAULT);
    $defaultPermissions = '{}'; // Start with zero module access

    $stmt = $pdo->prepare("
        INSERT INTO master_users 
        (emp_id, full_name, username, email, department, manager, phone, password_hash, app_permissions, is_iam_admin) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
    ");

    $stmt->execute([
        $data['emp_id'],
        $data['full_name'],
        $data['username'],
        $data['email'],
        $data['department'],
        $data['manager'],
        $data['phone'],
        $hashedPassword,
        $defaultPermissions
    ]);

    // Attempt to send the email
    $to = $data['email'];
    $subject = $data['mail_subject'];
    $message = $data['mail_body'];
    $headers = "From: it-support@apparelgroup.com\r\nReply-To: it-support@apparelgroup.com\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    
    @mail($to, $subject, $message, $headers); // Silently fails if local mail server isn't configured

    echo json_encode(['status' => 'success', 'message' => 'Identity created successfully.']);

} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo json_encode(['status' => 'error', 'message' => 'A user with this Username, Email, or Employee ID already exists.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
    }
}
?>