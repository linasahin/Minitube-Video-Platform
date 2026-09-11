<?php
/*
    Project: MiniTube (A YouTube Clone)
    Author: Lina Şahin
    File: install.php
    Description: Creates the database, all tables, then loads seed.sql to populate
                 data. After completion, redirects the user to the login page.
*/

$servername = "localhost";
$username   = "root";
$password   = "mysql";

// Connect to MySQL server (no DB selected yet)
$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// (i) Create database named after student
$dbname = "lina_sahin";
$conn->query("DROP DATABASE IF EXISTS `$dbname`");
if ($conn->query("CREATE DATABASE `$dbname`") === FALSE) {
    die("Error creating database: " . $conn->error);
}
$conn->select_db($dbname);

// (ii) USERS table
$conn->query("CREATE TABLE USERS (
    user_id    INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    user_image VARCHAR(512) NOT NULL,
    full_name  VARCHAR(100) NOT NULL,
    email      VARCHAR(100) NOT NULL UNIQUE,
    country    VARCHAR(100) NOT NULL,
    joined_on  DATE         NOT NULL,
    bio        TEXT
)");

// (iii) CHANNELS table — a user may own at most one channel (UNIQUE on owner_id)
$conn->query("CREATE TABLE CHANNELS (
    channel_id    INT AUTO_INCREMENT PRIMARY KEY,
    owner_id      INT          NOT NULL UNIQUE,
    channel_image VARCHAR(512) NOT NULL,
    name          VARCHAR(100) NOT NULL,
    description   TEXT,
    created_on    DATE         NOT NULL,
    category      VARCHAR(50)  NOT NULL,
    FOREIGN KEY (owner_id) REFERENCES USERS(user_id) ON DELETE CASCADE
)");

// (iv) VIDEOS table
$conn->query("CREATE TABLE VIDEOS (
    video_id         INT AUTO_INCREMENT PRIMARY KEY,
    channel_id       INT          NOT NULL,
    title            VARCHAR(150) NOT NULL,
    description      TEXT,
    url              VARCHAR(512) NOT NULL,
    duration_seconds INT          NOT NULL,
    uploaded_at      DATE         NOT NULL,
    view_count       INT          DEFAULT 0,
    like_count       INT          DEFAULT 0,
    FOREIGN KEY (channel_id) REFERENCES CHANNELS(channel_id) ON DELETE CASCADE
)");

// (v) SUBSCRIPTIONS table
$conn->query("CREATE TABLE SUBSCRIPTIONS (
    subscription_id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id   INT  NOT NULL,
    channel_id      INT  NOT NULL,
    subscribed_at   DATE NOT NULL,
    UNIQUE KEY unique_sub (subscriber_id, channel_id),
    FOREIGN KEY (subscriber_id) REFERENCES USERS(user_id)    ON DELETE CASCADE,
    FOREIGN KEY (channel_id)    REFERENCES CHANNELS(channel_id) ON DELETE CASCADE
)");

// (vi) COMMENTS table — parent_comment_id is NULL for top-level, non-NULL for replies
$conn->query("CREATE TABLE COMMENTS (
    comment_id        INT AUTO_INCREMENT PRIMARY KEY,
    video_id          INT  NOT NULL,
    user_id           INT  NOT NULL,
    parent_comment_id INT  DEFAULT NULL,
    body              TEXT NOT NULL,
    posted_at         DATE NOT NULL,
    FOREIGN KEY (video_id)          REFERENCES VIDEOS(video_id)    ON DELETE CASCADE,
    FOREIGN KEY (user_id)           REFERENCES USERS(user_id)      ON DELETE CASCADE,
    FOREIGN KEY (parent_comment_id) REFERENCES COMMENTS(comment_id) ON DELETE CASCADE
)");

// Generate seed data and load it
// First run generate_data.php logic to create seed.sql, then execute it
require_once 'generate_data.php'; // This creates seed.sql

// Now load and execute seed.sql to populate all tables
$seed_sql = file_get_contents('seed.sql');
if ($seed_sql === false) {
    die("Error: could not read seed.sql");
}

// Split by statement and execute each one
$statements = array_filter(array_map('trim', explode(";\n", $seed_sql)));
foreach ($statements as $stmt) {
    if (!empty($stmt)) {
        if ($conn->query($stmt) === FALSE) {
            // Log errors but continue — a duplicate or minor issue should not stop everything
            error_log("Seed SQL error: " . $conn->error . " | Statement: " . substr($stmt, 0, 100));
        }
    }
}

$conn->close();

// After successful initialization, redirect to the login page
header("Location: login.html");
exit();
?>
