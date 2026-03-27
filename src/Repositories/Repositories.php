<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Core\Database;

class UserRepository {
    public function findByEmail(string $email): ?array { $s=Database::connection()->prepare('SELECT * FROM users WHERE email=:email LIMIT 1'); $s->execute(['email'=>mb_strtolower(trim($email))]); return $s->fetch() ?: null; }
    public function findById(int|string $id): ?array { $s=Database::connection()->prepare('SELECT * FROM users WHERE id=:id LIMIT 1'); $s->execute(['id'=>$id]); return $s->fetch() ?: null; }
    public function create(array $d): int { $s=Database::connection()->prepare('INSERT INTO users (name,email,phone,cpf_cnpj,password_hash,role,created_at,updated_at) VALUES (:name,:email,:phone,:cpf,:hash,:role,NOW(),NOW())'); $s->execute(['name'=>$d['name'],'email'=>$d['email'],'phone'=>$d['phone']??'','cpf'=>$d['cpf_cnpj']??'','hash'=>$d['password_hash'],'role'=>$d['role']??'client']); return (int)Database::connection()->lastInsertId(); }
    public function updateProfile(int|string $id, array $d): bool { $s=Database::connection()->prepare('UPDATE users SET name=:name, phone=:phone, cpf_cnpj=:cpf, street=:street, number=:number, complement=:complement, neighborhood=:neighborhood, city=:city, state=:state, zip=:zip, updated_at=NOW() WHERE id=:id'); return $s->execute(['id'=>$id,'name'=>$d['name']??'','phone'=>$d['phone']??'','cpf'=>$d['cpfCnpj']??($d['cpf_cnpj']??''),'street'=>$d['address']['street']??($d['street']??''),'number'=>$d['address']['number']??($d['number']??''),'complement'=>$d['address']['complement']??($d['complement']??''),'neighborhood'=>$d['address']['neighborhood']??($d['neighborhood']??''),'city'=>$d['address']['city']??($d['city']??''),'state'=>$d['address']['state']??($d['state']??''),'zip'=>$d['address']['zip']??($d['zip']??'')]); }
    public function allClients(): array { return Database::connection()->query("SELECT id,name,email,phone,cpf_cnpj,role,created_at FROM users WHERE role='client' ORDER BY id DESC")->fetchAll(); }
    public function createAdminIfMissing(): void { $email=env('ADMIN_EMAIL','admin@autenticafashionf.com'); if ($this->findByEmail($email)) return; $this->create(['name'=>env('ADMIN_NAME','Administrador'),'email'=>$email,'phone'=>'','cpf_cnpj'=>'','password_hash'=>password_hash((string)env('ADMIN_PASSWORD','adm123@'), PASSWORD_BCRYPT),'role'=>'admin']); }
}

