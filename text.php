<?php /* Template Name: text */ ?>
<?php get_header(); ?>
<section class="text container mx-auto px-4 font-smoothing">
    <hr>
    <article class="flex flex-wrap pt-4 pb-12">
        <div class="w-full lg:w-2/3">
            <h2><?php single_post_title(); ?></h2>
            <?php the_content() ?>
			<br>
			<small>Last updated on <?php echo get_the_date('n/j/Y'); ?></small>
        </div>
        <div class="flex-1 hidden lg:block">
        </div>
    </article>
</section>
<?php get_footer(); ?>
