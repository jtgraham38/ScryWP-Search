<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../../../vendor/autoload.php';

use \NlpTools\Tokenizers\WhitespaceAndPunctuationTokenizer;
use jtgraham38\wpvectordb\VectorTable;
use jtgraham38\wpvectordb\VectorTableQueue;

//access to vector table
$VT = new VectorTable($this->get_prefix() ?? "scrywp_search_");
$Q = new VectorTableQueue($this->get_prefix() ?? "scrywp_search_");

//set default values
$post_id = null;
$embeddings = [];

//get the selected post, if one is selected
//get the post id of the selected post
if (isset($_REQUEST['post_id'])) {
    $post_id = $_REQUEST['post_id'];
    $selected_post = get_post($post_id);

    //get the embeddings of the post
    $embedding_ids = get_post_meta($post_id, $this->prefixed('embeddings'), true) ?? [];
    if (!is_array($embedding_ids)) {    //ensure the embeddings are an array
        $embedding_ids = [];
    }
    $embeddings = $VT->ids($embedding_ids);
}

//ensure the embeddings are an array
if (!is_array($embeddings)) {
    $embeddings = [];
}


if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

//create a wordpress list table for the queue records
class ScryWpSearch_ChatEmbeddingsTable extends WP_List_Table {

    private $Q;

    public function __construct($Q, $args = []) {
        parent::__construct($args);
        $this->Q = $Q;

        //set column headers
        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
            'title'
        ];
    }

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'title' => 'Title',
            'type' => 'Type',
            'status' => 'Status',
            'tries_remaining' => 'Tries remaining',
            'queued_at' => 'Queued at',
            'started_at' => 'Started at',
            'finished_at' => 'Finished at',
        ];
    }

    public function get_sortable_columns() {
        return [
            'type' => ['type', false],
            'status' => ['status', false],
            'queued_at' => ['queued_at', false],
            'started_at' => ['started_at', false],
            'finished_at' => ['finished_at', false],
        ];
    }

    public function process_bulk_action() {
        if ('bulk-delete' === $this->current_action()) {
            $nonce = isset($_REQUEST['_wpnonce']) ? $_REQUEST['_wpnonce'] : '';
            
            if (!wp_verify_nonce($nonce, 'bulk-' . $this->_args['plural'])) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error is-dismissible"><p>' . 
                         __('Security check failed. Please try again.', 'contentoracle-ai-chat') . 
                         '</p></div>';
                });
                return;
            }

            $post_ids = isset($_REQUEST['bulk-delete']) ? array_map('intval', $_REQUEST['bulk-delete']) : [];
            
            if (!empty($post_ids)) {
                $success_count = 0;
                foreach ($post_ids as $post_id) {
                    if ($this->Q->delete_post($post_id)) {
                        $success_count++;
                    }
                }

                if ($success_count > 0) {
                    add_action('admin_notices', function() use ($success_count) {
                        echo '<div class="notice notice-success is-dismissible"><p>' . 
                             sprintf(
                                 _n(
                                     '%d post removed from queue successfully.',
                                     '%d posts removed from queue successfully.',
                                     $success_count,
                                     'contentoracle-ai-chat'
                                 ),
                                 $success_count
                             ) . 
                             '</p></div>';
                    });
                }

                if ($success_count < count($post_ids)) {
                    add_action('admin_notices', function() use ($success_count, $post_ids) {
                        echo '<div class="notice notice-error is-dismissible"><p>' . 
                             sprintf(
                                 __('Failed to remove %d posts from queue.', 'contentoracle-ai-chat'),
                                 count($post_ids) - $success_count
                             ) . 
                             '</p></div>';
                    });
                }
            }
        }
    }

    public function prepare_items() {
        // Process bulk actions
        $this->process_bulk_action();

        // Set up pagination if needed
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = $this->Q->get_total_records();
        
        //get all records
        $queue_records = $this->Q->get_page_of_records($current_page, $per_page);

        
        // Set the items
        $this->items = $queue_records;

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'title':
                return sprintf(
                    '<a href="%s">%s</a>',
                    esc_url(get_edit_post_link($item->post_id)),
                    esc_html($item->post_title)
                );
            case 'type':
                return esc_html($item->post_type);
            case 'status':

                $string = '<span class="scrywp_search_queue_status ' . esc_attr($item->status) . '">' . esc_html($item->status) . '</span>';

                //if failed, create a dropdown with the error message
                if ($item->status === 'failed') {
                    $string = '<details><summary>' . $string . '</summary>' . esc_html($item->error_message) . '</details>';
                }

                return $string;
            case 'tries_remaining':
                return esc_html(3 - $item->error_count);
            case 'queued_at':
                return esc_html($item->queued_time);
            case 'started_at':
                return $item->start_time ? esc_html($item->start_time) : 'Not started';
            case 'finished_at':
                return $item->end_time ? esc_html($item->end_time) : 'Not finished';
            default:
                return isset($item->$column_name) ? esc_html($item->$column_name) : '';
        }
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="bulk-delete[]" value="%s" />',
            $item->post_id
        );
    }

    public function get_bulk_actions() {
        return [
            'bulk-delete' => __('Delete', 'contentoracle-ai-chat')
        ];
    }

    public function column_title($item) {
        $actions = array(
            'delete' => sprintf(
                '<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
                wp_nonce_url(
                    add_query_arg(
                        array(
                            'action' => 'delete',
                            'post_id' => $item->post_id
                        ),
                        admin_url('admin.php?page=contentoracle-ai-chat-embeddings')
                    ),
                    'delete_queue_item_' . $item->post_id
                ),
                esc_js(__('Are you sure you want to delete this item from the queue?', 'contentoracle-ai-chat')),
                __('Dequeue', 'contentoracle-ai-chat')
            )
        );

        return sprintf(
            '%1$s %2$s',
            sprintf(
                '<a href="%s">%s</a>',
                esc_url(get_edit_post_link($item->post_id)),
                esc_html($item->post_title)
            ),
            $this->row_actions($actions)
        );
    }
}

