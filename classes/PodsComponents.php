<?php
/**
 *
 */
class PodsComponents {

    /**
     * Root of Components directory
     *
     * @var string
     *
     * @private
     * @since 2.0.0
     */
    private $components_dir = null;

    /**
     * Available components
     *
     * @var string
     *
     * @private
     * @since 2.0.0
     */
    public $components = array();

    /**
     * Components settings
     *
     * @var string
     *
     * @private
     * @since 2.0.0
     */
    public $settings = array();

    /**
     * Setup actions and get options
     *
     * @since 2.0.0
     */
    public function __construct () {
        $this->components_dir = apply_filters( 'pods_components_dir', PODS_DIR . 'components/' );

        $settings = get_option( 'pods_component_settings', '' );

        if ( !empty( $settings ) )
            $this->settings = (array) json_decode( $settings, true );

        if ( !isset( $this->settings[ 'components' ] ) )
            $this->settings[ 'components' ] = array();

        // Get components
        add_action( 'after_setup_theme', array( $this, 'get_components' ), 11 );

        // Load in components
        add_action( 'after_setup_theme', array( $this, 'load' ), 12 );

        // AJAX handling
        if ( is_admin() ) {
            add_action( 'wp_ajax_pods_admin_components', array( $this, 'admin_ajax' ) );
            add_action( 'wp_ajax_nopriv_pods_admin_components', array( $this, 'admin_ajax' ) );
        }
    }

    /**
     * Add menu item
     *
     * @since 2.0.0
     */
    public function menu ( $parent ) {
        foreach ( $this->components as $component => $component_data ) {
            if ( !empty( $component_data[ 'Hide' ] ) )
                continue;

            if ( pods_var( 'DeveloperMode', $component_data, false ) && ( !defined( 'PODS_DEVELOPER' ) || !PODS_DEVELOPER ) )
                continue;

            if ( !isset( $component_data[ 'object' ] ) || !method_exists( $component_data[ 'object' ], 'admin' ) )
                continue;

            add_submenu_page(
                $parent,
                strip_tags( $component_data[ 'Name' ] ),
                '- ' . strip_tags( $component_data[ 'MenuName' ] ),
                'read',
                'pods-component-' . $component_data[ 'ID' ],
                array( $this, 'admin_handler' )
            );
        }
    }

    /**
     * Load activated components and init component
     *
     * @since 2.0.0
     */
    public function load () {
        foreach ( (array) $this->settings[ 'components' ] as $component => $options ) {
            if ( !isset( $this->components[ $component ] ) || 0 == $options )
                continue;

            if ( 'on' == $this->components[ $component ][ 'DeveloperMode' ] )
                continue;


            if ( !empty( $this->components[ $component ][ 'PluginDependency' ] ) ) {
                $dependency = explode( '|', $this->components[ $component ][ 'PluginDependency' ] );

                if ( !pods_is_plugin_active( $dependency[ 1 ] ) )
                    continue;
            }

            $component_data = $this->components[ $component ];

            if ( !file_exists( $component_data[ 'File' ] ) )
                continue;

            include_once $component_data[ 'File' ];

            if ( !empty( $component_data[ 'Class' ] ) && class_exists( $component_data[ 'Class' ] ) && !isset( $this->components[ $component ][ 'object' ] ) ) {
                $this->components[ $component ][ 'object' ] = new $component_data[ 'Class' ];

                if ( method_exists( $this->components[ $component ][ 'object' ], 'options' ) ) {
                    $this->components[ $component ][ 'options' ] = $this->components[ $component ][ 'object' ]->options();

                    $this->options( $component, $this->components[ $component ][ 'options' ] );
                }

                if ( method_exists( $this->components[ $component ][ 'object' ], 'handler' ) )
                    $this->components[ $component ][ 'object' ]->handler( $this->settings[ 'components' ][ $component ] );
            }
        }
    }

