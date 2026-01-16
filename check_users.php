<?php
require_once __DIR__ . '/api/_config.php';
require_once __DIR__ . '/api/_db.php';

$pdo = getDB();
$stmt = $pdo->query("SELECT user_id, username, real_name, user_type FROM t_user WHERE user_type = 'student'");
$users = $stmt->fetchAll();

echo "External Users:\n";
foreach ($users as $u) {
    echo "ID: {$u['user_id']}, Username: {$u['username']}, Name: {$u['real_name']}\n";
}
