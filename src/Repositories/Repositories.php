<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class UserRepository
{
    public function findByEmail(string $email): ?array
    {
        $s = Database::connection()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $s->execute([
            'email' => mb_strtolower(trim($email)),
        ]);

        return $s->fetch() ?: null;
    }

    public function findById(int|string $id): ?array
    {
        $s = Database::connection()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $s->execute(['id' => $id]);

        return $s->fetch() ?: null;
    }

    public function create(array $d): int
    {
        $s = Database::connection()->prepare(
            'INSERT INTO users (name, email, phone, cpf_cnpj, password_hash, role, created_at, updated_at)
             VALUES (:name, :email, :phone, :cpf, :hash, :role, NOW(), NOW())'
        );

        $s->execute([
            'name' => $d['name'] ?? '',
            'email' => mb_strtolower(trim((string)($d['email'] ?? ''))),
            'phone' => $d['phone'] ?? '',
            'cpf' => $d['cpf_cnpj'] ?? '',
            'hash' => $d['password_hash'] ?? '',
            'role' => $d['role'] ?? 'client',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function updateProfile(int|string $id, array $d): bool
    {
        $address = $d['address'] ?? [];

        $s = Database::connection()->prepare(
            'UPDATE users
             SET name = :name,
                 phone = :phone,
                 cpf_cnpj = :cpf,
                 street = :street,
                 number = :number,
                 complement = :complement,
                 neighborhood = :neighborhood,
                 city = :city,
                 state = :state,
                 zip = :zip,
                 updated_at = NOW()
             WHERE id = :id'
        );

        return $s->execute([
            'id' => $id,
            'name' => $d['name'] ?? '',
            'phone' => $d['phone'] ?? '',
            'cpf' => $d['cpfCnpj'] ?? ($d['cpf_cnpj'] ?? ''),
            'street' => $address['street'] ?? ($d['street'] ?? ''),
            'number' => $address['number'] ?? ($d['number'] ?? ''),
            'complement' => $address['complement'] ?? ($d['complement'] ?? ''),
            'neighborhood' => $address['neighborhood'] ?? ($d['neighborhood'] ?? ''),
            'city' => $address['city'] ?? ($d['city'] ?? ''),
            'state' => $address['state'] ?? ($d['state'] ?? ''),
            'zip' => $address['zip'] ?? ($d['zip'] ?? ''),
        ]);
    }

    public function allClients(): array
    {
        return Database::connection()
            ->query("SELECT id, name, email, phone, cpf_cnpj, role, created_at FROM users WHERE role = 'client' ORDER BY id DESC")
            ->fetchAll();
    }

    public function createAdminIfMissing(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@autenticafashionf.com');

        if ($this->findByEmail($email)) {
            return;
        }

        $this->create([
            'name' => env('ADMIN_NAME', 'Administrador'),
            'email' => $email,
            'phone' => '',
            'cpf_cnpj' => '',
            'password_hash' => password_hash((string) env('ADMIN_PASSWORD', 'adm123@'), PASSWORD_BCRYPT),
            'role' => 'admin',
        ]);
    }
}

class CategoryRepository
{
    public function all(): array
    {
        $rows = Database::connection()
            ->query('SELECT * FROM categories ORDER BY sort_order ASC, id DESC')
            ->fetchAll();

        $out = [];

        foreach ($rows as $row) {
            $s = Database::connection()->prepare(
                'SELECT id, name, slug
                 FROM subcategories
                 WHERE category_id = :id
                 ORDER BY sort_order ASC, id ASC'
            );
            $s->execute(['id' => $row['id']]);

            $out[] = [
                'id' => (string) $row['id'],
                'name' => $row['name'] ?? '',
                'slug' => $row['slug'] ?? '',
                'image' => $row['image_url'] ?? '',
                'image_url' => $row['image_url'] ?? '',
                'subcategories' => $s->fetchAll() ?: [],
            ];
        }

        return $out;
    }

    public function create(array $d): int
    {
        $s = Database::connection()->prepare(
            'INSERT INTO categories (name, slug, image_url, sort_order, is_active, created_at, updated_at)
             VALUES (:name, :slug, :image, :sort, :active, NOW(), NOW())'
        );

        $name = trim((string) ($d['name'] ?? ''));
        $slug = trim((string) ($d['slug'] ?? $this->slugify($name)));

        $s->execute([
            'name' => $name,
            'slug' => $slug,
            'image' => $d['image'] ?? ($d['image_url'] ?? ''),
            'sort' => (int) ($d['sort_order'] ?? 0),
            'active' => !empty($d['is_active']) || !isset($d['is_active']) ? 1 : 0,
        ]);

        $id = (int) Database::connection()->lastInsertId();
        $this->syncSubcategories($id, $d['subcategories'] ?? []);

        return $id;
    }

    public function update(int|string $id, array $d): bool
    {
        $existing = $this->findRaw((int) $id);
        $name = trim((string) ($d['name'] ?? ($existing['name'] ?? '')));
        $slug = trim((string) ($d['slug'] ?? ($existing['slug'] ?? $this->slugify($name))));

        $s = Database::connection()->prepare(
            'UPDATE categories
             SET name = :name,
                 slug = :slug,
                 image_url = :image,
                 sort_order = :sort,
                 is_active = :active,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $ok = $s->execute([
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'image' => $d['image'] ?? ($d['image_url'] ?? ($existing['image_url'] ?? '')),
            'sort' => (int) ($d['sort_order'] ?? ($existing['sort_order'] ?? 0)),
            'active' => isset($d['is_active']) ? (!empty($d['is_active']) ? 1 : 0) : (int) ($existing['is_active'] ?? 1),
        ]);

        if (array_key_exists('subcategories', $d)) {
            $this->syncSubcategories((int) $id, $d['subcategories'] ?? []);
        }

        return $ok;
    }

    public function delete(int|string $id): bool
    {
        $s = Database::connection()->prepare('DELETE FROM categories WHERE id = :id');
        return $s->execute(['id' => $id]);
    }

    private function findRaw(int $id): ?array
    {
        $s = Database::connection()->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
        $s->execute(['id' => $id]);
        return $s->fetch() ?: null;
    }

    private function syncSubcategories(int $categoryId, array $items): void
    {
        Database::connection()
            ->prepare('DELETE FROM subcategories WHERE category_id = :id')
            ->execute(['id' => $categoryId]);

        $s = Database::connection()->prepare(
            'INSERT INTO subcategories (category_id, name, slug, sort_order, is_active, created_at, updated_at)
             VALUES (:category_id, :name, :slug, :sort_order, :is_active, NOW(), NOW())'
        );

        $sort = 0;

        foreach ($items as $item) {
            $name = is_array($item) ? (string) ($item['name'] ?? '') : (string) $item;
            $name = trim($name);

            if ($name === '') {
                continue;
            }

            $s->execute([
                'category_id' => $categoryId,
                'name' => $name,
                'slug' => $this->slugify($name),
                'sort_order' => $sort++,
                'is_active' => 1,
            ]);
        }
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
        return trim($value, '-');
    }
}

class ProductRepository
{
    public function all(array $filters = []): array
    {
        $sql = 'SELECT * FROM products WHERE 1=1';
        $params = [];

        if (array_key_exists('active', $filters) && $filters['active'] !== null && $filters['active'] !== '') {
            $sql .= ' AND (is_active = :active OR active = :active)';
            $params['active'] = (int) $filters['active'];
        }

        if (!empty($filters['category'])) {
            $sql .= ' AND category = :category';
            $params['category'] = (string) $filters['category'];
        }

        if (!empty($filters['search'])) {
            $sql .= ' AND (name LIKE :search OR sku LIKE :search OR description LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql .= ' ORDER BY id DESC';

        $s = Database::connection()->prepare($sql);
        $s->execute($params);

        return array_map([$this, 'mapProduct'], $s->fetchAll());
    }

    public function find(int|string $id): ?array
    {
        $s = Database::connection()->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $s->execute(['id' => $id]);
        $row = $s->fetch();

        return $row ? $this->mapProduct($row) : null;
    }

    public function create(array $d): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $categoryName = trim((string) ($d['category'] ?? ''));
            $categoryId = $this->resolveCategoryId($categoryName);

            $gallery = $this->normalizeImages($d);
            $mainImage = $gallery[0] ?? '';

            $sizes = $this->normalizeSizes($d);
            $colors = $this->normalizeColors($d);

            $s = $pdo->prepare(
                'INSERT INTO products (
                    category_id, category, subcategory, product_type, type,
                    name, slug, sku, description,
                    price, price_normal, resale_price, price_resale,
                    stock,
                    image_url, gallery_json, images,
                    colors_json, colors,
                    sizes_json, sizes,
                    is_featured, featured,
                    is_launch, launch, is_new,
                    is_popular, popular,
                    is_active, active,
                    created_at, updated_at
                 ) VALUES (
                    :category_id, :category, :subcategory, :product_type, :type,
                    :name, :slug, :sku, :description,
                    :price, :price_normal, :resale_price, :price_resale,
                    :stock,
                    :image_url, :gallery_json, :images,
                    :colors_json, :colors,
                    :sizes_json, :sizes,
                    :is_featured, :featured,
                    :is_launch, :launch, :is_new,
                    :is_popular, :popular,
                    :is_active, :active,
                    NOW(), NOW()
                 )'
            );

            $name = trim((string) ($d['name'] ?? ''));
            $sku = trim((string) ($d['sku'] ?? ''));
            $type = trim((string) ($d['type'] ?? ($d['product_type'] ?? '')));

            $s->execute([
                'category_id' => $categoryId,
                'category' => $categoryName,
                'subcategory' => trim((string) ($d['subcategory'] ?? '')),
                'product_type' => $type,
                'type' => $type,
                'name' => $name,
                'slug' => trim((string) ($d['slug'] ?? $this->slugify($name))),
                'sku' => $sku,
                'description' => (string) ($d['description'] ?? ''),
                'price' => (float) ($d['price'] ?? $d['priceNormal'] ?? $d['price_normal'] ?? 0),
                'price_normal' => (float) ($d['priceNormal'] ?? $d['price_normal'] ?? $d['price'] ?? 0),
                'resale_price' => (float) ($d['priceResale'] ?? $d['price_resale'] ?? $d['resale_price'] ?? 0),
                'price_resale' => (float) ($d['priceResale'] ?? $d['price_resale'] ?? $d['resale_price'] ?? 0),
                'stock' => (int) ($d['stock'] ?? 0),
                'image_url' => $mainImage,
                'gallery_json' => json_encode($gallery, JSON_UNESCAPED_UNICODE),
                'images' => json_encode($gallery, JSON_UNESCAPED_UNICODE),
                'colors_json' => json_encode($colors, JSON_UNESCAPED_UNICODE),
                'colors' => json_encode($colors, JSON_UNESCAPED_UNICODE),
                'sizes_json' => json_encode($sizes, JSON_UNESCAPED_UNICODE),
                'sizes' => json_encode($sizes, JSON_UNESCAPED_UNICODE),
                'is_featured' => !empty($d['featured']) || !empty($d['is_featured']) ? 1 : 0,
                'featured' => !empty($d['featured']) || !empty($d['is_featured']) ? 1 : 0,
                'is_launch' => !empty($d['launch']) || !empty($d['is_launch']) ? 1 : 0,
                'launch' => !empty($d['launch']) || !empty($d['is_launch']) ? 1 : 0,
                'is_new' => !empty($d['isNew']) || !empty($d['is_new']) || !empty($d['launch']) ? 1 : 0,
                'is_popular' => !empty($d['popular']) || !empty($d['is_popular']) ? 1 : 0,
                'popular' => !empty($d['popular']) || !empty($d['is_popular']) ? 1 : 0,
                'is_active' => array_key_exists('active', $d) ? (!empty($d['active']) ? 1 : 0) : 1,
                'active' => array_key_exists('active', $d) ? (!empty($d['active']) ? 1 : 0) : 1,
            ]);

            $id = (int) $pdo->lastInsertId();

            $this->syncSizes($id, $sizes);
            $this->syncColors($id, $colors);
            $this->syncImages($id, $gallery);

            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function update(int|string $id, array $d): bool
    {
        $existing = $this->findRaw((int) $id);

        if (!$existing) {
            return false;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $categoryName = trim((string) ($d['category'] ?? ($existing['category'] ?? '')));
            $categoryId = $this->resolveCategoryId($categoryName);

            $gallery = $this->normalizeImages($d);
            if (empty($gallery) && !empty($existing['gallery_json'])) {
                $decoded = json_decode((string) $existing['gallery_json'], true);
                if (is_array($decoded)) {
                    $gallery = $decoded;
                }
            }
            if (empty($gallery) && !empty($existing['image_url'])) {
                $gallery = [(string) $existing['image_url']];
            }

            $mainImage = $gallery[0] ?? '';
            $sizes = $this->normalizeSizes($d);
            $colors = $this->normalizeColors($d);

            if (empty($sizes) && !empty($existing['sizes_json'])) {
                $sizes = json_decode((string) $existing['sizes_json'], true) ?: [];
            }

            if (empty($colors) && !empty($existing['colors_json'])) {
                $colors = json_decode((string) $existing['colors_json'], true) ?: [];
            }

            $type = trim((string) ($d['type'] ?? ($d['product_type'] ?? ($existing['type'] ?? $existing['product_type'] ?? ''))));

            $s = $pdo->prepare(
                'UPDATE products
                 SET category_id = :category_id,
                     category = :category,
                     subcategory = :subcategory,
                     product_type = :product_type,
                     type = :type,
                     name = :name,
                     slug = :slug,
                     sku = :sku,
                     description = :description,
                     price = :price,
                     price_normal = :price_normal,
                     resale_price = :resale_price,
                     price_resale = :price_resale,
                     stock = :stock,
                     image_url = :image_url,
                     gallery_json = :gallery_json,
                     images = :images,
                     colors_json = :colors_json,
                     colors = :colors,
                     sizes_json = :sizes_json,
                     sizes = :sizes,
                     is_featured = :is_featured,
                     featured = :featured,
                     is_launch = :is_launch,
                     launch = :launch,
                     is_new = :is_new,
                     is_popular = :is_popular,
                     popular = :popular,
                     is_active = :is_active,
                     active = :active,
                     updated_at = NOW()
                 WHERE id = :id'
            );

            $s->execute([
                'id' => $id,
                'category_id' => $categoryId,
                'category' => $categoryName,
                'subcategory' => trim((string) ($d['subcategory'] ?? ($existing['subcategory'] ?? ''))),
                'product_type' => $type,
                'type' => $type,
                'name' => trim((string) ($d['name'] ?? ($existing['name'] ?? ''))),
                'slug' => trim((string) ($d['slug'] ?? ($existing['slug'] ?? $this->slugify((string) ($d['name'] ?? $existing['name'] ?? ''))))),
                'sku' => trim((string) ($d['sku'] ?? ($existing['sku'] ?? ''))),
                'description' => (string) ($d['description'] ?? ($existing['description'] ?? '')),
                'price' => (float) ($d['price'] ?? $d['priceNormal'] ?? $d['price_normal'] ?? $existing['price'] ?? 0),
                'price_normal' => (float) ($d['priceNormal'] ?? $d['price_normal'] ?? $existing['price_normal'] ?? $existing['price'] ?? 0),
                'resale_price' => (float) ($d['priceResale'] ?? $d['price_resale'] ?? $existing['resale_price'] ?? 0),
                'price_resale' => (float) ($d['priceResale'] ?? $d['price_resale'] ?? $existing['price_resale'] ?? $existing['resale_price'] ?? 0),
                'stock' => (int) ($d['stock'] ?? ($existing['stock'] ?? 0)),
                'image_url' => $mainImage,
                'gallery_json' => json_encode($gallery, JSON_UNESCAPED_UNICODE),
                'images' => json_encode($gallery, JSON_UNESCAPED_UNICODE),
                'colors_json' => json_encode($colors, JSON_UNESCAPED_UNICODE),
                'colors' => json_encode($colors, JSON_UNESCAPED_UNICODE),
                'sizes_json' => json_encode($sizes, JSON_UNESCAPED_UNICODE),
                'sizes' => json_encode($sizes, JSON_UNESCAPED_UNICODE),
                'is_featured' => isset($d['featured']) || isset($d['is_featured']) ? ((!empty($d['featured']) || !empty($d['is_featured'])) ? 1 : 0) : (int) ($existing['is_featured'] ?? 0),
                'featured' => isset($d['featured']) || isset($d['is_featured']) ? ((!empty($d['featured']) || !empty($d['is_featured'])) ? 1 : 0) : (int) ($existing['featured'] ?? 0),
                'is_launch' => isset($d['launch']) || isset($d['is_launch']) ? ((!empty($d['launch']) || !empty($d['is_launch'])) ? 1 : 0) : (int) ($existing['is_launch'] ?? 0),
                'launch' => isset($d['launch']) || isset($d['is_launch']) ? ((!empty($d['launch']) || !empty($d['is_launch'])) ? 1 : 0) : (int) ($existing['launch'] ?? 0),
                'is_new' => isset($d['isNew']) || isset($d['is_new']) ? ((!empty($d['isNew']) || !empty($d['is_new'])) ? 1 : 0) : (int) ($existing['is_new'] ?? 0),
                'is_popular' => isset($d['popular']) || isset($d['is_popular']) ? ((!empty($d['popular']) || !empty($d['is_popular'])) ? 1 : 0) : (int) ($existing['is_popular'] ?? 0),
                'popular' => isset($d['popular']) || isset($d['is_popular']) ? ((!empty($d['popular']) || !empty($d['is_popular'])) ? 1 : 0) : (int) ($existing['popular'] ?? 0),
                'is_active' => isset($d['active']) ? (!empty($d['active']) ? 1 : 0) : (int) ($existing['is_active'] ?? 1),
                'active' => isset($d['active']) ? (!empty($d['active']) ? 1 : 0) : (int) ($existing['active'] ?? 1),
            ]);

            $this->syncSizes((int) $id, $sizes);
            $this->syncColors((int) $id, $colors);
            $this->syncImages((int) $id, $gallery);

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function delete(int|string $id): bool
    {
        $s = Database::connection()->prepare('DELETE FROM products WHERE id = :id');
        return $s->execute(['id' => $id]);
    }

    private function mapProduct(array $row): array
    {
        $images = $this->fetchImages((int) $row['id']);

        if (empty($images) && !empty($row['gallery_json'])) {
            $decoded = json_decode((string) $row['gallery_json'], true);
            if (is_array($decoded)) {
                $images = $decoded;
            }
        }

        if (empty($images) && !empty($row['image_url'])) {
            $images = [(string) $row['image_url']];
        }

        $sizes = $this->fetchSizes((int) $row['id']);
        if (empty($sizes) && !empty($row['sizes_json'])) {
            $sizes = json_decode((string) $row['sizes_json'], true) ?: [];
        }

        $colors = $this->fetchColors((int) $row['id']);
        if (empty($colors) && !empty($row['colors_json'])) {
            $colors = json_decode((string) $row['colors_json'], true) ?: [];
        }

        $mainImage = $images[0] ?? ($row['image_url'] ?? '');

        return [
            'id' => (string) $row['id'],
            'sku' => $row['sku'] ?? '',
            'name' => $row['name'] ?? '',
            'description' => $row['description'] ?? '',
            'category' => $row['category'] ?? '',
            'subcategory' => $row['subcategory'] ?? '',
            'priceNormal' => (float) ($row['price_normal'] ?? $row['price'] ?? 0),
            'priceResale' => (float) ($row['price_resale'] ?? $row['resale_price'] ?? 0),
            'stock' => (int) ($row['stock'] ?? 0),
            'active' => (bool) ($row['active'] ?? $row['is_active'] ?? 0),
            'featured' => (bool) ($row['featured'] ?? $row['is_featured'] ?? 0),
            'isNew' => (bool) ($row['is_new'] ?? 0),
            'isPopular' => (bool) ($row['is_popular'] ?? $row['popular'] ?? 0),
            'type' => $row['type'] ?? $row['product_type'] ?? '',
            'sizes' => array_values($sizes),
            'colors' => array_values($colors),
            'images' => array_values($images),
            'image' => $mainImage,
            'image_url' => $mainImage,
        ];
    }

    private function findRaw(int $id): ?array
    {
        $s = Database::connection()->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $s->execute(['id' => $id]);
        return $s->fetch() ?: null;
    }

    private function resolveCategoryId(string $categoryName): ?int
    {
        if ($categoryName === '') {
            return null;
        }

        $s = Database::connection()->prepare('SELECT id FROM categories WHERE name = :name LIMIT 1');
        $s->execute(['name' => $categoryName]);
        $row = $s->fetch();

        return $row ? (int) $row['id'] : null;
    }

    private function normalizeSizes(array $d): array
    {
        $raw = $d['sizes'] ?? $d['size'] ?? [];

        if (is_string($raw)) {
            $raw = array_map('trim', explode(',', $raw));
        }

        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn($v) => trim((string) $v),
            $raw
        ), fn($v) => $v !== ''));
    }

    private function normalizeColors(array $d): array
    {
        $raw = $d['colors'] ?? $d['color'] ?? [];

        if (is_string($raw)) {
            $raw = array_map('trim', explode(',', $raw));
        }

        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn($v) => trim((string) $v),
            $raw
        ), fn($v) => $v !== ''));
    }

    private function normalizeImages(array $d): array
    {
        $raw = $d['images'] ?? $d['gallery'] ?? [];

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = [$raw];
            }
        }

        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn($v) => trim((string) $v),
            $raw
        ), fn($v) => $v !== ''));
    }

    private function syncSizes(int $productId, array $sizes): void
    {
        Database::connection()
            ->prepare('DELETE FROM product_sizes WHERE product_id = :id')
            ->execute(['id' => $productId]);

        $s = Database::connection()->prepare(
            'INSERT INTO product_sizes (product_id, size, size_label, created_at, updated_at)
             VALUES (:product_id, :size, :size_label, NOW(), NOW())'
        );

        foreach ($sizes as $size) {
            $s->execute([
                'product_id' => $productId,
                'size' => $size,
                'size_label' => $size,
            ]);
        }
    }

    private function syncColors(int $productId, array $colors): void
    {
        Database::connection()
            ->prepare('DELETE FROM product_colors WHERE product_id = :id')
            ->execute(['id' => $productId]);

        $s = Database::connection()->prepare(
            'INSERT INTO product_colors (product_id, color, color_label, color_name, color_hex, created_at, updated_at)
             VALUES (:product_id, :color, :color_label, :color_name, :color_hex, NOW(), NOW())'
        );

        foreach ($colors as $color) {
            $hex = $this->colorToHex($color);

            $s->execute([
                'product_id' => $productId,
                'color' => $color,
                'color_label' => $color,
                'color_name' => $color,
                'color_hex' => $hex,
            ]);
        }
    }

    private function syncImages(int $productId, array $images): void
    {
        Database::connection()
            ->prepare('DELETE FROM product_images WHERE product_id = :id')
            ->execute(['id' => $productId]);

        $s = Database::connection()->prepare(
            'INSERT INTO product_images (product_id, image_url, image, sort_order, created_at, updated_at)
             VALUES (:product_id, :image_url, :image, :sort_order, NOW(), NOW())'
        );

        foreach ($images as $index => $image) {
            $s->execute([
                'product_id' => $productId,
                'image_url' => $image,
                'image' => $image,
                'sort_order' => $index,
            ]);
        }
    }

    private function fetchSizes(int $productId): array
    {
        $s = Database::connection()->prepare(
            'SELECT size_label, size
             FROM product_sizes
             WHERE product_id = :id
             ORDER BY id ASC'
        );
        $s->execute(['id' => $productId]);

        $rows = $s->fetchAll();
        $out = [];

        foreach ($rows as $row) {
            $out[] = $row['size_label'] ?: $row['size'];
        }

        return $out;
    }

    private function fetchColors(int $productId): array
    {
        $s = Database::connection()->prepare(
            'SELECT color_name, color_label, color
             FROM product_colors
             WHERE product_id = :id
             ORDER BY id ASC'
        );
        $s->execute(['id' => $productId]);

        $rows = $s->fetchAll();
        $out = [];

        foreach ($rows as $row) {
            $out[] = $row['color_name'] ?: ($row['color_label'] ?: $row['color']);
        }

        return $out;
    }

    private function fetchImages(int $productId): array
    {
        $s = Database::connection()->prepare(
            'SELECT image_url, image
             FROM product_images
             WHERE product_id = :id
             ORDER BY sort_order ASC, id ASC'
        );
        $s->execute(['id' => $productId]);

        $rows = $s->fetchAll();
        $out = [];

        foreach ($rows as $row) {
            $out[] = $row['image_url'] ?: $row['image'];
        }

        return array_values(array_filter($out));
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
        return trim($value, '-');
    }

    private function colorToHex(string $color): string
    {
        $map = [
            'branco' => '#FFFFFF',
            'preto' => '#000000',
            'bege' => '#F5F5DC',
            'marrom' => '#8B4513',
            'caramelo' => '#B87333',
            'vermelho' => '#FF0000',
            'azul' => '#0000FF',
            'verde' => '#008000',
            'rosa' => '#FFC0CB',
            'dourado' => '#D4AF37',
            'prata' => '#C0C0C0',
            'nude' => '#E3BC9A',
        ];

        $key = mb_strtolower(trim($color));
        return $map[$key] ?? '#FFFFFF';
    }
}