//create an instance of the table
$table = new ScryWpSearch_ChatEmbeddingsTable($Q);

// Handle queue removal
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['post_id'])) {
    $post_id = intval($_GET['post_id']);
    $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
    
    if (wp_verify_nonce($nonce, 'delete_queue_item_' . $post_id)) {
        $result = $Q->delete_post($post_id);
        
        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     __('Post removed from queue successfully.', 'contentoracle-ai-chat') . 
                     '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     __('Failed to remove post from queue.', 'contentoracle-ai-chat') . 
                     '</p></div>';
            });
        }
        
        // Redirect to remove query args
        wp_redirect(remove_query_arg(['action', 'post_id', '_wpnonce']));
        exit;
    } else {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error is-dismissible"><p>' . 
                 __('Security check failed. Please try again.', 'contentoracle-ai-chat') . 
                 '</p></div>';
        });
    }
}

?>

<strong>Note: Embeddings will only be generated for posts of the types set in the "Prompt" settings.  They will also only be generated if a chunking method is set.</strong>
<br>
<br>

<div id="<?php $this->pre('embeddings_explorer') ?>">

    <div>
        <h2>Embedding Queue</h2>
        <p>Below, you will find a queue of posts that are scheduled to have text embeddings generated for semantic text matching.</p>
    
        <details id="embeddings_queue_tips">
            <summary>Tips</summary>
            <ul>
                <li>
                    Use the "Add Posts to Queue" form to schedule posts to be embedded.
                </li>
                <li>
                    Each queue entry has a status.  The four options are:
                    <ul>
                        <li>
                            Pending: The post is scheduled to be embedded.
                        </li>
                        <li>
                            Processing: The post is currently being embedded.
                        </li>
                        <li>
                            Failed: The post failed to be embedded.  It will be retried up to 3 times.
                        </li>
                        <li>
                            Completed: The post has been embedded.  It will be removed from the queue after 24 hours.
                        </li>
                    </ul>
                </li>
                <li>
                    Each post can only be in the queue once.  If it is already in the queue, you must remove it before adding it again.
                </li>
                <li>
                    Use the bulk delete action or the inline dequeue button to remove posts from the queue.
                </li>
                <li>
                    Note that if a post does not appear in the queue after adding all posts, it may be because it does not have any embeddable content.
                </li>
            </ul>
        </details>
    </div>

    <div>
        <form 
            method="POST" 
            id="<?php $this->pre('bulk_generate_embeddings_form') ?>"
        >
            <label for="bulk_generate_embeddings_select">Add Posts to Queue</label>
            <div style="display: flex;" >
                
                <select 
                    name="bulk_generate_embeddings_option" 
                    id="bulk_generate_embeddings_select" 
                    required
                    title="Select an option to generate embeddings for many posts at once.  This will only generate embeddings for posts of the types selected in the prompt settings, and only if a chunking method is set."    
                >
                    <option value="" selected>Select an option...</option>
                    <option value="not_embedded">All Posts Without Embeddings</option>
                    <option value="all">All Posts</option>
                </select>
                <input type="submit" value="Generate Embeddings" class="button-primary">
            </div>
        </form>

        <!-- spinner, success, error -->
        <div id="<?php $this->pre('bulk_generate_embeddings_result_container') ?>" class="<?php $this->pre('generate_embeddings_result_container') ?>" >
            <span id="<?php $this->pre('bulk_generate_embeddings_spinner') ?>" 
                class="
                    <?php $this->pre('generate_embeddings_spinner') ?>
                    <?php $this->pre('generate_embeddings_hidden') ?>
            ">
            </span>
        </div>

        <div id="<?php $this->pre('bulk_generate_embeddings_success_msg') ?>" class="<?php $this->pre('generate_embeddings_success_msg') ?> <?php $this->pre('generate_embeddings_hidden') ?>">
            <p>Posts enqueued for embedding generation.</p>
        </div>

        <div id="<?php $this->pre('bulk_generate_embeddings_error_msg') ?>" class="<?php $this->pre('generate_embeddings_error_msg') ?> <?php $this->pre('generate_embeddings_hidden') ?>">
            <p>Error enqueuing posts for embedding generation!</p>
        </div>
    </div>

    <div>
        <form method="post">
            <?php 
            wp_nonce_field('bulk-' . $table->_args['plural']);
            $table->prepare_items();
            $table->display(); 
            ?>
        </form>
    </div>

</div>