    /**
     * Get list of components available
     *
     * @since 2.0.0
     */
    public function get_components () {
        $components = get_transient( 'pods_components' );

        if ( !is_array( $components ) || empty( $components ) || ( is_admin() && isset( $_GET[ 'page' ] ) && 'pods-components' == $_GET[ 'page' ] && isset( $_GET[ 'reload_components' ] ) ) ) {
            $component_dir = @opendir( rtrim( $this->components_dir, '/' ) );
            $component_files = array();

            if ( false !== $component_dir ) {
                while ( false !== ( $file = readdir( $component_dir ) ) ) {
                    if ( '.' == substr( $file, 0, 1 ) )
                        continue;
                    elseif ( is_dir( $this->components_dir . $file ) ) {
                        $component_subdir = @opendir( $this->components_dir . $file );

                        if ( $component_subdir ) {
                            while ( false !== ( $subfile = readdir( $component_subdir ) ) ) {
                                if ( '.' == substr( $subfile, 0, 1 ) )
                                    continue;
                                elseif ( '.php' == substr( $subfile, -4 ) )
                                    $component_files[] = $this->components_dir . $file . '/' . $subfile;
                            }

                            closedir( $component_subdir );
                        }
                    }
                    elseif ( '.php' == substr( $file, -4 ) )
                        $component_files[] = $this->components_dir . $file;
                }

                closedir( $component_dir );
            }

            $default_headers = array(
                'ID' => 'ID',
                'Name' => 'Name',
                'MenuName' => 'Menu Name',
                'Description' => 'Description',
                'Version' => 'Version',
                'Author' => 'Author',
                'Class' => 'Class',
                'Hide' => 'Hide',
                'PluginDependency' => 'Plugin Dependency',
                'DeveloperMode' => 'Developer Mode'
            );

            $components = array();

            foreach ( $component_files as $component_file ) {
                if ( !is_readable( $component_file ) )
                    continue;

                $component_data = get_file_data( $component_file, $default_headers, 'pods_component' );

                if ( empty( $component_data[ 'Name' ] ) || 'yes' == $component_data[ 'Hide' ] )
                    continue;

                if ( empty( $component_data[ 'MenuName' ] ) )
                    $component_data[ 'MenuName' ] = $component_data[ 'Name' ];

                if ( empty( $component_data[ 'Class' ] ) )
                    $component_data[ 'Class' ] = 'Pods_' . basename( $component_file, '.php' );

                if ( empty( $component_data[ 'ID' ] ) )
                    $component_data[ 'ID' ] = sanitize_title( $component_data[ 'Name' ] );

                if ( 'on' == $component_data[ 'DeveloperMode' ] || 1 == $component_data[ 'DeveloperMode' ] )
                    $component_data[ 'DeveloperMode' ] = true;
                else
                    $component_data[ 'DeveloperMode' ] = false;

                $component_data[ 'File' ] = $component_file;

                $components[ $component_data[ 'ID' ] ] = $component_data;
            }

            ksort( $components );

            set_transient( 'pods_components', $components );
        }

        $this->components = $components;

        return $this->components;
    }

    /**
     * @param $component
     * @param $options
     */
    public function options ( $component, $options ) {
        if ( !isset( $this->settings[ 'components' ][ $component ] ) || !is_array( $this->settings[ 'components' ][ $component ] ) )
            $this->settings[ 'components' ][ $component ] = array();

        foreach ( $options as $option => $data ) {
            if ( !isset( $this->settings[ 'components' ][ $component ][ $option ] ) && isset( $data[ 'default' ] ) )
                $this->settings[ 'components' ][ $component ][ $option ] = $data[ 'default' ];
        }
    }

    /**
     *
     */
    public function admin_handler () {
        $component = str_replace( 'pods-component-', '', $_GET[ 'page' ] );

        if ( isset( $this->components[ $component ] ) && isset( $this->components[ $component ][ 'object' ] ) && method_exists( $this->components[ $component ][ 'object' ], 'admin' ) )
            $this->components[ $component ][ 'object' ]->admin( $this->settings[ 'components' ][ $component ] );
    }

