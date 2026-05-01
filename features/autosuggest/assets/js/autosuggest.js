

// //log out the localized settings
console.log('restApiUrl: ' + localized.restApiUrl);
console.log('classSelector: ' + localized.classSelector);
//wait for the document to be ready
document.addEventListener('DOMContentLoaded', function () {
    console.log('document is ready');

    //first, load all search forms on the page that match the class selector
    //or just load them all if class selector is blank
    var candidateForms;
    var rawSelector = (localized.classSelector) ? String(localized.classSelector) : '';
    var classSelector = rawSelector.trim();
    if (classSelector) {
        try {
            candidateForms = document.querySelectorAll(classSelector);
        } catch (err) {
            console.warn('Scry Search autosuggest: invalid classSelector, falling back to all forms.', classSelector, err);
            candidateForms = document.querySelectorAll('form');
        }
    } else {
        candidateForms = document.querySelectorAll('form');
    }

    //determine which of the candidate forms are search forms
    var searchForms = Array.prototype.filter.call(candidateForms, isSearchForm);

    //then, for each search form, load the autosuggest script
    searchForms.forEach(function (searchForm) {
        autosuggest(searchForm);
    });
});

//main function implementing autosuggest on a form
var autosuggest = function (searchForm) {
    //locate the search input (type will be text or search)
    var searchInput = searchForm.querySelector('input[type="text"][name="s"], input[type="search"][name="s"]');
    if (!searchInput) {
        console.error('No search input found for search form: ' + searchForm.id);
        return;
    }

    //attach an event listener to the search input
    searchInput.addEventListener('input', async function (e) {
        //get the value of each input from the form data
        var formData = {};
        searchForm.querySelectorAll('input').forEach(function (input) {
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


        //render the autosuggest results under the search input
        renderAutosuggestResults(searchForm, data);
        //TODO: right now, we jsut contnuously appen new results objects.  this is bad!  Circle back around and fix this
    });
};

//render the autosuggest results under the search input
var renderAutosuggestResults = function (searchForm, data) {
    //make the absolutely-positioned container for the autosuggest results
    var autosuggestResults = document.createElement('div');
    autosuggestResults.classList.add('scry-search-autosuggest-results');
    autosuggestResults.style.width = searchForm.offsetWidth + 'px';     //width should match the width of the form

    //make a list inside the container
    var autosuggestResultsList = document.createElement('ul');
    autosuggestResultsList.classList.add('scry-search-autosuggest-results-list');
    autosuggestResults.appendChild(autosuggestResultsList);

    //make a list item for each result
    data.forEach(function (result) {
        var resultItem = document.createElement('li');
        resultItem.classList.add('scry-search-autosuggest-result-item');
        resultItem.innerHTML = '<a href="' + result.url + '">' + result.title + '</a>';
        autosuggestResultsList.appendChild(resultItem);
    });

    //once the results are fully created, add the container to the search input
    searchForm.appendChild(autosuggestResults);

}
// ============================================= HELPERS ============================================ \\

//test if a form is a search form
var isSearchForm = function (form) {
    if (!form) return false;

    var role = form.getAttribute('role');
    if (role && role.toLowerCase() === 'search') {
        return true;
    }

    return !!form.querySelector('input[type="text"][name="s"], input[type="search"][name="s"]');
};

