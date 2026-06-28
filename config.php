<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'anomakio_collection_adm');
define('DB_PASS', '@GE3cn093tkl@');
define('DB_NAME', 'anomakio_collection');

// Connection to database
function getConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
         // 2. Set Universal Database Time
    $pdo->exec("SET time_zone = '+03:00'"); // Change to '+05:30' for India time
        return $pdo;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Create tables if they don't exist
function initializeDatabase() {
    $pdo = getConnection();
    
   
    
    // Users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        role ENUM('super_admin', 'admin', 'agent') DEFAULT 'agent',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    
    // Regions table
    $sql = "CREATE TABLE IF NOT EXISTS regions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    
    // Stores table
    $sql = "CREATE TABLE IF NOT EXISTS stores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        address TEXT,
        region_id INT,
        mall VARCHAR(100),
        entity VARCHAR(100),
        brand VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (region_id) REFERENCES regions(id)
    )";
    $pdo->exec($sql);
    
    // Daily assignments table
    $sql = "CREATE TABLE IF NOT EXISTS daily_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_id INT,
        store_id INT,
        date_assigned DATE,
        status ENUM('pending', 'completed', 'submitted') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (agent_id) REFERENCES users(id),
        FOREIGN KEY (store_id) REFERENCES stores(id)
    )";
    $pdo->exec($sql);
    
    // Collections table
    $sql = "CREATE TABLE IF NOT EXISTS collections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assignment_id INT,
        amount_collected DECIMAL(10,2),
        pending_amount DECIMAL(10,2) DEFAULT 0,
        comments TEXT,
        receipt_images JSON,
        submitted_to_bank BOOLEAN DEFAULT FALSE,
        submitted_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (assignment_id) REFERENCES daily_assignments(id)
    )";
    $pdo->exec($sql);
    
    // Bank submissions table
    $sql = "CREATE TABLE IF NOT EXISTS bank_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_id INT,
        total_amount DECIMAL(10,2),
        receipt_image VARCHAR(255),
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        approved_by INT NULL,
        approved_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (agent_id) REFERENCES users(id),
        FOREIGN KEY (approved_by) REFERENCES users(id)
    )";
    $pdo->exec($sql);
    
    // Insert default super admin user if 'apprelsadmin' doesn't exist
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute(['apprelsadmin']);
    if ($stmt->fetchColumn() == 0) {
        $hashed_password = password_hash('x9n6X8o1u41TSRU95', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, name, phone, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['apprelsadmin', $hashed_password, 'Super Admin', '0000000000', 'super_admin']);
    }
    
    // Insert default admin user if 'admin' doesn't exist
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    if ($stmt->fetchColumn() == 0) {
        $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, name, phone, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', $hashed_password, 'Administrator', '0000000000', 'admin']);
    }
    
    // Insert default agent user if 'agent1' doesn't exist
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute(['agent1']);
    if ($stmt->fetchColumn() == 0) {
        $hashed_password = password_hash('agent123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, name, phone, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['agent1', $hashed_password, 'John Doe', '1234567890', 'agent']);
    }
}
?>