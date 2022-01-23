/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

document.addEventListener('DOMContentLoaded', () => {
  if (window.location.pathname !== '/search.php') {
    return;
  }
  // scroll to anchor if there is a search
  const params = new URLSearchParams(document.location.search.slice(1));
  if (params.has('type')) {
    window.location.hash = '#anchor';
  }

  const extendedArea = (document.getElementById('extendedArea') as HTMLTextAreaElement);

  // Submit form with ctrl+enter from within textarea
  extendedArea.addEventListener('keydown', event => {
    if ((event.keyCode == 10 || event.keyCode == 13) && (event.ctrlKey || event.metaKey)) {
      (document.getElementById('searchButton') as HTMLButtonElement).click();
    }
  });

  // a filter helper can be a select or an input (for date), so we need a function to get its value
  function getFilterValueFromElement(element: HTMLElement): string {
    if (element instanceof HTMLSelectElement) {
      if (element.options[element.selectedIndex].dataset.action === 'clear') {
        return '';
      }
      return `${element.options[element.selectedIndex].innerText}`;
    }
    if (element instanceof HTMLInputElement) {
      // a cleared date input will be empty
      if (element.value === '') {
        return '';
      }
      // for the date, get the operator
      let operator = '';
      if (element.dataset.filter === 'date') {
        const operatorSelect = document.getElementById('dateOperator') as HTMLSelectElement;
        operator = operatorSelect.options[operatorSelect.selectedIndex].value;
      }
      return operator + element.value;
    }
    return '😶';
  }

  // add a change event listener to all elements that helps constructing the query string
  document.querySelectorAll('.filterHelper').forEach(el => {
    el.addEventListener('change', event => {
      const elem = event.currentTarget as HTMLElement;
      const curVal = extendedArea.value;

      const hasInput = curVal.length != 0;
      const hasSpace = curVal.endsWith(' ');
      const addSpace = hasInput ? (hasSpace ? '' : ' ') : '';

      // look if the filter key already exists in the extendedArea
      // paste the regex on regex101.com to understand it, note that here \ need to be escaped
      const regex = new RegExp(elem.dataset.filter + ':(\\w+|\\d|"[\\w\\s+]+"|([=><!,]?=?)?(\\d{4}[\\-\\.\\/,]\\d{2}[\\-\\.\\/,]\\d{2}))\\s?');
      const found = curVal.match(regex);
      // don't add quotes unless we need them (space exists)
      let quotes = '';
      const filterValue = getFilterValueFromElement(elem);
      if (filterValue.includes(' ')) {
        quotes = '"';
      }
      // default value is clearing everything
      let filter = '';
      // but if we have a correct value, we add the filter
      if (filterValue !== '') {
        filter = `${elem.dataset.filter}:${quotes}${filterValue}${quotes}`;
      }

      if (found) {
        extendedArea.value = curVal.replace(regex, filter + (filter === '' ? '' : ' '));
      } else {
        extendedArea.value = extendedArea.value + addSpace + filter;
      }
    });
  });

  /*
  if (localStorage.getItem('isExtendedSearchMode') === '1') {
    $('.collapseExtendedSearch').collapse('toggle');
    document.getElementById('toggleSearchMode').innerHTML = 'Switch to Default Search';

    // Owner has to be set to team in extended search
    (document.getElementById('searchonly') as HTMLSelectElement).value = '0';

    // Only keep Experiments and Database entries in 'searchin' select
    const searchin = document.getElementById('searchin') as HTMLSelectElement;
    const keep = Array.from(searchin.children).slice(0,3);
    searchin.replaceChildren(...keep);
    searchin.selectedIndex = 0;
  }
 */
});
