<div class="container">
	<div class="row-fluid">
		<?php if (is_page()) { ?>
		<!-- don't display title (leave this blank) -->
		<?php } else { ?>
		<div class="page-header">
			<h1>
			<?php echo roots_title(); ?>   
			</h1>
		</div>
		<?php } ?>
	</div>
</div>