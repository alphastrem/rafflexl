<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TXC_Qualifying {

    const MAX_ATTEMPTS = 3;
    const COOLDOWN_SECONDS = 1800; // 30 minutes
    const TIME_LIMIT = 30;
    const TIME_GRACE = 5;

    /**
     * AJAX: get a random question for a competition.
     */
    public function ajax_get_question() {
        check_ajax_referer( 'txc_public_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'You must be logged in.' ] );
        }

        $user_id        = get_current_user_id();
        $competition_id = absint( $_POST['competition_id'] ?? 0 );

        if ( ! $competition_id ) {
            wp_send_json_error( [ 'message' => 'Invalid competition.' ] );
        }

        // Check if already qualified
        $qualified_key = 'txc_qualified_' . $competition_id;
        if ( WC()->session && WC()->session->get( $qualified_key ) ) {
            wp_send_json_success( [ 'already_qualified' => true ] );
            return;
        }

        // Check cooldown
        $cooldown = $this->check_cooldown( $user_id, $competition_id );
        if ( $cooldown > 0 ) {
            wp_send_json_error( [
                'message'          => 'Too many incorrect answers. Please try again later.',
                'cooldown_seconds' => $cooldown,
            ] );
            return;
        }

        // Get attempt count
        $attempts = $this->get_attempt_count( $user_id, $competition_id );

        // Select a question the user hasn't seen recently for this competition
        $question = $this->select_question( $user_id, $competition_id );
        if ( ! $question ) {
            wp_send_json_error( [ 'message' => 'No questions available.' ] );
            return;
        }

        // Store question issue time in transient for anti-cheat
        set_transient( "txc_q_issued_{$user_id}_{$competition_id}", time(), self::TIME_LIMIT + self::TIME_GRACE + 10 );
        set_transient( "txc_q_id_{$user_id}_{$competition_id}", $question->id, self::TIME_LIMIT + self::TIME_GRACE + 10 );

        wp_send_json_success( [
            'question_id' => (int) $question->id,
            'question'    => $question->question_text,
            'options'     => [
                'a' => $question->option_a,
                'b' => $question->option_b,
                'c' => $question->option_c,
                'd' => $question->option_d,
            ],
            'time_limit'    => self::TIME_LIMIT,
            'attempt'       => $attempts + 1,
            'max_attempts'  => self::MAX_ATTEMPTS,
        ] );
    }

    /**
     * AJAX: submit an answer.
     */
    public function ajax_submit_answer() {
        check_ajax_referer( 'txc_public_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'You must be logged in.' ] );
        }

        $user_id        = get_current_user_id();
        $competition_id = absint( $_POST['competition_id'] ?? 0 );
        $question_id    = absint( $_POST['question_id'] ?? 0 );
        $answer         = sanitize_text_field( $_POST['answer'] ?? '' );

        if ( ! $competition_id || ! $question_id || ! in_array( $answer, [ 'a', 'b', 'c', 'd' ], true ) ) {
            wp_send_json_error( [ 'message' => 'Invalid submission.' ] );
        }

        // Anti-cheat: verify this question was issued to this user
        $issued_qid = get_transient( "txc_q_id_{$user_id}_{$competition_id}" );
        if ( (int) $issued_qid !== $question_id ) {
            wp_send_json_error( [ 'message' => 'Invalid question.' ] );
        }

        // Anti-cheat: check time
        $issued_at = get_transient( "txc_q_issued_{$user_id}_{$competition_id}" );
        if ( $issued_at && ( time() - $issued_at ) > ( self::TIME_LIMIT + self::TIME_GRACE ) ) {
            wp_send_json_error( [ 'message' => 'Time expired. Please try again.' ] );
        }

        // Check cooldown
        $cooldown = $this->check_cooldown( $user_id, $competition_id );
        if ( $cooldown > 0 ) {
            wp_send_json_error( [
                'message'          => 'Cooldown active.',
                'cooldown_seconds' => $cooldown,
            ] );
            return;
        }

        // Get question
        global $wpdb;
        $table = $wpdb->prefix . 'txc_questions';
        $question = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $question_id ) );
        if ( ! $question ) {
            wp_send_json_error( [ 'message' => 'Question not found.' ] );
        }

        $is_correct = ( $answer === $question->correct_option );
        $attempts = $this->get_attempt_count( $user_id, $competition_id );

        // Record attempt
        $attempts_table = $wpdb->prefix . 'txc_question_attempts';
        $wpdb->insert( $attempts_table, [
            'user_id'         => $user_id,
            'competition_id'  => $competition_id,
            'question_id'     => $question_id,
            'selected_option' => $answer,
            'is_correct'      => $is_correct ? 1 : 0,
            'attempt_number'  => $attempts + 1,
            'attempted_at'    => current_time( 'mysql', true ),
        ] );

        // Clean up transients
        delete_transient( "txc_q_issued_{$user_id}_{$competition_id}" );
        delete_transient( "txc_q_id_{$user_id}_{$competition_id}" );

        if ( $is_correct ) {
            if ( WC()->session ) {
                WC()->session->set( 'txc_qualified_' . $competition_id, true );
            }
            wp_send_json_success( [
                'correct' => true,
                'message' => 'Correct! You can now enter this competition.',
            ] );
        }

        $new_attempts = $attempts + 1;
        $remaining = self::MAX_ATTEMPTS - $new_attempts;

        if ( $remaining <= 0 ) {
            wp_send_json_error( [
                'correct'          => false,
                'message'          => 'Incorrect. You have used all attempts. Please wait 30 minutes before trying again.',
                'cooldown_seconds' => self::COOLDOWN_SECONDS,
                'attempts_left'    => 0,
            ] );
        } else {
            wp_send_json_error( [
                'correct'       => false,
                'message'       => sprintf( 'Incorrect. You have %d attempt(s) remaining.', $remaining ),
                'attempts_left' => $remaining,
            ] );
        }
    }

    /**
     * Get number of attempts for this user/competition (within cooldown window).
     */
    private function get_attempt_count( $user_id, $competition_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_question_attempts';

        // Count failed attempts since last cooldown reset
        $cooldown_start = gmdate( 'Y-m-d H:i:s', time() - self::COOLDOWN_SECONDS );

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
            WHERE user_id = %d AND competition_id = %d AND is_correct = 0 AND attempted_at >= %s",
            $user_id,
            $competition_id,
            $cooldown_start
        ) );
    }

    /**
     * Check if user is in cooldown. Returns seconds remaining or 0.
     */
    private function check_cooldown( $user_id, $competition_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'txc_question_attempts';

        // Find the 3rd failed attempt time
        $third_fail = $wpdb->get_var( $wpdb->prepare(
            "SELECT attempted_at FROM {$table}
            WHERE user_id = %d AND competition_id = %d AND is_correct = 0
            ORDER BY attempted_at DESC LIMIT 1 OFFSET 2",
            $user_id,
            $competition_id
        ) );

        if ( ! $third_fail ) {
            return 0;
        }

        $fail_time = strtotime( $third_fail );
        $cooldown_end = $fail_time + self::COOLDOWN_SECONDS;

        if ( time() >= $cooldown_end ) {
            return 0;
        }

        return $cooldown_end - time();
    }

    /**
     * Select a random question the user hasn't recently answered for this competition.
     */
    private function select_question( $user_id, $competition_id ) {
        global $wpdb;
        $q_table = $wpdb->prefix . 'txc_questions';
        $a_table = $wpdb->prefix . 'txc_question_attempts';

        // Get question IDs the user has already answered for this competition
        $answered = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT question_id FROM {$a_table} WHERE user_id = %d AND competition_id = %d",
            $user_id,
            $competition_id
        ) );

        $exclude = '';
        if ( ! empty( $answered ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $answered ), '%d' ) );
            $exclude = $wpdb->prepare( " AND id NOT IN ({$placeholders})", ...$answered );
        }

        // Try to get an unseen question first
        $question = $wpdb->get_row(
            "SELECT * FROM {$q_table} WHERE active = 1 {$exclude} ORDER BY RAND() LIMIT 1"
        );

        // If all questions seen, allow repeat
        if ( ! $question ) {
            $question = $wpdb->get_row(
                "SELECT * FROM {$q_table} WHERE active = 1 ORDER BY RAND() LIMIT 1"
            );
        }

        return $question;
    }
}
