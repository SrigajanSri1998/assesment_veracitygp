<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Auth
{
    public static function login(PDO $pdo, string $email, string $password): ?string
    {
        $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return null;
        }

        return self::createToken((int) $user['id']);
    }

    public static function userIdFromToken(?string $header): ?int
    {
        if ($header === null || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $signature] = $parts;
        $expected = self::base64UrlEncode(hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, self::secret(), true));
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode((string) base64_decode(strtr($encodedPayload, '-_', '+/')), true);
        if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) {
            return null;
        }

        return (int) ($payload['sub'] ?? 0);
    }

    private static function createToken(int $userId): string
    {
        $header = self::base64UrlEncode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::base64UrlEncode((string) json_encode([
            'sub' => $userId,
            'iat' => time(),
            'exp' => time() + 86400,
        ]));
        $signature = self::base64UrlEncode(hash_hmac('sha256', $header . '.' . $payload, self::secret(), true));

        return $header . '.' . $payload . '.' . $signature;
    }

    private static function secret(): string
    {
        return Env::get('JWT_SECRET', 'change-me-super-secret') ?? 'change-me-super-secret';
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
