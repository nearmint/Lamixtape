<?php /* Template Name: text */ ?>
<?php get_header(); ?>
<section class="text container font-smoothing fade-in delay-1">
    <hr>
    <article class="row pt-3 pb-5">
        <div class="col-lg-8 col-md-12">
            <h2><?php single_post_title(); ?></h2>
            <?php the_content() ?>
			<br>
			<small>Last updated on <?php echo get_the_date('n/j/Y'); ?></small>
        </div>
        <div class="col d-none d-sm-none d-md-none d-lg-block fade-in delay-2">
        </div>
    </article>
</section>
<?php get_footer(); ?>