    /**
     * @param $component
     *
     * @return bool
     */
    public function toggle ( $component ) {
        $toggle = false;

        if ( isset( $this->components[ $component ] ) ) {
            if ( !isset( $this->settings[ 'components' ][ $component ] ) || 0 == $this->settings[ 'components' ][ $component ] ) {
                $this->settings[ 'components' ][ $component ] = array();
                $toggle = true;
            }
            else
                $this->settings[ 'components' ][ $component ] = 0;
        }

        $settings = json_encode( $this->settings );

        update_option( 'pods_component_settings', $settings );

        return $toggle;
    }

    /**
     *
     */
    public function admin_ajax () {
        if ( false === headers_sent() ) {
            if ( '' == session_id() )
                @session_start();

            header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
        }

        // Sanitize input
        $params = stripslashes_deep( (array) $_POST );
        foreach ( $params as $key => $value ) {
            if ( 'action' == $key )
                continue;
            unset( $params[ $key ] );
            $params[ str_replace( '_podsfix_', '', $key ) ] = $value;
        }
        if ( !defined( 'PODS_STRICT_MODE' ) || !PODS_STRICT_MODE )
            $params = pods_sanitize( $params );

        $params = (object) $params;

        $component = $params->component;
        $method = $params->method;

        if ( !isset( $component ) || !isset( $this->components[ $component ] ) || !isset( $this->settings[ 'components' ][ $component ] ) )
            pods_error( 'Invalid AJAX request', $this );

        if ( !isset( $this->components[ $component ][ 'object' ] ) || !method_exists( $this->components[ $component ][ 'object' ], 'ajax_' . $method ) )
            pods_error( 'API method does not exist', $this );

        if ( !isset( $params->_wpnonce ) || false === wp_verify_nonce( $params->_wpnonce, 'pods-component-' . $component . '-' . $method ) )
            pods_error( 'Unauthorized request', $this );

        // Cleaning up $params
        unset( $params->action );
        unset( $params->component );
        unset( $params->method );
        unset( $params->_wpnonce );

        $params = (object) apply_filters( 'pods_component_ajax_' . $component . '_' . $method, $params, $component, $method );

        $method = 'ajax_' . $method;

        // Dynamically call the component method
        $output = call_user_func( array( $this->components[ $params->component ][ 'object' ], $method ), $params );

        if ( !is_bool( $output ) )
            echo $output;

        die(); // KBAI!
    }
}

/**
 *
 */class PodsComponent {

    /**
     * Do things like register/enqueue scripts and stylesheets
     *
     * @since 2.0.0
     */
    public function __construct () {

    }

    /**
     * Add options and set defaults for field type, shows in admin area
     *
     * @return array $options
     *
     * @since 2.0.0
     */
    public function options () {
        $options = array( /*
            'option_name' => array(
                'label' => 'Option Label',
                'depends-on' => array( 'another_option' => 'specific-value' ),
                'default' => 'default-value',
                'type' => 'field_type',
                'data' => array(
                    'value1' => 'Label 1',

                    // Group your options together
                    'Option Group' => array(
                        'gvalue1' => 'Option Label 1',
                        'gvalue2' => 'Option Label 2'
                    ),

                    // below is only if the option_name above is the "{$fieldtype}_format_type"
                    'value2' => array(
                        'label' => 'Label 2',
                        'regex' => '[a-zA-Z]' // Uses JS regex validation for the value saved if this option selected
                    )
                ),

                // below is only for a boolean group
                'group' => array(
                    'option_boolean1' => array(
                        'label' => 'Option boolean 1?',
                        'default' => 1,
                        'type' => 'boolean'
                    ),
                    'option_boolean2' => array(
                        'label' => 'Option boolean 2?',
                        'default' => 0,
                        'type' => 'boolean'
                    )
                )
            ) */
        );

        return $options;
    }

    /**
     * Handler to run code based on $options
     *
     * @param $options
     *
     * @since 2.0.0
     */
    public function handler ( $options ) {
        // run code based on $options set
    }

    /**
     * Build admin area
     *
     * @param $options
     *
     * @since 2.0.0
    public function admin ( $options ) {
        // run code based on $options set
    }
     */
}