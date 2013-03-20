<?php
/*
Template Name: Property overview
*/
?>

<section>
  <div class="container">
    <div class="row-fluid">
      <div class="span9">
        <?php while (have_posts()) : the_post(); ?>
          <?php the_content(); ?>
          <?php wp_link_pages(array('before' => '<nav class="pagination">', 'after' => '</nav>')); ?>
        <?php endwhile; ?>
      </div>
                        <?php if (roots_display_sidebar()) : ?>
                    <div class="sidebar span3" role="complementary">
                      <div class="well">
                        <?php include roots_sidebar_path(); ?>
                      </div>
                    </div><!-- /.sidebar -->
                 <?php endif; ?>
    </div>
  </div>
</section>