class CategoryRepository {
    public function all(): array { $rows=Database::connection()->query('SELECT * FROM categories ORDER BY sort_order ASC, id DESC')->fetchAll(); foreach ($rows as &$r) { $s=Database::connection()->prepare('SELECT id,name FROM subcategories WHERE category_id=:id ORDER BY id ASC'); $s->execute(['id'=>$r['id']]); $r=['id'=>(string)$r['id'],'name'=>$r['name'],'image'=>$r['image_url']??'','subcategories'=>$s->fetchAll() ?: []]; } return $rows; }
    public function create(array $d): int { $s=Database::connection()->prepare('INSERT INTO categories (name,image_url,sort_order,created_at,updated_at) VALUES (:name,:image,:sort,NOW(),NOW())'); $s->execute(['name'=>$d['name'],'image'=>$d['image']??($d['image_url']??''),'sort'=>(int)($d['sort_order']??0)]); $id=(int)Database::connection()->lastInsertId(); $this->syncSub($id, $d['subcategories']??[]); return $id; }
    public function update(int|string $id, array $d): bool { $s=Database::connection()->prepare('UPDATE categories SET name=:name, image_url=:image, sort_order=:sort, updated_at=NOW() WHERE id=:id'); $ok=$s->execute(['id'=>$id,'name'=>$d['name'],'image'=>$d['image']??($d['image_url']??''),'sort'=>(int)($d['sort_order']??0)]); $this->syncSub((int)$id, $d['subcategories']??[]); return $ok; }
    public function delete(int|string $id): bool { $s=Database::connection()->prepare('DELETE FROM categories WHERE id=:id'); return $s->execute(['id'=>$id]); }
    private function syncSub(int $id, array $items): void { Database::connection()->prepare('DELETE FROM subcategories WHERE category_id=:id')->execute(['id'=>$id]); $s=Database::connection()->prepare('INSERT INTO subcategories (category_id,name,created_at,updated_at) VALUES (:id,:name,NOW(),NOW())'); foreach ($items as $it) { $name=is_array($it)?($it['name']??''):(string)$it; if (trim($name)!=='') $s->execute(['id'=>$id,'name'=>trim($name)]); } }
}
private function mapProduct(array $row): array
{
    $images = [];

    if (!empty($row['gallery_json'])) {
        $decoded = json_decode((string) $row['gallery_json'], true);
        if (is_array($decoded)) {
            $images = $decoded;
        }
    }

    if (empty($images) && !empty($row['image_url'])) {
        $images = [(string) $row['image_url']];
    }

    $mainImage = $images[0] ?? ($row['image_url'] ?? '');

    return [
        'id' => (string) $row['id'],
        'sku' => $row['sku'] ?? '',
        'name' => $row['name'] ?? '',
        'description' => $row['description'] ?? '',
        'category' => $row['category'] ?? '',
        'subcategory' => $row['subcategory'] ?? '',
        'priceNormal' => (float)($row['price_normal'] ?? $row['price'] ?? 0),
        'priceResale' => (float)($row['price_resale'] ?? $row['resale_price'] ?? 0),
        'stock' => (int)($row['stock'] ?? 0),
        'active' => (bool)($row['active'] ?? $row['is_active'] ?? 0),
        'featured' => (bool)($row['featured'] ?? $row['is_featured'] ?? 0),
        'isNew' => (bool)($row['is_new'] ?? 0),
        'isPopular' => (bool)($row['is_popular'] ?? $row['popular'] ?? 0),
        'type' => $row['type'] ?? $row['product_type'] ?? '',
        'sizes' => !empty($row['sizes_json']) ? (json_decode((string)$row['sizes_json'], true) ?: []) : [],
        'colors' => !empty($row['colors_json']) ? (json_decode((string)$row['colors_json'], true) ?: []) : [],
        'images' => $images,
        'image' => $mainImage,
        'image_url' => $mainImage,
    ];

class CouponRepository {
    public function all(): array { $rows=Database::connection()->query('SELECT * FROM coupons ORDER BY id DESC')->fetchAll(); return array_map([$this,'map'],$rows); }
    public function findActiveByCode(string $code): ?array { $s=Database::connection()->prepare('SELECT * FROM coupons WHERE code=:code AND active=1 LIMIT 1'); $s->execute(['code'=>mb_strtoupper(trim($code))]); $r=$s->fetch(); return $r ? $this->map($r) : null; }
    public function create(array $d): int { $s=Database::connection()->prepare('INSERT INTO coupons (code,type,discount,valid_until,max_uses,current_uses,uses_per_client,active,created_at,updated_at) VALUES (:code,:type,:discount,:valid,:max,:current,:per,:active,NOW(),NOW())'); $s->execute(['code'=>mb_strtoupper(trim((string)$d['code'])),'type'=>$d['type'],'discount'=>(float)$d['discount'],'valid'=>$d['validUntil']??($d['valid_until']??null),'max'=>(int)($d['maxUses']??$d['max_uses']??0),'current'=>(int)($d['currentUses']??$d['current_uses']??0),'per'=>(int)($d['usesPerClient']??$d['uses_per_client']??1),'active'=>!empty($d['active'])?1:0]); return (int)Database::connection()->lastInsertId(); }
    public function update(int|string $id, array $d): bool { $s=Database::connection()->prepare('UPDATE coupons SET code=:code,type=:type,discount=:discount,valid_until=:valid,max_uses=:max,current_uses=:current,uses_per_client=:per,active=:active,updated_at=NOW() WHERE id=:id'); return $s->execute(['id'=>$id,'code'=>mb_strtoupper(trim((string)$d['code'])),'type'=>$d['type'],'discount'=>(float)$d['discount'],'valid'=>$d['validUntil']??($d['valid_until']??null),'max'=>(int)($d['maxUses']??$d['max_uses']??0),'current'=>(int)($d['currentUses']??$d['current_uses']??0),'per'=>(int)($d['usesPerClient']??$d['uses_per_client']??1),'active'=>!empty($d['active'])?1:0]); }
    public function delete(int|string $id): bool { $s=Database::connection()->prepare('DELETE FROM coupons WHERE id=:id'); return $s->execute(['id'=>$id]); }
    public function incrementUse(string $code): void { $s=Database::connection()->prepare('UPDATE coupons SET current_uses=current_uses+1, updated_at=NOW() WHERE code=:code'); $s->execute(['code'=>mb_strtoupper(trim($code))]); }
    private function map(array $r): array { return ['id'=>(string)$r['id'],'code'=>$r['code'],'type'=>$r['type'],'discount'=>(float)$r['discount'],'validUntil'=>$r['valid_until'],'maxUses'=>(int)$r['max_uses'],'currentUses'=>(int)$r['current_uses'],'usesPerClient'=>(int)$r['uses_per_client'],'active'=>(bool)$r['active']]; }
}

class OrderRepository {
    public function all(): array { return array_map([$this,'map'], Database::connection()->query('SELECT * FROM orders ORDER BY id DESC')->fetchAll()); }
    public function find(int|string $id): ?array { $s=Database::connection()->prepare('SELECT * FROM orders WHERE id=:id LIMIT 1'); $s->execute(['id'=>$id]); $r=$s->fetch(); return $r ? $this->map($r) : null; }
    public function byUser(int|string $userId): array { $s=Database::connection()->prepare('SELECT * FROM orders WHERE user_id=:id ORDER BY id DESC'); $s->execute(['id'=>$userId]); return array_map([$this,'map'],$s->fetchAll()); }
    public function create(array $d): int { $pdo=Database::connection(); $pdo->beginTransaction(); $a=$d['address']??[]; $s=$pdo->prepare('INSERT INTO orders (user_id,customer_name,customer_email,customer_phone,street,number,complement,neighborhood,city,state,zip,price_type,subtotal,discount,total,coupon_code,status,tracking_code,carrier,created_at,updated_at) VALUES (:uid,:name,:email,:phone,:street,:number,:complement,:neighborhood,:city,:state,:zip,:ptype,:subtotal,:discount,:total,:coupon,:status,:tracking,:carrier,NOW(),NOW())'); $s->execute(['uid'=>$d['userId']??null,'name'=>$d['customerName']??'','email'=>$d['customerEmail']??'','phone'=>$d['customerPhone']??'','street'=>$a['street']??'','number'=>$a['number']??'','complement'=>$a['complement']??'','neighborhood'=>$a['neighborhood']??'','city'=>$a['city']??'','state'=>$a['state']??'','zip'=>$a['zip']??'','ptype'=>$d['priceType']??'normal','subtotal'=>(float)$d['subtotal'],'discount'=>(float)($d['discount']??0),'total'=>(float)$d['total'],'coupon'=>$d['couponCode']??null,'status'=>$d['status']??'em_analise','tracking'=>$d['trackingCode']??null,'carrier'=>$d['carrier']??null]); $id=(int)$pdo->lastInsertId(); $i=$pdo->prepare('INSERT INTO order_items (order_id,product_id,product_name,product_sku,price_type,unit_price,quantity,selected_color,size_distribution_json,image_url,created_at,updated_at) VALUES (:order_id,:product_id,:product_name,:product_sku,:price_type,:unit_price,:quantity,:selected_color,:size_distribution_json,:image_url,NOW(),NOW())'); foreach (($d['items']??[]) as $item) { $p=$item['product']??[]; $i->execute(['order_id'=>$id,'product_id'=>$p['id']??null,'product_name'=>$p['name']??'','product_sku'=>$p['sku']??'','price_type'=>$item['priceType']??'normal','unit_price'=>($item['priceType']??'normal')==='resale'?(float)($p['priceResale']??0):(float)($p['priceNormal']??0),'quantity'=>(int)($item['quantity']??1),'selected_color'=>$item['selectedColor']??null,'size_distribution_json'=>json_encode($item['sizeDistribution']??[], JSON_UNESCAPED_UNICODE),'image_url'=>$p['images'][0]??null]); } $pdo->commit(); return $id; }
    public function updateStatus(int|string $id, string $status): bool { $s=Database::connection()->prepare('UPDATE orders SET status=:status, updated_at=NOW() WHERE id=:id'); return $s->execute(['id'=>$id,'status'=>$status]); }
    public function updateTracking(int|string $id, string $tracking, string $carrier): bool { $s=Database::connection()->prepare('UPDATE orders SET tracking_code=:tracking, carrier=:carrier, updated_at=NOW() WHERE id=:id'); return $s->execute(['id'=>$id,'tracking'=>$tracking,'carrier'=>$carrier]); }
    public function dashboard(): array { $pdo=Database::connection(); return ['totalRevenue'=>(float)($pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ('pago','enviado','entregue')")->fetchColumn() ?: 0),'paidOrders'=>(int)($pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pago','enviado','entregue')")->fetchColumn() ?: 0),'pendingOrders'=>(int)($pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('em_analise','em_preparo')")->fetchColumn() ?: 0),'totalOrders'=>(int)($pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn() ?: 0)]; }
    private function map(array $r): array { $s=Database::connection()->prepare('SELECT * FROM order_items WHERE order_id=:id ORDER BY id ASC'); $s->execute(['id'=>$r['id']]); $items=array_map(fn($x)=>['product'=>['id'=>(string)$x['product_id'],'name'=>$x['product_name'],'sku'=>$x['product_sku'],'images'=>$x['image_url']?[$x['image_url']]:[],'priceNormal'=>(float)$x['unit_price'],'priceResale'=>(float)$x['unit_price']],'priceType'=>$x['price_type'],'quantity'=>(int)$x['quantity'],'selectedColor'=>$x['selected_color'],'sizeDistribution'=>json_decode((string)$x['size_distribution_json'], true) ?: []], $s->fetchAll()); return ['id'=>(string)$r['id'],'userId'=>$r['user_id']?(string)$r['user_id']:null,'customerName'=>$r['customer_name'],'customerEmail'=>$r['customer_email'],'customerPhone'=>$r['customer_phone'],'address'=>['street'=>$r['street']??'','number'=>$r['number']??'','complement'=>$r['complement']??'','neighborhood'=>$r['neighborhood']??'','city'=>$r['city']??'','state'=>$r['state']??'','zip'=>$r['zip']??''],'items'=>$items,'priceType'=>$r['price_type'],'subtotal'=>(float)$r['subtotal'],'discount'=>(float)$r['discount'],'total'=>(float)$r['total'],'couponCode'=>$r['coupon_code'],'status'=>$r['status'],'trackingCode'=>$r['tracking_code'],'carrier'=>$r['carrier'],'createdAt'=>$r['created_at'],'updatedAt'=>$r['updated_at']]; }
}
