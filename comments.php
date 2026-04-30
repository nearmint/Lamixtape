<?php 
// Display comments if there are any
if ( have_comments() ) : ?>
<ul class="list-unstyled">
    <?php wp_list_comments( array( 'type' => 'comment', 'callback' => 'lmt_comment_callback' ) ); ?>
</ul>
<?php endif; ?>
