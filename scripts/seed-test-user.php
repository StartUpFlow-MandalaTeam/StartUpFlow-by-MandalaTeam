<?php

declare(strict_types=1);

/**
 * seed-test-user.php
 * 
 * CLI script to create/update a test user for local development.
 * This script inserts or updates the default test user with known credentials
 * to allow developers to log in without going through the registration flow.
 * 
 * Usage: php scripts/seed-test-user.php
 * 
 * Test user credentials:
 *   Username: utente
 *   Password: password123
 *   Email:    utente@test.com
 */

require_once __DIR__ . '/../includes/db.php';

$username = 'utente';
$email = 'utente@test.com';
$password = 'password123';
$passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

try {
    // Check if user already exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $existingUser = $stmt->fetch();

    $userId = null;

    if ($existingUser) {
        // Update existing user
        $update = $pdo->prepare('
            UPDATE users 
            SET 
                email = :email,
                password_hash = :password_hash,
                is_active = 1,
                failed_login_attempts = 0,
                locked_until = NULL,
                updated_at = NOW()
            WHERE username = :username
        ');
        $update->execute([
            ':email' => $email,
            ':password_hash' => $passwordHash,
            ':username' => $username,
        ]);
        $userId = (int) $existingUser['id'];
        echo "Test user '$username' updated successfully.\n";
    } else {
        // Insert new user
        $insert = $pdo->prepare('
            INSERT INTO users 
            (username, email, password_hash, is_active, created_at, updated_at)
            VALUES (:username, :email, :password_hash, 1, NOW(), NOW())
        ');
        $insert->execute([
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $passwordHash,
        ]);
        $userId = (int) $pdo->lastInsertId();
        echo "Test user '$username' created successfully.\n";
    }

    // Creation of user's profile (idempotente: inserisce solo se manca)
    $profileCreation = $pdo->prepare('
        INSERT INTO users_profiles (user_id)
        VALUES (:user_id)
        ON DUPLICATE KEY UPDATE user_id = user_id
    ');
    $profileCreation->execute([
        ':user_id' => $userId,
    ]);
    echo "User profile for '$username' ensured (user_id = $userId).\n";

    echo "\nTest user credentials:\n";
    echo "  Username: $username\n";
    echo "  Password: $password\n";
    echo "  Email:    $email\n";
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
}
