<?php
require_once __DIR__ . '/api/_config.php';
require_once __DIR__ . '/api/_db.php';

$pdo = getDB();

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE t_approval_log");
    $pdo->exec("TRUNCATE TABLE t_payment");
    $pdo->exec("TRUNCATE TABLE t_borrow_record");
    $pdo->exec("TRUNCATE TABLE t_reservation");
    $pdo->exec("UPDATE t_device SET status = 1");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "All reservation-related tables cleared successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
