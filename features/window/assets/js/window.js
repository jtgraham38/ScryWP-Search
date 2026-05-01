
//create the scry search window object
window.scrySearch = {
    version: '1.0.0',   //version of the window object
    searchForms: [],    //collection of search forms on the page
    upgrades: {},       //array of upgrades that have been registered for custom functionality
    //initialize the window object
    init: function () {
        this.searchForms = document.querySelectorAll('form');

        //first, get all forms on the page
        candidateFormElements = Array.from(document.querySelectorAll('form'));

        //get search forms
        var searchFormElements = candidateFormElements.filter(function (form) {
            if (!form) return false;

            var role = form.getAttribute('role');
            if (role && role.toLowerCase() === 'search') {
                return true;
            }

            return !!form.querySelector('input[type="text"][name="s"], input[type="search"][name="s"]');
        });

        //convert the search form elements to search form objects
        this.searchForms = searchFormElements.map(function (formElement) {
            return new ScrySearch_SearchForm(formElement);
        });

        //emit a custom event to let other scripts know the window is ready
        document.dispatchEvent(new CustomEvent('scrySearchReady', {
            detail: {
                version: this.version,
                searchForms: this.searchForms,
                upgrades: this.upgrades,
            }
        }));
    },

    //get all search forms
    getSearchForms: function () {
        return this.searchForms;
    },

    //get all search forms with a particular class set on the <form> tag
    getSearchFormsByClass: function (className) {
        return this.searchForms.filter(function (form) {
            return form.classList.contains(className);
        });
    },
}

class ScrySearch_SearchForm {
    constructor(formElement) {
        this.formElement = formElement;
        this.autosuggestResults = {};
    }
}

//initialize the scry search window object
document.addEventListener('DOMContentLoaded', function () {
    window.scrySearch.init();
});