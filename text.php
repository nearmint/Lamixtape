<?php /* Template Name: text */ ?>
<?php get_header(); ?>
<section class="text tw:container tw:mx-auto tw:px-4 font-smoothing">
    <hr>
    <article class="tw:flex tw:flex-wrap tw:pt-4 tw:pb-12">
        <div class="tw:w-full tw:lg:w-2/3">
            <h2><?php single_post_title(); ?></h2>
            <?php the_content() ?>
			<br>
			<small>Last updated on <?php echo get_the_date('n/j/Y'); ?></small>
        </div>
        <div class="tw:flex-1 tw:hidden tw:lg:block">
        </div>
    </article>
</section>
<?php get_footer(); ?>
