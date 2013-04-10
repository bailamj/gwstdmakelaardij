<?php
/*
Template Name: Blog Post Gouwestad Makelaardij
*/
?>

<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/nl_NL/all.js#xfbml=1&appId=113978908731538";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>

<?php $post= $_GET['post_id']; ?>

<?php while (have_posts()) : the_post(); ?>
  <article <?php post_class(); ?>>

<section>
	<div class="container">
		<div class="row-fluid">
			<div class="span12">
				<div class="page-header center">
					<h1><?php single_post_title(); ?></h1>
				</div>
			</div>
		</div>
		<div class="row-fluid">
			<div class="span3">
				<p><a href="<?php the_permalink(); ?>"><?php the_post_thumbnail( array(500,500) ); ?></a></p>
				<p><i class="icon-calendar"></i> <?php echo get_the_time('d F Y', $post); ?> <br>
				<i class="icon-bookmark-empty"></i> <?php the_category(' '); ?> </p>
				<i class="icon-tags"></i> Tags:
				<?php the_tags('<ul><li>','</li><li>','</li></ul>'); ?>
				<i class="icon-share-alt"></i> Deel dit bericht<br>
				<!-- AddThis Button BEGIN -->
	            <div class="addthis_toolbox addthis_default_style addthis_32x32_style">
	            <a class="addthis_button_preferred_1"></a>
	            <a class="addthis_button_preferred_2"></a>
	            <a class="addthis_button_preferred_3"></a>
	            </div><br>
	           <!-- AddThis Button END -->
	            <i class="icon-thumbs-up"></i> Like ons
				<div class="fb-like" data-href="https://www.facebook.com/pages/Gouwestad-Makelaardij/176712525694328?fref=ts" data-send="false" data-width="100%" data-show-faces="true"></div>
			</div>
			<div class="span8 offset1">
				<?php the_content(); ?>
			</div>
		</div>
	</div>
</section>

<section class="graylighter smaller-font">
	<div class="container">				        
		<div class="row-fluid">
			<div class="span12">
				<ul class="pager">
					<li>
					<?php previous_post_link('%link') ?>
					</li>
					<li>
					<?php next_post_link('%link'); ?> 
					</li>
				</ul>				  
			</div>
		</div>
 
	</div>
</section>

</article>
<?php endwhile; ?>
