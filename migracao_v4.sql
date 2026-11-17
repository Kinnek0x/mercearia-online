-- Migração v4 — nome dos produtos em inglês (loja bilingue PT/EN)
-- (não é preciso correr isto se importar o database.sql novo do zero)

USE mercearia_online;

ALTER TABLE products ADD COLUMN name_en VARCHAR(255) NULL AFTER name;

UPDATE products SET name_en = 'Apples' WHERE name = 'Maçãs';
UPDATE products SET name_en = 'Bananas' WHERE name = 'Bananas';
UPDATE products SET name_en = 'Milk' WHERE name = 'Leite';
UPDATE products SET name_en = 'Bread' WHERE name = 'Pão';
UPDATE products SET name_en = 'Eggs' WHERE name = 'Ovos';
UPDATE products SET name_en = 'Cheese' WHERE name = 'Queijo';
UPDATE products SET name_en = 'Yoghurt' WHERE name = 'Iogurte';
UPDATE products SET name_en = 'Long-grain Rice 1kg' WHERE name = 'Arroz Agulha 1kg';
UPDATE products SET name_en = 'Tomatoes' WHERE name = 'Tomate';
UPDATE products SET name_en = 'Onions' WHERE name = 'Cebola';
UPDATE products SET name_en = 'Potatoes' WHERE name = 'Batata';
UPDATE products SET name_en = 'Chicken' WHERE name = 'Frango';
