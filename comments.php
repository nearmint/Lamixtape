<?php 
// Display comments if there are any
if ( have_comments() ) : ?>
<ul class="list-unstyled">
    <?php wp_list_comments( array('type' => 'comment', 'callback' => 'tape_comment') ); ?>
</ul>
<?php endif; ?>
