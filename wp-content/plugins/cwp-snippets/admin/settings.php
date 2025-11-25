<?php

// *********************************************************************************************************************************
//Register Settings fields

function fmcwp_register_settings() {
    // Register settings with core sanitizers (minimal change, satisfies PluginCheck)
    register_setting(
        'fmcwp_option_group',
        'fmcwp_host',
        array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        )
    );

    register_setting(
        'fmcwp_option_group',
        'fmcwp_insecure',
        array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false,
        )
    );

    register_setting(
        'fmcwp_option_group',
        'fmcwp_database',
        array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        )
    );

    register_setting(
        'fmcwp_option_group',
        'fmcwp_layout',
        array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        )
    );

    register_setting(
        'fmcwp_option_group',
        'fmcwp_user',
        array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        )
    );

    register_setting(
        'fmcwp_option_group',
        'fmcwp_password',
        array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        )
    );
}

add_action('admin_init', 'fmcwp_register_settings');



// *********************************************************************************************************************************
// Callback function to display the content of settings page

function fmcwp_settings_html() {

    

    fmcwp_header();

    $formRes = '';
    $color = '';
    $fmerror = '';
    $fmmessage = '';

    if(!empty(FM_HOST) && !empty(FM_DATABASE) && !empty(FM_LAYOUT) && !empty(FM_USER) && !empty(FM_PASSWORD)){

        // fmCWP( host.domain, database, layout, user, password, options)
        $fm = new fmCWP(FM_HOST, FM_DATABASE, FM_LAYOUT, FM_USER, FM_PASSWORD, FM_OPTIONS);

        // Get useful information about specific layout, including fields on the layout, portals,...
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
    <div class="content" style=" padding-left: 15px;">
        <div style="width: 750px;">
            <h1 style="margin-bottom: 40px;">Settings</h1>
            <form method="POST" action="options.php">
             <?php settings_fields('fmcwp_option_group'); ?>
                <p>
                    <label style="display: inline-block; width:120px;">Host Domain/IP:</label>
                    <input name="fmcwp_host" type="text" value="<?php echo esc_attr( get_option('fmcwp_host', '') ); ?>" required>
                </p>
                <!--
                <p>
                    <label style="display: inline-block; width:120px;">Allow Insecure:</label>
                    <select name="fmcwp_insecure">
                        <option><?php echo esc_html( get_option('fmcwp_insecure', false ) ); ?></option>
                        <option>true</option>
                        <option>false</option>
                    </select>
                </p>
                -->
                <p>
                    <label style="display: inline-block; width:120px;">Database:</label>
                    <input name="fmcwp_database" type="text" value="<?php echo esc_attr( get_option('fmcwp_database', '') ); ?>" required>
                </p>
                <p>
                    <label style="display: inline-block; width:120px;">Default Layout:</label>
                    <input name="fmcwp_layout" type="text" value="<?php echo esc_attr( get_option('fmcwp_layout', '') ); ?>" required>
                </p>
                <p>
                    <label style="display: inline-block; width:120px;">Username:</label>
                    <input name="fmcwp_user" type="text" value="<?php echo esc_attr( get_option('fmcwp_user', '') ); ?>" required>
                </p>
                <p>
                    <label style="display: inline-block; width:120px;">Password:</label>
                    <input name="fmcwp_password" type="password" value="<?php echo esc_attr( get_option('fmcwp_password', '') ); ?>" required>
                </p>
                <p>
                <button  class="button button-primary" type="submit" value="Submit">Submit</button>
                </p>
                <span style="padding-left: 10px; font-size: 16px; color:<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $formRes ); ?></span><br>
                
            </form>
        </div>
    </div>

    <?php

    fmcwp_footer();

}