<?php
declare(strict_types=1);
namespace App\Services;
use RuntimeException;

class JwtService {
    public function encode(array $payload): string {
        $header = ['alg'=>'HS256','typ'=>'JWT'];
        $parts = [$this->b64(json_encode($header)), $this->b64(json_encode($payload))];
        $parts[] = $this->b64(hash_hmac('sha256', implode('.', $parts), env('JWT_SECRET','secret'), true));
        return implode('.', $parts);
    }
    public function decode(string $jwt): array {
        $p = explode('.', $jwt);
        if (count($p) !== 3) throw new RuntimeException('Token inválido');
        [$h,$pl,$s] = $p;
        $expected = $this->b64(hash_hmac('sha256', $h.'.'.$pl, env('JWT_SECRET','secret'), true));
        if (!hash_equals($expected, $s)) throw new RuntimeException('Assinatura inválida');
        $payload = json_decode($this->ub64($pl), true);
        if (!is_array($payload)) throw new RuntimeException('Payload inválido');
        if (isset($payload['exp']) && time() > (int)$payload['exp']) throw new RuntimeException('Token expirado');
        return $payload;
    }
    private function b64(string $d): string { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
    private function ub64(string $d): string { return base64_decode(strtr($d, '-_', '+/')) ?: ''; }
}