class CouponRepository
{
    public function all(): array
    {
        return array_map([$this, 'map'], Database::connection()->query('SELECT * FROM coupons ORDER BY id DESC')->fetchAll());
    }

    public function findActiveByCode(string $code): ?array
    {
        $s = Database::connection()->prepare('SELECT * FROM coupons WHERE code = :code AND active = 1 LIMIT 1');
        $s->execute([
            'code' => mb_strtoupper(trim($code)),
        ]);

        $row = $s->fetch();
        return $row ? $this->map($row) : null;
    }

    public function create(array $d): int
    {
        $s = Database::connection()->prepare(
            'INSERT INTO coupons (code, type, discount, valid_until, max_uses, current_uses, uses_per_client, active, created_at, updated_at)
             VALUES (:code, :type, :discount, :valid_until, :max_uses, :current_uses, :uses_per_client, :active, NOW(), NOW())'
        );

        $s->execute([
            'code' => mb_strtoupper(trim((string) ($d['code'] ?? ''))),
            'type' => $d['type'] ?? 'percent',
            'discount' => (float) ($d['discount'] ?? 0),
            'valid_until' => $d['validUntil'] ?? ($d['valid_until'] ?? null),
            'max_uses' => (int) ($d['maxUses'] ?? ($d['max_uses'] ?? 0)),
            'current_uses' => (int) ($d['currentUses'] ?? ($d['current_uses'] ?? 0)),
            'uses_per_client' => (int) ($d['usesPerClient'] ?? ($d['uses_per_client'] ?? 1)),
            'active' => !empty($d['active']) ? 1 : 0,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function update(int|string $id, array $d): bool
    {
        $s = Database::connection()->prepare(
            'UPDATE coupons
             SET code = :code,
                 type = :type,
                 discount = :discount,
                 valid_until = :valid_until,
                 max_uses = :max_uses,
                 current_uses = :current_uses,
                 uses_per_client = :uses_per_client,
                 active = :active,
                 updated_at = NOW()
             WHERE id = :id'
        );

        return $s->execute([
            'id' => $id,
            'code' => mb_strtoupper(trim((string) ($d['code'] ?? ''))),
            'type' => $d['type'] ?? 'percent',
            'discount' => (float) ($d['discount'] ?? 0),
            'valid_until' => $d['validUntil'] ?? ($d['valid_until'] ?? null),
            'max_uses' => (int) ($d['maxUses'] ?? ($d['max_uses'] ?? 0)),
            'current_uses' => (int) ($d['currentUses'] ?? ($d['current_uses'] ?? 0)),
            'uses_per_client' => (int) ($d['usesPerClient'] ?? ($d['uses_per_client'] ?? 1)),
            'active' => !empty($d['active']) ? 1 : 0,
        ]);
    }

    public function delete(int|string $id): bool
    {
        $s = Database::connection()->prepare('DELETE FROM coupons WHERE id = :id');
        return $s->execute(['id' => $id]);
    }

    public function incrementUse(string $code): void
    {
        $s = Database::connection()->prepare(
            'UPDATE coupons SET current_uses = current_uses + 1, updated_at = NOW() WHERE code = :code'
        );
        $s->execute([
            'code' => mb_strtoupper(trim($code)),
        ]);
    }

    private function map(array $r): array
    {
        return [
            'id' => (string) $r['id'],
            'code' => $r['code'],
            'type' => $r['type'],
            'discount' => (float) $r['discount'],
            'validUntil' => $r['valid_until'],
            'maxUses' => (int) $r['max_uses'],
            'currentUses' => (int) $r['current_uses'],
            'usesPerClient' => (int) $r['uses_per_client'],
            'active' => (bool) $r['active'],
        ];
    }
}

class OrderRepository
{
    public function all(): array
    {
        return array_map([$this, 'map'], Database::connection()->query('SELECT * FROM orders ORDER BY id DESC')->fetchAll());
    }

    public function find(int|string $id): ?array
    {
        $s = Database::connection()->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
        $s->execute(['id' => $id]);
        $r = $s->fetch();

        return $r ? $this->map($r) : null;
    }

    public function byUser(int|string $userId): array
    {
        $s = Database::connection()->prepare('SELECT * FROM orders WHERE user_id = :id ORDER BY id DESC');
        $s->execute(['id' => $userId]);

        return array_map([$this, 'map'], $s->fetchAll());
    }

    public function create(array $d): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        $a = $d['address'] ?? [];

        $s = $pdo->prepare(
            'INSERT INTO orders (
                user_id, customer_name, customer_email, customer_phone,
                street, number, complement, neighborhood, city, state, zip,
                price_type, subtotal, discount, total, coupon_code,
                status, tracking_code, carrier, created_at, updated_at
             ) VALUES (
                :uid, :name, :email, :phone,
                :street, :number, :complement, :neighborhood, :city, :state, :zip,
                :ptype, :subtotal, :discount, :total, :coupon,
                :status, :tracking, :carrier, NOW(), NOW()
             )'
        );

        $s->execute([
            'uid' => $d['userId'] ?? null,
            'name' => $d['customerName'] ?? '',
            'email' => $d['customerEmail'] ?? '',
            'phone' => $d['customerPhone'] ?? '',
            'street' => $a['street'] ?? '',
            'number' => $a['number'] ?? '',
            'complement' => $a['complement'] ?? '',
            'neighborhood' => $a['neighborhood'] ?? '',
            'city' => $a['city'] ?? '',
            'state' => $a['state'] ?? '',
            'zip' => $a['zip'] ?? '',
            'ptype' => $d['priceType'] ?? 'normal',
            'subtotal' => (float) ($d['subtotal'] ?? 0),
            'discount' => (float) ($d['discount'] ?? 0),
            'total' => (float) ($d['total'] ?? 0),
            'coupon' => $d['couponCode'] ?? null,
            'status' => $d['status'] ?? 'em_analise',
            'tracking' => $d['trackingCode'] ?? null,
            'carrier' => $d['carrier'] ?? null,
        ]);

        $orderId = (int) $pdo->lastInsertId();

        $itemStmt = $pdo->prepare(
            'INSERT INTO order_items (
                order_id, product_id, product_name, sku, price_type,
                unit_price, quantity, line_total, color, size, meta_json, created_at
             ) VALUES (
                :order_id, :product_id, :product_name, :sku, :price_type,
                :unit_price, :quantity, :line_total, :color, :size, :meta_json, NOW()
             )'
        );

        foreach (($d['items'] ?? []) as $item) {
            $qty = (int) ($item['quantity'] ?? 1);
            $unit = (float) ($item['unitPrice'] ?? $item['price'] ?? 0);

            $itemStmt->execute([
                'order_id' => $orderId,
                'product_id' => $item['productId'] ?? $item['id'] ?? null,
                'product_name' => $item['name'] ?? '',
                'sku' => $item['sku'] ?? null,
                'price_type' => $item['priceType'] ?? 'normal',
                'unit_price' => $unit,
                'quantity' => $qty,
                'line_total' => $unit * $qty,
                'color' => $item['color'] ?? null,
                'size' => $item['size'] ?? null,
                'meta_json' => json_encode($item, JSON_UNESCAPED_UNICODE),
            ]);
        }

        $pdo->commit();
        return $orderId;
    }

