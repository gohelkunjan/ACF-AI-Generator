<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ACF_AIGenerator_Snippets {

    public function run() {
        add_action( 'admin_menu', array( $this, 'add_snippets_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function add_snippets_menu() {
        add_submenu_page(
            'acf-ai-generator',
            'ACF Template Snippets',
            'Template Snippets',
            'manage_options',
            'acf-ai-generator-snippets',
            array( $this, 'snippets_page' ),
            10
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== 'acf-ai-generator_page_acf-ai-generator-snippets' ) {
            return;
        }
        // Enqueue Dashicons for the copy icon
        wp_enqueue_style( 'dashicons' );
        // Enqueue custom script for copy-to-clipboard
        wp_add_inline_script( 'jquery', "
            jQuery(document).ready(function($) {
                $('.acf-ai-copy-code').on('click', function(e) {
                    e.preventDefault();
                    var textarea = $(this).siblings('textarea');
                    textarea.select();
                    try {
                        document.execCommand('copy');
                        alert('Code copied to clipboard!');
                    } catch (err) {
                        alert('Failed to copy code. Please select and copy manually.');
                    }
                });
            });
        " );
    }

    public function snippets_page() {
        // Handle form submission for generating template code
        $generated_php = '';
        $error_message = '';
        if ( isset( $_POST['generate_acf_snippets'] ) && check_admin_referer( 'acf_ai_generator_snippets', 'acf_ai_generator_snippets_nonce' ) ) {
            if ( empty( $_POST['selected_field_groups'] ) || ! is_array( $_POST['selected_field_groups'] ) ) {
                $error_message = '<div class="error"><p><strong>Error:</strong> Please select at least one field group.</p></div>';
            } elseif ( ! function_exists( 'acf_get_field_groups' ) ) {
                $error_message = '<div class="error"><p><strong>Error:</strong> ACF plugin is not active.</p></div>';
            } else {
                $selected_groups = array_map( 'sanitize_text_field', $_POST['selected_field_groups'] );
                $generated_php = $this->generate_template_snippets( $selected_groups );
                if ( empty( $generated_php ) ) {
                    $error_message = '<div class="error"><p><strong>Error:</strong> Failed to generate template code for selected field groups.</p></div>';
                }
            }
        }

        ?>
        <div class="wrap">
            <h1>ACF Template Snippets</h1>
            <p>Select ACF field groups to generate front-end PHP template code for rendering their fields.</p>
            
            <?php if ( $error_message ) echo $error_message; ?>

            <form method="post">
                <?php wp_nonce_field( 'acf_ai_generator_snippets', 'acf_ai_generator_snippets_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Select Field Groups</th>
                        <td>
                            <?php $this->render_field_group_selector(); ?>
                        </td>
                    </tr>
                </table>
                <input type="submit" name="generate_acf_snippets" class="button button-primary" value="Generate Template Code">
            </form>

            <?php if ( $generated_php ) : ?>
                <h2>Generated Template Code</h2>
                <p>Copy the PHP code below to your theme templates (e.g., <code>single.php</code>, <code>page.php</code>) to render the ACF fields.</p>
                <div style="position: relative;">
                    <textarea rows="20" cols="100" readonly><?php echo esc_textarea( $generated_php ); ?></textarea>
                    <a href="#" class="acf-ai-copy-code" title="Copy to Clipboard" style="position: absolute; font-size: 24px; text-decoration: none;">
                        <span class="dashicons dashicons-clipboard"></span>
                    </a>
                </div>
                <p>
                    <a href="data:text/plain;charset=utf-8,<?php echo rawurlencode( $generated_php ); ?>" 
                       download="acf_template_snippets.php" 
                       class="button button-secondary">Download PHP File</a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_field_group_selector() {
        if ( ! function_exists( 'acf_get_field_groups' ) ) {
            echo '<p><strong>Error:</strong> ACF plugin is not active.</p>';
            return;
        }

        $field_groups = acf_get_field_groups();
        if ( empty( $field_groups ) ) {
            echo '<p>No ACF field groups found.</p>';
            return;
        }

        echo '<select name="selected_field_groups[]" multiple size="10" style="width: 100%;">';
        foreach ( $field_groups as $group ) {
            printf(
                '<option value="%s">%s (%s)</option>',
                esc_attr( $group['key'] ),
                esc_html( $group['title'] ),
                esc_html( $group['key'] )
            );
        }
        echo '</select>';
        echo '<p class="description">Hold Ctrl (Windows) or Cmd (Mac) to select multiple field groups.</p>';
    }

    private function generate_template_snippets( $group_keys ) {
        $php_code = "<?php\n";
        $php_code .= "// ACF Template Snippets for Selected Field Groups\n";
        $php_code .= "// Generated on " . date( 'Y-m-d H:i:s' ) . "\n\n";

        foreach ( $group_keys as $group_key ) {
            $group = acf_get_field_group( $group_key );
            if ( ! $group ) {
                continue;
            }

            // Get fields for the group
            $fields = acf_get_fields( $group_key );
            if ( $fields === false ) {
                $fields = [];
            }

            // Ensure unique keys
            $group['key'] = $this->ensure_unique_key( $group['key'], 'group' );
            foreach ( $fields as &$field ) {
                $field['key'] = $this->ensure_unique_key( $field['key'], 'field' );
                $field = $this->process_nested_fields( $field );
            }

            // Generate template code for the field group
            $php_code .= "// Template for Field Group: {$group['title']} ({$group['key']})\n";
            $php_code .= $this->generate_field_template( $fields, $group['key'], 0 );
            $php_code .= "\n";
        }

        // No closing PHP tag to avoid issues when included in other PHP files
        return $php_code;
    }

    private function ensure_unique_key( $key, $prefix ) {
        static $used_keys = [];
        if ( in_array( $key, $used_keys ) ) {
            $key = sprintf( '%s_%s_%s', $prefix, uniqid(), time() );
        }
        $used_keys[] = $key;
        return $key;
    }

    private function process_nested_fields( $field ) {
        // Handle nested fields for repeater, flexible_content, group, clone, etc.
        if ( in_array( $field['type'], [ 'repeater', 'flexible_content', 'group', 'clone' ] ) ) {
            if ( ! empty( $field['sub_fields'] ) ) {
                foreach ( $field['sub_fields'] as &$sub_field ) {
                    $sub_field['key'] = $this->ensure_unique_key( $sub_field['key'], 'field' );
                    $sub_field = $this->process_nested_fields( $sub_field ); // Recursive call
                }
            }
        } elseif ( $field['type'] === 'flexible_content' && ! empty( $field['layouts'] ) ) {
            foreach ( $field['layouts'] as &$layout ) {
                $layout['key'] = $this->ensure_unique_key( $layout['key'], 'layout' );
                if ( ! empty( $layout['sub_fields'] ) ) {
                    foreach ( $layout['sub_fields'] as &$sub_field ) {
                        $sub_field['key'] = $this->ensure_unique_key( $sub_field['key'], 'field' );
                        $sub_field = $this->process_nested_fields( $sub_field ); // Recursive call
                    }
                }
            }
        }
        return $field;
    }

    private function generate_field_template( $fields, $group_key, $indent_level = 0, $parent_field = '' ) {
        $indent = str_repeat( '    ', $indent_level );
        $output = '';

        foreach ( $fields as $field ) {
            $field_name = $field['name'];
            $field_type = $field['type'];

            // Determine the context (top-level or sub-field)
            $field_accessor = $parent_field ? "get_sub_field('$field_name')" : "get_field('$field_name')";
            $the_field_accessor = $parent_field ? "the_sub_field('$field_name')" : "the_field('$field_name')";

            // Start field block
            $output .= "{$indent}/* Field: {$field['label']} ({$field_type}) */\n";

            switch ( $field_type ) {
                case 'text':
                case 'textarea':
                case 'number':
                case 'email':
                    $output .= "{$indent}if ( {$field_accessor} ) {\n";
                    $output .= "{$indent}    echo '<p>' . esc_html( {$the_field_accessor} ) . '</p>';\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'url':
                    $output .= "{$indent}\$url = {$field_accessor};\n";
                    $output .= "{$indent}if ( \$url ) {\n";
                    $output .= "{$indent}    echo '<a href=\"' . esc_url( \$url ) . '\">' . esc_html( \$url ) . '</a>';\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'password':
                    $output .= "{$indent}/* Password field: {$field_name} (not displayed for security) */\n";
                    break;

                case 'wysiwyg':
                    $output .= "{$indent}if ( {$field_accessor} ) {\n";
                    $output .= "{$indent}    echo '<div class=\"wysiwyg-content\">' . {$the_field_accessor} . '</div>';\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'image':
                    $output .= "{$indent}\$image = {$field_accessor};\n";
                    $output .= "{$indent}if ( \$image ) {\n";
                    $output .= "{$indent}    echo '<figure>';\n";
                    $output .= "{$indent}    echo wp_get_attachment_image( \$image['ID'], 'full' );\n";
                    $output .= "{$indent}    if ( \$image['caption'] ) {\n";
                    $output .= "{$indent}        echo '<figcaption>' . esc_html( \$image['caption'] ) . '</figcaption>';\n";
                    $output .= "{$indent}    }\n";
                    $output .= "{$indent}    echo '</figure>';\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'file':
                    $output .= "{$indent}\$file = {$field_accessor};\n";
                    $output .= "{$indent}if ( \$file ) {\n";
                    $output .= "{$indent}    echo '<a href=\"' . esc_url( \$file['url'] ) . '\" download>' . esc_html( \$file['title'] ) . '</a>';\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'oembed':
                    $output .= "{$indent}if ( {$field_accessor} ) {\n";
                    $output .= "{$indent}    echo '<div class=\"oembed-content\">' . {$the_field_accessor} . '</div>';\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'gallery':
                    $output .= "{$indent}\$images = {$field_accessor};\n";
                    $output .= "{$indent}if ( \$images ) {\n";
                    $output .= "{$indent}    echo '<div class=\"gallery\">';\n";
                    $output .= "{$indent}    foreach ( \$images as \$image ) {\n";
                    $output .= "{$indent}        echo '<figure>';\n";
                    $output .= "{$indent}        echo wp_get_attachment_image( \$image['ID'], 'thumbnail' );\n";
                    $output .= "{$indent}        if ( \$image['caption'] ) {\n";
                    $output .= "{$indent}            echo '<figcaption>' . esc_html( \$image['caption'] ) . '</figcaption>';\n";
                    $output .= "{$indent}        }\n";
                    $output .= "{$indent}        echo '</figure>';\n";
                    $output .= "{$indent}    }\n";
                    $output .= "{$indent}    echo '</div>';\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'select':
                case 'radio':
                    $output .= "{$indent}\$selected = {$field_accessor};\n";
                    $output .= "{$indent}if ( \$selected ) {\n";
                    $output .= "{$indent}    if ( is_array( \$selected ) ) {\n";
                    $output .= "{$indent}        echo '<ul>';\n";
                    $output .= "{$indent}        foreach ( \$selected as \$value ) {\n";
                    $output .= "{$indent}            echo '<li>' . esc_html( \$value ) . '</li>';\n";
                    $output .= "{$indent}        }\n";
                    $output .= "{$indent}        echo '</ul>';\n";
                    $output .= "{$indent}    } else {\n";
                    $output .= "{$indent}        echo '<p>' . esc_html( \$selected ) . '</p>';\n";
                    $output .= "{$indent}    }\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'checkbox':
                    $output .= "{$indent}\$choices = {$field_accessor};\n";
                    $output .= "{$indent}if ( \$choices ) {\n";
                    $output .= "{$indent}    echo '<ul class=\"checkbox-{$field_name}\">';\n";
                    $output .= "{$indent}    foreach ( \$choices as \$choice ) {\n";
                    $output .= "{$indent}        echo '<li>' . esc_html( \$choice ) . '</li>';\n";
                    $output .= "{$indent}    }\n";
                    $output .= "{$indent}    echo '</ul>';\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'button_group':
                    $output .= "{$indent}\$button = {$field_accessor};\n";
                    $output .= "{$indent}if ( \$button ) {\n";
                    $output .= "{$indent}    echo '<span class=\"button-group-{$field_name}\">' . esc_html( \$button ) . '</span>';\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'true_false':
                    $output .= "{$indent}if ( {$field_accessor} ) {\n";
                    $output .= "{$indent}    echo '<p>' . esc_html__( 'Enabled', 'text-domain' ) . '</p>';\n";
                    $output .= "{$indent}} else {\n";
                    $output .= "{$indent}    echo '<p>' . esc_html__( 'Disabled', 'text-domain' ) . '</p>';\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'link':
                    $output .= "{$indent}\$link = {$field_accessor};\n";
                    $output .= "{$indent}if ( \$link ) {\n";
                    $output .= "{$indent}    \$target = \$link['target'] ? 'target=\"' . esc_attr( \$link['target'] ) . '\"' : '';\n";
                    $output .= "{$indent}    echo '<a href=\"' . esc_url( \$link['url'] ) . '\" ' . \$target . '>' . esc_html( \$link['title'] ) . '</a>';\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'post_object':
                    $output .= "{$indent}\$post = {$field_accessor};\n";
                    $output .= "{$indent}if ( \$post ) {\n";
                    $output .= "{$indent}    if ( is_array( \$post ) ) {\n";
                    $output .= "{$indent}        echo '<ul>';\n";
                    $output .= "{$indent}        foreach ( \$post as \$p ) {\n";
                    $output .= "{$indent}            echo '<li><a href=\"' . esc_url( get_permalink( \$p->ID ) ) . '\">' . esc_html( get_the_title( \$p->ID ) ) . '</a></li>';\n";
                    $output .= "{$indent}        }\n";
                    $output .= "{$indent}        echo '</ul>';\n";
                    $output .= "{$indent}    } else {\n";
                    $output .= "{$indent}        echo '<a href=\"' . esc_url( get_permalink( \$post->ID ) ) . '\">' . esc_html( get_the_title( \$post->ID ) ) . '</a>';\n";
                    $output .= "{$indent}    }\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'page_link':
                    $output .= "{$indent}\$page_link = {$field_accessor};\n";
                    $output .= "{$indent}if ( \$page_link ) {\n";
                    $output .= "{$indent}    if ( is_array( \$page_link ) ) {\n";
                    $output .= "{$indent}        echo '<ul>';\n";
                    $output .= "{$indent}        foreach ( \$page_link as \$link ) {\n";
                    $output .= "{$indent}            echo '<li><a href=\"' . esc_url( \$link ) . '\">' . esc_html( get_the_title( url_to_postid( \$link ) ) ) . '</a></li>';\n";
                    $output .= "{$indent}        }\n";
                    $output .= "{$indent}        echo '</ul>';\n";
                    $output .= "{$indent}    } else {\n";
                    $output .= "{$indent}        echo '<a href=\"' . esc_url( \$page_link ) . '\">' . esc_html( get_the_title( url_to_postid( \$page_link ) ) ) . '</a>';\n";
                    $output .= "{$indent}    }\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'relationship':
                    $output .= "{$indent}\$posts = {$field_accessor};\n";
                    $output .= "{$indent}if ( \$posts ) {\n";
                    $output .= "{$indent}    echo '<ul class=\"relationship-{$field_name}\">';\n";
                    $output .= "{$indent}    foreach ( \$posts as \$post ) {\n";
                    $output .= "{$indent}        echo '<li><a href=\"' . esc_url( get_permalink( \$post->ID ) ) . '\">' . esc_html( get_the_title( \$post->ID ) ) . '</a></li>';\n";
                    $output .= "{$indent}    }\n";
                    $output .= "{$indent}    echo '</ul>';\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'taxonomy':
                    $output .= "{$indent}\$terms = {$field_accessor};\n";
                    $output .= "{$indent}if ( \$terms ) {\n";
                    $output .= "{$indent}    echo '<ul class=\"taxonomy-{$field_name}\">';\n";
                    $output .= "{$indent}    if ( is_array( \$terms ) ) {\n";
                    $output .= "{$indent}        foreach ( \$terms as \$term ) {\n";
                    $output .= "{$indent}            echo '<li><a href=\"' . esc_url( get_term_link( \$term ) ) . '\">' . esc_html( \$term->name ) . '</a></li>';\n";
                    $output .= "{$indent}        }\n";
                    $output .= "{$indent}    } else {\n";
                    $output .= "{$indent}        echo '<li><a href=\"' . esc_url( get_term_link( \$terms ) ) . '\">' . esc_html( \$terms->name ) . '</a></li>';\n";
                    $output .= "{$indent}    }\n";
                    $output .= "{$indent}    echo '</ul>';\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'user':
                    $output .= "{$indent}\$user = {$field_accessor};\n";
                    $output .= "{$indent}if ( \$user ) {\n";
                    $output .= "{$indent}    if ( is_array( \$user ) ) {\n";
                    $output .= "{$indent}        echo '<ul>';\n";
                    $output .= "{$indent}        foreach ( \$user as \$u ) {\n";
                    $output .= "{$indent}            echo '<li>' . esc_html( \$u['display_name'] ) . '</li>';\n";
                    $output .= "{$indent}        }\n";
                    $output .= "{$indent}        echo '</ul>';\n";
                    $output .= "{$indent}    } else {\n";
                    $output .= "{$indent}        echo '<p>' . esc_html( \$user['display_name'] ) . '</p>';\n";
                    $output .= "{$indent}    }\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'google_map':
                    $output .= "{$indent}\$map = {$field_accessor};\n";
                    $output .= "{$indent}if ( \$map ) {\n";
                    $output .= "{$indent}    echo '<div class=\"acf-map\" data-lat=\"' . esc_attr( \$map['lat'] ) . '\" data-lng=\"' . esc_attr( \$map['lng'] ) . '\">';\n";
                    $output .= "{$indent}    echo '<p>' . esc_html( \$map['address'] ) . '</p>';\n";
                    $output .= "{$indent}    echo '</div>';\n";
                    $output .= "{$indent}    /* Note: Add ACF Google Map JS or a static map API for full rendering */\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'date_picker':
                    $output .= "{$indent}\$date = {$field_accessor};\n";
                    $output .= "{$indent}if ( \$date ) {\n";
                    $output .= "{$indent}    echo '<p>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( \$date ) ) ) . '</p>';\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'date_time_picker':
                    $output .= "{$indent}\$datetime = {$field_accessor};\n";
                    $output .= "{$indent}if ( \$datetime ) {\n";
                    $output .= "{$indent}    echo '<p>' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( \$datetime ) ) ) . '</p>';\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'time_picker':
                    $output .= "{$indent}\$time = {$field_accessor};\n";
                    $output .= "{$indent}if ( \$time ) {\n";
                    $output .= "{$indent}    echo '<p>' . esc_html( date_i18n( get_option( 'time_format' ), strtotime( \$time ) ) ) . '</p>';\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'color_picker':
                    $output .= "{$indent}\$color = {$field_accessor};\n";
                    $output .= "{$indent}if ( \$color ) {\n";
                    $output .= "{$indent}    echo '<div style=\"background-color: ' . esc_attr( \$color ) . '; width: 100px; height: 100px;\"></div>';\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'repeater':
                    $output .= "{$indent}if ( have_rows('$field_name') ) {\n";
                    $output .= "{$indent}    echo '<div class=\"repeater-{$field_name}\">';\n";
                    $output .= "{$indent}    while ( have_rows('$field_name') ) : the_row();\n";
                    $output .= "{$indent}        echo '<div class=\"repeater-item\">';\n";
                    $output .= $this->generate_field_template( $field['sub_fields'], $group_key, $indent_level + 3, $field_name );
                    $output .= "{$indent}        echo '</div>';\n";
                    $output .= "{$indent}    endwhile;\n";
                    $output .= "{$indent}    echo '</div>';\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'flexible_content':
                    $output .= "{$indent}if ( have_rows('$field_name') ) {\n";
                    $output .= "{$indent}    echo '<div class=\"flexible-content-{$field_name}\">';\n";
                    $output .= "{$indent}    while ( have_rows('$field_name') ) : the_row();\n";
                    foreach ( $field['layouts'] as $layout ) {
                        $layout_name = $layout['name'];
                        $output .= "{$indent}        if ( get_row_layout() == '$layout_name' ) {\n";
                        $output .= "{$indent}            echo '<div class=\"layout-{$layout_name}\">';\n";
                        $output .= $this->generate_field_template( $layout['sub_fields'], $group_key, $indent_level + 4, $field_name );
                        $output .= "{$indent}            echo '</div>';\n";
                        $output .= "{$indent}        }\n";
                    }
                    $output .= "{$indent}    endwhile;\n";
                    $output .= "{$indent}    echo '</div>';\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'group':
                    $output .= "{$indent}if ( have_rows('$field_name') ) {\n";
                    $output .= "{$indent}    echo '<div class=\"group-{$field_name}\">';\n";
                    $output .= "{$indent}    while ( have_rows('$field_name') ) : the_row();\n";
                    $output .= $this->generate_field_template( $field['sub_fields'], $group_key, $indent_level + 3, $field_name );
                    $output .= "{$indent}    endwhile;\n";
                    $output .= "{$indent}    echo '</div>';\n";
                    $output .= "{$indent}}\n";
                    break;

                case 'clone':
                    $output .= "{$indent}/* Clone field: {$field_name} */\n";
                    $output .= $this->generate_field_template( $field['sub_fields'], $group_key, $indent_level, $parent_field );
                    break;

                case 'accordion':
                case 'tab':
                    $output .= "{$indent}/* {$field_type} field: {$field_name} (admin UI element, no front-end output) */\n";
                    break;

                default:
                    $output .= "{$indent}/* Unsupported field type: {$field_type} */\n";
                    $output .= "{$indent}if ( {$field_accessor} ) {\n";
                    $output .= "{$indent}    echo '<p>' . esc_html( {$the_field_accessor} ) . '</p>';\n";
                    $output .= "{$indent}}\n";
                    break;
            }
            $output .= "\n";
        }

        return $output;
    }
}