<?php
/**
 * WP-Property Overview Template
 *
 * To customize this file, copy it into your theme directory, and the plugin will
 * automatically load your version.
 *
 * You can also customize it based on property type.  For example, to create a custom
 * overview page for 'building' property type, create a file called property-overview-building.php
 * into your theme directory.
 *
 *
 * Settings passed via shortcode:
 * $properties: either array of properties or false
 * $show_children: default true
 * $thumbnail_size: slug of thumbnail to use for overview page
 * $thumbnail_sizes: array of image dimensions for the thumbnail_size type
 * $fancybox_preview: default loaded from configuration
 * $child_properties_title: default "Floor plans at location:"
 *
 *
 *
 * @version 1.4
 * @author Andy Potanin <andy.potnain@twincitiestech.com>
 * @package WP-Property
*/?>
<?php
 if ( have_properties() ) {

   $thumbnail_dimentions = WPP_F::get_image_dimensions($wpp_query['thumbnail_size']);

?>

                 <div class="<?php wpp_css('property_overview::row_view', "wpp_row_view wpp_property_view_result"); ?>">
                  <div class="<?php wpp_css('property_overview::all_properties', "all-properties"); ?>">
                  <?php foreach ( returned_properties('load_gallery=false') as $property) {  ?>

                    <div class="<?php wpp_css('property_overview::property_div', "property_div {$property['post_type']} "); ?>">
                    <div class="row-fluid">
                        <div class="span4">
                            <div class="<?php wpp_css('property_overview::left_column', "wpp_overview_left_column"); ?>">
                              <?php property_overview_image(); ?>
                            </div>

                                <?php 
                                  $huurstring = strip_tags($property["status"]);
                                  if($huurstring == Sold){
                                    echo "<span class='label'>Verkocht</span>";
                                    }
                                  elseif ($huurstring == Sold_under_conditions){
                                    echo "<span class='label'>Verkocht onder voorbehoud</span>";
                                    } 
                                    elseif ($huurstring == Rented_under_conditions){
                                    echo "<span class='label'>Verhuurd onder voorbehoud</span>";
                                    } 
                                    elseif ($huurstring == Rented){
                                    echo "<span class='label'>Verhuurd</span>";
                                    }
                                    else{

                                    }
                                  ?>
                            <ul class="elements">
                                <?php if($property['gbo']): ?>
                                      <li><i class="icon-exchange"></i> <?php echo $property['gbo']; ?> m<sup>2</sup></li>
                                  <?php endif; ?>
                                  <?php if($property['inhoud']): ?>
                                      <li><i class="icon-home"></i> <?php echo $property['inhoud']; ?> m<sup>3</sup></li>
                                  <?php endif; ?>
                                  <?php if($property['kamers']): ?>
                                      <li><i class="icon-group"></i> <?php echo $property['kamers']; ?></li>
                                  <?php endif; ?>
                                  <?php if($property['hoofdbestemming']): ?>
                                      <li><i class="icon-building"></i> <?php echo $property['hoofdbestemming']; ?></li>
                                  <?php endif; ?>
                                  <?php if($property['gbo_bog']): ?>
                                      <li><i class="icon-exchange"></i> <?php echo $property['gbo_bog']; ?> m<sup>2</sup></li>
                                  <?php endif; ?>
                                  <?php if($property['parkeren_bog']): ?>
                                      <li><i class="icon-truck"></i> <?php echo $property['parkeren_bog']; ?></li>
                                  <?php endif; ?>
                            </ul>
                        </div>
                        <div class="span8">

                            <div class="<?php wpp_css('property_overview::right_column', "wpp_overview_right_column"); ?>">

                                <ul class="<?php wpp_css('property_overview::data', "wpp_overview_data"); ?>">
                                    <h4 class="property_title"><small>
                                        <a <?php echo $in_new_window; ?> href="<?php echo $property['permalink']; ?>"><?php echo $property['post_title']; ?></a>
                                        <?php if($property['is_child']): ?>
                                            of <a <?php echo $in_new_window; ?> href='<?php echo $property['parent_link']; ?>'><?php echo $property['parent_title']; ?></a>
                                        <?php endif; ?> | </small>
                                        <span class="property_price">
                                            <?php 
                                            $huurstring = strip_tags($property["huren"]);
                                            if($huurstring == true){
                                              echo $property['huurprijs'];
                                              echo ",-";
                                              echo "<small> p mnd</small>";
                                            }
                                              else{
                                                echo $property['price'];
                                                echo ",-";
                                              }
                                            ?>

                                            <small> <?php 
                                            $pricetypestring = strip_tags($property["kosten_koper"]);
                                              if ($pricetypestring == "Costs_buyer"){
                                                echo "K.K.";
                                              }
                                                elseif ($pricetypestring == 'Free_on_name'){
                                                  echo 'V.O.N.';
                                              } 
                                              else {
                                                  echo $property["kosten_koper"];
                                              }
                                    
                                            ?>
                                        </small>
                                        </span>
                                    </h4>

                                <?php if($property['custom_attribute_overview'] || $property['tagline']): ?>
                                    <li class="property_tagline">
                                        <?php if($property['custom_attribute_overview']): ?>
                                            <?php echo $property['custom_attribute_overview']; ?>
                                        <?php elseif($property['tagline']): ?>
                                            <?php echo $property['tagline']; ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endif; ?>


                                <?php if($property['introductietext']): ?>
                                    <li class="korte-tekst"><?php
                                     $little_content = substr($property['introductietext'],0,110); 
                                     $string = strip_tags($little_content);
                                    echo $string ;
                                     ?> ...</li>
                                <?php endif; ?>

                                <div class="align-right">
                                    <a href="<?php echo $property['permalink']; ?>" class="btn btn-primary btn-small">  
                                      <?php $categoriestring = strip_tags($property["property_type"]);
                                          if ($categoriestring == "bedrijfsonroerend_goed"){
                                          echo "bekijk object";
                                          }  
                                          else{
                                          echo "bekijk woning";
                                          }?>
                                      <i class="icon-circle-arrow-right"></i></a>
                                </div>

                                <?php if($show_children && $property['children']): ?>
                                <li class="child_properties">
                                    <div class="wpd_floorplans_title"><?php echo $child_properties_title; ?></div>
                                    <table class="wpp_overview_child_properties_table">
                                        <?php foreach($property['children'] as $child): ?>
                                        <tr class="property_child_row">
                                            <th class="property_child_title"><a href="<?php echo $child['permalink']; ?>"><?php echo $child['post_title']; ?></a></th>
                                            <td class="property_child_price"><?php echo $child['price']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </table>
                                </li>
                                <?php endif; ?>

                                <?php if(!empty($wpp_query['detail_button'])) : ?>
                                <li><a <?php echo $in_new_window; ?> class="button" href="<?php echo $property['permalink']; ?>"><?php echo $wpp_query['detail_button'] ?></a></li>
                                <?php endif; ?>
                           </ul>

                            </div><?php // .wpp_right_column ?>
                        </div>
                    </div>

                        </div><?php // .property_div ?>

                    <?php } /** end of the propertyloop. */ ?>
                    </div><?php // .all-properties ?>
                	</div><?php // .wpp_row_view ?>
                    <?php } else {  ?>
                    <div class="wpp_nothing_found">
                       <p><?php echo sprintf(__('Sorry, no properties found - try expanding your search, or <a href="%s">view all</a>.','wpp'), site_url().'/'.$wp_properties['configuration']['base_slug']); ?></p>
                    </div>
                    <?php } ?>
