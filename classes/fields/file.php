<?php
/**
 *
 */
class PodsField_File extends PodsField {

    /**
     * Field Type Identifier
     *
     * @var string
     * @since 2.0.0
     */
    public static $type = 'file';

    /**
     * Field Type Label
     *
     * @var string
     * @since 2.0.0
     */
    public static $label = 'File / Image / Video';

    /**
     * Do things like register/enqueue scripts and stylesheets
     *
     * @since 2.0.0
     */
    public function __construct () {

    }

    /**
     * Add options and set defaults to
     *
     * @param array $options
     *
     * @since 2.0.0
     */
    public function options () {
        $sizes = get_intermediate_image_sizes();

        $image_sizes = array();

        foreach ( $sizes as $size ) {
            $image_sizes[ $size ] = ucwords( str_replace( '-', ' ', $size ) );
        }

        $options = array(
            'file_format_type' => array(
                'label' => __( 'File Type', 'pods' ),
                'default' => 'single',
                'type' => 'pick',
                'data' => array(
                    'single' => __( 'Single Select', 'pods' ),
                    'multi' => __( 'Multiple Select', 'pods' )
                ),
                'dependency' => true
            ),
            'file_uploader' => array(
                'label' => __( 'File Uploader', 'pods' ),
                'default' => 'plupload',
                'type' => 'pick',
                'data' => apply_filters(
                    'pods_form_ui_field_file_uploader_options',
                    array(
                        'plupload' => __( 'Plupload', 'pods' ),
                        'attachment' => __( 'Attachments (WP Media Library)', 'pods' )
                    )
                ),
                'dependency' => true
            ),
            'file_edit_title' => array(
                'label' => __( 'Editable Title', 'pods' ),
                'default' => 1,
                'type' => 'boolean'
            ),
            'file_limit' => array(
                'label' => __( 'File Limit', 'pods' ),
                'depends-on' => array( 'file_format_type' => 'multi' ),
                'default' => 0,
                'type' => 'number'
            ),
            'file_restrict_filesize' => array(
                'label' => __( 'Restrict File Size', 'pods' ),
                'default' => '10MB',
                'type' => 'text'
            ),
            'file_type' => array(
                'label' => __( 'Restrict File Types', 'pods' ),
                'default' => 'images',
                'type' => 'pick',
                'data' => apply_filters(
                    'pods_form_ui_field_file_type_options',
                    array(
                        'images' => __( 'Images (jpg, png, gif)', 'pods' ),
                        'video' => __( 'Video (mpg, mov, flv, mp4)', 'pods' ),
                        'other' => __( 'Other (customize allowed extensions)', 'pods' )
                    )
                ),
                'dependency' => true
            ),
            'file_allowed_extensions' => array(
                'label' => __( 'Allowed File Extensions', 'pods' ),
                'description' => __( 'Separate file extensions with a comma (ex. jpg,png,mp4,mov)', 'pods' ),
                'depends-on' => array( 'file_type' => 'other' ),
                'default' => '',
                'type' => 'text'
            ),
            'file_image_size' => array(
                'label' => __( 'Excluded Image Sizes', 'pods' ),
                'description' => __( 'Image sizes not to generate when processing the image', 'pods' ),
                'depends-on' => array( 'file_type' => 'images' ),
                'default' => 'images',
                'type' => 'pick',
                'pick_format_type' => 'multi',
                'pick_format_multi' => 'checkbox',
                'data' => apply_filters(
                    'pods_form_ui_field_file_image_size_options',
                    $image_sizes
                )
            )
        );
        return $options;
    }

    /**
     * Define the current field's schema for DB table storage
     *
     * @param array $options
     *
     * @return array
     * @since 2.0.0
     */
    public function schema ( $options = null ) {
        $schema = false;

        return $schema;
    }

    /**
     * Change the way the value of the field is displayed with Pods::get
     *
     * @param mixed $value
     * @param string $name
     * @param array $options
     * @param array $fields
     * @param array $pod
     * @param int $id
     *
     * @since 2.0.0
     */
    public function display ( $value = null, $name = null, $options = null, $pod = null, $id = null ) {
        return $value;
    }

