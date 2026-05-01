<?php /* Template Name: home */ ?>
<?php get_header(); ?>
<?php
// Query the latest post for the intro section
$the_query = new WP_Query( array(
    'posts_per_page' => 1,
));
?>
<section class="about font-smoothing">
    <div class="container mx-auto px-4">
        <hr>
        <article class="flex flex-wrap pt-6 pb-12">
            <div class="w-full lg:w-2/3">
                <p><?php esc_html_e('Hi and welcome to Lamixtape.', 'lamixtape'); ?></p>
				<p><?php esc_html_e('Between', 'lamixtape'); ?> <a class="underline" href="https://web.archive.org/web/20130612232050/http://lamixtape.fr/" target="_blank"><?php esc_html_e('2011', 'lamixtape'); ?></a> <?php esc_html_e('and 2022, we released mixtapes every month, curated by our roster of curators and', 'lamixtape'); ?> <a class="underline" href="https://lamixtape.fr/guests/"><?php esc_html_e('incredible guests', 'lamixtape'); ?></a>. <?php esc_html_e("Our foundational reason for building Lamixtape was that we're really excited about sharing music. If we had a central goal, it was to feed your ears and curiosity with as much quality and diversity as possible.", 'lamixtape'); ?></p>
                <p><?php esc_html_e('Lamixtape has no bullshit, no ads, no sponsored posts, and no paywalls. If you enjoy our mixtapes, please consider', 'lamixtape'); ?> <button type="button" class="lmt-link-button underline" data-lmt-dialog="donatemodal" aria-haspopup="dialog" aria-controls="donatemodal"><?php esc_html_e('supporting', 'lamixtape'); ?></button> <?php esc_html_e('what we do.', 'lamixtape'); ?></p>
                <p><?php esc_html_e('If you really want to see what we’re about, go and explore our', 'lamixtape'); ?> <a class="underline" href="#mixtapes">360+ mixtapes</a>.</p>
                <p><?php esc_html_e('And remember,', 'lamixtape'); ?>
                    <?php
                    $random = lmt_get_random_mixtape( 'home_about_inline' );
                    if ( $random ) :
                        echo '<a data-toggle="tooltip" data-placement="top" title="' . esc_attr__('Random mixtape', 'lamixtape') . '" href="' . esc_url( get_permalink( $random ) ) . '" class="underline">';
                        esc_html_e('getting lost', 'lamixtape');
                        echo '</a>';
                    endif;
                    ?>
                    <?php esc_html_e('can be a good thing.', 'lamixtape'); ?></p>
                <small>PS: we’re not on social media, but you can reach us <button type="button" class="lmt-link-button underline" data-lmt-dialog="contactmodal" aria-haspopup="dialog" aria-controls="contactmodal">here</button>.</small>
            </div>
            <div class="hidden lg:block lg:w-1/3 pt-12">
                <button type="button" class="lmt-link-button" data-lmt-dialog="donatemodal" aria-haspopup="dialog" aria-controls="donatemodal" aria-label="<?php esc_attr_e('Open the support us dialog', 'lamixtape'); ?>"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/img/booking.jpg" class="max-w-full h-auto illustration" alt="<?php esc_attr_e('Booking', 'lamixtape'); ?>" loading="lazy" decoding="async"></button>
            </div>
        </article>
    </div>
</section>
<section class="mixtape-list" id="mixtapes">
    <?php
    // First batch rendered server-side; the rest is loaded by
    // js/infinite-scroll.js via /wp-json/lamixtape/v1/posts.
    $batch_size    = defined( 'LMT_INFINITE_SCROLL_BATCH_SIZE' ) ? LMT_INFINITE_SCROLL_BATCH_SIZE : 30;
    $wpb_all_query = new WP_Query( array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $batch_size,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ) );
    ?>
    <?php if ( $wpb_all_query->have_posts() ) : ?>
        <div id="lmt-mixtapes-container">
            <?php while ( $wpb_all_query->have_posts() ) : $wpb_all_query->the_post(); ?>
                <?php get_template_part( 'template-parts/card-mixtape', null, array(
                    'h2_extra_classes'      => 'font-smoothing',
                    'highlight_mode'        => 'always_span',
                    'hide_curator_on_small' => true,
                ) ); ?>
            <?php endwhile; ?>
        </div>
        <?php wp_reset_postdata(); ?>
        <?php if ( $wpb_all_query->found_posts > $batch_size ) : ?>
            <div id="lmt-infinite-sentinel"
                 data-context="home"
                 data-initial-offset="<?php echo (int) $batch_size; ?>"
                 aria-hidden="true"></div>
        <?php endif; ?>
    <?php else : ?>
        <p><?php esc_html_e( 'Sorry, no posts matched your criteria.', 'lamixtape' ); ?></p>
    <?php endif; ?>
</section>

<?php get_footer(); ?>
