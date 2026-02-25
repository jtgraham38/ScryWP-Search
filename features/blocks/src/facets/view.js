import { getContext, store } from '@wordpress/interactivity';

const { state } = store('scry-search/facets', {
    state: {
        selectedTaxonomyFacets: [],
        get searchFacets() {
            //NOTE: later, when I add meta facets, we will combing them here
            return state.selectedTaxonomyFacets;

        },
        get allStateText() {
            return JSON.stringify(
                {
                    searchFacets: state.searchFacets,
                },
                null,
                2
            );
        },
    },
    actions: {
        handleFacetChange: (event) => {

            //get context data (passed in via data-wp-context)
            const context = getContext();

            //get the taxonomy objects from the context data
            const facetTaxonomiesObjects =
                context?.facetTaxonomies && typeof context.facetTaxonomies === 'object'
                    ? context.facetTaxonomies
                    : {};

            //find the taxonomy object with the same id as the event target value
            const allTerms = Object.values(facetTaxonomiesObjects).flat();
            const term = allTerms.find((term) => term.id == event.target.value);

            //if it was checked, add its record from the facetTaxonomiesObjects array to the selectedTaxonomyFacets array
            if (event.target.checked) {
                if (term) {
                    state.selectedTaxonomyFacets.push(term);
                }
            }
            //if it was unchecked, remove its record from the selectedTaxonomyFacets array
            else {
                state.selectedTaxonomyFacets = state.selectedTaxonomyFacets.filter((facet) => facet.id !== term.id);
            }
        },
        clearTaxonomyFacets: (event) => {

            //get context data (passed in via data-wp-context)
            const context = getContext();

            //get the taxonomy slug from the event target value
            const taxonomySlug = event.currentTarget.dataset.scryTaxonomySlug;

            //get the taxonomy objects with the matching taxonomy slug
            const allTerms = Object.values(context?.facetTaxonomies).flat();
            const taxonomyObjects = allTerms.filter((term) => term.taxonomy === taxonomySlug);

            //remove the taxonomy objects from the selectedTaxonomyFacets array
            state.selectedTaxonomyFacets = state.selectedTaxonomyFacets.filter((facet) => !taxonomyObjects.includes(facet));

            //uncheck all the checkboxes with the matching taxonomy slug
            //get the nearest "scry-facets-terms-container" element to the current target
            const termsContainer = event.currentTarget.closest('.scry-facets-terms-container');
            console.log(termsContainer);

            //uncheck all the checkboxes with the matching taxonomy slug
            const checkboxes = termsContainer.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach((checkbox) => {
                checkbox.checked = false;
            });
        },
    },
    callbacks: {
        init() {

            //get context data once while this callback has interactivity scope
            const context = getContext();

            //get the search form class from the context data
            const searchFormClassName = context?.searchFormClassName;
            console.log(searchFormClassName);

            //seed selected state from URL-selected facets so checks persist across reloads
            const selectedFacetIds = Array.isArray(context?.selectedFacetIds)
                ? context.selectedFacetIds.map((id) => Number(id))
                : [];
            if (selectedFacetIds.length > 0 && state.selectedTaxonomyFacets.length === 0) {
                const allTerms = Object.values(context?.facetTaxonomies ?? {}).flat();
                state.selectedTaxonomyFacets = selectedFacetIds
                    .map((id) => allTerms.find((term) => Number(term.id) === id))
                    .filter(Boolean);
            }

            const bindSearchForms = () => {
                console.log('[scry facets] init callback running');

                //here, we will search the webpage for all search forms, and we will submit our facets with their contents
                //if the searchFormClassName is set, we will filter for that too

                let searchForms = Array.from(document.querySelectorAll('form'));
                if (searchFormClassName) {
                    searchForms = searchForms.filter((form) => {
                        return form.classList.contains(searchFormClassName);
                    });
                }
                //otherwise, we will search for all forms
                else {
                    searchForms = Array.from(document.querySelectorAll('form'));
                }

                //we will keep only forms that have an input of type text/search with the name "s"
                searchForms = searchForms.filter((form) => {
                    const input = form.querySelector('input[name="s"]');
                    return input && (input.type === 'text' || input.type === 'search') && form.method.toUpperCase() === 'GET';
                });

                console.log('[scry facets] eligible search forms:', searchForms.length);

                //add an event listener for each search form to submit the facets with their contents
                searchForms.forEach((form) => {
                    if (form.dataset.scryFacetsBound === '1') {
                        return;
                    }
                    form.dataset.scryFacetsBound = '1';

                    form.addEventListener('submit', (event) => {
                        event.preventDefault();

                        //get the form data
                        const formData = new FormData(form);

                        //add new fields to the form data, nesting the facets under the key "scry-search->facets"
                        formData.delete('scry-search[facets]');
                        formData.delete('scry-search[facets][]');
                        state.selectedTaxonomyFacets.forEach((facet) => {
                            formData.append('scry-search[facets][]', String(facet.id));
                        });

                        //submit the form
                        const params = new URLSearchParams(formData);
                        window.location.assign(`${form.action}?${params.toString()}`);
                    });
                });
            };

            if (document.readyState === 'complete') {
                bindSearchForms();
            } else {
                window.addEventListener('load', bindSearchForms, { once: true });
            }
        },
    },
});