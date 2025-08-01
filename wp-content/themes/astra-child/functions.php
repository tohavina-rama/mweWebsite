<?php
function astra_child_enqueue_styles() {
    wp_enqueue_style('astra-parent-style', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('astra-child-style', get_stylesheet_directory_uri() . '/style.css', array('astra-parent-style'));
}
add_action('wp_enqueue_scripts', 'astra_child_enqueue_styles');


function mwe_full_content_slider() {
    $args = [
        'posts_per_page' => 5, // nombre d'articles à afficher
        'post_status' => 'publish',
    ];
    $query = new WP_Query($args);
    if (!$query->have_posts()) {
        return '<p>Aucun article trouvé.</p>';
    }
    
    ob_start(); ?>
    <div id="mwe-slider">
        <?php while ($query->have_posts()) : $query->the_post(); ?>
            <div class="mwe-slide">
                <div class="slide-image" style="float:left; width:45%; margin-right:5%;">
                    <?php if (has_post_thumbnail()) the_post_thumbnail('medium'); ?>
                </div>
                <div class="slide-content" style="float:left; width:50%;">
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <div><?php the_content(); ?></div>
                </div>
                <div style="clear:both;"></div>
            </div>
        <?php endwhile; wp_reset_postdata(); ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('mwe_full_slider', 'mwe_full_content_slider');

?>