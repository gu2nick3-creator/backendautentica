<?php
declare(strict_types=1);
namespace App\Middleware;
use App\Core\Request; use App\Core\Response; use App\Repositories\UserRepository; use App\Services\JwtService; use RuntimeException;

class AuthMiddleware {
    public function handle(Request $request, array &$params=[]): void {
        $token = $request->bearerToken();
        if (!$token) Response::error('Não autenticado', 401);
        try {
            $payload = (new JwtService())->decode($token);
            $user = (new UserRepository())->findById((int)$payload['sub']);
            if (!$user) Response::error('Usuário não encontrado', 401);
            $params['_auth'] = ['id'=>(int)$user['id'],'role'=>$user['role'],'user'=>$user];
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);
        }
    }
}

class AdminMiddleware {
    public function handle(Request $request, array &$params=[]): void {
        (new AuthMiddleware())->handle($request, $params);
        if (($params['_auth']['role'] ?? null) !== 'admin') Response::error('Acesso restrito ao administrador', 403);
    }
}
