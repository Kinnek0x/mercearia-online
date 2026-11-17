-- Migração para bases de dados JÁ criadas com a versão antiga do database.sql
-- (não é preciso correr isto se importar o database.sql novo do zero)
--
-- Antes: eliminar um produto apagava também as linhas de order_items (ON DELETE CASCADE),
-- e as encomendas antigas ficavam sem produtos. Agora o product_id fica NULL e o nome
-- do produto continua guardado em order_items.product_name.

USE mercearia_online;

-- Descobrir o nome da FK (normalmente order_items_ibfk_2):
-- SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
--  WHERE TABLE_NAME = 'order_items' AND COLUMN_NAME = 'product_id' AND REFERENCED_TABLE_NAME = 'products';

ALTER TABLE order_items DROP FOREIGN KEY order_items_ibfk_2;
ALTER TABLE order_items MODIFY product_id INT NULL;
ALTER TABLE order_items
    ADD CONSTRAINT fk_order_items_product
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL;
