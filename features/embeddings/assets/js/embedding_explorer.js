
document.addEventListener('DOMContentLoaded', function () {
    //    \\    BULK GENERATE EMBEDDINGS FORM    \\    //
    let bulk_generate_embeddings_form = document.getElementById('scrywp_search_bulk_generate_embeddings_form');

    //add event listener to the form for submit
    if (bulk_generate_embeddings_form) {
        bulk_generate_embeddings_form.addEventListener('submit', async function (event) {
            event.preventDefault();

            //hide the success and error messages
            let success_msg = document.getElementById('scrywp_search_bulk_generate_embeddings_success_msg');
            success_msg.classList.add('scrywp_search_generate_embeddings_hidden');

            let error_msg = document.getElementById('scrywp_search_bulk_generate_embeddings_error_msg');
            error_msg.classList.add('scrywp_search_generate_embeddings_hidden');

            //get the selected option
            let selected_option = document.getElementById('bulk_generate_embeddings_select').value;

            //show the spinner
            let spinner = document.getElementById('scrywp_search_bulk_generate_embeddings_spinner');
            spinner.classList.remove('scrywp_search_generate_embeddings_hidden');
            spinner.classList.add('scrywp_search_generate_embeddings_show');

            //hide the form
            bulk_generate_embeddings_form.classList.add('scrywp_search_generate_embeddings_hidden');


            //submit ajax to the generate embeddings
            try {
                const result = await scrywp_search_generate_embeddings(selected_option);
                console.log(result, "result");
                //show the success message
                success_msg.classList.remove('scrywp_search_generate_embeddings_hidden');

            } catch (error) {
                console.error(error, "error");
                //show the error message
                error_msg.classList.remove('scrywp_search_generate_embeddings_hidden');
            } finally {
                //hide the spinner
                spinner.classList.remove('scrywp_search_generate_embeddings_show');
                spinner.classList.add('scrywp_search_generate_embeddings_hidden');
                //show the form
                bulk_generate_embeddings_form.classList.remove('scrywp_search_generate_embeddings_hidden');


                //sleep for 1 second
                await new Promise(resolve => setTimeout(resolve, 1000));

                //reload the page
                location.reload();
            }
        });
    } else {
        console.error("bulk_generate_embeddings_form not found");
    }

    //    \\    SINGULAR EMBEDDINGS FORM    \\    //
    let singular_generate_embeddings_form = document.getElementById('scrywp_search_singular_generate_embeddings_form');


});