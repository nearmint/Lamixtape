<?php get_header(); ?>
<div class="container mx-auto px-4 pb-[calc(30vh+2rem)] lg:pb-12 font-smoothing">
    <hr class="mb-6">
    <h1 class="lmt-404-title"><?php esc_html_e('Looks like you got lost', 'lamixtape'); ?></h1>
    <p class="lmt-404-subtitle pb-6"><?php esc_html_e('Sorry, the page you are looking for has moved', 'lamixtape'); ?></p>
    <div class="flex flex-col items-start gap-3 mb-8 lg:flex-row">
        <a class="lmt-404-btn hidden lg:inline-flex items-center bg-transparent text-white border-2 border-current rounded-none px-5 py-2.5 text-lg uppercase" href="<?php echo esc_url( home_url( '/explore/' ) ); ?>"><?php esc_html_e('Search', 'lamixtape'); ?></a>
        <a class="lmt-404-btn inline-flex items-center bg-transparent text-white border-2 border-current rounded-none px-5 py-2.5 text-lg uppercase" href="<?php echo esc_url( rest_url( 'lamixtape/v1/random-mixtape' ) ); ?>"><?php esc_html_e('Random Mixtape', 'lamixtape'); ?></a>
    </div>
    <img src="<?php echo esc_url( get_template_directory_uri() );?>/img/404.gif" class="travolta" alt="<?php esc_attr_e( 'Page not found illustration', 'lamixtape' ); ?>" loading="lazy" decoding="async">
</div>
<?php get_footer(); ?>
