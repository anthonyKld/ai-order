-- OPCIONAL: importar manualmente após 002_products.sql. Cada execução adiciona 3 produtos.
START TRANSACTION;
INSERT INTO products (name, description, price, stock, active)
VALUES ('Camiseta Básica', 'Camiseta de algodão com variações de cor e tamanho.', 39.90, 0, 1);
SET @demo_product_id = LAST_INSERT_ID();
INSERT INTO product_variants (product_id, name, stock) VALUES
(@demo_product_id, 'Preta / P', 10), (@demo_product_id, 'Preta / M', 15), (@demo_product_id, 'Branca / G', 8);
INSERT INTO products (name, description, price, stock, active) VALUES
('Caneca Personalizada', 'Caneca de cerâmica.', 24.90, 20, 1),
('Boné Bordado', NULL, 49.90, 0, 0);
COMMIT;
