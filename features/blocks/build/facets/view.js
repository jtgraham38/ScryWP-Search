import * as __WEBPACK_EXTERNAL_MODULE__wordpress_interactivity_8e89b257__ from "@wordpress/interactivity";
/******/ var __webpack_modules__ = ({

/***/ "@wordpress/interactivity"
/*!*******************************************!*\
  !*** external "@wordpress/interactivity" ***!
  \*******************************************/
(module) {

module.exports = __WEBPACK_EXTERNAL_MODULE__wordpress_interactivity_8e89b257__;

/***/ }

/******/ });
/************************************************************************/
/******/ // The module cache
/******/ var __webpack_module_cache__ = {};
/******/ 
/******/ // The require function
/******/ function __webpack_require__(moduleId) {
/******/ 	// Check if module is in cache
/******/ 	var cachedModule = __webpack_module_cache__[moduleId];
/******/ 	if (cachedModule !== undefined) {
/******/ 		return cachedModule.exports;
/******/ 	}
/******/ 	// Check if module exists (development only)
/******/ 	if (__webpack_modules__[moduleId] === undefined) {
/******/ 		var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 		e.code = 'MODULE_NOT_FOUND';
/******/ 		throw e;
/******/ 	}
/******/ 	// Create a new module (and put it into the cache)
/******/ 	var module = __webpack_module_cache__[moduleId] = {
/******/ 		// no module.id needed
/******/ 		// no module.loaded needed
/******/ 		exports: {}
/******/ 	};
/******/ 
/******/ 	// Execute the module function
/******/ 	__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 
/******/ 	// Return the exports of the module
/******/ 	return module.exports;
/******/ }
/******/ 
/************************************************************************/
/******/ /* webpack/runtime/make namespace object */
/******/ (() => {
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = (exports) => {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/ })();
/******/ 
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!****************************!*\
  !*** ./src/facets/view.js ***!
  \****************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/interactivity */ "@wordpress/interactivity");

const {
  state
} = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('scry-search/facets', {
  state: {
    selectedTaxonomyFacets: [],
    get searchFacets() {
      //NOTE: later, when I add meta facets, we will combing them here
      return state.selectedTaxonomyFacets;
    },
    get allStateText() {
      return JSON.stringify({
        searchFacets: state.searchFacets
      }, null, 2);
    }
  },
  actions: {
    handleFacetChange: event => {
      //get context data (passed in via data-wp-context)
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();

      //get the taxonomy objects from the context data
      const facetTaxonomiesObjects = context?.facetTaxonomies && typeof context.facetTaxonomies === 'object' ? context.facetTaxonomies : {};

      //find the taxonomy object with the same id as the event target value
      const allTerms = Object.values(facetTaxonomiesObjects).flat();
      const term = allTerms.find(term => term.id == event.target.value);

      //if it was checked, add its record from the facetTaxonomiesObjects array to the selectedTaxonomyFacets array
      if (event.target.checked) {
        if (term) {
          state.selectedTaxonomyFacets.push(term);
        }
      }
      //if it was unchecked, remove its record from the selectedTaxonomyFacets array
      else {
        state.selectedTaxonomyFacets = state.selectedTaxonomyFacets.filter(facet => facet.id !== term.id);
      }
    },
    clearTaxonomyFacets: event => {
      //get context data (passed in via data-wp-context)
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();

      //get the taxonomy slug from the event target value
      const taxonomySlug = event.currentTarget.dataset.scryTaxonomySlug;

      //get the taxonomy objects with the matching taxonomy slug
      const allTerms = Object.values(context?.facetTaxonomies).flat();
      const taxonomyObjects = allTerms.filter(term => term.taxonomy === taxonomySlug);

      //remove the taxonomy objects from the selectedTaxonomyFacets array
      state.selectedTaxonomyFacets = state.selectedTaxonomyFacets.filter(facet => !taxonomyObjects.includes(facet));

      //uncheck all the checkboxes with the matching taxonomy slug
      //get the nearest "scry-facets-terms-container" element to the current target
      const termsContainer = event.currentTarget.closest('.scry-facets-terms-container');
      console.log(termsContainer);

      //uncheck all the checkboxes with the matching taxonomy slug
      const checkboxes = termsContainer.querySelectorAll('input[type="checkbox"]');
      checkboxes.forEach(checkbox => {
        checkbox.checked = false;
      });
    }
  },
  callbacks: {
    init() {
      //here, we will search the webpage for all search forms, and we will submit our facets with their contents
      document.addEventListener('DOMContentLoaded', () => {
        const searchForms = document.querySelectorAll('form');
        console.log(searchForms);
      });
    }
  }
});
})();


//# sourceMappingURL=view.js.map