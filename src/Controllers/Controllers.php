<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\{UserRepository, CategoryRepository, ProductRepository, CouponRepository, OrderRepository};
use App\Services\JwtService;
use CURLFile;

class BaseController
{
    protected function normalizeUser(array $u): array
    {
        return [
            'id' => (string) $u['id'],
            'name' => $u['name'],
            'email' => $u['email'],
            'phone' => $u['phone'] ?? '',
            'cpfCnpj' => $u['cpf_cnpj'] ?? '',
            'address' => [
                'street' => $u['street'] ?? '',
                'number' => $u['number'] ?? '',
                'complement' => $u['complement'] ?? '',
                'neighborhood' => $u['neighborhood'] ?? '',
                'city' => $u['city'] ?? '',
                'state' => $u['state'] ?? '',
                'zip' => $u['zip'] ?? '',
            ],
            'role' => $u['role'],
            'createdAt' => $u['created_at'] ?? null,
        ];
    }
}

class HealthController extends BaseController
{
    public function index(Request $r, array $p): void
    {
        Response::ok([
            'status' => 'ok',
            'time' => date('c'),
        ]);
    }
}

class AuthController extends BaseController
{
    public function register(Request $r, array $p): void
    {
        $d = $r->input();

        foreach (['name', 'email', 'password'] as $field) {
            if (empty($d[$field])) {
                Response::error("Campo obrigatório: {$field}", 422);
            }
        }

        $repo = new UserRepository();
        $email = mb_strtolower(trim((string) $d['email']));

        if ($repo->findByEmail($email)) {
            Response::error('E-mail já cadastrado', 422);
        }

        $id = $repo->create([
            'name' => trim((string) $d['name']),
            'email' => $email,
            'phone' => $d['phone'] ?? '',
            'cpf_cnpj' => $d['cpfCnpj'] ?? ($d['cpf_cnpj'] ?? ''),
            'password_hash' => password_hash((string) $d['password'], PASSWORD_BCRYPT),
            'role' => 'client',
        ]);

        $user = $repo->findById($id);
        $exp = time() + (int) env('JWT_EXPIRES_IN', '86400');
        $token = (new JwtService())->encode([
            'sub' => $user['id'],
            'role' => $user['role'],
            'exp' => $exp,
        ]);

        Response::ok([
            'user' => $this->normalizeUser($user),
            'token' => $token,
        ], 201);
    }

    public function login(Request $r, array $p): void
    {
        $d = $r->input();
        $repo = new UserRepository();
        $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
        $password = (string) ($d['password'] ?? '');

        $user = $repo->findByEmail($email);

        if (!$user) {
            Response::error('Credenciais inválidas', 401);
        }

        if (!isset($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
            Response::error('Credenciais inválidas', 401);
        }

        $exp = time() + (int) env('JWT_EXPIRES_IN', '86400');
        $token = (new JwtService())->encode([
            'sub' => $user['id'],
            'role' => $user['role'],
            'exp' => $exp,
        ]);

        Response::ok([
            'user' => $this->normalizeUser($user),
            'token' => $token,
        ]);
    }

    public function me(Request $r, array $p): void
    {
        Response::ok($this->normalizeUser($p['_auth']['user']));
    }

    public function logout(Request $r, array $p): void
    {
        Response::noContent();
    }
}

class CustomerController extends BaseController
{
    public function me(Request $r, array $p): void
    {
        Response::ok($this->normalizeUser($p['_auth']['user']));
    }

    public function updateMe(Request $r, array $p): void
    {
        $repo = new UserRepository();
        $repo->updateProfile((int) $p['_auth']['id'], $r->input());
        Response::ok($this->normalizeUser($repo->findById((int) $p['_auth']['id'])));
    }
}

class ProductController extends BaseController
{
    public function index(Request $r, array $p): void
    {
        Response::ok((new ProductRepository())->all([
            'active' => $r->query('active'),
            'category' => $r->query('category'),
            'search' => $r->query('search'),
        ]));
    }

