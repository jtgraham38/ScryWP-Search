
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
    var searchInput = searchForm.formElement.querySelector('input[type="text"][name="s"], input[type="search"][name="s"]');
    if (!searchInput) {
        console.error('No search input found for search form: ' + searchForm.formElement.id);
        return;
    }

    //attach an event listener to the search input
    searchInput.addEventListener('input', async function (e) {

        //exit if the value is less than 3 characters
        if (e.target.value.length < 3) {
            return;
        }

        //send a debounced request to the rest api (returns undefined if superseded by a newer keystroke)
        try {
            var data = await scrySearch_debouncedAutoSuggest(searchForm);
            if (!data) {
                return;
            }
            searchForm.autosuggestResults = data || [];
            scrySearch_renderAutosuggestResults(searchForm);
        } catch (err) {
            console.error('Scry Search autosuggest request failed', err);
        }
        //TODO: right now, we just contnuously appen new results objects.  this is bad!  Circle back around and fix this
    });
};

//render the autosuggest results under the search input
var scrySearch_renderAutosuggestResults = function (searchForm) {

    //first, check if the autosuggest results container already exists, and if so, remove it
    if (searchForm.autoSuggestElement) {
        searchForm.autoSuggestElement.remove();
        searchForm.autoSuggestElement = null;
    }

    //return early if no autosuggest results
    if (searchForm.autosuggestResults.length === 0) {
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
    var results = searchForm.autosuggestResults || [];
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
        searchForm.autoSuggestElement.remove();
        searchForm.autoSuggestElement = null;
    });
    autosuggestResults.appendChild(closeButton);

    //once the results are fully created, add the container to the search input
    searchForm.formElement.appendChild(autosuggestResults);
    searchForm.autoSuggestElement = autosuggestResults;
}
// ============================================= HELPERS ============================================ \\


/**
 * Debounce an async function and return a Promise that resolves with its result
 * (or undefined if a newer call replaced this one before the timer fired).
 */
function scrySearch_debounceAsync(fn, timeout) {
    var timer = null;
    var seq = 0;
    return function () {
        var args = arguments;
        var mySeq = ++seq;
        clearTimeout(timer);
        return new Promise(function (resolve, reject) {
            timer = setTimeout(function () {
                timer = null;
                Promise.resolve(fn.apply(null, args))
                    .then(function (result) {
                        if (mySeq === seq) {
                            resolve(result);
                        } else {
                            resolve(undefined);
                        }
                    })
                    .catch(function (err) {
                        if (mySeq === seq) {
                            reject(err);
                        } else {
                            resolve(undefined);
                        }
                    });
            }, timeout);
        });
    };
}

//function that actually sends the autosuggest request to the rest api
var scrySearch_sendAutoSuggestRequest = async function (searchForm) {
    //get the value of each input from the form data
    var formData = {};
    searchForm.formElement.querySelectorAll('input').forEach(function (input) {
        var value = input.value;
        var key = input.name;
        formData[key] = value;
    });
    //send a request to the rest api
    var searchResults = await fetch(localized.restApiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify(formData),
    });
    var data = await searchResults.json();

    return data;
}

// debounced autosuggest: await waits until typing pauses, then returns API JSON (or undefined if superseded)
var scrySearch_debouncedAutoSuggest = scrySearch_debounceAsync(scrySearch_sendAutoSuggestRequest, 400);