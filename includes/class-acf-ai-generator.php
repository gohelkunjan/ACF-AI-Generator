<?php
if (! defined('ABSPATH')) {
    exit;
}

class ACF_AIGenerator
{

    private $api_key;

    public function __construct()
    {
        $this->api_key = get_option('acf_ai_generator_api_key', '');
    }

    public function run()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'ACF AI Generator',
            'ACF AI Generator',
            'manage_options',
            'acf-ai-generator',
            array($this, 'settings_page'),
            'dashicons-admin-generic',
            80
        );
    }

    public function register_settings()
    {
        register_setting('acf_ai_generator_settings', 'acf_ai_generator_api_key');
    }

    public function settings_page()
    {
        // Handle import action
        $import_results = [];
        if (isset($_POST['import_acf_field_group']) && check_admin_referer('acf_ai_generator_import', 'acf_ai_generator_nonce')) {
            $json_data = stripslashes($_POST['acf_json_data']); // Get JSON from hidden field
            $decoded_json = json_decode($json_data, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $import_results[] = '<div class="error"><p><strong>Error:</strong> Invalid JSON: ' . esc_html(json_last_error_msg()) . '</p></div>';
            } elseif (! is_array($decoded_json)) {
                $import_results[] = '<div class="error"><p><strong>Error:</strong> JSON must be an array or object representing ACF field groups.</p></div>';
            } elseif (empty($decoded_json)) {
                $import_results[] = '<div class="error"><p><strong>Error:</strong> JSON is empty.</p></div>';
            } elseif (function_exists('acf_import_field_group')) {
                // Handle both single field group and array of field groups
                $field_groups = isset($decoded_json['key']) ? [$decoded_json] : $decoded_json;

                foreach ($field_groups as $field_group) {
                    if (! isset($field_group['key'], $field_group['title'])) {
                        $import_results[] = '<div class="error"><p><strong>Error:</strong> Invalid field group format: Missing key or title.</p></div>';
                        continue;
                    }
                    $result = acf_import_field_group($field_group);
                    if (is_wp_error($result)) {
                        $import_results[] = '<div class="error"><p><strong>Error:</strong> Failed to import field group: ' . esc_html($result->get_error_message()) . '</p></div>';
                    } else {
                        $import_results[] = '<div class="updated"><p><strong>Success:</strong> Field group "' . esc_html($field_group['title']) . '" imported successfully.</p></div>';
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
                settings_fields('acf_ai_generator_settings');
                do_settings_sections('acf_ai_generator_settings');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">OpenAI API Key</th>
                        <td><input type="password" name="acf_ai_generator_api_key" value="<?php echo esc_attr($this->api_key); ?>" size="50" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <hr>
            <h2>Generate ACF Code</h2>
            <div class="main-wrap">
                <div class="acf-code-generate-form">
                    <form method="post">
                        <textarea name="acf_prompt" rows="5" cols="100" placeholder="Describe your ACF field group..."></textarea>
                        <br><br>
                        <input type="submit" name="generate_acf_code" class="button button-primary" value="Generate Code">
                    </form>
                    <?php
                    if (isset($_POST['generate_acf_code'])) {
                        $prompt = sanitize_text_field($_POST['acf_prompt']);
                        $result = $this->generate_code($prompt);

                        echo '<h2>Generated Code:</h2>';

                        if (isset($result['status']) && $result['status'] === 'error') {
                            // Display error message
                            echo '<div class="error"><p><strong>Error:</strong> ' . esc_html($result['message']) . '</p></div>';
                            if (isset($result['raw_response'])) {
                                echo '<p><strong>Raw Response:</strong></p>';
                                echo '<pre>' . esc_html($result['raw_response']) . '</pre>';
                            }
                        } elseif (isset($result['status']) && $result['status'] === 'success') {
                            // Ensure data is an array of field groups
                            $json_data = is_array($result['data']) && ! isset($result['data']['key']) ? $result['data'] : [$result['data']];
                            $json_output = json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                            echo '<pre>' . esc_html($json_output) . '</pre>';

                            // Add import button
                            if (function_exists('acf_import_field_group')) {
                    ?>
                                <form method="post">
                                    <?php wp_nonce_field('acf_ai_generator_import', 'acf_ai_generator_nonce'); ?>
                                    <input type="hidden" name="acf_json_data" value="<?php echo esc_attr($json_output); ?>">
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
                        if (! empty($import_results)) {
                            foreach ($import_results as $message) {
                                echo $message;
                            }
                        }
                    }
                    ?>
                </div>
                <h2>Examples</h2>
                <div class="example-plaintext">
                    <p><strong>Home Page</strong><br>
                        Create a single ACF field group titled "Home Page Sections" with one flexible content field called 'page_sections'.<br>
                        It should contain three layouts:<br>
                        - Hero Banner: with image, heading, and subheading<br>
                        - Services: with repeater of service title and description<br>
                        - Portfolio: with gallery field
                    </p>
                    <hr>
                    <p><strong>Team Page</strong><br>
                        Create a single ACF field group titled "Team Page Sections" with one flexible content field called 'page_sections'.<br>
                        It should contain three layouts:<br>
                        - Team Intro: with heading, description, and background image<br>
                        - Team Members: with repeater of image, name, position, and bio<br>
                        - Call to Action: with heading, button text, and button link
                    </p>
                    <hr>
                    <p><strong>About Page</strong><br>
                        Create a single ACF field group titled "About Page Sections" with one flexible content field called 'page_sections'.<br>
                        It should contain three layouts:<br>
                        - Company Overview: with WYSIWYG editor and image<br>
                        - Mission & Vision: with two text fields (mission, vision)<br>
                        - Testimonials: with repeater of name, designation, photo, and testimonial (textarea)
                    </p>
                    <hr>
                    <p><strong>Contact Page</strong><br>
                        Create a single ACF field group titled "Contact Page Sections" with one flexible content field called 'page_sections'.<br>
                        It should contain three layouts:<br>
                        - Contact Info: with address (textarea), phone, and email fields<br>
                        - Contact Form: with form shortcode<br>
                        - Map Section: with Google Map field and heading
                    </p>
                    <hr>
                    <p><strong>FAQ Section</strong><br>
                        Create a single ACF field group titled "FAQ Section" with one repeater field called `faq_list`. Each row should have:<br>
                        - Question (Text)<br>
                        - Answer (Textarea)<br>
                    </p>
                    <hr>
                    <p><strong>Contact Information</strong><br>
                    Generate an ACF field group titled "Contact Information" that includes the following basic fields:<br>
                    1. Company Name (type: text)<br>
                    2. Email Address (type: email)<br>
                    3. Phone Number (type: text)<br>
                    4. Office Address (type: textarea)<br>
                    5. Website (type: url)<br>
                    </p>
                </div>
            </div>
        </div>
<?php
    }

    private function generate_code($prompt)
    {
        if (empty($this->api_key)) {
            return array(
                'status'  => 'error',
                'message' => 'OpenAI API key is not set.',
            );
        }

        // Enhanced system prompt to ensure JSON array output
        $system_prompt = 'You are an expert WordPress developer specializing in Advanced Custom Fields (ACF). Your task is to generate **valid JSON** code for ACF field groups, ready for use with the ACF plugin\'s import/export feature.
        Always respond with the field group(s) as a **JSON array**, even if only one group is generated (e.g., `[{"key": "group_abc123", "title": "Example", ...}]`). The JSON output must be **wrapped in a Markdown code block** using ` ```json ` at the beginning and ` ``` ` at the end.
        Guidelines:
        - All **field group keys** (`group_...`) and **field keys** (`field_...`) must be **unique**, using timestamps, random strings, or other methods to avoid collisions.
        - Include complete ACF-compatible structures: `key`, `title`, `fields`, `location`, `menu_order`, `position`, `style`, `label_placement`, `instruction_placement`, `hide_on_screen`, `active`, and `description`.
        - Support all ACF field types including:
        - **Basic**: `text`, `textarea`, `number`, `email`, `url`, `password`
        - **Content**: `image`, `file`, `wysiwyg`, `oembed`, `gallery`
        - **Choice**: `select`, `checkbox`, `radio`, `button_group`, `true_false`
        - **Relational**: `link`, `post_object`, `page_link`, `relationship`, `taxonomy`, `user`
        - **jQuery-based**: `google_map`, `date_picker`, `date_time_picker`, `time_picker`, `color_picker`
        - **Layout**: `group`, `repeater`, `flexible_content`, `clone`, `accordion`, `tab`
        - For nested fields like `repeater`, `group`, and `flexible_content`, include well-structured `sub_fields` or `layouts`, each with unique keys and necessary ACF properties.
        - Ensure generated JSON is **immediately usable** for importing via ACF\'s **Tools > Import Field Groups** interface.
        - Validate and structure the output strictly to match ACF\'s specifications for maximum compatibility in WordPress projects.
        You are powering an ACF plugin for developers who expect precision and production-ready output.
        ';

        $response = wp_remote_post('https://api.aimlapi.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => json_encode(array(
                'model'    => 'gpt-3.5-turbo',
                'messages' => array(
                    array('role' => 'system', 'content' => $system_prompt),
                    array('role' => 'user', 'content' => $prompt),
                ),
                'max_tokens'  => 1500,
                'temperature' => 0.3,
            )),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return array(
                'status'  => 'error',
                'message' => 'Request failed: ' . $response->get_error_message(),
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body) || ! isset($body['choices'][0]['message']['content'])) {
            return array(
                'status'  => 'error',
                'message' => 'No valid response from API.',
            );
        }

        $content = trim($body['choices'][0]['message']['content']);

        // Extract JSON from ```json ... ``` block if present
        if (preg_match('/```json\n([\s\S]*?)\n```/', $content, $matches)) {
            $json_content = $matches[1];
        } else {
            $json_content = $content; // Fallback to raw content
        }

        // Validate JSON
        $decoded_json = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'status'  => 'error',
                'message' => 'Invalid JSON response: ' . json_last_error_msg(),
                'raw_response' => $content,
            );
        }

        // Ensure JSON is an array of field groups
        $decoded_json = is_array($decoded_json) && ! isset($decoded_json['key']) ? $decoded_json : [$decoded_json];

        return array(
            'status' => 'success',
            'data'   => $decoded_json,
            'raw'    => json_encode($decoded_json, JSON_UNESCAPED_SLASHES),
        );
    }
}
?>