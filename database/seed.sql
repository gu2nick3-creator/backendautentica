INSERT INTO categories (name,image_url,sort_order,created_at,updated_at)
SELECT 'Rasteirinhas','',1,NOW(),NOW() WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name='Rasteirinhas');
INSERT INTO categories (name,image_url,sort_order,created_at,updated_at)
SELECT 'Sapatos','',2,NOW(),NOW() WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name='Sapatos');
INSERT INTO categories (name,image_url,sort_order,created_at,updated_at)
SELECT 'Tênis','',3,NOW(),NOW() WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name='Tênis');
INSERT INTO categories (name,image_url,sort_order,created_at,updated_at)
SELECT 'Sandálias','',4,NOW(),NOW() WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name='Sandálias');
INSERT INTO coupons (code,type,discount,valid_until,max_uses,current_uses,uses_per_client,active,created_at,updated_at)
SELECT 'BEMVINDA10','percentage',10.00,DATE_ADD(CURDATE(), INTERVAL 365 DAY),1000,0,1,1,NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM coupons WHERE code='BEMVINDA10');
