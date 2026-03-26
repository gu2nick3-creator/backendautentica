<?php
declare(strict_types=1);

use App\Config\Env;
use App\Controllers\{AdminController,AuthController,CategoryController,CouponController,CustomerController,HealthController,OrderController,ProductController,UploadController};
use App\Core\{Request,Response,Router};
use App\Middleware\{AdminMiddleware,AuthMiddleware};
use App\Repositories\UserRepository;

require dirname(__DIR__) . '/src/Support/helpers.php';

spl_autoload_register(function(string $class): void {
    $prefix='App\\'; $baseDir=dirname(__DIR__).'/src/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $map=[
        'Config/Env.php',
        'Core/Database.php','Core/Http.php',
        'Services/JwtService.php',
        'Repositories/Repositories.php',
        'Middleware/Middleware.php',
        'Controllers/Controllers.php',
    ];
    foreach ($map as $file) require_once $baseDir . $file;
});

Env::load(dirname(__DIR__) . '/.env');
(new UserRepository())->createAdminIfMissing();

$allowed = array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '*'))));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array('*', $allowed, true)) header('Access-Control-Allow-Origin: *');
elseif ($origin !== '' && in_array($origin, $allowed, true)) header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Credentials: true');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }

$router = new Router();
$router->add('GET','/api/health',[HealthController::class,'index']);

$router->add('POST','/api/auth/register',[AuthController::class,'register']);
$router->add('POST','/api/auth/login',[AuthController::class,'login']);
$router->add('GET','/api/auth/me',[AuthController::class,'me'],[AuthMiddleware::class]);
$router->add('POST','/api/auth/logout',[AuthController::class,'logout'],[AuthMiddleware::class]);

$router->add('GET','/api/customers/me',[CustomerController::class,'me'],[AuthMiddleware::class]);
$router->add('PUT','/api/customers/me',[CustomerController::class,'updateMe'],[AuthMiddleware::class]);

$router->add('GET','/api/categories',[CategoryController::class,'index']);
$router->add('GET','/api/products',[ProductController::class,'index']);
$router->add('GET','/api/products/{id}',[ProductController::class,'show']);
$router->add('POST','/api/coupons/validate',[CouponController::class,'validate']);
$router->add('GET','/api/orders/my',[OrderController::class,'myOrders'],[AuthMiddleware::class]);
$router->add('POST','/api/orders',[OrderController::class,'store'],[AuthMiddleware::class]);

$router->add('GET','/api/admin/dashboard',[AdminController::class,'dashboard'],[AdminMiddleware::class]);
$router->add('GET','/api/admin/products',[ProductController::class,'index'],[AdminMiddleware::class]);
$router->add('POST','/api/admin/products',[ProductController::class,'store'],[AdminMiddleware::class]);
$router->add('PUT','/api/admin/products/{id}',[ProductController::class,'update'],[AdminMiddleware::class]);
$router->add('DELETE','/api/admin/products/{id}',[ProductController::class,'destroy'],[AdminMiddleware::class]);
$router->add('GET','/api/admin/categories',[CategoryController::class,'index'],[AdminMiddleware::class]);
$router->add('POST','/api/admin/categories',[CategoryController::class,'store'],[AdminMiddleware::class]);
$router->add('PUT','/api/admin/categories/{id}',[CategoryController::class,'update'],[AdminMiddleware::class]);
$router->add('DELETE','/api/admin/categories/{id}',[CategoryController::class,'destroy'],[AdminMiddleware::class]);
$router->add('GET','/api/admin/customers',[AdminController::class,'customers'],[AdminMiddleware::class]);
$router->add('GET','/api/admin/orders',[OrderController::class,'index'],[AdminMiddleware::class]);
$router->add('PUT','/api/admin/orders/{id}/status',[OrderController::class,'updateStatus'],[AdminMiddleware::class]);
$router->add('PUT','/api/admin/orders/{id}/tracking',[OrderController::class,'updateTracking'],[AdminMiddleware::class]);
$router->add('GET','/api/admin/coupons',[CouponController::class,'index'],[AdminMiddleware::class]);
$router->add('POST','/api/admin/coupons',[CouponController::class,'store'],[AdminMiddleware::class]);
$router->add('PUT','/api/admin/coupons/{id}',[CouponController::class,'update'],[AdminMiddleware::class]);
$router->add('DELETE','/api/admin/coupons/{id}',[CouponController::class,'destroy'],[AdminMiddleware::class]);
$router->add('POST','/api/admin/uploads',[UploadController::class,'store'],[AdminMiddleware::class]);

try { $router->dispatch(new Request()); }
catch (Throwable $e) { Response::error($e->getMessage(), 500); }
