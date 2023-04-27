<?php

// https://tarteaucitron.io/fr/install/


function eschaton_tarteaucitron_load() {
  wp_enqueue_script( 'tarteaucitron', ESC_URL . '/js/tarteaucitron/tarteaucitron.js' );
} 
add_action( 'wp_enqueue_scripts', 'eschaton_tarteaucitron_load' );



function eschaton_tarteaucitron_init() { ?> 

<script type="text/javascript">

  document.addEventListener('DOMContentLoaded', () => {
    if( typeof tarteaucitron !== 'undefined' ) {

      tarteaucitron.init({
        "privacyUrl": "", /* Privacy policy url */
        "bodyPosition": "bottom", /* or top to bring it as first element for accessibility */

        "hashtag": "#tarteaucitron", /* Open the panel with this hashtag */
        "cookieName": "tarteaucitron", /* Cookie name */

        "orientation": "middle", /* Banner position (top - bottom) */
    
        "groupServices": false, /* Group services by category */
        "serviceDefaultState": "wait", /* Default state (true - wait - false) */
                        
        "showAlertSmall": false, /* Show the small banner on bottom right */
        "cookieslist": false, /* Show the cookie list */
                        
        "closePopup": false, /* Show a close X on the banner */

        "showIcon": false, /* Show cookie icon to manage cookies */
        //"iconSrc": "", /* Optionnal: URL or base64 encoded image */
        "iconPosition": "BottomRight", /* BottomRight, BottomLeft, TopRight and TopLeft */

        "adblocker": false, /* Show a Warning if an adblocker is detected */
                        
        "DenyAllCta" : true, /* Show the deny all button */
        "AcceptAllCta" : true, /* Show the accept all button when highPrivacy on */
        "highPrivacy": true, /* HIGHLY RECOMMANDED Disable auto consent */
                        
        "handleBrowserDNTRequest": false, /* If Do Not Track == 1, disallow all */

        "removeCredit": true, /* Remove credit link */
        "moreInfoLink": false, /* Show more info link */

        "useExternalCss": false, /* If false, the tarteaucitron.css file will be loaded */
        "useExternalJs": false, /* If false, the tarteaucitron.js file will be loaded */

        //"cookieDomain": ".my-multisite-domaine.fr", /* Shared cookie for multisite */
        "readmoreLink": "", /* Change the default readmore link */

        "mandatory": true, /* Show a message about mandatory cookies */
        "mandatoryCta": true /* Show the disabled accept button when mandatory on */
      });
    }

    tarteaucitron.user.gtagUa = 'G-D8K5WSFSGV';
    tarteaucitron.user.gtagMore = function () { /* add here your optionnal gtag() */ };
    (tarteaucitron.job = tarteaucitron.job || []).push('gtag');


  });
</script>
  
<?php
}		
add_action('wp_head', 'eschaton_tarteaucitron_init');