    public function updateStatus(int|string $id, string $status): bool
    {
        $s = Database::connection()->prepare('UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :id');
        return $s->execute([
            'id' => $id,
            'status' => $status,
        ]);
    }

    public function updateTracking(int|string $id, string $trackingCode, string $carrier = ''): bool
    {
        $s = Database::connection()->prepare(
            'UPDATE orders
             SET tracking_code = :tracking, carrier = :carrier, updated_at = NOW()
             WHERE id = :id'
        );

        return $s->execute([
            'id' => $id,
            'tracking' => $trackingCode,
            'carrier' => $carrier,
        ]);
    }

    public function dashboard(): array
    {
        $pdo = Database::connection();

        $totals = $pdo->query(
            "SELECT
                COUNT(*) AS orders_count,
                COALESCE(SUM(total), 0) AS revenue,
                SUM(CASE WHEN status = 'em_analise' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN status = 'enviado' THEN 1 ELSE 0 END) AS shipped_count
             FROM orders"
        )->fetch();

        return [
            'ordersCount' => (int) ($totals['orders_count'] ?? 0),
            'revenue' => (float) ($totals['revenue'] ?? 0),
            'pendingCount' => (int) ($totals['pending_count'] ?? 0),
            'shippedCount' => (int) ($totals['shipped_count'] ?? 0),
        ];
    }

    private function map(array $r): array
    {
        $s = Database::connection()->prepare('SELECT * FROM order_items WHERE order_id = :id ORDER BY id ASC');
        $s->execute(['id' => $r['id']]);

        return [
            'id' => (string) $r['id'],
            'userId' => (string) $r['user_id'],
            'customerName' => $r['customer_name'],
            'customerEmail' => $r['customer_email'],
            'customerPhone' => $r['customer_phone'],
            'status' => $r['status'],
            'trackingCode' => $r['tracking_code'],
            'carrier' => $r['carrier'],
            'priceType' => $r['price_type'] ?? 'normal',
            'subtotal' => (float) $r['subtotal'],
            'discount' => (float) $r['discount'],
            'total' => (float) $r['total'],
            'couponCode' => $r['coupon_code'] ?? null,
            'items' => $s->fetchAll(),
            'createdAt' => $r['created_at'],
            'updatedAt' => $r['updated_at'],
        ];
    }
}
