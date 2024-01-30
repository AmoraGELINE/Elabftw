/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
import { getEntity, notif, reloadElement, collectForm, updateCatStat } from './misc';
import tinymce from 'tinymce/tinymce';
import { getTinymceBaseConfig } from './tinymce';
import i18next from 'i18next';
import { Model, Target } from './interfaces';
import Templates from './Templates.class';
import { getEditor } from './Editor.class';
import Tab from './Tab.class';
import { Ajax } from './Ajax.class';
import { Api } from './Apiv2.class';
import $ from 'jquery';

document.addEventListener('DOMContentLoaded', () => {
  if (window.location.pathname !== '/ucp.php') {
    return;
  }

  // show the handles to reorder when the menu entry is clicked
  $('#toggleReorder').on('click', function() {
    $('.sortableHandle').toggle();
  });

  const EntityC = new Templates();
  const ApiC = new Api();

  const entity = getEntity();
  const TabMenu = new Tab();
  TabMenu.init(document.querySelector('.tabbed-menu'));

  // Which editor are we using? md or tiny
  const editor = getEditor();
  editor.init();

  // CATEGORY SELECT
  $(document).on('change', '.catstatSelect', function() {
    updateCatStat($(this).data('target'), entity, String($(this).val()));
  });

  // FILTER TEMPLATES
  if (document.getElementById('templatesFilterInput')) {
    document.getElementById('templatesFilterInput').addEventListener('keyup', event => {
      const el = (event.target as HTMLInputElement);
      const query = el.value;
      // find all links that are endpoints
      document.querySelectorAll('#tplTable tr').forEach(el => {
        // begin by showing all so they don't stay hidden
        el.removeAttribute('hidden');
        // now simply hide the ones that don't match the query
        if (!(el as HTMLElement).innerText.toLowerCase().includes(query)) {
          el.setAttribute('hidden', '');
        }
      });
    });
  }

  // MAIN LISTENER
  document.querySelector('.real-container').addEventListener('click', (event) => {
    const el = (event.target as HTMLElement);
    // CREATE TEMPLATE
    if (el.matches('[data-action="create-template"]')) {
      const title = prompt(i18next.t('template-title'));
      if (title) {
        // no body on template creation
        // Note: here we create one and then patch it for the correct content_type but it would probably be better to allow setting the content_type directly on creation
        EntityC.create(title).then(resp => {
          const location = resp.headers.get('location').split('/');
          const newId = parseInt(location[location.length -1], 10);
          EntityC.update(newId, Target.ContentType, String(editor.typeAsInt)).then(() => {
            window.location.href = `ucp.php?tab=3&mode=edit&templateid=${newId}`;
          });
        });
      }
    // LOCK TEMPLATE
    } else if (el.matches('[data-action="toggle-lock"]')) {
      EntityC.lock(parseInt(el.dataset.id)).then(() => reloadElement('lockTemplateButton'));
    // UPDATE TEMPLATE
    } else if (el.matches('[data-action="update-template"]')) {
      EntityC.update(entity.id, Target.Body, editor.getContent());
    // SWITCH EDITOR TODO duplicated code from edit.ts
    } else if (el.matches('[data-action="switch-editor"]')) {
      EntityC.update(entity.id, Target.ContentType, editor.switch() === 'tiny' ? '1' : '2');

    // DESTROY TEMPLATE
    } else if (el.matches('[data-action="destroy-template"]')) {
      if (confirm(i18next.t('generic-delete-warning'))) {
        EntityC.destroy(parseInt(el.dataset.id))
          .then(() => window.location.replace('ucp.php?tab=3'))
          .catch((e) => notif({'res': false, 'msg': e.message}));
      }

    } else if (el.matches('[data-action="patch-account"]')) {
      const params = collectForm(document.getElementById('ucp-account-form'));
      if (params['orcid'] === '') {
        delete params['orcid'];
      }
      ApiC.patch(`${Model.User}/me`, params);

    // CLEAR THE FILTER INPUT FOR TEMPLATES
    } else if (el.matches('[data-action="clear-templates-search"]')) {
      const searchInput = el.parentElement.parentElement.querySelector('input');
      searchInput.value = '';
      searchInput.focus();
      document.querySelectorAll('#tplTable tr').forEach(el => {
        el.removeAttribute('hidden');
      });
    // CREATE API KEY
    } else if (el.matches('[data-action="create-apikey"]')) {
      // clear any previous new key message
      const nameInput = (document.getElementById('apikeyName') as HTMLInputElement);
      const content = nameInput.value;
      if (!content) {
        notif({'res': false, 'msg': 'A name is required!'});
        // set the border in red to bring attention
        nameInput.style.borderColor = 'red';
        return;
      }
      const canwrite = parseInt((document.getElementById('apikeyCanwrite') as HTMLInputElement).value, 10);
      ApiC.post(`${Model.Apikey}`, {'name': content, 'canwrite': canwrite}).then(resp => {
        const location = resp.headers.get('location').split('/');
        reloadElement('apiTable');
        const warningDiv = document.createElement('div');
        warningDiv.classList.add('alert', 'alert-warning');
        const chevron = document.createElement('i');
        chevron.classList.add('fas', 'fa-chevron-right', 'color-warning', 'fa-fw');
        warningDiv.appendChild(chevron);

        const newkey = document.createElement('p');
        newkey.innerText = location[location.length -1];
        const warningTextSpan = document.createElement('span');

        warningTextSpan.innerText = i18next.t('new-apikey-warning');
        warningTextSpan.classList.add('ml-1');
        warningDiv.appendChild(warningTextSpan);
        warningDiv.appendChild(newkey);
        const placeholder = document.getElementById('newKeyPlaceholder');
        placeholder.innerHTML = '';
        placeholder.appendChild(warningDiv);
      });
    // DESTROY API KEY
    } else if (el.matches('[data-action="destroy-apikey"]')) {
      if (confirm(i18next.t('generic-delete-warning'))) {
        const id = parseInt(el.dataset.apikeyid, 10);
        ApiC.delete(`${Model.Apikey}/${id}`).then(() => reloadElement('apiTable'));
      }
    } else if (el.matches('[data-action="show-import-tpl"]')) {
      document.getElementById('import_tpl').toggleAttribute('hidden');
    }
  });

  // input to upload an ELN archive
  const importTplInput = document.getElementById('import_tpl');
  if (importTplInput) {
    importTplInput.addEventListener('change', (event) => {
      const params = {
        'type': 'archive',
        'file': (event.target as HTMLInputElement).files[0],
        'target': 'experiments_templates:0',
      };
      // TODO check for file size here too, like the other import modal
      (new Ajax()).postForm('app/controllers/ImportController.php', params).then(() => {
        window.location.reload();
      });
    });
  }

  // TinyMCE
  tinymce.init(getTinymceBaseConfig('ucp'));
});
