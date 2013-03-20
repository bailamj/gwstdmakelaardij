<div class="cookie-warning">
        <div class="alert alert-block alert-error">
          <button type="button" class="close close-cookie" data-dismiss="alert">&times;</button>
          Cookiegebruik: 
          Om de gebruiksvriendelijkheid van deze site te verbeteren maken wij gebruik van cookies.
          <button type="button" class="btn btn-danger closecookie" data-dismiss="alert">deze melding sluiten</button>
        </div>
</div>

<div class="navbar">
  <div class="navbar-inner">
    <div class="container">
      <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
      </a>
      <a class="brand" href="<?php echo home_url(); ?>/">
        <img src="http://www.gouwestadmakelaardij.nl/assets/img/logogouwestad.png" alt="logo"/>
      </a>
      <nav class="nav-collapse" role="navigation">
        <?php
          if (has_nav_menu('primary_navigation')) :
            wp_nav_menu(array('theme_location' => 'primary_navigation', 'menu_class' => 'nav pull-right'));
          endif;
        ?>
      </nav>
    </div>
  </div>
</div>

<header>
    <div class="header">
    </div>
    <div class="container">         
        <div class="row-fluid clearfix">
          <div class="span6">
            <h1>De makelaar voor Waddinxveen en omstreken</h1>
              <a href="#" class="btn btn-large btn-primary btn-block" id="zoekform-link"><i class="icon-search"></i> Doorzoek ons woningaanbod </a>
          </div>
          <div class="span6 zoekformulier" id="zoekformulier">
            <div class="well">
              <h5>Snel zoeken</h5>
              <?php echo do_shortcode("[property_search do_not_use_cache=true searchable_attributes=location,plaats,price per_page=15]"); ?>
            </div>
          </div>
        </div>
    </div>
  </header>
