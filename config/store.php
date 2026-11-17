<?php

// =========================================================
// Configuração da loja — dados do cliente e regras de venda
// Altere aqui o nome, contactos, portes e limites.
// =========================================================

// Identidade
define('STORE_NAME', 'Mercearia Online');
define('STORE_TAGLINE', 'Fresco, todos os dias');
define('STORE_EMAIL', 'encomendas@mercearia-online.pt');
define('STORE_PHONE', '+351 210 000 000');
define('STORE_ADDRESS', 'Rua do Mercado, 12, 1100-000 Lisboa');
define('STORE_NIF', '500 000 000');
define('STORE_HOURS', 'Segunda a sábado, 8h–20h');
define('STORE_DELIVERY_AREA', 'Grande Lisboa');

// Versões em inglês dos textos acima (usadas quando o cliente escolhe EN)
define('STORE_TAGLINE_EN', 'Fresh, every day');
define('STORE_HOURS_EN', 'Monday to Saturday, 8am–8pm');
define('STORE_DELIVERY_AREA_EN', 'Greater Lisbon');

// Entregas
define('DELIVERY_FEE', 3.50);          // portes (€)
define('FREE_DELIVERY_FROM', 30.00);   // portes grátis a partir deste subtotal (€)
define('MIN_ORDER_TOTAL', 10.00);      // valor mínimo de encomenda (€)
define('DELIVERY_ESTIMATE', '24–48 horas');
define('DELIVERY_ESTIMATE_EN', '24–48 hours');

// Limites contra encomendas falsas / abusivas
define('ORDER_MAX_QTY_PER_PRODUCT', 20);   // unidades de um mesmo produto por encomenda
define('ORDER_MAX_LINES', 30);             // produtos diferentes por encomenda
define('ORDER_MAX_TOTAL', 500.00);         // valor máximo por encomenda (€)
define('ORDER_MAX_PER_HOUR_IP', 3);        // encomendas por hora a partir do mesmo IP
define('ORDER_MAX_PENDING_PER_EMAIL', 3);  // encomendas pendentes com o mesmo email
define('ORDER_MIN_FORM_SECONDS', 4);       // tempo mínimo a preencher o checkout (anti-bot)

// Categorias disponíveis (ordem em que aparecem na loja)
define('PRODUCT_CATEGORIES', ['Frutas', 'Legumes', 'Laticínios e Ovos', 'Padaria', 'Talho', 'Mercearia']);

// Unidades de venda
define('PRODUCT_UNITS', ['un' => 'unidade', 'kg' => 'kg', 'L' => 'litro', 'dúzia' => 'dúzia', 'emb.' => 'embalagem']);

// Estados de uma encomenda
define('ORDER_STATUSES', [
    'pendente'   => 'Pendente',
    'confirmada' => 'Confirmada',
    'enviada'    => 'Em entrega',
    'entregue'   => 'Entregue',
    'cancelada'  => 'Cancelada',
]);
