-- Criar base de dados com suporte UTF-8
DROP DATABASE IF EXISTS mercearia_online;
CREATE DATABASE mercearia_online CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mercearia_online;

-- Tabela de produtos 
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NULL,             -- nome em inglês (opcional)
    category VARCHAR(50) NOT NULL DEFAULT 'Mercearia',
    price DECIMAL(10,2) NOT NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'un',
    quantity INT NOT NULL DEFAULT 0,
    image VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela de utilizadores (administradores)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela de encomendas
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL DEFAULT '',
    phone VARCHAR(30) NOT NULL DEFAULT '',
    birth_date DATE NOT NULL,
    address TEXT NOT NULL,
    postal_code VARCHAR(10) NOT NULL DEFAULT '',
    city VARCHAR(100) NOT NULL DEFAULT '',
    notes VARCHAR(500) NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('pendente','confirmada','enviada','entregue','cancelada') NOT NULL DEFAULT 'pendente',
    ip_hash CHAR(64) NULL,                 -- hash do IP (limite de encomendas por hora)
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabela de itens das encomendas
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    -- SET NULL: eliminar um produto não apaga o histórico das encomendas
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Inserir utilizador admin padrão
-- Username: admin
-- Password: 123456
INSERT INTO users (username, password) VALUES 
('admin', '$2y$10$wUXmRhICEAa4YekMNEjE0uvwxfjsmKOBVdOcHLmSj/UCUid8c7XGW');

-- Produtos iniciais com imagens
INSERT INTO products (name, name_en, category, price, unit, quantity, image) VALUES
('Maçãs',            'Apples',              'Frutas',            1.50, 'kg',    50, 'images/products/macas.webp'),
('Bananas',          'Bananas',             'Frutas',            1.20, 'kg',    30, 'images/products/bananas.webp'),
('Leite',            'Milk',                'Laticínios e Ovos', 0.85, 'L',     25, 'images/products/leite.webp'),
('Pão',              'Bread',               'Padaria',           0.60, 'un',    40, 'images/products/pao.webp'),
('Ovos',             'Eggs',                'Laticínios e Ovos', 2.30, 'dúzia', 20, 'images/products/ovos.webp'),
('Queijo',           'Cheese',              'Laticínios e Ovos', 3.50, 'un',    15, 'images/products/queijo.webp'),
('Iogurte',          'Yoghurt',             'Laticínios e Ovos', 1.80, 'un',    0,  'images/products/iogurte.webp'),
('Arroz Agulha 1kg', 'Long-grain Rice 1kg', 'Mercearia',         1.25, 'emb.',  35, 'images/products/arroz.webp'),
('Tomate',           'Tomatoes',            'Legumes',           2.10, 'kg',    25, 'images/products/tomate.webp'),
('Cebola',           'Onions',              'Legumes',           1.30, 'kg',    30, 'images/products/cebola.webp'),
('Batata',           'Potatoes',            'Legumes',           0.90, 'kg',    45, 'images/products/batata.webp'),
('Frango',           'Chicken',             'Talho',             4.50, 'kg',    20, 'images/products/frango.webp');

-- Criar índices para melhor performance
CREATE INDEX idx_orders_date ON orders(order_date);
CREATE INDEX idx_orders_ip_date ON orders(ip_hash, order_date);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_email ON orders(email);
CREATE INDEX idx_products_category ON products(category);
CREATE INDEX idx_order_items_order ON order_items(order_id);
CREATE INDEX idx_order_items_product ON order_items(product_id);
CREATE INDEX idx_products_name ON products(name);
