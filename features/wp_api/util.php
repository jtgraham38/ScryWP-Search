<?php
// exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

use NlpTools\Tokenizers\WhitespaceAndPunctuationTokenizer;

trait ScryWpChunkingMixin {
	private int $CHUNK_SIZE = 256;

	/**
	 * Chunk a post's content based on the configured chunking method
	 * 
	 * @param WP_Post $post The post to chunk
	 * @return ScryWpSearch_ChunksForPost The chunked post content
	 * @throws Exception If an invalid chunking method is configured
	 */
	public function chunk_post($post) {
		$post_id = $post->ID;
		$chunking_method = get_option($this->get_prefix() . 'chunking_method', 'none');
		
		switch ($chunking_method) {
			case 'token:256':
				//get the post content
				$body = strip_tags($post->post_content);

				//split the body into tokens
				$tokenizer = new WhitespaceAndPunctuationTokenizer();
				$tokens = $tokenizer->tokenize($body);

				//group the tokens into chunks
				$chunks = array_chunk($tokens, $this->CHUNK_SIZE);

				//return the post id mapped to the chunks
				return new ScryWp_ChunksForPost($post_id, $chunks);
				
			case '':
			case 'none':
				//return the post id mapped to the entire post content
				return new ScryWp_ChunksForPost($post_id, [$post->post_content]);
				
			default:
				throw new Exception('Invalid chunking method: ' . $chunking_method);
		}
	}

	/**
	 * Get the chunk size used for token-based chunking
	 * 
	 * @return int The chunk size
	 */
	public function get_chunk_size(): int {
		return $this->CHUNK_SIZE;
	}

	/**
	 * Set the chunk size used for token-based chunking
	 * 
	 * @param int $size The new chunk size
	 */
	public function set_chunk_size(int $size): void {
		$this->CHUNK_SIZE = $size;
	}
}

trait ScryWpBulkContentEmbeddingMixin {

    public function scrywp_search_generate_embeddings($cps){
        //if cps is not an array, make it into a single-element array
        if (!is_array($cps)) {
            $cps = [$cps];
        }

        //add each record to the payload
        $content = [];
        foreach ($cps as $cp) {
            //create chunks
            $_chunks = array_map(
                function($chunk){
                    return implode(' ', $chunk);
                },
                $cp->chunks
            );

            //skip generation if no chunks exist
            if (empty($_chunks)) {
                //unset the generate embedding post meta
                update_post_meta($cp->post_id, $this->get_prefix() . 'should_generate_embeddings', false);
                continue;
            }

            //add record to content
            $content[] = [
                'id' => $cp->post_id,
                'url' => get_permalink($cp->post_id),
                'title' => get_the_title($cp->post_id),
                'chunks' => $_chunks,   //convert chunk from array of tokens to a string
                'type' => get_post_type($cp->post_id),
            ];
        }

        //ensure content is not empty
        if (empty($content)){

            return [];
        }

        //create an array of content to send to the coai api
        $payload = [
            'headers' => array(
                'Authorization' => 'Bearer ' . get_option($this->get_prefix() . 'api_token'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode([
                'chunking_method' => get_option($this->get_prefix() . 'chunking_method', 'none'),
                'client_ip' => $this->get_client_ip(),
                'content' => $content
            ]),
            'timeout' => $this->get_embed_timeout(),
        ];

        //make the request
        $url = ScryWpApiConnection::get_base_url() . '/v1/ai/embed';
        $response = wp_remote_post($url, $payload);

        //handle wordpress errors
        if (is_wp_error($response)){
            throw new Exception($response->get_error_message());
        }
        
        //retrieve and format the response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        //ensure the response is valid
        if (empty($data['embeddings'])) {
            $msg = $data['error'] . ": " . $data['message'] ?? 'Invalid response from ScryWP Search: embeddings key not set';
            throw new Exception($msg);
        }

        return $data['embeddings'];
    }

    //get the client ip address
        //get the ip address of the client
    function get_client_ip(){
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            // Check for IP from shared internet
            $ip = filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Check for IP passed from proxy
            $ip = filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = filter_var($_SERVER['HTTP_X_FORWARDED'], FILTER_VALIDATE_IP);
        } elseif (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
            $ip = filter_var($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'], FILTER_VALIDATE_IP);
        } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = filter_var($_SERVER['HTTP_FORWARDED_FOR'], FILTER_VALIDATE_IP);
        } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
            $ip = filter_var($_SERVER['HTTP_FORWARDED'], FILTER_VALIDATE_IP);
        } else {
            // Default fallback to REMOTE_ADDR
            $ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
        }

        // Handle multiple IPs (e.g., "client IP, proxy IP")
        if (strpos($ip, ',') !== false)
            $ip = explode(',', $ip)[0];

        // Sanitize IP address
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'UNKNOWN';
    }
}