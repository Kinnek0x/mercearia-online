-- Migração v5 — fotos dos produtos em WebP (24 MB -> ~380 KB)
-- (não é preciso correr isto se importar o database.sql novo do zero)

USE mercearia_online;

UPDATE products
SET image = REPLACE(image, '.png', '.webp')
WHERE image IN (
    'images/products/macas.png',  'images/products/bananas.png', 'images/products/leite.png',
    'images/products/pao.png',    'images/products/ovos.png',    'images/products/queijo.png',
    'images/products/iogurte.png','images/products/arroz.png',   'images/products/tomate.png',
    'images/products/cebola.png', 'images/products/batata.png',  'images/products/frango.png'
);
