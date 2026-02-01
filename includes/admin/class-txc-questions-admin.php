<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Questions_Admin {

    public function handle_actions() {
        if ( ! isset( $_POST['txc_question_action'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( $_POST['txc_question_nonce'] ?? '', 'txc_manage_question' ) ) {
            return;
        }
        if ( ! current_user_can( 'txc_manage_questions' ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'txc_questions';
        $action = sanitize_text_field( $_POST['txc_question_action'] );

        if ( 'add' === $action ) {
            $wpdb->insert( $table, [
                'question_text'  => sanitize_textarea_field( $_POST['question_text'] ?? '' ),
                'option_a'       => sanitize_text_field( $_POST['option_a'] ?? '' ),
                'option_b'       => sanitize_text_field( $_POST['option_b'] ?? '' ),
                'option_c'       => sanitize_text_field( $_POST['option_c'] ?? '' ),
                'option_d'       => sanitize_text_field( $_POST['option_d'] ?? '' ),
                'correct_option' => sanitize_text_field( $_POST['correct_option'] ?? 'a' ),
                'category'       => sanitize_text_field( $_POST['category'] ?? 'general' ),
                'difficulty'     => absint( $_POST['difficulty'] ?? 1 ),
                'active'         => 1,
            ] );
            add_settings_error( 'txc_questions', 'added', 'Question added.', 'updated' );
        }

        if ( 'edit' === $action ) {
            $id = absint( $_POST['question_id'] ?? 0 );
            if ( $id ) {
                $wpdb->update( $table, [
                    'question_text'  => sanitize_textarea_field( $_POST['question_text'] ?? '' ),
                    'option_a'       => sanitize_text_field( $_POST['option_a'] ?? '' ),
                    'option_b'       => sanitize_text_field( $_POST['option_b'] ?? '' ),
                    'option_c'       => sanitize_text_field( $_POST['option_c'] ?? '' ),
                    'option_d'       => sanitize_text_field( $_POST['option_d'] ?? '' ),
                    'correct_option' => sanitize_text_field( $_POST['correct_option'] ?? 'a' ),
                    'category'       => sanitize_text_field( $_POST['category'] ?? 'general' ),
                    'difficulty'     => absint( $_POST['difficulty'] ?? 1 ),
                ], [ 'id' => $id ] );
                add_settings_error( 'txc_questions', 'updated', 'Question updated.', 'updated' );
            }
        }

        if ( 'toggle' === $action ) {
            $id = absint( $_POST['question_id'] ?? 0 );
            if ( $id ) {
                $current = $wpdb->get_var( $wpdb->prepare( "SELECT active FROM {$table} WHERE id = %d", $id ) );
                $wpdb->update( $table, [ 'active' => $current ? 0 : 1 ], [ 'id' => $id ] );
            }
        }
    }

    public function render_page() {
        if ( ! current_user_can( 'txc_manage_questions' ) ) {
            wp_die( 'Unauthorized' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'txc_questions';
        $questions = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY category ASC, id DESC" );
        $editing = null;

        if ( isset( $_GET['edit'] ) ) {
            $edit_id = absint( $_GET['edit'] );
            $editing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $edit_id ) );
        }

        $categories = [ 'general', 'uk_geography', 'science', 'history', 'maths', 'sport', 'entertainment', 'food_drink' ];
        ?>
        <div class="wrap">
            <h1>Qualifying Questions</h1>
            <?php settings_errors( 'txc_questions' ); ?>

            <h2><?php echo $editing ? 'Edit Question' : 'Add New Question'; ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'txc_manage_question', 'txc_question_nonce' ); ?>
                <input type="hidden" name="txc_question_action" value="<?php echo $editing ? 'edit' : 'add'; ?>" />
                <?php if ( $editing ) : ?>
                    <input type="hidden" name="question_id" value="<?php echo esc_attr( $editing->id ); ?>" />
                <?php endif; ?>
                <table class="form-table">
                    <tr>
                        <th><label for="question_text">Question</label></th>
                        <td><textarea id="question_text" name="question_text" rows="2" class="large-text" required><?php echo esc_textarea( $editing->question_text ?? '' ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th>Options</th>
                        <td>
                            <?php foreach ( [ 'a', 'b', 'c', 'd' ] as $opt ) : ?>
                                <p>
                                    <label><strong><?php echo strtoupper( $opt ); ?>:</strong>
                                    <input type="text" name="option_<?php echo $opt; ?>" value="<?php echo esc_attr( $editing->{"option_{$opt}"} ?? '' ); ?>" class="regular-text" required />
                                    </label>
                                </p>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="correct_option">Correct Answer</label></th>
                        <td>
                            <select id="correct_option" name="correct_option">
                                <?php foreach ( [ 'a', 'b', 'c', 'd' ] as $opt ) : ?>
                                    <option value="<?php echo $opt; ?>" <?php selected( $editing->correct_option ?? 'a', $opt ); ?>><?php echo strtoupper( $opt ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="category">Category</label></th>
                        <td>
                            <select id="category" name="category">
                                <?php foreach ( $categories as $cat ) : ?>
                                    <option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $editing->category ?? 'general', $cat ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $cat ) ) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="difficulty">Difficulty</label></th>
                        <td>
                            <select id="difficulty" name="difficulty">
                                <option value="1" <?php selected( $editing->difficulty ?? 1, 1 ); ?>>Easy</option>
                                <option value="2" <?php selected( $editing->difficulty ?? 1, 2 ); ?>>Medium</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button( $editing ? 'Update Question' : 'Add Question' ); ?>
            </form>

            <h2>All Questions (<?php echo count( $questions ); ?>)</h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Question</th>
                        <th>Correct</th>
                        <th>Category</th>
                        <th>Difficulty</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $questions as $q ) : ?>
                        <tr>
                            <td><?php echo esc_html( $q->id ); ?></td>
                            <td><?php echo esc_html( wp_trim_words( $q->question_text, 15 ) ); ?></td>
                            <td><?php echo esc_html( strtoupper( $q->correct_option ) . ': ' . $q->{'option_' . $q->correct_option} ); ?></td>
                            <td><?php echo esc_html( ucwords( str_replace( '_', ' ', $q->category ) ) ); ?></td>
                            <td><?php echo $q->difficulty == 1 ? 'Easy' : 'Medium'; ?></td>
                            <td><?php echo $q->active ? '<span style="color:green;">Yes</span>' : '<span style="color:red;">No</span>'; ?></td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=txc-questions&edit=' . $q->id ) ); ?>">Edit</a>
                                |
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field( 'txc_manage_question', 'txc_question_nonce' ); ?>
                                    <input type="hidden" name="txc_question_action" value="toggle" />
                                    <input type="hidden" name="question_id" value="<?php echo esc_attr( $q->id ); ?>" />
                                    <button type="submit" class="button-link"><?php echo $q->active ? 'Deactivate' : 'Activate'; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
