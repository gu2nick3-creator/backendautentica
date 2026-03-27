class ProductRepository
{
    public function all(array $filters = []): array
    {
        $rows = Database::connection()->query('SELECT * FROM products ORDER BY id DESC')->fetchAll();
        return array_map([$this, 'mapProduct'], $rows);
    }

    public function find(int|string $id): ?array
    {
        $s = Database::connection()->prepare('SELECT * FROM products WHERE id=:id LIMIT 1');
        $s->execute(['id' => $id]);
        $row = $s->fetch();

        return $row ? $this->mapProduct($row) : null;
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
            'priceNormal' => (float) ($row['price_normal'] ?? $row['price'] ?? 0),
            'priceResale' => (float) ($row['price_resale'] ?? $row['resale_price'] ?? 0),
            'stock' => (int) ($row['stock'] ?? 0),
            'active' => (bool) ($row['active'] ?? $row['is_active'] ?? 0),
            'featured' => (bool) ($row['featured'] ?? $row['is_featured'] ?? 0),
            'isNew' => (bool) ($row['is_new'] ?? 0),
            'isPopular' => (bool) ($row['is_popular'] ?? $row['popular'] ?? 0),
            'type' => $row['type'] ?? $row['product_type'] ?? '',
            'sizes' => !empty($row['sizes_json']) ? (json_decode((string) $row['sizes_json'], true) ?: []) : [],
            'colors' => !empty($row['colors_json']) ? (json_decode((string) $row['colors_json'], true) ?: []) : [],
            'images' => $images,
            'image' => $mainImage,
            'image_url' => $mainImage,
        ];
    }
}
