<?php
/**
 * Security Note: Blocks direct access to the plugin PHP files.
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class WordPress_ShareASale {

    /**
     * Static property to hold our singleton instance.
     *
     * @since 1.0.0
     * @var (boolean|object) $instance Description.
     */
    public static $instance = false;

    /**
     * Holds all of the plugin settings.
     *
     * @since 1.0.0
     * @access private
     * @var array $settings {
     *     Settings array.
     *
     *     @type array $settings general settings.
     *     @type string $page settings page.
     *     @type string $db_version current database version.
     *     @type array $tabs {
     *         Holds all of the setting pages.
     *
     *         @type string $settings settings page.
     *     }
     * }
     */
    private $settings = array(
        'shareasale_settings' => array(),
        'page'                => 'options-general.php',
        'db_version'          => '0.0.1',
        'tabs'                => array(
            'shareasale_settings' => 'Settings',
            'shareasale_reports'  => 'Reports',
        ),
    );

    /**
     * Returns an instance.
     *
     * If an instance exists, this returns it.  If not, it creates one and
     * retuns it.
     *
     * @since 1.0.0
     *
     * @return object
     */
    public static function get_instance() {

        if ( ! self::$instance ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Plugin initialization.
     *
     * Initializes the plugins functionality.
     *
     * @since 1.0.0
     *
     */
    public function __construct() {

        // Change pref page if network activated
        if ( is_plugin_active_for_network( plugin_basename( SHAREASALE_PLUGIN ) ) ) {
            $this->settings['page'] = 'settings.php';
        }

        // Load the plugin settings.
        $this->_load_settings();

        // Call the plugin WordPress action hooks.
        $this->_actions();

        // Call the plugin WordPress filters.
        $this->_filters();
    }

    /**
     * Load the settings / defaults.
     *
     * Load the settings from the database, and merge with the defaults where required.
     *
     * @since 1.0.0
     * @access private
     */
    private function _load_settings() {
        $default_settings =  array(
            'affiliate_id'         => '',
            'api_token'            => '',
            'secret_key'           => '',
            'caching'              => 1,
            'cache_time'           => 14400
        );

        // Merge and update new changes
        if ( isset( $_POST['shareasale_settings'] ) ) {
            $saved_settings =  $_POST['shareasale_settings'];
            if ( is_plugin_active_for_network( plugin_basename( SHAREASALE_PLUGIN ) ) ) {
                update_site_option( 'shareasale_settings', $saved_settings );
            } else {
                update_option( 'shareasale_settings', $saved_settings );
            }
        }

        // Retrieve the settings
        if ( is_plugin_active_for_network( plugin_basename( SHAREASALE_PLUGIN ) ) ) {
            $saved_settings = (array) get_site_option( 'shareasale_settings' );
        } else {
            $saved_settings = (array) get_option( 'shareasale_settings' );
        }

        $this->settings['shareasale_settings'] = array_merge(
            $default_settings,
            $saved_settings
        );
    }

    /**
     * WordPress actions.
     *
     * Adds WordPress actions using the plugin API.
     *
     * @since 1.0.0
     * @access private
     *
     * @link http://codex.wordpress.org/Plugin_API/Action_Reference
     */
    private function _actions() {
        if ( is_plugin_active_for_network( plugin_basename( SHAREASALE_PLUGIN ) ) ) {
            add_action( 'network_admin_menu', array( &$this, 'admin_menu' ) );
            add_action( 'network_admin_edit_shareasale', array( &$this, 'update_network_setting' ) );
        }
        add_action( 'admin_init', array( &$this, 'admin_init' ) );
        add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
        add_action( 'wp_enqueue_scripts', array( &$this, 'wp_enqueue_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueue_scripts' ) );
    }

    /**
     * WordPress filters.
     *
     * Adds WordPress filters.
     *
     * @since 1.0.0
     * @access private
     *
     * @link http://codex.wordpress.org/Function_Reference/add_filter
     */
    private function _filters() {
        add_filter( 'plugin_row_meta', array( &$this, 'plugin_row_meta' ), 10, 2 );
        if ( is_plugin_active_for_network( plugin_basename( SHAREASALE_PLUGIN ) ) ) {
            add_filter( 'network_admin_plugin_action_links_' . plugin_basename( SHAREASALE_PLUGIN ), array( &$this, 'plugin_action_links' ) );
        } else {
            add_filter( 'plugin_action_links_' . plugin_basename( SHAREASALE_PLUGIN ), array( &$this, 'plugin_action_links' ) );
        }
    }

    /**
     * Uses admin_menu.
     *
     * Used to add extra submenus and menu options to the admin panel's menu
     * structure.
     *
     * @since 1.0.0
     *
     * @link http://codex.wordpress.org/Plugin_API/Action_Reference/admin_menu
     *
     * @return void
     */
    public function admin_menu() {

      if ( is_plugin_active_for_network( plugin_basename( SHAREASALE_PLUGIN ) ) ) {
          $hook_suffix = add_submenu_page(
              'settings.php',
              __( 'ShareASale Settings', 'shareasale' ),
              __( 'ShareASale', 'shareasale' ),
              'manage_network',
              'shareasale',
              array( &$this, 'settings_page' )
          );
      } else {
        // Register plugin settings page.
        $hook_suffix = add_options_page(
            __( 'ShareASale Settings', 'shareasale' ),
            __( 'ShareASale', 'shareasale' ),
            'manage_options',
            'shareasale',
            array( &$this, 'settings_page' )
        );
      }

      // Load ShareASale settings from the database.
      add_action( "load-{$hook_suffix}", array( &$this, 'load_settings' ) );
    }

    /**
     * Admin Scripts
     *
     * Adds CSS and JS files to the admin pages.
     *
     * @since 1.0.0
     *
     * @return void | boolean
     */
    public function load_settings() {
        if ( $this->settings['page'] !== $GLOBALS['pagenow'] ) {
            return false;
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            wp_enqueue_style( 'shareasale-admin', plugins_url( 'build/css-dev/style.css', SHAREASALE_PLUGIN ) );
        } else {
            wp_enqueue_style( 'shareasale-admin', plugins_url( 'build/css/style.css', SHAREASALE_PLUGIN ) );
        }
    }

    /**
     * Renders a pager.
     *
     * @since 1.0.0
     * @access private
     *
     * @param int $num_pages Total number of pages.
     * @param string $tab Current page tab.
     * @param int $page Current page number.
     * @param int $total Total number of records
     */
    private function _pager( $limit = 10, $total_num, $page, $tab ) {
        $max_pages = 11;
        $num_pages = ceil( $total_num / $limit );
        $cnt       = 0;

        $start = 1;
        if ( $page > 5 ) {
            $start = ( $page - 4 );
        }

        if ( 1 != $page ) {
            if ( 2 != $page ) {
                $pre_html = '<li><a href="' . $this->_admin_url() . '?page=shareasale&tab=' . $tab . '&p=1"><i class="fa fa-angle-double-left"></i></a>';
            }
            $pre_html .= '<li><a href="' . $this->_admin_url() . '?page=shareasale&tab=' . $tab . '&p=' . ( $page - 1 ) . '"><i class="fa fa-angle-left"></i></a>';
        }

        echo '<ul class="plugin__pager">';
        if ( isset( $pre_html ) ) {
            echo $pre_html;
        }
        for ( $i = $start; $i <= $num_pages; $i ++ ) {
            $cnt ++;
            if ( $cnt >= $max_pages ) {
                break;
            }

            if ( $num_pages != $page ) {
                $post_html = '<li><a href="' . $this->_admin_url() . '?page=shareasale&tab=' . $tab . '&p=' . ( $page + 1 ) . '"><i class="fa fa-angle-right"></i></a>';
                if ( ( $page + 1 ) != $num_pages ) {
                    $post_html .= '<li><a href="' . $this->_admin_url() . '?page=shareasale&tab=' . $tab . '&p=1"><i class="fa fa-angle-double-right"></i></a>';
                }
            }

            $class = '';
            if ( $page == $i ) {
                $class = ' class="plugin__page-selected"';
            }
            echo '<li><a href="' . $this->_admin_url() . '?page=shareasale&tab=' . $tab . '&p=' . $i . '"' . $class . '>' . $i . '</a>';
        }

        if( isset( $post_html ) ) {
            echo $post_html;
        }
        echo '</ul>';
        ?>
        <div class="plugin__page-info">
            <?php echo __( 'Page ', 'shareasale' ) . number_format( $page, 0 ) . ' of ' . number_format( $num_pages, 0 ); ?>
            (<?php echo number_format( $total_num, 0 ) . __( ' total records found', 'shareasale' ); ?>)
        </div>
        <?php
    }

    /**
     * Returns the percent of 2 numbers.
     *
     * @since 1.0.0
     * @access private
     */
    private function _get_percent( $num1, $num2 ) {
        return number_format( ( $num1 / $num2 ) * 100, 2 );
    }

    /**
     * Uses admin_init.
     *
     * Triggered before any other hook when a user accesses the admin area.
     *
     * @since 1.0.0
     *
     * @link http://codex.wordpress.org/Plugin_API/Action_Reference/admin_init
     */
    public function admin_init() {
      $this->_register_settings();
    }

    /**
     * Add setting link to plugin.
     *
     * Applied to the list of links to display on the plugins page (beside the activate/deactivate links).
     *
     * @since 1.0.0
     *
     * @link http://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
     */
    public function plugin_action_links( $links ) {
        $link = array( '<a href="' . $this->_admin_url() . '?page=shareasale">' . __( 'Settings', 'shareasale' ) . '</a>' );

        return array_merge( $links, $link );
    }

    /**
     * Plugin meta links.
     *
     * Adds links to the plugins meta.
     *
     * @since 1.0.0
     *
     * @link http://codex.wordpress.org/Plugin_API/Filter_Reference/preprocess_comment
     */
    public function plugin_row_meta( $links, $file ) {
        if ( false !== strpos( $file, 'shareasale.php' ) ) {
          $links = array_merge( $links, array( '<a href="http://www.benmarshall.me/shareasale-plugin/">ShareASale</a>' ) );
          $links = array_merge( $links, array( '<a href="https://www.gittip.com/bmarshall511/">Donate</a>' ) );
        }

        return $links;
    }

    /**
     * Registers the settings.
     *
     * Appends the key to the plugin settings tabs array.
     *
     * @since 1.0.0
     * @access private
     */
    private function _register_settings() {
        register_setting( 'shareasale_settings', 'shareasale_settings' );

        add_settings_section( 'section_general', __( 'General Settings', 'shareasale' ), false, 'shareasale_settings' );

        add_settings_field( 'affiliate_id', __( 'Affiliate ID', 'shareasale' ), array( &$this, 'field_affiliate_id' ), 'shareasale_settings', 'section_general' );
        add_settings_field( 'api_token', __( 'API Token', 'shareasale' ), array( &$this, 'field_api_token' ), 'shareasale_settings', 'section_general' );
        add_settings_field( 'secret_key', __( 'Secret Key', 'shareasale' ), array( &$this, 'field_secret_key' ), 'shareasale_settings', 'section_general' );
        add_settings_field( 'caching', __( 'Caching', 'shareasale' ), array( &$this, 'field_caching' ), 'shareasale_settings', 'section_general' );
        add_settings_field( 'cache_time', __( 'Cache Time', 'shareasale' ), array( &$this, 'field_cache_time' ), 'shareasale_settings', 'section_general' );
    }

    /**
     * Cache time option.
     *
     * Field callback, renders a text input, note the name and value.
     *
     * @since 1.0.0
     */
    public function field_cache_time() {
        ?>
        <label for="affiliate_id">
            <input type="number" class="regular-text" name="shareasale_settings[cache_time]" value="<?php echo esc_attr( $this->settings['shareasale_settings']['cache_time'] ); ?>">
        <p class="description"><?php echo __( 'Enter the number of seconds to cache ShareASale API data.', 'shareasale' ); ?></p>
        </label>
        <?php
    }

    /**
     * Caching option.
     *
     * Field callback, renders radio inputs, note the name and value.
     *
     * @since 1.0.0
     */
    public function field_caching() {
        if ( ! isset( $this->settings['shareasale_settings']['caching'] ) ) {
            $this->settings['shareasale_settings']['caching'] = '0';
        }
        ?>
        <label for="caching">
            <input type="checkbox" id="caching" name="shareasale_settings[caching]" value="1" <?php if ( isset( $this->settings['shareasale_settings']['caching']) ): checked( $this->settings['shareasale_settings']['caching'] ); endif; ?> /> <?php echo __( 'API Caching', 'shareasale' ); ?>
        </label>

        <p class="description"><?php echo __( 'API report requests are limited to 200 per month. <b>It\'s highly recommended caching be enabled to avoid overage limits.</b>', 'shareasale' ); ?></p>
        <?php
    }

    /**
     * Affiliate ID option.
     *
     * Field callback, renders a text input, note the name and value.
     *
     * @since 1.0.0
     */
    public function field_affiliate_id() {
        ?>
        <label for="affiliate_id">
            <input type="text" class="regular-text" name="shareasale_settings[affiliate_id]" value="<?php echo esc_attr( $this->settings['shareasale_settings']['affiliate_id'] ); ?>">
        <p class="description"><?php echo __( 'Enter your ShareASale affiliate ID.', 'shareasale' ); ?></p>
        </label>
        <?php
    }

    /**
     * API token option.
     *
     * Field callback, renders a text input, note the name and value.
     *
     * @since 1.0.0
     */
    public function field_api_token() {
        ?>
        <label for="api_token">
          <input type="text" class="regular-text" name="shareasale_settings[api_token]" value="<?php echo esc_attr( $this->settings['shareasale_settings']['api_token'] ); ?>">
        <p class="description"><?php echo __( 'Enter your ShareASale API token.', 'shareasale' ); ?></p>
        </label>
        <?php
    }

     /**
     * Secret key option.
     *
     * Field callback, renders a text input, note the name and value.
     *
     * @since 1.0.0
     */
    public function field_secret_key() {
      ?>
      <label for="secret_key">
        <input type="text" class="regular-text" name="shareasale_settings[secret_key]" value="<?php echo esc_attr( $this->settings['shareasale_settings']['secret_key'] ); ?>">
      <p class="description"><?php echo __( 'Enter your ShareASale secret key.', 'shareasale' ); ?></p>
      </label>
      <?php
    }

  /**
   * Renders setting tabs.
   *
   * Walks through the object's tabs array and prints them one by one.
   * Provides the heading for the settings_page method.
   *
   * @since 1.0.0
   * @access private
   */
  private function _options_tabs() {
    $current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'settings';
    echo '<h2 class="nav-tab-wrapper">';
    foreach ( $this->settings['tabs'] as $key => $name ) {
      $active = $current_tab == $key ? 'nav-tab-active' : '';
      echo '<a class="nav-tab ' . $active . '" href="?page=shareasale&tab=' . $key . '">' . $name . '</a>';
    }
    echo '</h2>';
  }

  /**
   * Add plugin scripts.
   *
   * Adds the plugins JS files.
   *
   * @since 1.0.0
   *
   * @link http://codex.wordpress.org/Function_Reference/wp_enqueue_script
   */
  public function wp_enqueue_scripts() {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
      wp_register_script( 'shareasale', plugins_url( '/build/js-dev/shareasale.js' , SHAREASALE_PLUGIN ), array( 'jquery' ), '1.1.0', true );
    } else {
      wp_register_script( 'shareasale', plugins_url( '/build/js/shareasale.min.js' , SHAREASALE_PLUGIN ), array( 'jquery' ), '1.1.0', true );
    }
    //wp_localize_script( 'shareasale', 'shareasale', array( 'key' => $this->_get_key() ) );
    wp_enqueue_script( 'shareasale' );
  }


  /**
   * Add admin scripts.
   *
   * Adds the CSS & JS for the ShareASale settings page.
   *
   * @since 1.5.2
   *
   * @link http://codex.wordpress.org/Plugin_API/Action_Reference/admin_enqueue_scripts
   *
   * @param string $hook Used to target a specific admin page.
   * @return void
   */
  public function admin_enqueue_scripts( $hook ) {
    if ( 'settings_page_shareasale' != $hook ) {
          return;
      }

      // Create nonce for AJAX requests.
      $ajax_nonce = wp_create_nonce( 'shareasale' );

      // Register the ShareASale admin script.
      if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        wp_register_script(
          'shareasale-admin', plugin_dir_url( SHAREASALE_PLUGIN ) .
          'build/js-dev/shareasale-admin.js'
        );
      } else {
        wp_register_script(
          'shareasale-admin',
          plugin_dir_url( SHAREASALE_PLUGIN ) .
          'build/js/shareasale-admin.min.js'
        );
      }

      // Localize the script with the plugin data.
      $plugin_array = array( 'nonce' => $ajax_nonce );
      wp_localize_script( 'shareasale-admin', 'shareasale_admin', $plugin_array );

    // Enqueue the script.
    wp_enqueue_script( 'shareasale-admin' );
  }

  /**
   * Returns number of days since a date.
   *
   * @since 1.0.0
   * @access private
   *
   * @return int Number of days since the specified date.
   */
  private function _num_days( $date ) {
    $datediff = time() - strtotime( $date );

    return floor( $datediff / ( DAY_IN_SECONDS ) );
  }

  /**
   * Update network settings.
   *
   * Used when plugin is network activated to save settings.
   *
   * @since 1.0.0
   *
   * @link http://wordpress.stackexchange.com/questions/64968/settings-api-in-multisite-missing-update-message
   * @link http://benohead.com/wordpress-network-wide-plugin-settings/
   */
  public function update_network_setting() {
    update_site_option( 'settings', $_POST['settings'] );
    wp_redirect( add_query_arg(
      array(
        'page'    => 'shareasale',
        'updated' => 'true',
        ),
      network_admin_url( 'settings.php' )
    ) );
    exit;
  }

  /**
   * Return proper admin_url for settings page.
   *
   * @since 1.0.0
   *
   * @return string|void
   */
  private function _admin_url()
  {
    if ( is_plugin_active_for_network( plugin_basename( SHAREASALE_PLUGIN ) ) ) {
      $settings_url = network_admin_url( $this->settings['page'] );
    } else if ( home_url() != site_url() ) {
      $settings_url = home_url( '/wp-admin/' . $this->settings['page'] );
    } else {
      $settings_url = admin_url( $this->settings['page'] );
    }

    return $settings_url;
  }

  /**
     * Parses a XML string.
     *
     * @param string $xml the XML string
     *
     * @return array an array of the parsed Excel file.
     *
     * @access public
     * @since Method available since Release 1.0.0
     */
    public function parse_xml( $xml )
    {
        $xml = json_decode( json_encode( ( array ) simplexml_load_string( $xml ) ), 1 );

        return $xml;
    }

  /**
   * Plugin options page.
   *
   * Rendering goes here, checks for active tab and replaces key with the related
   * settings key. Uses the _options_tabs method to render the tabs.
   *
   * @since 1.0.0
   */
  public function settings_page()
  {
    $plugin = get_plugin_data( SHAREASALE_PLUGIN );
    $tab    = isset( $_GET['tab'] ) ? $_GET['tab'] : 'shareasale_settings';
    $page   = isset( $_GET['p'] ) ? $_GET['p'] : 1;
    $action = is_plugin_active_for_network( plugin_basename( SHAREASALE_PLUGIN ) ) ? 'edit.php?action=shareasale' : 'options.php';
    ?>
    <div class="wrap">
      <h2><?php echo __( 'ShareASale', 'shareasale' ); ?></h2>
      <?php $this->_options_tabs(); ?>
      <div class="plugin__row">
        <div class="plugin__right">
        <?php require_once( SHAREASALE_ROOT . 'inc/admin-sidebar.tpl.php' ); ?>
        </div>
        <div class="plugin__left">
        <?php
        switch ( $tab ) {
          case 'shareasale_settings':
            require_once( SHAREASALE_ROOT . 'inc/settings.tpl.php' );
          break;
          case 'shareasale_reports':
            $token_count = $this->shareasale_api( array( 'action' => 'apitokencount' ) );

            require_once( SHAREASALE_ROOT . 'inc/reports.tpl.php' );
          break;
        }
        ?>
        </div>
      </div>
    </div>
    <?php
  }

  /**
   * ShareASale API
   *
   * Perform queries to the ShareASale API (v1.4+)
   *
   * @since 1.0.0
   *
   * @link https://www.shareasale.com/a-apiManager.cfm
   */
  public function shareasale_api( $args ) {

    $result         = false;
    $affiliate_id   = $this->settings['shareasale_settings']['affiliate_id'];
    $api_token      = $this->settings['shareasale_settings']['api_token'];
    $api_version    = 1.8;
    $action         = isset( $args['action'] ) ? $args['action'] : 'traffic';
    $url            = "https://shareasale.com/x.cfm?affiliateId=$affiliate_id&token=$api_token&version=$api_version&action=$action&XMLFormat=1";

    switch ( $action ) {
      case 'traffic':
      case 'activity':
        $date_start = isset( $args['date_start'] ) ? $args['date_start'] : date( 'm/d/Y', strtotime( date( 'm/1/Y' ) ) );
        $date_end = isset( $args['date_end'] ) ? $args['date_end'] : date( 'm/d/Y', strtotime( date( 'm/' . date( 't' ) . '/Y' ) ) );

        $url .= "&dateStart=$date_start&dateEnd=$date_end";
      break;
      case 'paymentSummary':
        // @todo - Can't seem to get this one to work.
        $payment_date = isset( $args['payment_date'] ) ? $args['payment_date'] : date( 'm/d/Y', strtotime( 'now -1 day' ) );

        $url .= "&paymentDate=$payment_date";
      break;
    }

    $cache_string = $action . '-' . serialize( $args );
    $cache = new CacheBlocks( SHAREASALE_ROOT . '/cache/', $this->settings['shareasale_settings']['cache_time'] );
    if( ! $result = $cache->Load( $cache_string ) ) {

        $api_secret_key = $this->settings['shareasale_settings']['secret_key'];
        $timestamp      = gmdate( DATE_RFC1123 );
        $signature      = $api_token . ':' . $timestamp . ':' . $action . ':' . $api_secret_key;
        $signature_hash = hash( 'sha256', $signature );
        $headers        = array( "x-ShareASale-Date: $timestamp", "x-ShareASale-Authentication: $signature_hash" );

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        $result = curl_exec($ch);

        if ( $result ) {

            // Parse HTTP Body to determine result of request.
            if ( stripos( $result, 'Error Code ' ) ) {
                // Error occurred
                trigger_error( $result, E_USER_ERROR );
            }
            else {
                // Success
                $result = $this->parse_xml( $result );
            }
        }
        else {
            // Connection error
            trigger_error( curl_error( $ch ),E_USER_ERROR );
        }

        curl_close($ch);

        $cache->Save( $result, $cache_string );
    }

    return $result;
  }
}