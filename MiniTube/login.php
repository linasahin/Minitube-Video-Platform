<?php
/*
    Project: MiniTube (A YouTube Clone)
    Author: Lina Şahin
    File: login.php
    Description: Handles user authentication via POST. Verifies username and
                 hashed password, then redirects to feed.php with user_id in URL.
                 On failure, redirects back to login.html with error=1.
*/

$servername = "localhost";
$username   = "root";
$password   = "mysql";
$dbname     = "lina_sahin";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    // Use prepared statement to safely query the matching user record
    $sql  = "SELECT user_id, password FROM USERS WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();

    // FIXED: was incorrectly written as num_num_rows (typo) — now correctly num_rows
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Verify the submitted password against the stored bcrypt hash
        if (password_verify($pass, $row['password'])) {
            // Success: forward the user_id as a URL parameter to all subsequent pages
            header("Location: feed.php?user_id=" . $row['user_id']);
            exit();
        }
    }

    // Authentication failed: redirect back to login with error flag
    header("Location: login.html?error=1");
    exit();
}

$conn->close();
?>
