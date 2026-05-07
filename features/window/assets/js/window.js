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
            return form?.formElement?.classList.contains(className) || false;
        });
    },

    //add an upgrade to the window object
    registerUpgrade: function (name, version) {
        if (!this.upgrades[name]) {
            this.upgrades[name] = new ScrySearch_Upgrade(name, version);
        } else {
            console.warn('Upgrade already exists, skipping', name);
        }
    },

}

class ScrySearch_SearchForm {
    constructor(formElement) {
        this.formElement = formElement;
        this.data = {
            core: {
                autoSuggestElement: null,    //holds the autosuggest results container element
                autosuggestResults: [],    //holds the autosuggest results data
            },
        }; //arbitrary data storage for the search form

        this.preSubmitActions = []; //a list of ScrySearch_SubmitAction objects to call before the form is submitted
        this.postSubmitActions = []; //a list of ScrySearch_SubmitAction objects to call after the form is submitted

        this.preSubmitAjaxActions = []; //a list of ScrySearch_SubmitAction objects to call before the form is submitted via AJAX
        this.postSubmitAjaxActions = []; //a list of ScrySearch_SubmitAction objects to call after the form is submitted via AJAX

        this.searchInput = formElement.querySelector('input[type="text"][name="s"], input[type="search"][name="s"]');

        //ensure when the formElement is submitted, we always call the submit function first
        this.formElement.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submit();
        });

        // Ensure *all* callers hit the debounce (even external code calling form.submitAjax()).
        this.submitAjax = this.debounceAjaxSubmit(this.submitAjax.bind(this));
    }

    //submit the search form
    submit() {

        //sort and call the pre submit handles
        this.preSubmitActions.sort((a, b) => a.order - b.order).forEach(action => action.call(this));

        //submit the form
        this.formElement.submit();

        //sort and call the post submit actions
        //these will likely not be used much due to the page refresh on submit
        this.postSubmitActions.sort((a, b) => a.order - b.order).forEach(action => action.call(this));
    }

    //submit the search form via AJAX
    async submitAjax() {
        //sort and call the pre submit ajax handles
        this.preSubmitAjaxActions.sort((a, b) => a.order - b.order).forEach(action => action.call(this));

        //submit the form via AJAX
        var data = await this._handleAjaxSubmit();

        //sort and call the post submit ajax actions
        this.postSubmitAjaxActions.sort((a, b) => a.order - b.order).forEach(action => action.call(this, data));

        //return the data
        return data;
    }

    //handle ajax submission
    async _handleAjaxSubmit() {
        // Turn FormData into a JSON-able object (supports names like filters[facets][], etc.).
        const deepSet = (obj, path, value) => {
            if (Object(obj) !== obj) return obj;
            if (!Array.isArray(path)) path = path.toString().match(/[^.[\]]+/g) || [];
            path.slice(0, -1).reduce((a, c, i) =>
                Object(a[c]) === a[c]
                    ? a[c]
                    : a[c] = (Math.abs(path[i + 1]) >> 0) === +path[i + 1] ? [] : {},
                obj
            )[path[path.length - 1]] = value;
            return obj;
        };

        //call the deep set function to convert the form data to a json object
        const formData = new FormData(this.formElement);
        const formDataObject = {};
        for (const [path, value] of formData) {
            deepSet(formDataObject, path, value);
        }
        //send a request to the rest api
        var searchResults = await fetch(localized.restApiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(formDataObject),
        });
        var data = await searchResults.json();

        return data;
    }

    //debounce the ajax submission
    debounceAjaxSubmit(fn, waitMs = 250) {
        // Debounce network submissions so rapid typing can't overwhelm the server.
        // Returns a promise so callers can still await the eventual AJAX result.
        let timerId = null;
        return (...args) => {
            if (timerId) {
                clearTimeout(timerId);
            }
            return new Promise((resolve, reject) => {
                timerId = setTimeout(async () => {
                    try {
                        resolve(await fn(...args));
                    } catch (err) {
                        reject(err);
                    }
                }, waitMs);
            });
        };
    }

    //add actions to the pre submit actions
    addPreSubmitAction(func, order) {
        //ensure the function of the submit action is not already in the list
        if (this.preSubmitActions.some(action => action.func === func)) {
            console.warn('Submit action already exists, skipping', func);
            return;
        }
        this.preSubmitActions.push(new ScrySearch_SubmitAction(func, order));
    }

    //add actions to the post submit actions
    addPostSubmitAction(func, order) {
        //ensure the function of the submit action is not already in the list
        if (this.postSubmitActions.some(action => action.func === func)) {
            console.warn('Submit action already exists, skipping', func);
            return;
        }
        this.postSubmitActions.push(new ScrySearch_SubmitAction(func, order));
    }

    //add actions to the pre submit ajax actions
    addPreSubmitAjaxAction(func, order) {
        //ensure the function of the submit action is not already in the list
        if (this.preSubmitAjaxActions.some(action => action.func === func)) {
            console.warn('Submit action already exists, skipping', func);
            return;
        }
        this.preSubmitAjaxActions.push(new ScrySearch_SubmitAction(func, order));
    }

    //add actions to the post submit ajax actions
    addPostSubmitAjaxAction(func, order) {
        //ensure the function of the submit action is not already in the list
        if (this.postSubmitAjaxActions.some(action => action.func === func)) {
            console.warn('Submit action already exists, skipping', func);
            return;
        }
        this.postSubmitAjaxActions.push(new ScrySearch_SubmitAction(func, order));
    }
}

class ScrySearch_SubmitAction {
    constructor(func, order) {
        this.func = func;
        this.order = order;
    }

    //call the function
    call(searchForm, data) {
        // Actions may be written as (searchForm) or (searchForm, data).
        // We key off argument count so "falsy" data (0, "", null) isn't accidentally dropped.
        if (arguments.length >= 2) {
            this.func(searchForm, data);
            return;
        }
        this.func(searchForm);
    }
}

class ScrySearch_Upgrade {
    constructor(name, version, min_window_version, init) {
        this.name = name;
        this.version = version;
        this.data = {}; //arbitrary data storage for the upgrade
    }
}

//initialize the scry search window object
document.addEventListener('DOMContentLoaded', function () {
    window.scrySearch.init();
});