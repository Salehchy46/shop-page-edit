/**
 * Display product categories as tabs above the shop loop
 */
add_action( 'woocommerce_before_shop_loop', 'display_category_tabs', 5 );
function display_category_tabs() {
    // Only on shop page and category archives
    if ( ! is_shop() && ! is_product_category() ) {
        return;
    }

    // Get all top-level product categories
    $categories = get_terms( array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
        'parent'     => 0,
    ) );

    if ( empty( $categories ) || is_wp_error( $categories ) ) {
        return;
    }

    // Determine current category (if any)
    $current_cat = get_queried_object();
    $current_cat_id = ( isset( $current_cat->term_id ) && is_product_category() ) ? $current_cat->term_id : 0;

    echo '<div class="category-tabs-wrapper">';
    echo '<ul class="category-tabs">';

    // "All Products" tab
    $all_products_class = ( ! is_product_category() ) ? 'active' : '';
    echo '<li class="' . esc_attr( $all_products_class ) . '">';
    echo '<a href="' . esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ) . '">' . esc_html__( 'All Products', 'woocommerce' ) . '</a>';
    echo '</li>';

    // Loop through categories
    foreach ( $categories as $category ) {
        $active_class = ( $category->term_id == $current_cat_id ) ? 'active' : '';
        echo '<li class="' . esc_attr( $active_class ) . '">';
        echo '<a href="' . esc_url( get_term_link( $category ) ) . '">' . esc_html( $category->name ) . '</a>';
        echo '</li>';
    }

    echo '</ul>';
    echo '</div>';
}