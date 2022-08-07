/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
import { notif, reloadElement, addAutocompleteToTagInputs } from './misc';
import tinymce from 'tinymce/tinymce';
import { getTinymceBaseConfig } from './tinymce';
import Apikey from './Apikey.class';
import i18next from 'i18next';
import { EntityType, Target } from './interfaces';
import EntityClass from './Entity.class';
import Tab from './Tab.class';
import { Ajax } from './Ajax.class';

document.addEventListener('DOMContentLoaded', () => {
  if (window.location.pathname !== '/ucp.php') {
    return;
  }

  // show the handles to reorder when the menu entry is clicked
  $('#toggleReorder').on('click', function() {
    $('.sortableHandle').toggle();
  });

  const ApikeyC = new Apikey();
  const EntityC = new EntityClass(EntityType.Template);

  const TabMenu = new Tab();
  TabMenu.init(document.querySelector('.tabbed-menu'));

  // MAIN LISTENER
  document.querySelector('.real-container').addEventListener('click', (event) => {
    const el = (event.target as HTMLElement);
    // CREATE TEMPLATE
    if (el.matches('[data-action="create-template"]')) {
      const title = prompt(i18next.t('template-title'));
      if (title) {
        // no body on template creation
        EntityC.create(title, []).then(resp => {
          resp.json().then(json => {
            window.location.replace(`ucp.php?tab=3&templateid=${json.value}`);
          });
        });
      }
    // LOCK TEMPLATE
    } else if (el.matches('[data-action="toggle-lock"]')) {
      EntityC.lock(parseInt(el.dataset.id)).then(() => {
        reloadElement('templatesDiv').then(() => {
          addAutocompleteToTagInputs();
          tinymce.remove();
          tinymce.init(getTinymceBaseConfig('ucp'));
        });
      });
    // UPDATE TEMPLATE
    } else if (el.matches('[data-action="update-template"]')) {
      const id = el.dataset.id;
      const body = tinymce.activeEditor.getContent();
      tinymce.activeEditor.setDirty(false);
      EntityC.update(parseInt(id), Target.Body, body);
    // DOWNLOAD TEMPLATE
    } else if (el.matches('[data-action="download-template"]')) {
      window.location.href = `make.php?what=eln&type=experiments_templates&id=${el.dataset.id}`;
    // DESTROY TEMPLATE
    } else if (el.matches('[data-action="destroy-template"]')) {
      if (confirm(i18next.t('generic-delete-warning'))) {
        EntityC.destroy(parseInt(el.dataset.id))
          .then(() => window.location.replace('ucp.php?tab=3'))
          .catch((e) => notif({'res': false, 'msg': e.message}));
      }

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
      const canwrite = parseInt((document.getElementById('apikeyCanwrite') as HTMLInputElement).value);
      ApikeyC.create(content, canwrite).then(json => {
        reloadElement('apiTable');
        const warningDiv = document.createElement('div');
        warningDiv.classList.add('alert', 'alert-warning');
        const chevron = document.createElement('i');
        chevron.classList.add('fas', 'fa-chevron-right');
        warningDiv.appendChild(chevron);

        const newkey = document.createElement('p');
        newkey.innerText = json.value as string;
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
        ApikeyC.destroy(parseInt(el.dataset.apikeyid)).then(() => {
          // only reload children of apiTable
          reloadElement('apiTable');
        });
      }
    } else if (el.matches('[data-action="show-import-tpl"]')) {
      document.getElementById('import_tpl').toggleAttribute('hidden');
    } else if (el.matches('[data-action="pin"]')) {
      EntityC.pin(parseInt(el.dataset.id)).then(() => {
        reloadElement('templatesDiv').then(() => {
          addAutocompleteToTagInputs();
          tinymce.remove();
          tinymce.init(getTinymceBaseConfig('ucp'));
        });
      });
    }
  });

  // CAN READ/WRITE SELECT PERMISSION
  $(document).on('change', '.permissionSelectTpl', function() {
    const value = $(this).val();
    const rw = $(this).data('rw');
    const id = $(this).data('id');
    $.post('app/controllers/EntityAjaxController.php', {
      updatePermissions: true,
      rw: rw,
      id: id,
      type: 'experiments_templates',
      value: value,
    }).done(function(json) {
      notif(json);
    });
  });

  // input to upload an ELN archive
  document.getElementById('import_tpl').addEventListener('change', (event) => {
    const el = (event.target as HTMLInputElement);
    const AjaxC = new Ajax();
    const params = {
      'type': 'archive',
      'file': el.files[0],
      'target': 'experiments_templates:0',
      'canread': 'team',
      'canwrite': 'user',
    };
    AjaxC.postForm('app/controllers/ImportController.php', params).then(() => {
      window.location.reload();
    });
  });

  // TinyMCE
  tinymce.init(getTinymceBaseConfig('ucp'));

  // auto update title on blur
  $(document).on('blur', '#title_input', function() {
    const content = (document.getElementById('title_input') as HTMLInputElement).value;
    const id = $(this).data('id');
    EntityC.update(id, Target.Title, content);
  });
});
