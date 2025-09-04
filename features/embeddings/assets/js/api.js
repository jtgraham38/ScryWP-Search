//log the nonce
console.log(contentoracle_ai_chat_embeddings.nonce, 'nonce');


//this function calls the generate embeddings route
async function scrywp_search_generate_embeddings(_for) {
    const embed_url = contentoracle_ai_chat_embeddings.api_base_url + 'contentoracle-ai-chat/v1/content-embed';
    const body = { for: _for };

    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', embed_url, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('X-WP-Nonce', contentoracle_ai_chat_embeddings.nonce);
        xhr.timeout = 900000;

        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                console.log(xhr.responseText, 'response');
                resolve(JSON.parse(xhr.responseText)); // Resolve with parsed response
            } else {
                reject(new Error(`Request failed with status ${xhr.status}`));
            }
        };

        xhr.onerror = function () {
            reject(new Error('Network error occurred'));
        };

        xhr.ontimeout = function () {
            reject(new Error('Request timed out'));
        };

        xhr.send(JSON.stringify(body));
    });
}