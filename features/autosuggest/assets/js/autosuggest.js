
//wait for the document to be ready
document.addEventListener('scrySearchReady', function () {
    //get all search forms with the class selector
    if (localized.classSelector) {
        var searchForms = window.scrySearch.getSearchFormsByClass(localized.classSelector);
    } else {
        var searchForms = window.scrySearch.getSearchForms();
    }

    //then, for each search form, load the autosuggest script
    searchForms.forEach(function (searchForm) {
        scrySearch_autosuggest(searchForm);
    });
});

//main function implementing autosuggest on a form
var scrySearch_autosuggest = function (searchForm) {
    //locate the search input (type will be text or search)
    var searchInput = searchForm.searchInput;
    if (!searchInput) {
        console.error('No search input found for search form: ' + searchForm.formElement.id);
        return;
    }

    //add the save autosuggest results action to the search form, this should run before most everything else
    searchForm.addPostSubmitAjaxAction(scrySearch_saveAutosuggestResults, 9);
    //add the render autosuggest results action to the search form, this should run last after all other actions
    searchForm.addPostSubmitAjaxAction(scrySearch_renderAutosuggestResults, 999);

    //attach an event listener to the search input
    searchInput.addEventListener('input', async function (e) {

        //exit if the value is less than 3 characters
        if (e.target.value.length < 3) {
            return;
        }

        //send a debounced request to the rest api (returns undefined if superseded by a newer keystroke)
        try {
            await searchForm.submitAjax();
        } catch (err) {
            console.error('Scry Search autosuggest request failed', err);
        }
        //TODO: right now, we just contnuously appen new results objects.  this is bad!  Circle back around and fix this
    });
};

//save the autosuggest results to the search form data, so it can be read by other actions
var scrySearch_saveAutosuggestResults = function (searchForm, data) {
    searchForm.data.core.autosuggestResults = data;
}

//render the autosuggest results under the search input
var scrySearch_renderAutosuggestResults = function (searchForm) {

    //first, check if the autosuggest results container already exists, and if so, remove it
    if (searchForm.data.core?.autoSuggestElement) {
        searchForm.data.core.autoSuggestElement.remove();
        searchForm.data.core.autoSuggestElement = null;
    }

    //return early if no autosuggest results
    if (!searchForm.data.core?.autosuggestResults || searchForm.data.core.autosuggestResults.length === 0) {
        return;
    }

    //make the absolutely-positioned container for the autosuggest results
    var autosuggestResults = document.createElement('div');
    autosuggestResults.classList.add('scry-search-autosuggest-results');
    autosuggestResults.style.width = searchForm.formElement.offsetWidth + 'px';     //width should match the width of the form

    //make a list inside the container
    var autosuggestResultsList = document.createElement('ul');
    autosuggestResultsList.classList.add('scry-search-autosuggest-results-list');
    autosuggestResults.appendChild(autosuggestResultsList);

    //make a list item for each result
    var results = searchForm.data.core.autosuggestResults || [];
    results.forEach(function (result) {
        var resultItem = document.createElement('li');
        resultItem.classList.add('scry-search-autosuggest-result-item');
        resultItem.innerHTML = '<a href="' + result.url + '">' + result.title + '</a>';
        autosuggestResultsList.appendChild(resultItem);
    });

    //add a close button to the top left of the container
    var closeButton = document.createElement('button');
    closeButton.classList.add('scry-search-autosuggest-results-close-button');
    closeButton.innerHTML = '<span class="dashicons dashicons-no-alt"></span>';
    closeButton.addEventListener('click', function () {
        searchForm.data.core.autoSuggestElement.remove();
        searchForm.data.core.autoSuggestElement = null;
    });
    autosuggestResults.appendChild(closeButton);

    //once the results are fully created, add the container to the search input
    searchForm.formElement.appendChild(autosuggestResults);
    searchForm.data.core.autoSuggestElement = autosuggestResults;
}