    public function show(Request $r, array $p): void
    {
        $item = (new ProductRepository())->find($p['id']);

        if (!$item) {
            Response::error('Produto não encontrado', 404);
        }

        Response::ok($item);
    }

    public function store(Request $r, array $p): void
    {
        $d = $r->input();

        if (empty($d['name'])) {
            Response::error('Nome do produto é obrigatório', 422);
        }

        $repo = new ProductRepository();
        $id = $repo->create($d);

        Response::ok($repo->find($id), 201);
    }

    public function update(Request $r, array $p): void
    {
        $repo = new ProductRepository();
        $repo->update($p['id'], $r->input());
        Response::ok($repo->find($p['id']));
    }

    public function destroy(Request $r, array $p): void
    {
        (new ProductRepository())->delete($p['id']);
        Response::noContent();
    }
}

class CategoryController extends BaseController
{
    public function index(Request $r, array $p): void
    {
        Response::ok((new CategoryRepository())->all());
    }

    public function store(Request $r, array $p): void
    {
        $d = $r->input();

        if (empty($d['name'])) {
            Response::error('Nome da categoria é obrigatório', 422);
        }

        $repo = new CategoryRepository();
        $id = $repo->create($d);
        $created = array_values(array_filter(
            $repo->all(),
            fn($c) => (string) $c['id'] === (string) $id
        ));

        Response::ok($created[0] ?? ['id' => (string) $id], 201);
    }

    public function update(Request $r, array $p): void
    {
        $repo = new CategoryRepository();
        $repo->update($p['id'], $r->input());
        $updated = array_values(array_filter(
            $repo->all(),
            fn($c) => (string) $c['id'] === (string) $p['id']
        ));

        Response::ok($updated[0] ?? ['id' => (string) $p['id']]);
    }

    public function destroy(Request $r, array $p): void
    {
        (new CategoryRepository())->delete($p['id']);
        Response::noContent();
    }
}

class CouponController extends BaseController
{
    public function index(Request $r, array $p): void
    {
        Response::ok((new CouponRepository())->all());
    }

    public function validate(Request $r, array $p): void
    {
        $code = (string) ($r->input()['code'] ?? '');

        if ($code === '') {
            Response::error('Informe um cupom', 422);
        }

        $coupon = (new CouponRepository())->findActiveByCode($code);

        if (!$coupon) {
            Response::ok(['valid' => false]);
        }

        Response::ok([
            'valid' => true,
            'coupon' => $coupon,
        ]);
    }

    public function store(Request $r, array $p): void
    {
        $repo = new CouponRepository();
        $id = $repo->create($r->input());
        $created = array_values(array_filter(
            $repo->all(),
            fn($c) => (string) $c['id'] === (string) $id
        ));

        Response::ok($created[0] ?? ['id' => (string) $id], 201);
    }

    public function update(Request $r, array $p): void
    {
        $repo = new CouponRepository();
        $repo->update($p['id'], $r->input());
        $updated = array_values(array_filter(
            $repo->all(),
            fn($c) => (string) $c['id'] === (string) $p['id']
        ));

        Response::ok($updated[0] ?? ['id' => (string) $p['id']]);
    }

    public function destroy(Request $r, array $p): void
    {
        (new CouponRepository())->delete($p['id']);
        Response::noContent();
    }
}

class OrderController extends BaseController
{
    public function index(Request $r, array $p): void
    {
        Response::ok((new OrderRepository())->all());
    }

    public function myOrders(Request $r, array $p): void
    {
        Response::ok((new OrderRepository())->byUser((int) $p['_auth']['id']));
    }

    public function show(Request $r, array $p): void
    {
        $order = (new OrderRepository())->find($p['id']);

        if (!$order) {
            Response::error('Pedido não encontrado', 404);
        }

        $isAdmin = ($p['_auth']['role'] ?? null) === 'admin';
        $isOwner = (string) ($p['_auth']['id'] ?? '') === (string) ($order['userId'] ?? '');

        if (!$isAdmin && !$isOwner) {
            Response::error('Sem permissão para acessar este pedido', 403);
        }

        Response::ok($order);
    }

