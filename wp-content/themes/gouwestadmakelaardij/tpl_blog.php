<?php
/*
Template Name: Blog Gouwestad Makelaardij
*/
?>

<?php get_template_part('templates/page', 'header'); ?>

<section>
	<div class="container">
		<div class="row-fluid">
			<div class="span12">
				<div class="page-header center">
					<h1>Het laatste nieuws</h1>
					<p class="intro center">Lees hier over ons, de ontwikkelingen op de woningmarkt maar ook op de financiele markt voor bijvoorbeelde de hypotheken.</p>
				</div>
			</div>
		</div>
	</div>
</section>

<?php query_posts('post_type=post&post_status=publish&posts_per_page=2&paged='. get_query_var('paged')); ?>
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>

<section>
	<div class="container">
		<div class="row-fluid">
			<div class="span4">
				<p><a href="<?php the_permalink(); ?>"><?php the_post_thumbnail( array(700,700) ); ?></a></p>
				<p><i class="icon-calendar"></i> <?php the_date(); ?> <br>
				<i class="icon-bookmark-empty"></i> <?php the_category(' '); ?> </p>
			</div>

			<div class="span8">
				<div id="post-<?php get_the_ID(); ?>" <?php post_class(); ?>>
					<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
					<p><?php the_excerpt(); ?></p>
					<div class="align-right">
	                    <a href="<?php the_permalink(); ?>" class="btn btn-primary btn-small"> lees verder <i class="icon-circle-arrow-right"></i></a>
	                </div>
				</div><!-- /#post-<?php get_the_ID(); ?> -->
			</div>
		</div>
	  <hr>
	</div>
</section>

		<?php endwhile; else: ?>
		<p><?php _e('Sorry, no posts matched your criteria.'); ?></p>
		<?php endif; ?>

<section class="graylighter smaller-font">
	<div class="container">
					        
		<div class="row-fluid">
			<div class="span12">
				<ul class="pager">
					<li>
					<?php previous_posts_link(__('<i class="icon-double-angle-left"></i> Nieuwere berichten')) ?>
					</li>
					<li>
					<?php next_posts_link(__('Oudere berichten <i class="icon-double-angle-right"></i>')) ?> 
					</li>
				</ul>				  
			</div>
		</div>
	</div>
</section>
