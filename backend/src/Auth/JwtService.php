<?php

declare(strict_types=1);

namespace App\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtService
{
    private const ALGORITHM = 'HS256';
    private const TOKEN_DURATION_SECONDS = 28800; // 8 ore

    private string $secret;

    public function __construct()
    {
        $secret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET');

        if (!is_string($secret) || strlen($secret) < 32) {
            throw new \RuntimeException('JWT_SECRET deve contenere almeno 32 caratteri');
        }

        $this->secret = $secret;
    }

    public function createToken(array $user): string
    {
        $issuedAt = time();

        return JWT::encode([
            'iss' => 'academy-api',
            'iat' => $issuedAt,
            'exp' => $issuedAt + self::TOKEN_DURATION_SECONDS,
            'sub' => (string) $user['id'],
            'email' => $user['email'],
            'ruolo' => $user['ruolo'],
        ], $this->secret, self::ALGORITHM);
    }

    public function decodeToken(string $token): array
    {
        $payload = JWT::decode($token, new Key($this->secret, self::ALGORITHM));

        return [
            'id' => (int) $payload->sub,
            'email' => (string) $payload->email,
            'ruolo' => (string) $payload->ruolo,
        ];
    }
}