    public function store(Request $r, array $p): void
    {
        $user = $p['_auth']['user'];
        $d = $r->input();

        $d['userId'] = $p['_auth']['id'];
        $d['customerName'] = $user['name'] ?? '';
        $d['customerEmail'] = $user['email'] ?? '';
        $d['customerPhone'] = $user['phone'] ?? '';

        if (!empty($d['couponCode'])) {
            $repoCoupon = new CouponRepository();
            $coupon = $repoCoupon->findActiveByCode((string) $d['couponCode']);

            if ($coupon) {
                $repoCoupon->incrementUse((string) $coupon['code']);
            }
        }

        $repo = new OrderRepository();
        $id = $repo->create($d);

        Response::ok($repo->find($id), 201);
    }

    public function updateStatus(Request $r, array $p): void
    {
        $status = (string) ($r->input()['status'] ?? '');

        if ($status === '') {
            Response::error('Status é obrigatório', 422);
        }

        $repo = new OrderRepository();
        $repo->updateStatus($p['id'], $status);
        Response::ok($repo->find($p['id']));
    }

    public function updateTracking(Request $r, array $p): void
    {
        $d = $r->input();
        $tracking = (string) ($d['trackingCode'] ?? '');

        if ($tracking === '') {
            Response::error('Código de rastreio é obrigatório', 422);
        }

        $repo = new OrderRepository();
        $repo->updateTracking($p['id'], $tracking, (string) ($d['carrier'] ?? ''));
        Response::ok($repo->find($p['id']));
    }
}

class AdminController extends BaseController
{
    public function dashboard(Request $r, array $p): void
    {
        Response::ok((new OrderRepository())->dashboard());
    }

    public function customers(Request $r, array $p): void
    {
        $rows = (new UserRepository())->allClients();
        Response::ok(array_map(fn($u) => $this->normalizeUser($u), $rows));
    }
}

class PaymentController extends BaseController
{
    public function createInfinitePayCheckout(Request $r, array $p): void
    {
        $d = $r->input();

        $items = $d['items'] ?? [];
        if (!is_array($items) || empty($items)) {
            Response::error('Itens do pedido são obrigatórios', 422);
        }

        $customer = $d['customer'] ?? [];
        $customerName = trim((string)($customer['name'] ?? ''));
        $customerEmail = trim((string)($customer['email'] ?? ''));
        $customerPhone = trim((string)($customer['phone_number'] ?? ''));

        if ($customerName === '' || $customerEmail === '' || $customerPhone === '') {
            Response::error('Dados do cliente são obrigatórios', 422);
        }

        $payloadItems = [];
        foreach ($items as $item) {
            $quantity = (int)($item['quantity'] ?? 0);
            $price = (float)($item['price'] ?? 0);
            $description = trim((string)($item['description'] ?? ''));

            if ($quantity <= 0 || $price <= 0 || $description === '') {
                Response::error('Item inválido no pedido', 422);
            }

            $payloadItems[] = [
                'quantity' => $quantity,
                'price' => (int) round($price * 100),
                'description' => $description,
            ];
        }

        $handle = trim((string)env('INFINITEPAY_HANDLE', ''));
        $redirectUrl = trim((string)env('INFINITEPAY_REDIRECT_URL', ''));
        $webhookUrl = trim((string)env('INFINITEPAY_WEBHOOK_URL', ''));
        $apiBase = rtrim((string)env('INFINITEPAY_API_BASE', 'https://api.infinitepay.io'), '/');

        if ($handle === '' || $redirectUrl === '' || $webhookUrl === '') {
            Response::error('InfinitePay não configurada no .env', 500);
        }

        $orderNsu = trim((string)($d['order_nsu'] ?? ('PED-' . time())));

        $payload = [
            'handle' => $handle,
            'redirect_url' => $redirectUrl,
            'webhook_url' => $webhookUrl,
            'order_nsu' => $orderNsu,
            'items' => $payloadItems,
            'customer' => [
                'name' => $customerName,
                'email' => $customerEmail,
                'phone_number' => $customerPhone,
            ],
        ];

        $ch = curl_init($apiBase . '/invoices/public/checkout/links');

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 60,
        ]);

