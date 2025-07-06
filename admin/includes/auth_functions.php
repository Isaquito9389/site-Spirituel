<?php
// Include bootstrap file for secure configuration and error handling
require_once 'bootstrap.php';
/**
 * Authentication Functions
 *
 * This file provides functions for user authentication and management.
 */

// Include database connection
require_once 'db_connect.php';

/**
 * Register a new user
 *
 * @param string $username Username
 * @param string $password Password (will be hashed)
 * @param string $email User email
 * @param string $full_name User's full name
 * @param string $role User role (admin, editor, author)
 * @return array Status and message
 */
function register_user($username, $password, $email, $full_name, $role = 'author') {
    global $pdo;

    try {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return ['status' => false, 'message' => "Ce nom d'utilisateur est déjà utilisé."];
        }

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return ['status' => false, 'message' => "Cette adresse email est déjà utilisée."];
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert new user
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role) VALUES (:username, :password, :email, :full_name, :role)");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':role', $role);
        $stmt->execute();

        return ['status' => true, 'message' => "Utilisateur créé avec succès."];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => "Erreur lors de la création de l'utilisateur: " . $e->getMessage()];
    }
}

/**
 * Authenticate a user
 *
 * @param string $username Username
 * @param string $password Password
 * @return array Status, message and user data if successful
 */
function authenticate_user($username, $password) {
    global $pdo;

    try {
        // Get user by username
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username AND status = 'active'");
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            return ['status' => false, 'message' => "Nom d'utilisateur ou mot de passe incorrect."];
        }

        $user = $stmt->fetch();

        // Verify password
        if (password_verify($password, $user['password'])) {
            // Update last login time
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
            $stmt->bindParam(':id', $user['id']);
            $stmt->execute();

            // Remove password from user data
            unset($user['password']);

            return [
                'status' => true,
                'message' => "Authentification réussie.",
                'user' => $user
            ];
        } else {
            return ['status' => false, 'message' => "Nom d'utilisateur ou mot de passe incorrect."];
        }
    } catch (PDOException $e) {
        return ['status' => false, 'message' => "Erreur lors de l'authentification: " . $e->getMessage()];
    }
}

/**
 * Create default admin user if no users exist
 *
 * @return boolean Success status
 */
function create_default_admin() {
    global $pdo;

    try {
        // Check if any users exist
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch();

        if ($result['count'] === 0) {
            // Create default admin with more secure credentials
            return register_user(
                'Alphamed',
                '@Soumanou96',
                'admin@mysticaocculta.com',
                'Admin',
                'admin'
            )['status'];
        }

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Check if user has specific role
 *
 * @param int $user_id User ID
 * @param string $role Role to check
 * @return boolean Has role
 */
function user_has_role($user_id, $role) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            return false;
        }

        $user = $stmt->fetch();

        // Admin role has access to everything
        if ($user['role'] === 'admin') {
            return true;
        }

        return $user['role'] === $role;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Update admin credentials
 *
 * This function updates the credentials of the admin user
 * from the old values to the new secure values.
 *
 * @param string $old_username Old username
 * @param string $new_username New username
 * @param string $new_password New password
 * @return array Status and message
 */
function update_admin_credentials($old_username, $new_username, $new_password) {
    global $pdo;

    try {
        // Check if the old username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->bindParam(':username', $old_username);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            return ['status' => false, 'message' => "L'utilisateur $old_username n'existe pas."];
        }

        $user_id = $stmt->fetch()['id'];

        // Check if new username already exists (if different from old)
        if ($old_username !== $new_username) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username AND id != :id");
            $stmt->bindParam(':username', $new_username);
            $stmt->bindParam(':id', $user_id);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return ['status' => false, 'message' => "Le nom d'utilisateur $new_username est déjà utilisé."];
            }
        }

        // Hash new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update user credentials
        $stmt = $pdo->prepare("UPDATE users SET username = :username, password = :password WHERE id = :id");
        $stmt->bindParam(':username', $new_username);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':id', $user_id);
        $stmt->execute();

        return ['status' => true, 'message' => "Identifiants de l'administrateur mis à jour avec succès."];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => "Erreur lors de la mise à jour des identifiants: " . $e->getMessage()];
    }
}
