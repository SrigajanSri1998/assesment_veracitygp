<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $dsn = Env::get('DB_DSN', 'sqlite:./storage/tasks.sqlite');
        if (str_starts_with((string) $dsn, 'sqlite:./')) {
            $dsn = 'sqlite:' . dirname(__DIR__) . substr((string) $dsn, strlen('sqlite:.'));
        }
        $user = Env::get('DB_USER', '');
        $pass = Env::get('DB_PASS', '');

        self::$instance = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        self::migrate(self::$instance);

        return self::$instance;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            status VARCHAR(20) NOT NULL DEFAULT "todo",
            due_date DATE NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )');

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $email = Env::get('DEFAULT_USER_EMAIL', 'admin@example.com');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            $insert = $pdo->prepare('INSERT INTO users(email, password_hash, created_at) VALUES(:email, :hash, :created_at)');
            $insert->execute([
                'email' => $email,
                'hash' => password_hash(Env::get('DEFAULT_USER_PASSWORD', 'password123'), PASSWORD_DEFAULT),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
