-- Migração v3 — para bases de dados criadas com uma versão anterior do database.sql
-- (não é preciso correr isto se importar o database.sql novo do zero)
--
-- Adiciona: contactos do cliente, portes, estado da encomenda, IP (hash) para limitar
-- encomendas abusivas, e categoria/unidade nos produtos.

USE mercearia_online;

ALTER TABLE orders
    ADD COLUMN email VARCHAR(255) NOT NULL DEFAULT '' AFTER customer_name,
    ADD COLUMN phone VARCHAR(30) NOT NULL DEFAULT '' AFTER email,
    ADD COLUMN postal_code VARCHAR(10) NOT NULL DEFAULT '' AFTER address,
    ADD COLUMN city VARCHAR(100) NOT NULL DEFAULT '' AFTER postal_code,
    ADD COLUMN notes VARCHAR(500) NULL AFTER city,
    ADD COLUMN subtotal DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER notes,
    ADD COLUMN delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER subtotal,
    ADD COLUMN status ENUM('pendente','confirmada','enviada','entregue','cancelada') NOT NULL DEFAULT 'pendente' AFTER total,
    ADD COLUMN ip_hash CHAR(64) NULL AFTER status,
    ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER order_date;

UPDATE orders SET subtotal = total WHERE subtotal = 0;

CREATE INDEX idx_orders_ip_date ON orders(ip_hash, order_date);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_email ON orders(email);

ALTER TABLE products
    ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'Mercearia' AFTER name,
    ADD COLUMN unit VARCHAR(20) NOT NULL DEFAULT 'un' AFTER price;

CREATE INDEX idx_products_category ON products(category);

-- Categorias e unidades dos produtos iniciais
UPDATE products SET category = 'Frutas',            unit = 'kg'    WHERE name IN ('Maçãs', 'Bananas');
UPDATE products SET category = 'Legumes',           unit = 'kg'    WHERE name IN ('Tomate', 'Cebola', 'Batata');
UPDATE products SET category = 'Laticínios e Ovos', unit = 'L'     WHERE name = 'Leite';
UPDATE products SET category = 'Laticínios e Ovos', unit = 'un'    WHERE name IN ('Queijo', 'Iogurte');
UPDATE products SET category = 'Laticínios e Ovos', unit = 'dúzia', name = 'Ovos' WHERE name = 'Ovos (dúzia)';
UPDATE products SET category = 'Padaria',           unit = 'un'    WHERE name = 'Pão';
UPDATE products SET category = 'Talho',             unit = 'kg',   name = 'Frango' WHERE name = 'Frango (kg)';
UPDATE products SET category = 'Mercearia',         unit = 'emb.', name = 'Arroz Agulha 1kg' WHERE name = 'Arroz (1kg)';
