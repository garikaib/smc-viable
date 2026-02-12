<?php
/**
 * Shop Custom Post Types Manager.
 *
 * @package SMC\Viable
 */

declare(strict_types=1);

namespace SMC\Viable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Shop_CPT
 */
class Shop_CPT {

	/**
	 * Init Hooks.
	 */
	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register_product_cpt' ] );
		add_action( 'init', [ __CLASS__, 'register_order_cpt' ] );
		add_action( 'init', [ __CLASS__, 'register_lesson_cpt' ] );
        add_action( 'init', [ __CLASS__, 'register_shop_meta' ] );
        add_action( 'add_meta_boxes', [ __CLASS__, 'add_product_meta_boxes' ] );
        add_action( 'save_post', [ __CLASS__, 'save_product_meta' ] );
        add_action( 'updated_post_meta', [ __CLASS__, 'auto_create_course_from_product' ], 10, 4 );
        add_action( 'added_post_meta', [ __CLASS__, 'auto_create_course_from_product' ], 10, 4 );
        
        // Deletion Sync
        add_action( 'trashed_post', [ __CLASS__, 'sync_trash_linked_training' ] );
        add_action( 'untrashed_post', [ __CLASS__, 'sync_untrash_linked_training' ] );
        add_action( 'before_delete_post', [ __CLASS__, 'sync_delete_linked_training' ] );
	}

	/**
	 * Register Product CPT.
	 */
	public static function register_product_cpt(): void {
		$labels = [
			'name'                  => _x( 'Products', 'Post Type General Name', 'smc-viable' ),
			'singular_name'         => _x( 'Product', 'Post Type Singular Name', 'smc-viable' ),
			'menu_name'             => __( 'Products', 'smc-viable' ),
            'add_new'               => __( 'Add New Product', 'smc-viable' ),
            'edit_item'             => __( 'Edit Product', 'smc-viable' ),
		];

		$args = [
			'label'                 => __( 'Product', 'smc-viable' ),
			'description'           => __( 'SMC Shop Products', 'smc-viable' ),
			'labels'                => $labels,
			'supports'              => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => true, // Visible in admin menu for easier management
			'capability_type'       => 'post',
			'show_in_rest'          => true,
            'map_meta_cap'          => true,
		];

		register_post_type( 'smc_product', $args );
	}

	/**
	 * Register Order CPT.
	 */
	public static function register_order_cpt(): void {
		$labels = [
			'name'                  => _x( 'Orders', 'Post Type General Name', 'smc-viable' ),
			'singular_name'         => _x( 'Order', 'Post Type Singular Name', 'smc-viable' ),
			'menu_name'             => __( 'Orders', 'smc-viable' ),
            'search_items'          => __( 'Search Orders', 'smc-viable' ),
		];

		$args = [
			'label'                 => __( 'Order', 'smc-viable' ),
			'description'           => __( 'SMC Shop Orders', 'smc-viable' ),
			'labels'                => $labels,
			'supports'              => [ 'title', 'custom-fields' ], // Title is Order ID
			'public'                => false,
			'show_ui'               => true,
			'show_in_menu'          => true, // Visible in admin menu
            'capabilities'          => [
                'create_posts' => 'do_not_allow', // Orders are created programmatically
            ],
            'map_meta_cap'          => true,
			'capability_type'       => 'post',
			'show_in_rest'          => true,
		];

		register_post_type( 'smc_order', $args );
	}

	/**
	 * Register Lesson CPT.
	 */
	public static function register_lesson_cpt(): void {
		$labels = [
			'name'                  => _x( 'Lessons', 'Post Type General Name', 'smc-viable' ),
			'singular_name'         => _x( 'Lesson', 'Post Type Singular Name', 'smc-viable' ),
			'menu_name'             => __( 'Lessons', 'smc-viable' ),
            'add_new'               => __( 'Add New Lesson', 'smc-viable' ),
            'edit_item'             => __( 'Edit Lesson', 'smc-viable' ),
		];

		$args = [
			'label'                 => __( 'Lesson', 'smc-viable' ),
			'description'           => __( 'Course Lessons', 'smc-viable' ),
			'labels'                => $labels,
			'supports'              => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => true, // Visible in admin for now
			'capability_type'       => 'post',
			'show_in_rest'          => true,
            'map_meta_cap'          => true,
		];

		register_post_type( 'smc_lesson', $args );
	}

    /**
     * Register Meta Fields.
     */
    public static function register_shop_meta(): void {
        // Product Meta
        register_post_meta( 'smc_product', '_price', [
            'type'         => 'number',
            'single'       => true,
            'show_in_rest' => true,
        ] );

        register_post_meta( 'smc_product', '_product_type', [
            'type'         => 'string', // 'plan', 'course', 'service'
            'single'       => true,
            'show_in_rest' => true,
        ] );
        
        // If type is 'plan', which level does it grant?
        register_post_meta( 'smc_product', '_plan_level', [
            'type'         => 'string', // 'basic', 'standard'
            'single'       => true,
            'show_in_rest' => true,
        ] );

        // If type is 'single', what does it unlock? (e.g., specific Training ID)
         register_post_meta( 'smc_product', '_linked_training_id', [
            'type'         => 'integer', 
            'single'       => true,
            'show_in_rest' => true,
        ] );

        // Link product to a course (for standalone purchase enrollment)
        register_post_meta( 'smc_product', '_linked_course_id', [
             'type'         => 'integer', // Kept for BC or generic linkage, but prefer _linked_training_id
             'single'       => true,
             'show_in_rest' => true,
         ] );

        // Rich Product Data
        register_post_meta( 'smc_product', '_long_description', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
        ] );

        register_post_meta( 'smc_product', '_features', [
            'type'         => 'array',
            'single'       => true,
            'show_in_rest' => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [ 'type' => 'string' ],
                ],
            ],
        ] );

        register_post_meta( 'smc_product', '_course_average_rating', [
            'type'         => 'number',
            'single'       => true,
            'show_in_rest' => true,
        ] );

        // Lesson Meta
        register_post_meta( 'smc_lesson', '_lesson_type', [
            'type'         => 'string', // 'video', 'text', 'quiz', 'assignment'
            'single'       => true,
            'show_in_rest' => true,
        ] );

        register_post_meta( 'smc_lesson', '_lesson_video_url', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
        ] );
        
        register_post_meta( 'smc_lesson', '_lesson_quiz_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
        ] );

         register_post_meta( 'smc_lesson', '_lesson_duration', [ // Minutes
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
        ] );

        register_post_meta( 'smc_lesson', '_parent_course_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
        ] );

        // Order Meta
        register_post_meta( 'smc_order', '_customer_id', [
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
        ] );

        register_post_meta( 'smc_order', '_order_total', [
            'type'         => 'number',
            'single'       => true,
            'show_in_rest' => true,
        ] );

        register_post_meta( 'smc_order', '_order_status', [
            'type'         => 'string', // 'pending', 'completed', 'failed'
            'single'       => true,
            'show_in_rest' => true,
        ] );
        
        register_post_meta( 'smc_order', '_order_items', [
            'type'         => 'object', // Array of items
            'single'       => true,
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [ 'type' => 'integer' ],
                            'price'      => [ 'type' => 'number' ],
                            'name'       => [ 'type' => 'string' ],
                        ],
                    ],
                ],
            ],
        ] );
    }

    /**
     * Auto Create Course from Product.
     * Hooks into updated_post_meta / added_post_meta.
     *
     * @param int    $meta_id    Meta ID.
     * @param int    $object_id  Post ID.
     * @param string $meta_key   Meta Key.
     * @param mixed  $meta_value Meta Value.
     */
    public static function auto_create_course_from_product( $meta_id, $object_id, $meta_key, $meta_value ) {
        if ( '_product_type' !== $meta_key ) {
            return;
        }

        // Only act if type is 'course'
        // meta_value might be passed as array if multiple? Usually string.
        if ( 'course' !== $meta_value ) {
            return;
        }

        $product = get_post( $object_id );
        if ( ! $product || 'smc_product' !== $product->post_type ) {
            return;
        }

        // Check if already linked
        $linked_id = get_post_meta( $object_id, '_linked_training_id', true );
        if ( $linked_id ) {
            // Validate if valid
            if ( get_post( $linked_id ) ) {
                return; // Already linked
            }
        }

        // Create Training
        // remove_action to prevent recursion if we sync back? We aren't syncing type back.
        
        $training_id = wp_insert_post( [
            'post_title'   => $product->post_title,
            'post_content' => $product->post_content, // Copy description
            'post_type'    => 'smc_training', // The consolidated CPT
            'post_status'  => 'publish', // Or match product status? Let's pubish for visibility.
        ] );

        if ( is_wp_error( $training_id ) ) {
            return;
        }

        // Set Meta on Training
        update_post_meta( $training_id, '_access_type', 'standalone' );
        update_post_meta( $training_id, '_plan_level', 'free' ); // Default
        update_post_meta( $training_id, '_smc_access_modes', [ 'standalone' ] );
        update_post_meta( $training_id, '_smc_allowed_plans', [] );
        wp_set_post_terms( $training_id, [ 'standalone' ], 'smc_access_mode', false );
        
        // Linkage
        update_post_meta( $object_id, '_linked_training_id', $training_id );
        update_post_meta( $training_id, '_linked_product_id', $object_id );

        // Sync Thumbnail if exists
        $thumb_id = get_post_thumbnail_id( $object_id );
        if ( $thumb_id ) {
            set_post_thumbnail( $training_id, $thumb_id );
        }
    }

    /**
     * Add Meta Boxes.
     */
    public static function add_product_meta_boxes(): void {
        add_meta_box(
            'smc_product_settings',
            __( 'Product Settings', 'smc-viable' ),
            [ __CLASS__, 'render_product_settings_meta_box' ],
            'smc_product',
            'normal',
            'high',
            [
                '__block_editor_compatible_meta_box' => true,
            ]
        );
    }

    /**
     * Render Meta Box.
     */
    public static function render_product_settings_meta_box( $post ): void {
        wp_nonce_field( 'smc_product_meta_box', 'smc_product_meta_box_nonce' );

        $price = get_post_meta( $post->ID, '_price', true );
        $type = get_post_meta( $post->ID, '_product_type', true ) ?: 'plan';
        $plan_level = get_post_meta( $post->ID, '_plan_level', true ) ?: 'free';
        $training_id = get_post_meta( $post->ID, '_linked_training_id', true );

        ?>
        <div class="smc-product-meta-field">
            <p><strong><?php _e( 'Product Price ($)', 'smc-viable' ); ?></strong></p>
            <input type="number" name="smc_product_price" value="<?php echo esc_attr( $price ); ?>" step="0.01" class="widefat" />
        </div>

        <div class="smc-product-meta-field">
            <p><strong><?php _e( 'Product Type', 'smc-viable' ); ?></strong></p>
            <select name="smc_product_type" id="smc_product_type" class="widefat">
                <option value="plan" <?php selected( $type, 'plan' ); ?>><?php _e( 'Membership Plan', 'smc-viable' ); ?></option>
                <option value="course" <?php selected( $type, 'course' ); ?>><?php _e( 'Course Module', 'smc-viable' ); ?></option>
                <option value="service" <?php selected( $type, 'service' ); ?>><?php _e( 'Service / One-on-One', 'smc-viable' ); ?></option>
            </select>
        </div>

        <div class="smc-product-meta-field" id="plan_level_field" style="<?php echo $type === 'plan' ? '' : 'display:none;'; ?>">
            <p><strong><?php _e( 'Plan Level', 'smc-viable' ); ?></strong></p>
            <select name="smc_product_plan_level" class="widefat">
                <?php foreach ( Plan_Tiers::get_level_labels() as $slug => $label ) : ?>
                    <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $plan_level, $slug ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="smc-product-meta-field" id="training_link_field" style="<?php echo $type === 'course' ? '' : 'display:none;'; ?>">
            <p><strong><?php _e( 'Linked Training / Course', 'smc-viable' ); ?></strong></p>
            <?php
            $trainings = get_posts( [ 'post_type' => 'smc_training', 'posts_per_page' => -1 ] );
            ?>
            <select name="smc_product_training_id" class="widefat">
                <option value=""><?php _e( 'None (Auto-create if saved)', 'smc-viable' ); ?></option>
                <?php foreach ( $trainings as $t ) : ?>
                    <option value="<?php echo $t->ID; ?>" <?php selected( $training_id, $t->ID ); ?>><?php echo esc_html( $t->post_title ); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php _e( 'Setting this to "None" and saving as "Course Module" will auto-create a new training module.', 'smc-viable' ); ?></p>
        </div>

        <script>
            document.getElementById('smc_product_type').addEventListener('change', function() {
                document.getElementById('plan_level_field').style.display = this.value === 'plan' ? 'block' : 'none';
                document.getElementById('training_link_field').style.display = this.value === 'course' ? 'block' : 'none';
            });
        </script>
        <?php
    }

    /**
     * Save Meta Box Data.
     */
    public static function save_product_meta( $post_id ): void {
        if ( ! isset( $_POST['smc_product_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['smc_product_meta_box_nonce'], 'smc_product_meta_box' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( isset( $_POST['smc_product_price'] ) ) {
            update_post_meta( $post_id, '_price', sanitize_text_field( $_POST['smc_product_price'] ) );
        }

        $product_type = '';
        if ( isset( $_POST['smc_product_type'] ) ) {
            $product_type = sanitize_key( (string) $_POST['smc_product_type'] );
            update_post_meta( $post_id, '_product_type', $product_type );
        }

        if ( 'plan' === $product_type && isset( $_POST['smc_product_plan_level'] ) ) {
            $plan_level = Plan_Tiers::normalize_or_default( (string) $_POST['smc_product_plan_level'], 'free' );
            update_post_meta( $post_id, '_plan_level', $plan_level );
        } else {
            delete_post_meta( $post_id, '_plan_level' );
        }

        if ( isset( $_POST['smc_product_training_id'] ) ) {
            update_post_meta( $post_id, '_linked_training_id', sanitize_text_field( $_POST['smc_product_training_id'] ) );
            if ( ! empty( $_POST['smc_product_training_id'] ) ) {
                update_post_meta( (int) $_POST['smc_product_training_id'], '_linked_product_id', $post_id );
            }
        }
    }

    /**
     * Sync Trash.
     */
    public static function sync_trash_linked_training( $post_id ): void {
        if ( get_post_type( $post_id ) !== 'smc_product' ) return;
        $training_id = get_post_meta( $post_id, '_linked_training_id', true );
        if ( $training_id && get_post( $training_id ) ) {
            wp_trash_post( (int) $training_id );
        }
    }

    /**
     * Sync Untrash.
     */
    public static function sync_untrash_linked_training( $post_id ): void {
        if ( get_post_type( $post_id ) !== 'smc_product' ) return;
        $training_id = get_post_meta( $post_id, '_linked_training_id', true );
        if ( $training_id && get_post( $training_id ) ) {
            wp_untrash_post( (int) $training_id );
        }
    }

    /**
     * Sync Permanent Delete.
     */
    public static function sync_delete_linked_training( $post_id ): void {
        if ( get_post_type( $post_id ) !== 'smc_product' ) return;
        $training_id = get_post_meta( $post_id, '_linked_training_id', true );
        if ( $training_id && get_post( $training_id ) ) {
            wp_delete_post( (int) $training_id, true );
        }
    }
}
