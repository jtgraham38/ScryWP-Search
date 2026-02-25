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
            //here, we will search the webpage for all search forms, and we will submit our facets with their contents
            document.addEventListener('DOMContentLoaded', () => {
                const searchForms = document.querySelectorAll('form');
                console.log(searchForms);
            });

        },
    },
});
