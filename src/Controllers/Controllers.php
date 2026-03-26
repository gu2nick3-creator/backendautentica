public function login(Request $r, array $p): void
{
    error_log('LOGIN STEP 1');

    $d = $r->input();
    error_log('LOGIN STEP 2');

    $repo = new UserRepository();
    error_log('LOGIN STEP 3');

    $email = (string)($d['email'] ?? '');
    $password = (string)($d['password'] ?? '');

    $user = $repo->findByEmail($email);
    error_log('LOGIN STEP 4');

    if (!$user) {
        Response::error('Usuário não encontrado', 404);
        return;
    }

    error_log('LOGIN STEP 5');
    error_log('USER DATA: ' . json_encode($user));

    if (!isset($user['password_hash'])) {
        Response::error('Campo password_hash não encontrado no usuário', 500);
        return;
    }

    if (!password_verify($password, $user['password_hash'])) {
        Response::error('Senha inválida', 401);
        return;
    }

    error_log('LOGIN STEP 6');

    Response::ok([
        'debug' => true,
        'message' => 'login chegou até aqui',
        'user' => [
            'id' => $user['id'] ?? null,
            'name' => $user['name'] ?? null,
            'email' => $user['email'] ?? null,
            'role' => $user['role'] ?? null,
        ]
    ]);
}
