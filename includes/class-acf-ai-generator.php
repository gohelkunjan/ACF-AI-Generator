<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ACF_AIGenerator {

    private $api_key;

    public function __construct() {
        $this->api_key = get_option( 'acf_ai_generator_api_key', '' );
    }

    public function run() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_admin_menu() {
        add_menu_page(
            'ACF AI Generator',
            'ACF AI Generator',
            'manage_options',
            'acf-ai-generator',
            array( $this, 'settings_page' ),
            'dashicons-admin-generic',
            80
        );
    }

    public function register_settings() {
        register_setting( 'acf_ai_generator_settings', 'acf_ai_generator_api_key' );
    }

    public function settings_page() {
        // Handle import action
        $import_results = [];
        if ( isset( $_POST['import_acf_field_group'] ) && check_admin_referer( 'acf_ai_generator_import', 'acf_ai_generator_nonce' ) ) {
            $json_data = stripslashes( $_POST['acf_json_data'] ); // Get JSON from hidden field
            $decoded_json = json_decode( $json_data, true );

            if ( json_last_error() !== JSON_ERROR_NONE ) {
                $import_results[] = '<div class="error"><p><strong>Error:</strong> Invalid JSON: ' . esc_html( json_last_error_msg() ) . '</p></div>';
            } elseif ( ! is_array( $decoded_json ) ) {
                $import_results[] = '<div class="error"><p><strong>Error:</strong> JSON must be an array or object representing ACF field groups.</p></div>';
            } elseif ( empty( $decoded_json ) ) {
                $import_results[] = '<div class="error"><p><strong>Error:</strong> JSON is empty.</p></div>';
            } elseif ( function_exists( 'acf_import_field_group' ) ) {
                // Handle both single field group and array of field groups
                $field_groups = isset( $decoded_json['key'] ) ? [ $decoded_json ] : $decoded_json;

                foreach ( $field_groups as $field_group ) {
                    if ( ! isset( $field_group['key'], $field_group['title'] ) ) {
                        $import_results[] = '<div class="error"><p><strong>Error:</strong> Invalid field group format: Missing key or title.</p></div>';
                        continue;
                    }
                    $result = acf_import_field_group( $field_group );
                    if ( is_wp_error( $result ) ) {
                        $import_results[] = '<div class="error"><p><strong>Error:</strong> Failed to import field group: ' . esc_html( $result->get_error_message() ) . '</p></div>';
                    } else {
                        $import_results[] = '<div class="updated"><p><strong>Success:</strong> Field group "' . esc_html( $field_group['title'] ) . '" imported successfully.</p></div>';
                    }
                }
            } else {
                $import_results[] = '<div class="error"><p><strong>Error:</strong> ACF import function not available. Ensure ACF is active.</p></div>';
            }
        }

        ?>
        <div class="wrap">
            <h1>ACF AI Generator</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'acf_ai_generator_settings' );
                do_settings_sections( 'acf_ai_generator_settings' );
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">OpenAI API Key</th>
                        <td><input type="password" name="acf_ai_generator_api_key" value="<?php echo esc_attr( $this->api_key ); ?>" size="50" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <hr>
            <h2>Generate ACF Code</h2>
            <p><strong>Example</strong><br>
            Home Page Flexible Content<br>
            1. Hero Banner<br>
            Repeater: Heading, Image, Read More Button<br>
            2. Services<br>
            Section Heading<br>
            Repeater: Title, Image, Read More Link<br>
            3. Portfolio<br>
            Section Heading<br>
            Repeater: Image, Title, Website Link
            </p>
            <form method="post">
                <textarea name="acf_prompt" rows="5" cols="100" placeholder="Describe your ACF field group..."></textarea>
                <br><br>
                <input type="submit" name="generate_acf_code" class="button button-primary" value="Generate Code">
            </form>
            <?php
            if ( isset( $_POST['generate_acf_code'] ) ) {
                $prompt = sanitize_text_field( $_POST['acf_prompt'] );
                $result = $this->generate_code( $prompt );

                echo '<h2>Generated Code:</h2>';

                if ( isset( $result['status'] ) && $result['status'] === 'error' ) {
                    // Display error message
                    echo '<div class="error"><p><strong>Error:</strong> ' . esc_html( $result['message'] ) . '</p></div>';
                    if ( isset( $result['raw_response'] ) ) {
                        echo '<p><strong>Raw Response:</strong></p>';
                        echo '<pre>' . esc_html( $result['raw_response'] ) . '</pre>';
                    }
                } elseif ( isset( $result['status'] ) && $result['status'] === 'success' ) {
                    // Ensure data is an array of field groups
                    $json_data = is_array( $result['data'] ) && ! isset( $result['data']['key'] ) ? $result['data'] : [ $result['data'] ];
                    $json_output = json_encode( $json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
                    echo '<pre>' . esc_html( $json_output ) . '</pre>';

                    // Add import button
                    if ( function_exists( 'acf_import_field_group' ) ) {
                        ?>
                        <form method="post">
                            <?php wp_nonce_field( 'acf_ai_generator_import', 'acf_ai_generator_nonce' ); ?>
                            <input type="hidden" name="acf_json_data" value="<?php echo esc_attr( $json_output ); ?>">
                            <input type="submit" name="import_acf_field_group" class="button button-secondary" value="Import with ACF">
                        </form>
                        <?php
                    } else {
                        echo '<div class="error"><p><strong>Error:</strong> ACF plugin is not active or import function is unavailable.</p></div>';
                    }
                } else {
                    echo '<div class="error"><p><strong>Error:</strong> Unexpected response format.</p></div>';
                }

                // Display import results if any
                if ( ! empty( $import_results ) ) {
                    foreach ( $import_results as $message ) {
                        echo $message;
                    }
                }
            }
            ?>
        </div>
        <?php
    }

    private function generate_code( $prompt ) {
        if ( empty( $this->api_key ) ) {
            return array(
                'status'  => 'error',
                'message' => 'OpenAI API key is not set.',
            );
        }

        // Enhanced system prompt to ensure JSON array output
        $system_prompt = 'You are an expert WordPress developer specializing in Advanced Custom Fields (ACF). Always respond with valid JSON code for ACF field groups, wrapped in a ```json\n...\n``` block. The JSON must be an array of field group objects, even if only one field group is generated (e.g., [{ \"key\": \"group_123\", \"title\": \"Example\", ... }]). Ensure the JSON is complete, properly formatted, and ready for ACF import/export. Each field group and its fields must have **unique keys** (e.g., \"group_123\", \"field_123\"), generated dynamically to avoid conflicts (e.g., using timestamps, random strings, or incremental IDs). Support all ACF field types, including but not limited to: Basic (text, textarea, number, email, url, password), Content (image, file, wysiwyg, oembed, gallery), Choice (select, checkbox, radio, button_group, true_false), Relational (link, post_object, page_link, relationship, taxonomy, user), jQuery (google_map, date_picker, date_time_picker, time_picker, color_picker), Layout (group, repeater, flexible_content, clone, accordion, tab). For complex fields like Repeater and Flexible Content, include properly structured sub_fields or layouts with unique keys and all not require properties. Ensure location rules, conditional logic, and other ACF settings are included as specified in the prompt. Verify that the JSON adheres to ACF\'s import/export standards and includes all necessary properties (e.g., \"key\", \"title\", \"fields\", \"location\", \"menu_order\", \"position\", \"style\", \"label_placement\", \"instruction_placement\", \"hide_on_screen\", \"active\", \"description\").';

        $response = wp_remote_post( 'https://api.aimlapi.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => json_encode( array(
                'model'    => 'gpt-3.5-turbo',
                'messages' => array(
                    array( 'role' => 'system', 'content' => $system_prompt ),
                    array( 'role' => 'user', 'content' => $prompt ),
                ),
                'max_tokens'  => 1500,
                'temperature' => 0.3,
            ) ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'status'  => 'error',
                'message' => 'Request failed: ' . $response->get_error_message(),
            );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body ) || ! isset( $body['choices'][0]['message']['content'] ) ) {
            return array(
                'status'  => 'error',
                'message' => 'No valid response from API.',
            );
        }

        $content = trim( $body['choices'][0]['message']['content'] );

        // Extract JSON from ```json ... ``` block if present
        if ( preg_match( '/```json\n([\s\S]*?)\n```/', $content, $matches ) ) {
            $json_content = $matches[1];
        } else {
            $json_content = $content; // Fallback to raw content
        }

        // Validate JSON
        $decoded_json = json_decode( $json_content, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return array(
                'status'  => 'error',
                'message' => 'Invalid JSON response: ' . json_last_error_msg(),
                'raw_response' => $content,
            );
        }

        // Ensure JSON is an array of field groups
        $decoded_json = is_array( $decoded_json ) && ! isset( $decoded_json['key'] ) ? $decoded_json : [ $decoded_json ];

        return array(
            'status' => 'success',
            'data'   => $decoded_json,
            'raw'    => json_encode( $decoded_json, JSON_UNESCAPED_SLASHES ),
        );
    }
}
?>