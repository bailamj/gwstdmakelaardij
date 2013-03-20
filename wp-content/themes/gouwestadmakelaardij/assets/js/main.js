/* Author:
 Gouwestad Marketing & Media
*/


// Cookies When document is ready replaces the need for onload
$(document).ready(function() {

    // Grab your button (based on your posted html)
    $('.close-cookie').click(function( e ){

        // Do not perform default action when button is clicked
        e.preventDefault();

        /* If you just want the cookie for a session don't provide an expires
         Set the path as root, so the cookie will be valid across the whole site */
        $.cookie('alert-box', 'closed', { path: '/' });

    });

});

$(document).ready(function() {

    // Check if alert has been closed
    if( $.cookie('alert-box') === 'closed' ){

        $('.alert').hide();

    }


});


 /* Init Fancybox
-------------------------------------------------------------- */
$(document).ready(function() {
  $('.plattegrond').fancybox({
    width: '95%',
    height: '95%',
    autoSize: true
    });
});

$(document).ready(function() {
  $('.fancybox').fancybox({
    });
});

 /* Animation on doc ready
-------------------------------------------------------------- */
$(document).ready(function()
{
  $('.home header').addClass('animate');
});


$(document).ready(function() {
	$('#zoekform-link').click(function() {
	  $('#zoekformulier').toggle('slow', function() {
	    // Animation complete.
	  });
	});
});

$(document).ready(function() {
	$('#overige-teksten-link').click(function() {
	  $('#overige-teksten').toggle('slow', function() {
	    // Animation complete.
	  });
	});
});




