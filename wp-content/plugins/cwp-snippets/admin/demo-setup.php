<?php

// *********************************************************************************************************************************
//Register Settings fields

function fmcwp_register_demo_settings() {
    // Register demo settings using a map to avoid duplicating register_setting calls.
    $options = array(
        'fmcwp_host_demo' => array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ),
        'fmcwp_insecure_demo' => array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false,
        ),
        'fmcwp_database_demo' => array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ),
        'fmcwp_layout_demo' => array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ),
        'fmcwp_user_demo' => array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ),
        'fmcwp_password_demo' => array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ),
    );

    foreach ( $options as $option_name => $meta ) {
        register_setting(
            'fmcwp_demo_option_group',
            $option_name,
            array(
                'type' => $meta['type'],
                'sanitize_callback' => $meta['sanitize_callback'],
                'default' => $meta['default'],
            )
        );
    }
}

add_action('admin_init', 'fmcwp_register_demo_settings');



// *********************************************************************************************************************************
// Callback function to display the content of settings page 3

function fmcwp_demo_setup_html() {

    fmcwp_header();

    $formRes = '';
    $color = '';
    $fmerror = '';
    $fmmessage = '';

    if(!empty(FM_HOST_DEMO) && !empty(FM_DATABASE_DEMO) && !empty(FM_LAYOUT_DEMO) && !empty(FM_USER_DEMO) && !empty(FM_PASSWORD_DEMO)){

        // fmCWP( host.domain, database, layout, user, password, options)
        $fm = new fmCWP(FM_HOST_DEMO, FM_DATABASE_DEMO, FM_LAYOUT_DEMO, FM_USER_DEMO, FM_PASSWORD_DEMO, FM_OPTIONS_DEMO);

        // Get Layout Metadata to confirm connection is successful.
        $result = $fm->getLayoutMetadata();

        // Capture Response Array
        $response = $fm->getResponse($result);

        // Capture error
        $fmerror = $fm->isError($result) ;

        if ($fmerror){

            if ( is_array($response)){
                
                $fmmessage = 'Error!! ' . $response['messages'][0]['message'];
                
            }else{
                
                $fmmessage = 'Error!! ' . $response;
                
            }
                
        }else{

            $fmmessage = 'Connection Successful!!';

        }

    }

    

    // Set error message and color
    if ( !$fmerror ){ $formRes = $fmmessage; $color = 'green'; } else { $formRes = $fmmessage ; $color = 'red'; }

    ?>
    <div>
        <div class="content" style="display:flex; flex-wrap: wrap; gap: 100px; padding-left: 15px;">
            <div>
                <h1 style="margin-bottom: 40px;">Demo Settings</h1>
                <form method="POST" action="options.php">
                <?php settings_fields('fmcwp_demo_option_group'); ?>
                    <p>
                        <label style="display: inline-block; width:120px;">Host Domain/IP:</label>
                        <input name="fmcwp_host_demo" type="text" value="<?php echo esc_attr( get_option('fmcwp_host_demo', '') ); ?>">
                    </p>
                    <!--
                    <p>
                        <label style="display: inline-block; width:120px;">Allow Insecure:</label>
                        <select name="fmcwp_insecure_demo">
                            <option><?php echo esc_html( get_option('fmcwp_insecure_demo', false ) ); ?></option>
                            <option>true</option>
                            <option>false</option>
                        </select>
                    </p>
                    -->
                    <p>
                        <label style="display: inline-block; width:120px;">Database:</label>
                        <input name="fmcwp_database_demo" type="text" value="<?php echo esc_attr( get_option('fmcwp_database_demo', '') ); ?>">
                    </p>
                    <p>
                        <label style="display: inline-block; width:120px;">Default Layout:</label>
                        <input name="fmcwp_layout_demo" type="text" value="<?php echo esc_attr( get_option('fmcwp_layout_demo', '') ); ?>">
                    </p>
                    <p>
                        <label style="display: inline-block; width:120px;">Username:</label>
                        <input name="fmcwp_user_demo" type="text" value="<?php echo esc_attr( get_option('fmcwp_user_demo', '') ); ?>">
                    </p>
                    <p>
                        <label style="display: inline-block; width:120px;">Password:</label>
                        <input name="fmcwp_password_demo" type="password" value="<?php echo esc_attr( get_option('fmcwp_password_demo', '') ); ?>">
                    </p>
                    <p>
                    <button  class="button button-primary" type="submit" value="Submit">Submit</button>
                    </p>
                    <span style="padding-left: 10px; font-size: 16px; color:<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $formRes ); ?></span><br>
                    
                </form>
            </div>

            <!-- Demo Instructions -->
            <div>
                <h1 style="margin-bottom: 40px;">Demo Database</h1>
                
                <div>        
                    <p style="font-size: 16px;">1. Download the demo databases and upload to your server.</p>
                    <p style="font-size: 16px;"><a href="../wp-content/plugins/cwp-snippets/assets/demo/CWP Snippets.fmp12" target="_blank">CWP Snippets.fmp12</a></p>
                    <p style="font-size: 16px;"><a href="#" id="cwp-demo-settings-link">Demo Settings</a></p>
                    
                    <p style="font-size: 16px;">2. Enter the Host Domain / IP of your FileMaker server.</p>
                    <p style="font-size: 16px;">3. Enter database name, layout and account info.</p>
                </div>
            </div>
        </div>


    </div>

    <div id="cwp-demo-settings-modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
        <div style="background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; position: relative;">
            <span id="cwp-demo-settings-close" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
            <div style="font-family: sans-serif; padding: 20px;">
                <h2>CWP Snippets.fmp12 Demo Database Settings</h2>
                <hr>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr style="background-color: #f2f2f2;">
                        <td style="padding: 8px; border: 1px solid #ddd;"><strong>Host Domain/ip</strong></td>
                        <td style="padding: 8px; border: 1px solid #ddd;">Your Server Location</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;"><strong>Database</strong></td>
                        <td style="padding: 8px; border: 1px solid #ddd;">CWP Snippets.fmp12</td>
                    </tr>
                    <tr style="background-color: #f2f2f2;">
                        <td style="padding: 8px; border: 1px solid #ddd;"><strong>Default Layout</strong></td>
                        <td style="padding: 8px; border: 1px solid #ddd;">Contact Details</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;"><strong>Username</strong></td>
                        <td style="padding: 8px; border: 1px solid #ddd;">api</td>
                    </tr>
                    <tr style="background-color: #f2f2f2;">
                        <td style="padding: 8px; border: 1px solid #ddd;"><strong>Password</strong></td>
                        <td style="padding: 8px; border: 1px solid #ddd;">api123</td>
                    </tr>
                </table>
                <h3 style="margin-top: 30px;">FULL Access Account</h3>
                <hr>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr style="background-color: #f2f2f2;">
                        <td style="padding: 8px; border: 1px solid #ddd;"><strong>Username</strong></td>
                        <td style="padding: 8px; border: 1px solid #ddd;">admin</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;"><strong>Password</strong></td>
                        <td style="padding: 8px; border: 1px solid #ddd;">admin123</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var modal = document.getElementById('cwp-demo-settings-modal');
        var link = document.getElementById('cwp-demo-settings-link');
        var closeBtn = document.getElementById('cwp-demo-settings-close');
        var modalContent = modal.querySelector('div');

        link.onclick = function(e) {
            e.preventDefault();
            var rect = link.getBoundingClientRect();
            modal.style.display = 'block';
            modalContent.style.position = 'absolute';
            modalContent.style.top = (rect.top) + 'px';
            modalContent.style.left = (rect.right + 10) + 'px';
            modalContent.style.margin = 0;
        }

        closeBtn.onclick = function() {
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    });
    </script>
    <?php
    fmcwp_footer();

}
