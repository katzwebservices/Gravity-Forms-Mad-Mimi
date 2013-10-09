<?php

//------------------------------------------
if (class_exists("GFForms")) {

    class KWSGFMadMimiAddon extends KWSGFAddOn {
        protected $_version = "2.0.0";
        protected $_min_gravityforms_version = "1.7";
        protected $_slug = "madmimi";
        protected $_path = "gravity-forms-mad-mimi/madmimi.beta.php";
        protected $_full_path = __FILE__;
        protected $_title = "Gravity Forms MadMimi Add-On (Beta)";
        protected $_short_title = "Mad Mimi";
        protected $_service_name = "Mad Mimi";

        public function get_service_icon() {
            return '<img src="'.plugins_url( 'images/madmimi_wordpress_icon_32.png', $this->_full_path ).'" class="alignleft" style="margin-right:10px;" />';
        }

        public function plugin_settings_fields() {
            return array(
                array(
                    "title"  => sprintf(__("%s Account Information", 'kwsaddon'), $this->get_service_name()),
                    "fields" => array(
                        array(
                            'type'    => 'html',
                            'value'    => $this->get_service_signup_message(),
                            'dependency' => 'KWSGFMadMimiAddon::is_invalid_api',
                        ),
                        array(
                            'type'    => 'html',
                            'value'    => $this->invalid_api_message(),
                            'dependency' => 'KWSGFMadMimiAddon::is_invalid_api',
                        ),
                        array(
                            'type'    => 'html',
                            'value'    => $this->valid_api_message(),
                            'dependency' => 'KWSGFMadMimiAddon::is_valid_api',
                        ),
                        array(
                            "name"    => "email",
                            "label"   => __("Mad Mimi Account Email Address", "gravity-forms-madmimi"),
                            "type"    => "text",
                            "class"   => "medium",
                            'feedback_callback' => array(&$this, 'is_valid_api'),
                        ),
                        array(
                            "name"    => "api_key",
                            "label"   => __("API Key", "gravity-forms-madmimi"),
                            "type"    => "password",
                            "class"   => "medium",
                            'feedback_callback' => array(&$this, 'is_valid_api'),
                        ),
                        array(
                            "name"    => "debug",
                            "label"   => __("Debug Form Submissions", "gravity-forms-madmimi"),
                            "type"    => "checkbox",
                            'dependency' => 'KWSGFMadMimiAddon::is_valid_api',
                            "choices" => array(
                                array(
                                    'label' => 'Enable Debug',
                                    'name' => 'debug'
                                )
                            )
                        )
                    )
                )
            );
        }

        protected function feed_settings_fields_field_map() {
            return array(
                array(
                    'name' => 'email',
                    'required' => true,
                    'label' => __("Email")
                ),
                array(
                    'name' => 'first_name',
                    'required' => false,
                    'label' => __("Name (First)")
                ),
                array(
                    'name' => 'last_name',
                    'required' => false,
                    'label' => __("Name (Last)")
                ),
                array(
                    'name' => 'title',
                    'required' => false,
                    'label' => __("Title")
                ),
                array(
                    'name' => 'company',
                    'required' => false,
                    'label' => __("Company")
                ),
                array(
                    'name' => 'home_number',
                    'required' => false,
                    'label' => __("Home Phone")
                ),
                array(
                    'name' => 'cell_number',
                    'required' => false,
                    'label' => __("Cell Phone")
                ),
                array(
                    'name' => 'work_number',
                    'required' => false,
                    'label' => __("Work Phone")
                ),
                array(
                    'name' => 'fax',
                    'required' => false,
                    'label' => __("Fax")
                ),
                array(
                    'name' => 'address',
                    'required' => false,
                    'label' => __("Address (Street Address)")
                ),
                array(
                    'name' => 'address_2',
                    'required' => false,
                    'label' => __("Address (Address Line 2)")
                ),
                array(
                    'name' => 'city',
                    'required' => false,
                    'label' => __("Address (City)")
                ),
                array(
                    'name' => 'state',
                    'required' => false,
                    'label' => __("Address (State / Province)")
                ),
                array(
                    'name' => 'country',
                    'required' => false,
                    'label' => __("Address (Country)")
                ),
                array(
                    'name' => 'zip',
                    'required' => false,
                    'label' => __("Address (Zip / Postal Code)")
                ),
                array(
                    'name' => 'website',
                    'required' => false,
                    'label' => __('Website')
                ),
                array(
                    'name' => 'twitter',
                    'required' => false,
                    'label' => __('Twitter Username')
                ),
                array(
                    'name' => 'message',
                    'required' => false,
                    'label' => __('Message')
                ),
                array(
                    'name' => 'source_form',
                    'required' => false,
                    'label' => __('Source Form Name')
                )
            );
        }

        public function get_api() {

            if(!empty($this->_service_api) && is_a($this->_service_api, 'MadMimi_SuperClass')) {
                return $this->_service_api;
            }

            if(!class_exists("MadMimi")){
                require_once($this->get_base_path()."/lib/MadMimi.class.php");
            }
            if(!class_exists('MadMimi_SuperClass')) {
                require_once($this->get_base_path()."/lib/MadMimiSuperClass.class.php");
            }

            $email = $this->get_plugin_setting('email');
            $api_key = $this->get_plugin_setting('api_key');
            $debug = $this->get_plugin_setting('debug');

            $this->_service_api = new MadMimi_SuperClass($email, $api_key, $debug);

            return $this->_service_api;
        }

        /**
         * [feed_settings_service_lists description]
         * @todo Check what happens with one list.
         * @return array Array of lists to use in the feed
         */
        protected function feed_settings_service_lists() {

            $lists = $this->get_service_lists();
            $feed_lists = array();
            foreach ($lists as $list) {
                $feed_lists[] = array(
                    'name' => 'lists['.$list['id'].']',
                    'label' => esc_html( $list['name'] ),
                    'value' => $list['id']
                );
            }

            return $feed_lists;
        }


        /**
         * {@inheritDoc}
         */
        protected function get_service_lists() {

            if(isset($this->_service_lists)) {
                return $this->_service_lists;
            }

            if(!$api = $this->get_api()) { return false; }

            if(!$listsResponse = $api->Lists(true)) { return false; }

            $listsXML = @simplexml_load_string($listsResponse);  // Added @ 1.2.2

            if(is_object($listsXML)) {

                $lists = array();
                foreach($listsXML->list as $list) {
                    $listItem = array();
                    foreach($list->attributes() as $k => $v) {
                        $listItem[(string)$k] = (string)$v;
                    }
                    $lists[$listItem['id']] = $listItem;
                }

                $this->_service_lists = $lists;

            } else {
                $this->_service_lists = false;
            }

            return $this->_service_lists;
        }

        /**
         * Export the entry on submit.
         * @param  array $feed  Feed array
         * @param  array $entry Entry array
         * @param  array $form  Form array
         */
        public function process_feed( $feed, $entry, $form ) {

            self::log_debug( "Opt-in condition met; adding entry {$entry["id"]} to Mad Mimi" );

            try {
                $merge_vars = $this->get_merge_vars_from_entry($feed, $entry, $form);

                if(!isset($merge_vars['source_form']) && apply_filters('gf_madmimi_add_source', true) && isset($form['title'])) {
                    $merge_vars['source_form'] = esc_html($form['title']);
                }

                $api = $this->get_api();

                foreach($merge_vars as $key => $var) {
                    if(is_array($var)) {
                        $var = implode(', ', $var);
                    } else {
                        $var = GFCommon::trim_all($var);
                    }
                    if(empty($var) && $var !== '0') {
                        unset($merge_vars[$key]);
                    } else {
                        $merge_vars[$key] = $var;
                    }
                }

                $lists = $this->feed_get_active_lists($feed);

                // If there are multiple lists, add users to each list.
                foreach ((array)$lists as $list) {
                    $return = $api->AddMembership(
                        $list,
                        $merge_vars['email'],
                        $merge_vars,
                        true
                    );

                    $lastError = $api->getlastError();

                    // If it returns false, there was an error.
                    if(!$return) {
                        self::log_error( "There was an error adding {$entry['id']} to Mad Mimi list {$list}: {$lastError}");
                    } else {
                        // Otherwise, it was a success.
                        self::log_debug( "Entry {$entry['id']} was added to Mad Mimi list {$list}.");
                    }
                }
            } catch(Exception $e) {
                // Otherwise, it was a success.
                self::log_error( "Error: ".$e->getMessage());
            }

            return;
        }

    }

}