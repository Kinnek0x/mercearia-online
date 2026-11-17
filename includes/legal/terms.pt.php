<?php // Conteúdo em português (incluído por pages/termos.php) ?>
<article class="prose">
            <h2>1. Identificação</h2>
            <p>Esta loja online é explorada por <strong><?php echo e(STORE_NAME); ?></strong>, NIF <?php echo e(STORE_NIF); ?>,
               com sede em <?php echo e(STORE_ADDRESS); ?>. Contacto: <?php echo e(STORE_EMAIL); ?> · <?php echo e(STORE_PHONE); ?>.</p>

            <h2>2. Encomendas</h2>
            <p>As encomendas feitas no site ficam no estado <em>pendente</em> até serem confirmadas por telefone.
               A encomenda só se considera aceite depois dessa confirmação. Reservamo-nos o direito de recusar encomendas
               com dados incompletos ou falsos.</p>
            <ul>
                <li>Encomenda mínima: <?php echo formatPrice(MIN_ORDER_TOTAL); ?>.</li>
                <li>Máximo de <?php echo ORDER_MAX_QTY_PER_PRODUCT; ?> unidades por produto e <?php echo formatPrice(ORDER_MAX_TOTAL); ?> por encomenda. Para quantidades maiores, contacte-nos.</li>
                <li>É necessário ter pelo menos 18 anos.</li>
            </ul>

            <h2>3. Preços e pagamento</h2>
            <p>Os preços incluem IVA à taxa legal em vigor. Produtos vendidos ao peso (kg) podem ter pequenas variações
               de peso; o valor final é ajustado na entrega. O pagamento é feito no momento da entrega.</p>

            <h2>4. Entregas</h2>
            <p>Entregamos na <?php echo e(STORE_DELIVERY_AREA); ?> num prazo estimado de <?php echo e(DELIVERY_ESTIMATE); ?>.
               Os portes são de <?php echo formatPrice(DELIVERY_FEE); ?>, sendo grátis em encomendas a partir de <?php echo formatPrice(FREE_DELIVERY_FROM); ?>.</p>

            <h2>5. Cancelamentos e devoluções</h2>
            <p>Pode cancelar a encomenda sem custos até ao momento em que saia para entrega, contactando-nos.
               Nos termos do Decreto-Lei n.º 24/2014, o direito de livre resolução não se aplica a bens perecíveis
               ou com prazo de validade curto. Produtos com defeito ou em mau estado são substituídos ou reembolsados.</p>

            <h2>6. Reclamações</h2>
            <p>Dispomos de <a href="https://www.livroreclamacoes.pt" target="_blank" rel="noopener noreferrer">Livro de Reclamações Eletrónico</a>.
               Em caso de litígio, o consumidor pode recorrer a uma entidade de Resolução Alternativa de Litígios de consumo
               (lista disponível em <a href="https://www.consumidor.gov.pt" target="_blank" rel="noopener noreferrer">consumidor.gov.pt</a>).</p>
        </article>