    /**
     * Customize output of the form field
     *
     * @param string $name
     * @param mixed $value
     * @param array $options
     * @param array $pod
     * @param int $id
     *
     * @since 2.0.0
     */
    public function input ( $name, $value = null, $options = null, $pod = null, $id = null ) {
        $options = (array) $options;

        if ( ( ( defined( 'PODS_DISABLE_FILE_UPLOAD' ) && true === PODS_DISABLE_FILE_UPLOAD )
               || ( defined( 'PODS_UPLOAD_REQUIRE_LOGIN' ) && is_bool( PODS_UPLOAD_REQUIRE_LOGIN ) && true === PODS_UPLOAD_REQUIRE_LOGIN && !is_user_logged_in() )
               || ( defined( 'PODS_UPLOAD_REQUIRE_LOGIN' ) && !is_bool( PODS_UPLOAD_REQUIRE_LOGIN ) && ( !is_user_logged_in() || !current_user_can( PODS_UPLOAD_REQUIRE_LOGIN ) ) ) )
             && ( ( defined( 'PODS_DISABLE_FILE_BROWSER' ) && true === PODS_DISABLE_FILE_BROWSER )
                  || ( defined( 'PODS_FILES_REQUIRE_LOGIN' ) && is_bool( PODS_FILES_REQUIRE_LOGIN ) && true === PODS_FILES_REQUIRE_LOGIN && !is_user_logged_in() )
                  || ( defined( 'PODS_FILES_REQUIRE_LOGIN' ) && !is_bool( PODS_FILES_REQUIRE_LOGIN ) && ( !is_user_logged_in() || !current_user_can( PODS_FILES_REQUIRE_LOGIN ) ) ) )
        ) {
?>
    <p>You do not have access to upload / browse files. Contact your website admin to resolve.</p>
<?php
            return;
        }

        if ( 'plupload' == pods_var( 'file_uploader', $options ) )
            $field_type = 'plupload';
        elseif ( 'attachment' == pods_var( 'file_uploader', $options ) )
            $field_type = 'attachment';
        else {
            // Support custom File Uploader integration
            do_action( 'pods_form_ui_field_file_uploader_' . pods_var( 'file_uploader', $options ), $name, $value, $options, $pod, $id );
            do_action( 'pods_form_ui_field_file_uploader', pods_var( 'file_uploader', $options ), $name, $value, $options, $pod, $id );
            return;
        }

        pods_view( PODS_DIR . 'ui/fields/' . $field_type . '.php', compact( array_keys( get_defined_vars() ) ) );
    }

    /**
     * Build regex necessary for JS validation
     *
     * @param mixed $value
     * @param string $name
     * @param array $options
     * @param string $pod
     * @param int $id
     *
     * @since 2.0.0
     */
    public function regex ( $value = null, $name = null, $options = null, $pod = null, $id = null ) {
        return false;
    }

    /**
     * Validate a value before it's saved
     *
     * @param mixed $value
     * @param string $name
     * @param array $options
     * @param array $fields
     * @param array $pod
     * @param int $id
     *
     * @since 2.0.0
     */
    public function validate ( &$value, $name = null, $options = null, $fields = null, $pod = null, $id = null, $params = null ) {
        // check file size
        // check file extensions
        return true;
    }

    /**
     * Change the value or perform actions after validation but before saving to the DB
     *
     * @param mixed $value
     * @param int $id
     * @param string $name
     * @param array $options
     * @param array $fields
     * @param array $pod
     * @param object $params
     *
     * @since 2.0.0
     */
    public function pre_save ( $value, $id = null, $name = null, $options = null, $fields = null, $pod = null, $params = null ) {
        return $value;
    }

    /**
     * Perform actions after saving to the DB
     *
     * @param mixed $value
     * @param int $id
     * @param string $name
     * @param array $options
     * @param array $fields
     * @param array $pod
     * @param object $params
     *
     * @since 2.0.0
     */
    public function post_save ( $value, $id = null, $name = null, $options = null, $fields = null, $pod = null, $params = null ) {

    }

    /**
     * Perform actions before deleting from the DB
     *
     * @param string $name
     * @param string $pod
     * @param int $id
     * @param object $api
     *
     * @since 2.0.0
     */
    public function pre_delete ( $id = null, $name = null, $options = null, $pod = null ) {

    }

    /**
     * Perform actions after deleting from the DB
     *
     * @param int $id
     * @param string $name
     * @param array $options
     * @param array $pod
     *
     * @since 2.0.0
     */
    public function post_delete ( $id = null, $name = null, $options = null, $pod = null ) {

    }

    /**
     * Customize the Pods UI manage table column output
     *
     * @param int $id
     * @param mixed $value
     * @param string $name
     * @param array $options
     * @param array $fields
     * @param array $pod
     *
     * @since 2.0.0
     */
    public function ui ( $id, $value, $name = null, $options = null, $fields = null, $pod = null ) {
        // link to file in new target
        // show thumbnail
    }

    /**
     * Handle file row output for uploaders
     *
     * @param array $attributes
     * @param int $limit
     * @param int $id
     * @param string $icon
     * @param string $name
     *
     * @since 2.0.0
     */
    public function markup ( $attributes, $limit = 1, $editable = true, $id = null, $icon = null, $name = null ) {
        ob_start();

        if ( empty ($id ) )
            $id = '{{id}}';

        if ( empty ( $icon ) )
            $icon = '{{icon}}';

        if ( empty ( $name ) )
            $name = '{{name}}';
?>
    <li class="pods-file hidden" id="pods-file-{{id}}">
        <input type="hidden" class="pods-file-id" name="<?php echo $attributes[ 'name' ]; ?>[{{id}}][id]" value="<?php echo $id; ?>" />

        <ul class="pods-file-meta media-item">
            <?php if ( 1 < $limit ) { ?>
                <li class="pods-file-col pods-file-handle">Handle</li>
            <?php } ?>

            <li class="pods-file-col pods-file-icon">
                <img class="pinkynail" src="<?php echo $icon; ?>" alt="Icon" />
            </li>

            <li class="pods-file-col pods-file-name">
                <?php
                    if ( $editable )
                        echo PodsForm::field( $attributes[ 'name' ] . '[' . $id . '][id]', $name, 'text' );
                    else
                        echo ( empty( $name ) ? '{{name}}' : $name );
                ?>
            </li>

            <li class="pods-file-col pods-file-delete">Delete</li>
        </ul>
    </li>
<?php
        return ob_get_clean();
    }
}