        $raw = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $curlErr !== '') {
            Response::error('Falha ao criar checkout na InfinitePay: ' . $curlErr, 500);
        }

        $json = json_decode((string)$raw, true);

        if (!is_array($json)) {
            Response::error('Resposta inválida da InfinitePay', 500);
        }

        if ($httpCode >= 400) {
            $msg = $json['message'] ?? $json['error'] ?? 'Erro ao criar checkout da InfinitePay';
            Response::error(is_string($msg) ? $msg : 'Erro ao criar checkout da InfinitePay', 500);
        }

        $checkoutUrl = (string)($json['url'] ?? '');
        if ($checkoutUrl === '') {
            Response::error('InfinitePay não retornou a URL do checkout', 500);
        }

        Response::ok([
            'checkout_url' => $checkoutUrl,
            'provider' => 'infinitepay',
            'order_nsu' => $orderNsu,
            'raw' => $json,
        ], 201);
    }

    public function infinitePayWebhook(Request $r, array $p): void
    {
        $data = $r->input();

        Response::ok([
            'received' => true,
            'payload' => $data,
        ]);
    }
}

class UploadController extends BaseController
{
    public function store(Request $r, array $p): void
    {
        $file = $r->files()['image'] ?? $r->files()['file'] ?? null;

        if (!$file) {
            Response::error('Arquivo não enviado', 422);
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::error('Falha no upload', 422);
        }

        if (($file['size'] ?? 0) > (int) env('UPLOAD_MAX_SIZE', '10485760')) {
            Response::error('Arquivo acima do limite', 422);
        }

        $mime = mime_content_type($file['tmp_name']) ?: '';
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => '',
        };

        if ($ext === '') {
            Response::error('Formato inválido', 422);
        }

        $cloudName = trim((string) env('CLOUDINARY_CLOUD_NAME', ''));
        $apiKey = trim((string) env('CLOUDINARY_API_KEY', ''));
        $apiSecret = trim((string) env('CLOUDINARY_API_SECRET', ''));

        if ($cloudName === '' || $apiKey === '' || $apiSecret === '') {
            Response::error('Cloudinary não configurado no .env', 500);
        }

        $timestamp = time();
        $folder = trim((string) env('CLOUDINARY_FOLDER', 'autenticafashionf'));
        $signatureBase = "folder={$folder}&timestamp={$timestamp}{$apiSecret}";
        $signature = sha1($signatureBase);

        $endpoint = "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload";

        $postFields = [
            'file' => new CURLFile(
                $file['tmp_name'],
                $mime,
                $file['name'] ?? ('upload.' . $ext)
            ),
            'api_key' => $apiKey,
            'timestamp' => (string) $timestamp,
            'folder' => $folder,
            'signature' => $signature,
        ];

        $ch = curl_init($endpoint);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_TIMEOUT => 60,
        ]);

        $raw = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $curlErr !== '') {
            Response::error('Falha ao enviar imagem para o Cloudinary: ' . $curlErr, 500);
        }

        $json = json_decode((string) $raw, true);

        if (!is_array($json)) {
            Response::error('Resposta inválida do Cloudinary', 500);
        }

        if ($httpCode >= 400 || empty($json['secure_url'])) {
            $msg = $json['error']['message'] ?? 'Falha no upload para o Cloudinary';
            Response::error($msg, 500);
        }

        $url = (string) $json['secure_url'];

        Response::ok([
            'url' => $url,
            'image' => $url,
            'image_url' => $url,
        ], 201);
    }
}
