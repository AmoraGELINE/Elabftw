/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
import i18next from 'i18next';
import { InputType, Malle, SelectOptions } from '@deltablot/malle';
import { Metadata } from './Metadata.class';
import { Ajax } from './Ajax.class';
import { getEntity, updateCategory, relativeMoment, reloadElement, showContentPlainText } from './misc';
import { BoundEvent, EntityType, Payload, Method, Action, Target, Model } from './interfaces';
import { DateTime } from 'luxon';
import EntityClass from './Entity.class';
import Comment from './Comment.class';
declare let key: any; // eslint-disable-line @typescript-eslint/no-explicit-any

document.addEventListener('DOMContentLoaded', () => {

  if (!document.getElementById('info')) {
    return;
  }
  // holds info about the page through data attributes
  const about = document.getElementById('info').dataset;

  // only run in view mode
  if (about.page !== 'view') {
    return;
  }

  // add the title in the page name (see #324)
  document.title = document.getElementById('documentTitle').textContent + ' - eLabFTW';

  const entity = getEntity();
  const EntityC = new EntityClass(entity.type);
  const CommentC = new Comment(entity);
  const AjaxC = new Ajax();

  // add extra fields elements from metadata json
  const MetadataC = new Metadata(entity);
  MetadataC.display('view').then(() => {
    // go over all the type: url elements and create a link dynamically
    document.querySelectorAll('[data-gen-link="true"]').forEach(el => {
      const link = document.createElement('a');
      const url = (el as HTMLSpanElement).innerText;
      link.href = url;
      link.text = url;
      el.replaceWith(link);
    });
  });

  // EDIT SHORTCUT
  key(about.scedit, () => window.location.href = `?mode=edit&id=${entity.id}`);

  // Add click listener and do action based on which element is clicked
  document.querySelector('.real-container').addEventListener('click', (event) => {
    const el = (event.target as HTMLElement);
    // DUPLICATE
    if (el.matches('[data-action="duplicate-entity"]')) {
      EntityC.duplicate(entity.id).then(resp => window.location.href = resp.headers.get('location'));

    // EDIT
    } else if (el.matches('[data-action="edit"]')) {
      window.location.href = `?mode=edit&id=${entity.id}`;

    // TOGGLE LOCK
    } else if (el.matches('[data-action="lock-entity"]')) {
      // reload the page to change the icon and make the edit button disappear (#1897)
      EntityC.lock(entity.id).then(() => window.location.href = `?mode=view&id=${entity.id}`);

    // SEE EVENTS
    } else if (el.matches('[data-action="see-events"]')) {
      const payload: Payload = {
        method: Method.GET,
        action: Action.Read,
        entity: entity,
        model: entity.type,
        target: Target.BoundEvent,
      };
      AjaxC.send(payload).then(json => {
        const bookingsDiv = document.getElementById('boundBookings');
        for (const msg of (json.value as Array<BoundEvent>)) {
          const el = document.createElement('a');
          el.href = `team.php?item=${msg.item}&start=${encodeURIComponent(msg.start)}`;
          const button = document.createElement('button');
          button.classList.add('mr-2', 'btn', 'btn-neutral', 'relative-moment');
          const locale = document.getElementById('user-prefs').dataset.jslang;
          button.innerText = DateTime.fromISO(msg.start, {'locale': locale}).toRelative();
          el.appendChild(button);
          bookingsDiv.append(el);
        }
      });

    // SHARE
    } else if (el.matches('[data-action="share"]')) {
      EntityC.read(entity.id).then(json => {
        const link = (document.getElementById('shareLinkInput') as HTMLInputElement);
        link.value = json.sharelink;
        link.hidden = false;
        link.focus();
        link.select();
      });

    // TOGGLE PINNED
    } else if (el.matches('[data-action="pin"]')) {
      EntityC.pin(entity.id).then(() => document.getElementById('toggle-pin-icon').classList.toggle('grayed-out'));

    // TIMESTAMP button in modal
    } else if (el.matches('[data-action="timestamp"]')) {
      // prevent double click
      (event.target as HTMLButtonElement).disabled = true;
      const payload: Payload = {
        method: Method.POST,
        action: Action.Timestamp,
        entity: entity,
        model: entity.type,
        target: Target.TsClassic,
      };
      AjaxC.send(payload).then(() => window.location.replace(`experiments.php?mode=view&id=${entity.id}`));

    // BLOXBERG
    } else if (el.matches('[data-action="bloxberg"]')) {
      const overlay = document.createElement('div');
      const loading = document.createElement('p');
      const ring = document.createElement('div');
      ring.classList.add('lds-dual-ring');
      // see https://loading.io/css/
      const emptyDiv = document.createElement('div');
      ring.appendChild(emptyDiv);
      ring.appendChild(emptyDiv);
      ring.appendChild(emptyDiv);
      ring.appendChild(emptyDiv);
      overlay.classList.add('full-screen-overlay');
      loading.appendChild(ring);
      overlay.appendChild(loading);
      document.getElementById('container').append(overlay);
      const payload: Payload = {
        method: Method.POST,
        action: Action.Timestamp,
        entity: entity,
        model: entity.type,
        target: Target.TsBloxberg,
      };
      AjaxC.send(payload).then(() => window.location.replace(`?mode=view&id=${entity.id}`));

    // SHOW CONTENT OF PLAIN TEXT FILES
    } else if (el.matches('[data-action="show-plain-text"]')) {
      showContentPlainText(el);
    }
  });

  // COMMENTS
  document.getElementById('commentsDiv').addEventListener('click', event => {
    const el = (event.target as HTMLElement);
    // CREATE COMMENT
    if (el.matches('[data-action="create-comment"]')) {
      const content = (document.getElementById('commentsCreateArea') as HTMLTextAreaElement).value;
      CommentC.create(content).then(() => reloadElement('commentsDiv'));

    // DESTROY COMMENT
    } else if (el.matches('[data-action="destroy-comment"]')) {
      if (confirm(i18next.t('generic-delete-warning'))) {
        CommentC.destroy(parseInt(el.dataset.target, 10)).then(() => reloadElement('commentsDiv'));
      }
    }
  });

  // UPDATE MALLEABLE COMMENT
  const malleableComments = new Malle({
    cancel : i18next.t('cancel'),
    cancelClasses: ['button', 'btn', 'btn-danger', 'mt-2', 'ml-1'],
    inputClasses: ['form-control'],
    fun: (value, original) => {
      CommentC.update(parseInt(original.dataset.id, 10), value);
      return value;
    },
    inputType: InputType.Textarea,
    listenOn: '.comment.editable',
    submit : i18next.t('save'),
    submitClasses: ['button', 'btn', 'btn-primary', 'mt-2'],
    tooltip: i18next.t('click-to-edit'),
  });

  // UPDATE MALLEABLE CATEGORY
  new Malle({
    cancel : i18next.t('cancel'),
    cancelClasses: ['button', 'btn', 'btn-danger', 'mt-2', 'ml-1'],
    inputClasses: ['form-control'],
    fun: value => updateCategory(entity, value),
    inputType: InputType.Select,
    selectOptionsValueKey: 'category_id',
    selectOptionsTextKey: 'category',
    selectOptions: AjaxC.send({
      method: Method.GET,
      action: Action.Read,
      // problem here is that status is a subtype of experiments, and itemstypes is an abstractentity itself
      // so processor will read model for experiments and return entity for itemstypes
      // even if model is defined as itemstypes, it will return the entity, so fill entity key with itemstype and no id
      entity: {type: EntityType.ItemType, id: null},
      model: entity.type === EntityType.Experiment ? Model.Status : EntityType.ItemType,
    }).then(json => (json.value as Array<SelectOptions>)),
    listenOn: '.malleableCategory',
    submit : i18next.t('save'),
    submitClasses: ['button', 'btn', 'btn-primary', 'mt-2'],
    tooltip: i18next.t('click-to-edit'),
  }).listen();

  // listen on existing comments
  malleableComments.listen();

  // add an observer so new comments will get an event handler too
  new MutationObserver(() => {
    malleableComments.listen();
    relativeMoment();
  }).observe(document.getElementById('commentsDiv'), {childList: true});
  // END COMMENTS
});
