<?php // English content (included by pages/termos.php) ?>
<article class="prose">
            <h2>1. Who we are</h2>
            <p>This online shop is run by <strong><?php echo e(STORE_NAME); ?></strong>, tax number (NIF) <?php echo e(STORE_NIF); ?>,
               registered at <?php echo e(STORE_ADDRESS); ?>. Contact: <?php echo e(STORE_EMAIL); ?> · <?php echo e(STORE_PHONE); ?>.</p>

            <h2>2. Orders</h2>
            <p>Orders placed on the website remain <em>pending</em> until we confirm them by phone. An order is only accepted
               after that confirmation. We may refuse orders with incomplete or false details.</p>
            <ul>
                <li>Minimum order: <?php echo formatPrice(MIN_ORDER_TOTAL); ?>.</li>
                <li>Maximum of <?php echo ORDER_MAX_QTY_PER_PRODUCT; ?> units per product and <?php echo formatPrice(ORDER_MAX_TOTAL); ?> per order. For larger quantities, please contact us.</li>
                <li>You must be at least 18 years old.</li>
            </ul>

            <h2>3. Prices and payment</h2>
            <p>Prices include VAT at the current legal rate. Products sold by weight (kg) may vary slightly in weight;
               the final amount is adjusted on delivery. Payment is made on delivery.</p>

            <h2>4. Delivery</h2>
            <p>We deliver across <?php echo e(STORE_DELIVERY_AREA_EN); ?> within an estimated <?php echo e(DELIVERY_ESTIMATE_EN); ?>.
               Delivery costs <?php echo formatPrice(DELIVERY_FEE); ?> and is free on orders over <?php echo formatPrice(FREE_DELIVERY_FROM); ?>.</p>

            <h2>5. Cancellations and returns</h2>
            <p>You can cancel your order free of charge until it leaves for delivery by contacting us.
               Under Portuguese Decree-Law no. 24/2014, the right of withdrawal does not apply to perishable goods
               or goods with a short shelf life. Faulty or damaged products will be replaced or refunded.</p>

            <h2>6. Complaints</h2>
            <p>We provide an <a href="https://www.livroreclamacoes.pt" target="_blank" rel="noopener noreferrer">Electronic Complaints Book</a>.
               In case of dispute, consumers may contact an Alternative Dispute Resolution entity
               (list available at <a href="https://www.consumidor.gov.pt" target="_blank" rel="noopener noreferrer">consumidor.gov.pt</a>).</p>
        </article>
