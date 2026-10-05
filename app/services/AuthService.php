<?php

namespace App;

final class AuthService
{
    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        try {
            $user = \db()->fetch(
                "SELECT id, email, username, display_name
                 FROM users
                 WHERE id = ?
                 LIMIT 1",
                [(int)$_SESSION['user_id']]
            );

            if ($user) {
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['display_name'] = $user['display_name'] ?: $user['username'];

                return [
                    'id' => (int)$user['id'],
                    'email' => $user['email'],
                    'display_name' => $user['display_name'] ?: $user['username'],
                    'roles' => $_SESSION['roles'] ?? [],
                ];
            }
        } catch (\Throwable) {
            // Fall back to session values if the database is unavailable.
        }

        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'] ?? null,
            'display_name' => $_SESSION['display_name'] ?? 'Admin',
            'roles' => $_SESSION['roles'] ?? [],
        ];
    }

    public static function hasRole(string $role): bool
    {
        return in_array($role, $_SESSION['roles'] ?? [], true);
    }
}
